<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Module\Moderation\Blocklist\Contact;

use Friendica\App\Arguments;
use Friendica\App\BaseURL;
use Friendica\App\Page;
use Friendica\AppHelper;
use Friendica\Core\L10n;
use Friendica\Core\Renderer;
use Friendica\Core\Session\Capability\IHandleUserSessions;
use Friendica\Core\Worker;
use Friendica\Database\Database;
use Friendica\Model;
use Friendica\Module\Response;
use Friendica\Navigation\SystemMessages;
use Friendica\Network\HTTPException;
use Friendica\Util\Profiler;
use Psr\Log\LoggerInterface;

class Import extends \Friendica\Module\BaseModeration
{
	/** @var Database */
	private $database;

	/** @var array of contacts to import */
	private $contactlist = [];

	/** @var array of notices/errors to display */
	private $notices = [];

	public function __construct(Database $database, Page $page, AppHelper $appHelper, SystemMessages $systemMessages, IHandleUserSessions $session, L10n $l10n, BaseURL $baseUrl, Arguments $args, LoggerInterface $logger, Profiler $profiler, Response $response, array $server, array $parameters = [])
	{
		parent::__construct($page, $appHelper, $systemMessages, $session, $l10n, $baseUrl, $args, $logger, $profiler, $response, $server, $parameters);

		$this->database = $database;
	}

	/**
	 * @param array $request
	 * @return void
	 * @throws HTTPException\ForbiddenException
	 * @throws HTTPException\FoundException
	 * @throws HTTPException\InternalServerErrorException
	 * @throws HTTPException\MovedPermanentlyException
	 * @throws HTTPException\TemporaryRedirectException
	 */
	protected function post(array $request = [])
	{
		$this->checkModerationAccess();

		if (!isset($request['page_contactblock_upload']) && !isset($request['page_contactblock_import'])) {
			return;
		}

		self::checkFormSecurityTokenRedirectOnError('/moderation/blocklist/contact/import', 'moderation_contactblock_import');

		if (isset($request['page_contactblock_upload'])) {
			try {
				$this->contactlist = $this->extractFromCSVFile($_FILES['listfile']['tmp_name']);
				if (empty($this->contactlist)) {
					$this->notices[] = $this->t('No valid contacts found in CSV file.');
					return;
				}
				// Show preview, don't import yet
				return;
			} catch (\Throwable $e) {
				$this->notices[] = $this->t('Error importing contact file: %s', $e->getMessage());
				return;
			}
		} elseif (isset($request['page_contactblock_import'])) {
			$this->contactlist = json_decode($request['contactlist'], true);
			if ($this->contactlist === null) {
				$this->notices[] = $this->t('Error parsing contact data.');
				return;
			}
		}

		$blocked = 0;
		$skipped = 0;
		$errors = [];
		$purge = $request['purge'] ?? false;

		foreach ($this->contactlist as $item) {
			$contact_url = $item['url'] ?? '';
			$block_reason = $item['reason'] ?? '';

			if (empty($contact_url)) {
				$skipped++;
				continue;
			}

			$contact = Model\Contact::getByURL($contact_url, null, ['id', 'nurl']);
			if (empty($contact)) {
				$errors[] = $this->t('Contact not found: %s', $contact_url);
				$skipped++;
				continue;
			}

			if ($this->baseUrl->isLocalUrl($contact['nurl'])) {
				$errors[] = $this->t('Skipped local contact: %s', $contact_url);
				$skipped++;
				continue;
			}

			// Check if already blocked
			$existing = $this->database->selectFirst('contact', ['blocked'], ['id' => $contact['id'], 'uid' => 0]);
			if (!empty($existing) && $existing['blocked']) {
				$errors[] = $this->t('Already blocked: %s', $contact_url);
				$skipped++;
				continue;
			}

			Model\Contact::block($contact['id'], $block_reason);
			$blocked++;

			if ($purge) {
				foreach (Model\Contact::selectToArray(['id'], ['nurl' => $contact['nurl']]) as $contact_entry) {
					Worker::add(Worker::PRIORITY_LOW, 'Contact\RemoveContent', $contact_entry['id']);
				}
			}
		}

		// Build result messages
		if ($blocked > 0) {
			$this->notices[] = $this->tt('%d contact was blocked.', '%d contacts were blocked.', $blocked);
		}
		if ($skipped > 0) {
			$this->notices[] = $this->tt('%d contact was skipped.', '%d contacts were skipped.', $skipped);
		}
		if (!empty($errors)) {
			$this->notices = array_merge($this->notices, array_slice($errors, 0, 10)); // Show max 10 errors
			if (count($errors) > 10) {
				$this->notices[] = $this->t('... and %d more errors', count($errors) - 10);
			}
		}
		if ($blocked === 0 && $skipped === 0) {
			$this->notices[] = $this->t('No contacts were imported.');
		}

		// Clear contactlist after import
		$this->contactlist = [];
	}

	/**
	 * @param array $request
	 * @return string
	 * @throws HTTPException\ServiceUnavailableException
	 */
	protected function content(array $request = []): string
	{
		parent::content();

		$t = Renderer::getMarkupTemplate('moderation/blocklist/contact/import.tpl');
		return Renderer::replaceMacros($t, [
			'$notices' => $this->notices,
			'$l10n' => [
				'return_list'    => $this->t('← Return to the list'),
				'title'          => $this->t('Moderation'),
				'page'           => $this->t('Import a Contact Blocklist'),
				'download'       => $this->t('<p>Upload a CSV file with contact URLs and reasons for blocking.</p>'),
				'upload'         => $this->t('Upload file'),
				'contacts'       => $this->t('Contacts to import'),
				'contact_url'    => $this->t('Contact URL'),
				'block_reason'   => $this->t('Block Reason'),
				'import'         => $this->t('Import Contacts'),
				'contact_count'  => $this->tt('%d total contact', '%d total contacts', count($this->contactlist)),
				'purge'          => $this->t('Also purge contacts'),
				'purge_desc'     => $this->t('Removes all content related to these contacts from the node. Keeps the contact records. This action cannot be undone.'),
			],
			'$listfile'            => ['listfile', $this->t('Contact blocklist CSV file'), '', '', $this->t('Required'), '', 'file'],
			'$purge'               => ['purge', $this->t('Also purge contacts'), false, $this->t('Removes all content related to these contacts from the node. Keeps the contact records. This action cannot be undone.')],
			'$contactlist'         => $this->contactlist,
			'$form_security_token' => self::getFormSecurityToken('moderation_contactblock_import')
		]);
	}

	/**
	 * Extracts a contact block list from the provided CSV file name.
	 *
	 * @param string $filename
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function extractFromCSVFile(string $filename): array
	{
		$fp = fopen($filename, 'r');
		if ($fp === false) {
			throw new \Exception($this->l10n->t('The file "%s" could not be opened for importing', $filename));
		}

		$contactlist = [];
		while (($data = fgetcsv($fp, 1000)) !== false) {
			$item = [
				'url' => $data[0] ?? '',
				'reason' => $data[1] ?? '',
			];
			
			if (!empty($item['url']) && !in_array($item, $contactlist)) {
				$contactlist[] = $item;
			}
		}

		fclose($fp);
		return $contactlist;
	}
}
