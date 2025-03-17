<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Friendica\Event;

/**
 * Allow Event listener to modify an array.
 *
 * @internal
 */
final class ArrayFilterEvent extends Event
{
	public const APP_MENU = 'friendica.data.app_menu';

	public const NAV_INFO = 'friendica.data.nav_info';

	public const FEATURE_ENABLED = 'friendica.data.feature_enabled';

	public const FEATURE_GET = 'friendica.data.feature_get';

	public const INSERT_POST_LOCAL_START = 'friendica.data.insert_post_local_start';

	public const INSERT_POST_LOCAL = 'friendica.data.insert_post_local';

	public const INSERT_POST_LOCAL_END = 'friendica.data.insert_post_local_end';

	public const INSERT_POST_REMOTE = 'friendica.data.insert_post_remote';

	public const INSERT_POST_REMOTE_END = 'friendica.data.insert_post_remote_end';

	/**
	 * item array before any work
	 */
	public const PREPARE_POST_START = 'friendica.data.prepare_post_start';

	/**
	 * before first bbcode to html
	 */
	public const PREPARE_POST_FILTER_CONTENT = 'friendica.data.prepare_post_filter_content';

	/**
	 * after first bbcode to html
	 */
	public const PREPARE_POST = 'friendica.data.prepare_post';

	/**
	 * after attach icons and blockquote special case handling (spoiler, author)
	 */
	public const PREPARE_POST_END = 'friendica.data.prepare_post_end';

	public const PHOTO_UPLOAD_FORM = 'friendica.data.photo_upload_form';

	public const NETWORK_TO_NAME = 'friendica.data.network_to_name';

	public const CONVERSATION_START = 'friendica.data.conversation_start';

	public const ITEM_TAGGED = 'friendica.data.item_tagged';

	public const DISPLAY_ITEM = 'friendica.data.display_item';

	public const DETECT_LANGUAGES = 'friendica.data.detect_languages';

	public const RENDER_LOCATION = 'friendica.data.render_location';

	public const ITEM_PHOTO_MENU = 'friendica.data.item_photo_menu';

	public const CONTACT_PHOTO_MENU = 'friendica.data.contact_photo_menu';

	public const OEMBED_FETCH_END = 'friendica.data.oembed_fetch_end';

	public const PAGE_INFO = 'friendica.data.page_info';

	public const SMILEY_LIST = 'friendica.data.smiley_list';

	public const BBCODE_TO_HTML_START = 'friendica.data.bbcode_to_html_start';

	public const HTML_TO_BBCODE_END = 'friendica.data.html_to_bbcode_end';

	public const BBCODE_TO_MARKDOWN_END = 'friendica.data.bbcode_to_markdown_end';

	public const JOT_NETWORKS = 'friendica.data.jot_networks';

	public const PROTOCOL_SUPPORTS_FOLLOW = 'friendica.data.protocol_supports_follow';

	public const PROTOCOL_SUPPORTS_REVOKE_FOLLOW = 'friendica.data.protocol_supports_revoke_follow';

	public const PROTOCOL_SUPPORTS_PROBE = 'friendica.data.protocol_supports_probe';

	public const FOLLOW_CONTACT = 'friendica.data.follow_contact';

	public const UNFOLLOW_CONTACT = 'friendica.data.unfollow_contact';

	public const REVOKE_FOLLOW_CONTACT = 'friendica.data.revoke_follow_contact';

	public const BLOCK_CONTACT = 'friendica.data.block_contact';

	public const UNBLOCK_CONTACT = 'friendica.data.unblock_contact';

	public const AVATAR_LOOKUP = 'friendica.data.avatar_lookup';

	public const EVENT_CREATED = 'friendica.data.event_created';

	public const EVENT_UPDATED = 'friendica.data.event_updated';

	public const ADD_WORKER_TASK = 'friendica.data.add_worker_task';

	public const STORAGE_CONFIG = 'friendica.data.storage_config';

	public const STORAGE_INSTANCE = 'friendica.data.storage_instance';

	public const DB_STRUCTURE_DEFINITION = 'friendica.data.db_structure_definition';

	public const DB_VIEW_DEFINITION = 'friendica.data.db_view_definition';

	private array $array;

	public function __construct(string $name, array $array)
	{
		parent::__construct($name);

		$this->array = $array;
	}

	public function getArray(): array
	{
		return $this->array;
	}

	public function setArray(array $array): void
	{
		$this->array = $array;
	}
}
