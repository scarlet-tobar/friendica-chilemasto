<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Friendica\Test\Unit\Core\Addon;

use Friendica\Core\Addon\AddonInfo;
use Friendica\Core\Addon\AddonManagerHelper;
use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Util\Profiler;
use PHPUnit\Framework\TestCase;

class AddonManagerHelperTest extends TestCase
{
	public function testGetAddonInfoReturnsAddonInfo(): void
	{
		$addonManagerHelper = new AddonManagerHelper(
			__DIR__ . '/../../../Util/addons',
			$this->createStub(IManageConfigValues::class),
			$this->createStub(Profiler::class)
		);

		$info = $addonManagerHelper->getAddonInfo('helloaddon');

		$this->assertInstanceOf(AddonInfo::class, $info);

		$this->assertEquals('Hello Addon', $info->getName());
	}

	public function testEnabledAddons(): void
	{
		$config = $this->createStub(IManageConfigValues::class);
		$config->method('get')->willReturn([
			'helloaddon' => [
				'last_update' => 1738760499,
				'admin' => false,
			],
		]);

		$addonManagerHelper = new AddonManagerHelper(
			__DIR__ . '/../../../Util/addons',
			$config,
			$this->createStub(Profiler::class)
		);

		$this->assertSame([], $addonManagerHelper->getEnabledAddons());
		$this->assertFalse($addonManagerHelper->isAddonEnabled('helloaddon'));

		$addonManagerHelper->loadAddons();

		$this->assertSame(['helloaddon'], $addonManagerHelper->getEnabledAddons());
		$this->assertTrue($addonManagerHelper->isAddonEnabled('helloaddon'));
	}
}
