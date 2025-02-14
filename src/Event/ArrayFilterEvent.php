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

	public const POST_LOCAL_START = 'friendica.data.post_local_start';

	public const POST_LOCAL = 'friendica.data.post_local';

	public const POST_LOCAL_END = 'friendica.data.post_local_end';

	public const PHOTO_UPLOAD_FORM = 'friendica.data.photo_upload_form';

	public const NETWORK_TO_NAME = 'friendica.data.network_to_name';

	public const CONVERSATION_START = 'friendica.data.conversation_start';

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
