<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Friendica\Test\Unit\Core\Addon;

use Exception;
use Friendica\Core\Addon\AddonInfo;
use Friendica\Core\Addon\AddonManagerHelper;
use Friendica\Core\Cache\Capability\ICanCache;
use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Database\Database;
use Friendica\Util\Profiler;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AddonManagerHelperTest extends TestCase
{
	public function testGetAddonInfoReturnsAddonInfo(): void
	{
		$addonManagerHelper = new AddonManagerHelper(
			__DIR__ . '/../../../Util/addons',
			$this->createStub(Database::class),
			$this->createStub(IManageConfigValues::class),
			$this->createStub(ICanCache::class),
			$this->createStub(LoggerInterface::class),
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
			$this->createStub(Database::class),
			$config,
			$this->createStub(ICanCache::class),
			$this->createStub(LoggerInterface::class),
			$this->createStub(Profiler::class)
		);

		$this->assertSame([], $addonManagerHelper->getEnabledAddons());
		$this->assertFalse($addonManagerHelper->isAddonEnabled('helloaddon'));

		$addonManagerHelper->loadAddons();

		$this->assertSame(['helloaddon'], $addonManagerHelper->getEnabledAddons());
		$this->assertTrue($addonManagerHelper->isAddonEnabled('helloaddon'));
	}

	public function testGetVisibleEnabledAddons(): void
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
			$this->createStub(Database::class),
			$config,
			$this->createStub(ICanCache::class),
			$this->createStub(LoggerInterface::class),
			$this->createStub(Profiler::class)
		);

		$this->assertSame(['helloaddon'], $addonManagerHelper->getVisibleEnabledAddons());
	}

	public function testGetEnabledAddonsWithAdminSettings(): void
	{
		$config = $this->createStub(IManageConfigValues::class);
		$config->method('get')->willReturn([
			'helloaddon' => [
				'last_update' => 1738760499,
				'admin' => false,
			],
			'addonwithadminsettings' => [
				'last_update' => 1738760499,
				'admin' => true,
			],
		]);

		$addonManagerHelper = new AddonManagerHelper(
			__DIR__ . '/../../../Util/addons',
			$this->createStub(Database::class),
			$config,
			$this->createStub(ICanCache::class),
			$this->createStub(LoggerInterface::class),
			$this->createStub(Profiler::class)
		);

		$this->assertSame(['addonwithadminsettings'], $addonManagerHelper->getEnabledAddonsWithAdminSettings());
	}

	public function testGetAvailableAddons(): void
	{
		$addonManagerHelper = new AddonManagerHelper(
			__DIR__ . '/../../../Util/addons',
			$this->createStub(Database::class),
			$this->createStub(IManageConfigValues::class),
			$this->createStub(ICanCache::class),
			$this->createStub(LoggerInterface::class),
			$this->createStub(Profiler::class)
		);

		$this->assertSame(['helloaddon'], $addonManagerHelper->getAvailableAddons());
	}

	public function testInstallAddonIncludesAddonFile(): void
	{
		$root = vfsStream::setup(__FUNCTION__ . '_addons', 0777, [
			'helloaddon' => [
				'helloaddon.php' => '<?php throw new \Exception("Addon file loaded");',
			]
		]);

		$addonManagerHelper = new AddonManagerHelper(
			$root->url(),
			$this->createStub(Database::class),
			$this->createStub(IManageConfigValues::class),
			$this->createStub(ICanCache::class),
			$this->createStub(LoggerInterface::class),
			$this->createStub(Profiler::class)
		);

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Addon file loaded');

		$addonManagerHelper->installAddon('helloaddon');
	}

	public function testInstallAddonCallsInstallFunction(): void
	{
		// We need a unique name for the addon to avoid conflicts
		// with other tests that may define the same install function.
		$addonName = __FUNCTION__;

		$root = vfsStream::setup(__FUNCTION__ . '_addons', 0777, [
			$addonName => [
				$addonName . '.php' => <<<PHP
					<?php
					function {$addonName}_install()
					{
						throw new \Exception("Addon installed");
					}
					PHP,
			]
		]);

		$addonManagerHelper = new AddonManagerHelper(
			$root->url(),
			$this->createStub(Database::class),
			$this->createStub(IManageConfigValues::class),
			$this->createStub(ICanCache::class),
			$this->createStub(LoggerInterface::class),
			$this->createStub(Profiler::class)
		);

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Addon installed');

		$addonManagerHelper->installAddon($addonName);
	}

	public function testInstallAddonUpdatesConfig(): void
	{
		$root = vfsStream::setup(__FUNCTION__ . '_addons', 0777, [
			'helloaddon' => [
				'helloaddon.php' => '<?php',
			]
		]);

		$root->getChild('helloaddon/helloaddon.php')->lastModified(1234567890);

		$config = $this->createMock(IManageConfigValues::class);
		$config->expects($this->once())->method('set')->with(
			'addons',
			'helloaddon',
			['last_update' => 1234567890, 'admin' => false]
		);

		$addonManagerHelper = new AddonManagerHelper(
			$root->url(),
			$this->createStub(Database::class),
			$config,
			$this->createStub(ICanCache::class),
			$this->createStub(LoggerInterface::class),
			$this->createStub(Profiler::class)
		);

		$addonManagerHelper->installAddon('helloaddon');
	}

	public function testInstallAddonEnablesAddon(): void
	{
		$root = vfsStream::setup(__FUNCTION__ . '_addons', 0777, [
			'helloaddon' => [
				'helloaddon.php' => '<?php',
			]
		]);

		$addonManagerHelper = new AddonManagerHelper(
			$root->url(),
			$this->createStub(Database::class),
			$this->createStub(IManageConfigValues::class),
			$this->createStub(ICanCache::class),
			$this->createStub(LoggerInterface::class),
			$this->createStub(Profiler::class)
		);

		$this->assertSame([], $addonManagerHelper->getEnabledAddons());

		$this->assertTrue($addonManagerHelper->installAddon('helloaddon'));

		$this->assertSame(['helloaddon'], $addonManagerHelper->getEnabledAddons());
	}
	public function testUninstallAddonIncludesAddonFile(): void
	{
		$root = vfsStream::setup(__FUNCTION__ . '_addons', 0777, [
			'helloaddon' => [
				'helloaddon.php' => '<?php throw new \Exception("Addon file loaded");',
			]
		]);

		$addonManagerHelper = new AddonManagerHelper(
			$root->url(),
			$this->createStub(Database::class),
			$this->createStub(IManageConfigValues::class),
			$this->createStub(ICanCache::class),
			$this->createStub(LoggerInterface::class),
			$this->createStub(Profiler::class)
		);

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Addon file loaded');

		$addonManagerHelper->uninstallAddon('helloaddon');
	}

	public function testUninstallAddonCallsUninstallFunction(): void
	{
		// We need a unique name for the addon to avoid conflicts
		// with other tests that may define the same install function.
		$addonName = __FUNCTION__;

		$root = vfsStream::setup(__FUNCTION__ . '_addons', 0777, [
			$addonName => [
				$addonName . '.php' => <<<PHP
					<?php
					function {$addonName}_uninstall()
					{
						throw new \Exception("Addon uninstalled");
					}
					PHP,
			]
		]);

		$addonManagerHelper = new AddonManagerHelper(
			$root->url(),
			$this->createStub(Database::class),
			$this->createStub(IManageConfigValues::class),
			$this->createStub(ICanCache::class),
			$this->createStub(LoggerInterface::class),
			$this->createStub(Profiler::class)
		);

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Addon uninstalled');

		$addonManagerHelper->uninstallAddon($addonName);
	}

	public function testUninstallAddonRemovesHooksFromDatabase(): void
	{
		$root = vfsStream::setup(__FUNCTION__ . '_addons', 0777, [
			'helloaddon' => [
				'helloaddon.php' => '<?php',
			]
		]);

		$database = $this->createMock(Database::class);
		$database->expects($this->once())
			->method('delete')
			->with(
				'hook',
				['`file` LIKE ?', '%/helloaddon/helloaddon.php']
			);

		$addonManagerHelper = new AddonManagerHelper(
			$root->url(),
			$database,
			$this->createStub(IManageConfigValues::class),
			$this->createStub(ICanCache::class),
			$this->createStub(LoggerInterface::class),
			$this->createStub(Profiler::class)
		);

		$addonManagerHelper->uninstallAddon('helloaddon');
	}

	public function testUninstallAddonDisablesAddon(): void
	{
		$root = vfsStream::setup(__FUNCTION__ . '_addons', 0777, [
			'helloaddon' => [
				'helloaddon.php' => '<?php',
			]
		]);

		$config = $this->createStub(IManageConfigValues::class);
		$config->method('get')->willReturn([
			'helloaddon' => [
				'last_update' => 1234567890,
				'admin' => false,
			],
		]);

		$addonManagerHelper = new AddonManagerHelper(
			$root->url(),
			$this->createStub(Database::class),
			$config,
			$this->createStub(ICanCache::class),
			$this->createStub(LoggerInterface::class),
			$this->createStub(Profiler::class)
		);

		$addonManagerHelper->loadAddons();

		$this->assertSame(['helloaddon'], $addonManagerHelper->getEnabledAddons());

		$addonManagerHelper->uninstallAddon('helloaddon');

		$this->assertSame([], $addonManagerHelper->getEnabledAddons());
	}

	public function testReloadAddonsInstallsAddon(): void
	{
		// We need a unique name for the addon to avoid conflicts
		// with other tests that may define the same install function.
		$addonName = __FUNCTION__;

		$root = vfsStream::setup(__FUNCTION__ . '_addons', 0777, [
			$addonName => [
				$addonName . '.php' => <<<PHP
					<?php
					function {$addonName}_install()
					{
						throw new \Exception("Addon reinstalled");
					}
					PHP,
			]
		]);

		$root->getChild($addonName . '/' . $addonName . '.php')->lastModified(1234567890);

		$config = $this->createStub(IManageConfigValues::class);
		$config->method('get')->willReturn([
			$addonName => [
				'last_update' => 0,
				'admin' => false,
			],
		]);

		$addonManagerHelper = new AddonManagerHelper(
			$root->url(),
			$this->createStub(Database::class),
			$config,
			$this->createStub(ICanCache::class),
			$this->createStub(LoggerInterface::class),
			$this->createStub(Profiler::class)
		);

		$addonManagerHelper->loadAddons();

		$this->assertSame([$addonName], $addonManagerHelper->getEnabledAddons());

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Addon reinstalled');

		$addonManagerHelper->reloadAddons();
	}
}
