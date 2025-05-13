<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Friendica\Test\Unit\Core\Addon;

use Friendica\Core\Addon\AddonInfo;
use Friendica\Core\Addon\AddonManagerHelper;
use Friendica\Util\Profiler;
use PHPUnit\Framework\TestCase;

class AddonManagerHelperTest extends TestCase
{
	public function testGetAddonInfoReturnsAddonInfo(): void
	{
		$addonManagerHelper = new AddonManagerHelper(
			__DIR__ . '/../../../Util/addons',
			$this->createStub(Profiler::class)
		);

		$info = $addonManagerHelper->getAddonInfo('helloaddon');

		$this->assertInstanceOf(AddonInfo::class, $info);

		$this->assertEquals('Hello Addon', $info->getName());
	}
}
