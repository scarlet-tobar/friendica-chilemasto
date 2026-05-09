<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Module\Settings;

use Friendica\App;
use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Core\L10n;
use Friendica\Core\PConfig\Capability\IManagePersonalConfigValues;
use Friendica\Core\Protocol;
use Friendica\Core\Renderer;
use Friendica\Core\Session\Capability\IHandleUserSessions;
use Friendica\Core\Worker;
use Friendica\Model\APContact;
use Friendica\Model\Contact;
use Friendica\Module\BaseSettings;
use Friendica\Module\Response;
use Friendica\Navigation\SystemMessages;
use Friendica\Network\HTTPClient\Capability\ICanSendHttpRequests;
use Friendica\Network\HTTPClient\Client\HttpClientAccept;
use Friendica\Network\HTTPClient\Client\HttpClientOptions;
use Friendica\Network\HTTPClient\Client\HttpClientRequest;
use Friendica\Network\HTTPException;
use Friendica\Protocol\ActivityPub;
use Friendica\Util\Network;
use Friendica\Util\Profiler;
use Friendica\Worker\AddContact;
use Psr\Log\LoggerInterface;

/**
 * Module to export user data
 **/
class ContactImport extends BaseSettings
{
	/** @var IManageConfigValues */
	private $config;
	/** @var IManagePersonalConfigValues */
	private $pconfig;
	/** @var SystemMessages */
	protected $systemMessages;
	/** @var ICanSendHttpRequests */
	private $httpClient;

	public function __construct(ICanSendHttpRequests $httpClient, SystemMessages $systemMessages, IManagePersonalConfigValues $pconfig, IManageConfigValues $config, IHandleUserSessions $session, App\Page $page, L10n $l10n, App\BaseURL $baseUrl, App\Arguments $args, LoggerInterface $logger, Profiler $profiler, Response $response, array $server, array $parameters = [])
	{
		parent::__construct($session, $page, $l10n, $baseUrl, $args, $logger, $profiler, $response, $server, $parameters);

		$this->config         = $config;
		$this->pconfig        = $pconfig;
		$this->systemMessages = $systemMessages;
		$this->httpClient     = $httpClient;
	}

	protected function post(array $request = [])
	{
		if (!$this->session->getLocalUserId()) {
			throw new HTTPException\ForbiddenException($this->l10n->t('Permission denied.'));
		}

		self::checkFormSecurityTokenRedirectOnError($this->args->getQueryString(), 'contactimport');

		parent::post();

		if (!empty($request['importcontact-submit'])) {
			if (isset($request['legacy_contact'])) {
				$this->import($request['legacy_contact']);
			}

			// Import Contacts from CSV file
			if (isset($_FILES['importcontact-filename']) && !empty($_FILES['importcontact-filename']['tmp_name'])) {
				// was there an error
				if ($_FILES['importcontact-filename']['error'] > 0) {
					$this->logger->notice('Contact CSV file upload error', ['error' => $_FILES['importcontact-filename']['error']]);
					$this->systemMessages->addNotice($this->l10n->t('Contact CSV file upload error'));
				} else {
					$csvArray = array_map('str_getcsv', file($_FILES['importcontact-filename']['tmp_name']));
					$this->logger->notice('Import started', ['lines' => count($csvArray)]);
					// import contacts
					foreach ($csvArray as $csvRow) {
						// The 1st row may, or may not contain the headers of the table
						// We expect the 1st field of the row to contain either the URL
						// or the handle of the account, therefore we check for either
						// "http" or "@" to be present in the string.
						// All other fields from the row will be ignored
						if ((strpos($csvRow[0], '@') !== false) || Network::isValidHttpUrl($csvRow[0])) {
							AddContact::add(Worker::PRIORITY_MEDIUM, $this->session->getLocalUserId(), trim($csvRow[0], '@'));
						} else {
							$this->logger->notice('Invalid account', ['url' => $csvRow[0]]);
						}
					}
					$this->logger->notice('Import done');

					$this->systemMessages->addInfo($this->l10n->t('Importing Contacts done'));
					// delete temp file
					unlink($_FILES['importcontact-filename']['tmp_name']);
				}
			}
		}
		$this->baseUrl->redirect($this->args->getQueryString());
	}

	protected function content(array $request = []): string
	{
		if (!$this->session->getLocalUserId()) {
			throw new HTTPException\ForbiddenException($this->l10n->t('Permission denied.'));
		}

		parent::content();

		$tpl = Renderer::getMarkupTemplate('settings/contactimport.tpl');
		return Renderer::replaceMacros($tpl, [
			'$title'                 => $this->l10n->t('Import Contacts'),
			'$submit'                => $this->l10n->t('Save Settings'),
			'$form_security_token'   => self::getFormSecurityToken('contactimport'),
			'$importcontact_text'    => $this->l10n->t('Upload a CSV file that contains the handle of your followed accounts in the first column you exported from the old account. Your contacts will be added in the background. This can take some time.'),
			'$importcontact_button'  => $this->l10n->t('Upload File'),
			'$importcontact_maxsize' => $this->config->get('system', 'max_csv_file_size', 30720),
			'$legacy_contact'        => ['legacy_contact', $this->t('Your legacy ActivityPub/GNU Social account'), '', $this->t('If you enter your old account name from an ActivityPub based system or your GNU Social/Statusnet account name here (in the format user@domain.tld), your contacts will be added in the background. This can take some time.')],
		]);
	}

	private function import(string $url)
	{
		$contact = Contact::getByURL($url);
		if (!$contact) {
			$this->logger->notice('Couldn\'t fetch information for contact.', ['url' => $url]);
			return;
		}

		$uid = $this->session->getLocalUserId();

		if ($contact['network'] == Protocol::OSTATUS) {
			$api = $contact['baseurl'] . '/api/';

			// Fetching friends
			$curlResult = $this->httpClient->get($api . 'statuses/friends.json?screen_name=' . $contact['nick'], HttpClientAccept::JSON, [HttpClientOptions::REQUEST => HttpClientRequest::OSTATUS]);

			if (!$curlResult->isSuccess()) {
				$this->logger->notice('Couldn\'t fetch friends for contact.', ['url' => $url]);
				return;
			}

			$friends = $curlResult->getBodyString();
			if (empty($friends)) {
				$this->logger->notice('Couldn\'t fetch following contacts.', ['url' => $url]);
				return;
			}
			$friends = json_decode($friends);
		} elseif ($apcontact = APContact::getByURL($contact['url'])) {
			if (empty($apcontact['following'])) {
				$this->logger->notice('Couldn\'t fetch remote profile.', ['url' => $url]);
				return;
			}
			$followings = ActivityPub::fetchItems($apcontact['following']);
			if (empty($followings)) {
				$this->logger->notice('Couldn\'t fetch following contacts.', ['url' => $url]);
				return;
			}
			$friends = $followings;
		} else {
			$this->logger->notice('Unsupported network', ['url' => $url]);
			return;
		}

		if (empty($friends)) {
			$friends = [];
		}

		foreach ($friends as $friend) {
			$contact = $friend->statusnet_profile_url ?? $friend;
			AddContact::add(Worker::PRIORITY_MEDIUM, $uid, $contact);
			$this->logger->notice('Added contact', ['url' => $url, 'contact' => $contact]);
		}

		$this->logger->notice('Done', ['url' => $url]);
		return;
	}
}
