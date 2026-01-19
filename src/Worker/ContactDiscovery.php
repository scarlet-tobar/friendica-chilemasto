<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Worker;

use Friendica\Core\Worker;
use Friendica\DI;
use Friendica\Model\Contact;

class ContactDiscovery
{
	/**
	 * Discover contact relations
	 * @param string $url
	 */
	public static function execute(string $url)
	{
		Contact\Relation::discoverByUrl($url);
	}

	/**
	 * @param array|int $run_parameters Priority constant or array of options described in Worker::add
	 * @param string    $url
	 * @return int
	 */
	public static function add($run_parameters, string $url): int
	{
		DI::logger()->debug('Contact discovery', ['url' => $url]);
		return Worker::add($run_parameters, 'ContactDiscovery', $url);
	}
}
