<?php

declare(strict_types=1);

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Content\Conversation\Factory;

use Friendica\Content\Conversation\Repository\UserDefinedChannel;
use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Core\L10n;
use Friendica\Core\Protocol;
use Friendica\Database\Database;
use Friendica\Model\Contact;
use Friendica\Model\Item;
use Friendica\Model\Post;
use Friendica\Model\Tag;
use Psr\Log\LoggerInterface;

/**
 * ChannelPost factory class.
 *
 * Handles caching of posts into user-defined channels based on engagement
 * and matching rules.
 *
 * @package Friendica\Content\Conversation\Factory
 */
final class ChannelPost
{
	private LoggerInterface $logger;
	private UserDefinedChannel $channelRepository;
	private Database $dba;
	private IManageConfigValues $config;

	/**
	 * ChannelPost constructor.
	 *
	 * @param Database $dba Database access object.
	 * @param UserDefinedChannel $channel Channel repository.
	 * @param LoggerInterface $logger Logger instance.
	 * @param IManageConfigValues $config Configuration manager.
	 */
	public function __construct(Database $dba, UserDefinedChannel $channel, LoggerInterface $logger, IManageConfigValues $config)
	{
		$this->dba               = $dba;
		$this->logger            = $logger;
		$this->channelRepository = $channel;
		$this->config            = $config;
	}

	/**
	 * Determine whether a post may be cached for a given channel.
	 *
	 * Private posts stored for specific users are allowed. Non-federated posts
	 * stored for specific users are allowed. Federated public/unlisted posts
	 * are only allowed when stored globally (uid = 0) so they're reachable
	 * for all users.
	 *
	 * @param int $uid Owner user id (0 for global).
	 * @param int $private Visibility constant (use values from `Item`).
	 * @param string $network Network identifier.
	 * @return bool True if caching is allowed for the given parameters.
	 */
	public function isValidChannelPostBase(int $uid, int $private, string $network): bool
	{
		// Private posts that are stored for specific users are okay
		if ($private === Item::PRIVATE && $uid !== 0) {
			return true;
		}

		// Non federated posts that are stored for specific users are okay
		if (!in_array($network, Protocol::FEDERATED) && $uid !== 0) {
			return true;
		}

		// For federated public/unlisted posts we only allow caching when uid = 0 to make them reachable to all users
		if (in_array($network, Protocol::FEDERATED) && in_array($private, [Item::PUBLIC, Item::UNLISTED]) && $uid === 0) {
			return true;
		}

		return false;
	}

	/**
	 * Add a post to matching user-defined channels.
	 *
	 * This will insert entries into the `channel-post` cache table when the
	 * system's channel caching is enabled and matching channels are found.
	 *
	 * @param int $uri_id URI id of the post.
	 * @param int $uid User id context.
	 * @param int $reshare_id Optional reshare id.
	 * @return void
	 */
	public function add(int $uri_id, int $uid, int $reshare_id = 0): void
	{
		if (!$this->config->get('system', 'channel_cache')) {
			return;
		}

		$engagement = $this->dba->selectFirst('post-engagement', ['searchtext', 'media-type', 'owner-id', 'language'], ['uri-id' => $uri_id]);
		if ($engagement === false || $engagement === []) {
			$this->logger->debug('No engagement found', ['uri-id' => $uri_id]);
			return;
		}

		$language = $engagement['language'] !== L10n::UNDETERMINED_LANGUAGE ? $engagement['language'] : '';
		$tags     = array_column(Tag::getByURIId($uri_id, [Tag::HASHTAG]), 'name');

		$channels = $this->channelRepository->getMatchingChannels($engagement['searchtext'], $language, $tags, $engagement['media-type'], $engagement['owner-id'], $reshare_id, $uid);
		if (!($channels instanceof \Friendica\Content\Conversation\Collection\UserDefinedChannels) || $channels->count() === 0) {
			return;
		}

		$post = Post::selectFirstPost(['created', 'received', 'commented'], ['uri-id' => $uri_id]);
		if ($post === false || $post === []) {
			return;
		}

		foreach ($channels as $channel) {
			if ($uid == 0 && in_array($channel->circle, [-3, -4, -5]) && !Contact::isSharing($engagement['owner-id'], $channel->uid)) {
				continue;
			}

			$cache = [
				'channel'   => (int)$channel->code,
				'uid'       => $channel->uid,
				'uri-id'    => $uri_id,
				'created'   => $post['created'],
				'received'  => $post['received'],
				'commented' => $post['commented'],
			];
			$ret = $this->dba->insert('channel-post', $cache, Database::INSERT_UPDATE);
			$this->logger->debug('Added channel post', ['uri-id' => $uri_id, 'cache' => $cache, 'ret' => $ret]);
		}
	}
}
