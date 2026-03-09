<?php

declare(strict_types=1);

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Content\Conversation\Factory;

use Friendica\Content\Conversation\Repository\UserDefinedChannel;
use Friendica\Content\Conversation\Factory\Activity as ActivityFactory;
use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Content\Conversation\Entity\Channel;
use Friendica\Core\Protocol;
use Friendica\Database\Database;
use Friendica\Model\Contact;
use Friendica\Model\Item;
use Friendica\Model\Post;
use Friendica\Model\User;
use Friendica\Protocol\Activity;
use Friendica\Util\DateTimeFormat;
use Psr\Log\LoggerInterface;

/**
 * SystemChannelPost factory class.
 *
 * Evaluates posts and stores them into system channels (e.g. whatshot,
 * foryou, discover) for users based on engagement, relations and
 * repository thresholds.
 *
 * @package Friendica\Content\Conversation\Factory
 */
final class SystemChannelPost
{
	private LoggerInterface $logger;
	private UserDefinedChannel $channelRepository;
	private Database $dba;
	private IManageConfigValues $config;
	private ActivityFactory $activityFactory;

	/**
	 * SystemChannelPost constructor.
	 *
	 * @param Database $dba Database access object.
	 * @param UserDefinedChannel $channel Channel repository.
	 * @param LoggerInterface $logger Logger instance.
	 * @param IManageConfigValues $config Configuration manager.
	 * @param ActivityFactory $activityFactory Activity factory.
	 */
	public function __construct(Database $dba, UserDefinedChannel $channel, LoggerInterface $logger, IManageConfigValues $config, ActivityFactory $activityFactory)
	{
		$this->dba               = $dba;
		$this->logger            = $logger;
		$this->channelRepository = $channel;
		$this->config            = $config;
		$this->activityFactory   = $activityFactory;
	}

	/**
	 * Add a post to matching system channels for one or many users.
	 *
	 * When system channel caching is enabled this computes whether a post
	 * should be stored in various system channels for the given user(s)
	 * and inserts cache entries into `system-channel-post`.
	 *
	 * @param array $engagement post-engagement record
	 * @param int $gravity Gravity of the post
	 * @param int $post_uid User id context (0 for all users).
	 * @param string $network Network identifier.
	 * @param int $reshare_id Optional reshare id.
	 * @return void
	 */
	public function add(array $engagement, int $gravity, int $post_uid, string $network, int $reshare_id = 0): void
	{
		if (!$this->config->get('system', 'system_channel_cache')) {
			return;
		}

		$this->logger->debug('Adding system channel post', ['uri-id' => $engagement['uri-id'], 'post_uid' => $post_uid, 'reshare_id' => $reshare_id]);

		$owner = $reshare_id ?: $engagement['owner-id'];

		$post = Post::selectFirstPost(['created', 'received', 'commented', 'network', 'private'], ['uri-id' => $engagement['uri-id']]);
		if ($post === false || $post === []) {
			$this->logger->debug('Post not found', ['uri-id' => $engagement['uri-id'], 'post_uid' => $post_uid, 'reshare_id' => $reshare_id]);
			return;
		}

		if (in_array($network, [Protocol::MAIL, Protocol::FEED])) {
			$channels = [Channel::QUIETSHARERS, Channel::IMAGE, Channel::VIDEO, Channel::AUDIO, Channel::LANGUAGE];
		} elseif ($engagement['restricted']) {
			$channels = [Channel::FORYOU, Channel::QUIETSHARERS, Channel::IMAGE, Channel::VIDEO, Channel::AUDIO, Channel::LANGUAGE];
		} elseif ($gravity === Item::GRAVITY_PARENT) {
			$channels = [Channel::WHATSHOT, Channel::FORYOU, Channel::DISCOVER, Channel::FOLLOWERS, Channel::SHARERSOFSHARERS, Channel::QUIETSHARERS, Channel::IMAGE, Channel::VIDEO, Channel::AUDIO, Channel::LANGUAGE];
		} else {
			$channels = [Channel::WHATSHOT, Channel::FORYOU, Channel::DISCOVER];
		}

		$uids = $this->channelRepository->getUsersForPost($engagement['uri-id'], $post_uid, $post['network'], $post['private']);

		foreach ($uids as $uid) {
			if ($engagement['restricted'] && !Post::exists(['parent-uri-id' => $engagement['uri-id'], 'uid' => $uid])) {
				continue;
			}

			$activities = $this->activityFactory->getActivities($uid, $network);

			foreach ($channels as $channel) {
				if ($this->dba->exists('system-channel-post', ['channel' => $channel, 'uid' => $uid, 'uri-id' => $engagement['uri-id']])) {
					continue;
				}

				$wanted = User::getWantedLanguages($uid);
				if ($channel !== Channel::LANGUAGE && $wanted && !in_array($engagement['language'], $wanted)) {
					continue;
				}

				$store = false;
				switch ($channel) {
					case Channel::WHATSHOT:
						$store = ($engagement['comments'] > $activities->medianComments || $engagement['activities'] > $activities->medianActivities || $engagement['views'] > $activities->medianViews) && $engagement['contact-type'] != Contact::TYPE_COMMUNITY;
						break;

					case Channel::FORYOU:
						if ($relation = $this->dba->selectFirst('contact-relation', ['relation-thread-score', 'follows'], ['relation-cid' => $activities->cid, 'cid' => $owner])) {
							$store = $relation['relation-thread-score'] > $activities->medianThreadScore;
							if (!$store && $relation['follows']) {
								$store = ($engagement['comments'] >= $activities->medianComments || $engagement['activities'] >= $activities->medianActivities || $engagement['views'] >= $activities->medianViews);
							}
						}

						if (!$store && $user_contact = $this->dba->selectFirst('user-contact', ['channel-frequency', 'notify_new_posts'], ['cid' => $owner, 'uid' => $uid])) {
							$store = $user_contact['channel-frequency'] == Contact\User::FREQUENCY_ALWAYS || $user_contact['notify_new_posts'];
						}
						break;

					case Channel::DISCOVER:
						if (!$this->dba->exists('account-user-view', ['pid' => $owner, 'uid' => $uid, 'rel' => [Contact::SHARING, Contact::FRIEND]])) {
							$store = $this->dba->exists('contact-relation', ["`cid` = ? AND `relation-cid` = ? AND `relation-thread-score` > ?",
								$owner, $activities->cid, $activities->medianThreadScore]);
							if (!$store) {
								$store = $this->dba->exists('contact-relation', ["`relation-cid` = ? AND `cid` = ? AND `relation-thread-score` > ?",
									$owner, $activities->cid, $activities->medianThreadScore]);
							}
							if (!$store) {
								$store = $this->dba->exists('contact-relation', ["`relation-cid` = ? AND `cid` = ? AND `follows` AND `relation-thread-score` > ?",
									$owner, $activities->cid, 0]);
							}
							if (!$store && ($engagement['comments'] >= $activities->medianComments || $engagement['activities'] >= $activities->medianActivities) || $engagement['views'] >= $activities->medianViews) {
								$store = $this->dba->exists('contact-relation', ["`relation-cid` = ? AND `cid` = ? AND `relation-thread-score` > ?", $owner, $activities->cid, 0]);
								if (!$store) {
									$store = $this->dba->exists('contact-relation', ["`cid` = ? AND `relation-cid` = ? AND `relation-thread-score` > ?", $owner, $activities->cid, 0]);
								}
								if (!$store) {
									$condition = [
										"`cid` = ? AND `follows` AND `last-interaction` > ?
										AND `relation-cid` IN (SELECT `cid` FROM `contact-relation` WHERE `follows` AND `relation-cid` = ? AND `relation-thread-score` >= ?)
										AND NOT `cid` IN (SELECT `cid` FROM `contact-relation` WHERE `follows` AND `relation-cid` = ?)",
										$owner, DateTimeFormat::utc('now - ' . $this->config->get('channel', 'sharer_interaction_days') . ' day'), $activities->cid, $activities->medianThreadScore, $activities->cid
									];
									$store = $this->dba->exists('contact-relation', $condition);
								}
							}
						}
						break;

					case Channel::FOLLOWERS:
						$store = $this->dba->exists('account-user-view', ['pid' => $owner, 'uid' => $uid, 'rel' => Contact::FOLLOWER]);
						break;

					case Channel::SHARERSOFSHARERS:
						$condition = [
							"`cid` = ? AND `follows` AND `last-interaction` > ?
							AND `relation-cid` IN (SELECT `cid` FROM `contact-relation` WHERE `follows` AND `relation-cid` = ? AND `relation-thread-score` >= ?)
							AND NOT `cid` IN (SELECT `cid` FROM `contact-relation` WHERE `follows` AND `relation-cid` = ?)",
							$owner, DateTimeFormat::utc('now - ' . $this->config->get('channel', 'sharer_interaction_days') . ' day'), $activities->cid, $activities->medianThreadScore, $activities->cid
						];
						$store = $this->dba->exists('contact-relation', $condition);
						break;

					case Channel::QUIETSHARERS:
						$relation = $this->dba->selectFirst('contact-relation', ['post-score'], [
							'follows'      => true,
							'relation-cid' => $activities->cid,
							'cid'          => $owner,
						]);
						$store = ($relation && $relation['post-score'] <= $activities->medianPostScore);
						break;

					case Channel::IMAGE:
						$store = $engagement['media-type'] & 1;
						break;

					case Channel::VIDEO:
						$store = $engagement['media-type'] & 2;
						break;

					case Channel::AUDIO:
						$store = $engagement['media-type'] & 4;
						break;

					case Channel::LANGUAGE:
						$store = $engagement['language'] === User::getLanguageCode($uid);
						break;
				}

				if (!$store) {
					continue;
				}

				$cache = [
					'channel'     => $channel,
					'uid'         => $uid,
					'uri-id'      => $engagement['uri-id'],
					'in-timeline' => Post::exists(["`parent-uri-id` = ? AND `uid` = ? AND NOT `verb` IN (?, ?, ?)", $engagement['uri-id'], $uid, Activity::FOLLOW, Activity::VIEW, Activity::READ]),
					'created'     => $post['created'],
					'received'    => $post['received'],
					'commented'   => $post['commented'],
				];
				$ret = $this->dba->insert('system-channel-post', $cache, Database::INSERT_UPDATE);
				$this->logger->debug('Added system channel post', ['uri-id' => $engagement['uri-id'], 'cache' => $cache, 'ret' => $ret]);
			}
		}
	}
}
