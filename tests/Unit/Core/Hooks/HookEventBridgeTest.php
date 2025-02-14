<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Friendica\Test\Unit\Core\Hooks;

use FastRoute\RouteCollector;
use Friendica\Core\Config\Util\ConfigFileManager;
use Friendica\Core\Hooks\HookEventBridge;
use Friendica\Event\ArrayFilterEvent;
use Friendica\Event\CollectRoutesEvent;
use Friendica\Event\ConfigLoadedEvent;
use Friendica\Event\Event;
use Friendica\Event\HtmlFilterEvent;
use PHPUnit\Framework\TestCase;

class HookEventBridgeTest extends TestCase
{
	public function testGetStaticSubscribedEventsReturnsStaticMethods(): void
	{
		$expected = [
			Event::INIT                         => 'onNamedEvent',
			Event::HOME_INIT                    => 'onNamedEvent',
			ConfigLoadedEvent::CONFIG_LOADED    => 'onConfigLoadedEvent',
			CollectRoutesEvent::COLLECT_ROUTES  => 'onCollectRoutesEvent',
			ArrayFilterEvent::APP_MENU          => 'onArrayFilterEvent',
			ArrayFilterEvent::NAV_INFO          => 'onArrayFilterEvent',
			ArrayFilterEvent::FEATURE_ENABLED   => 'onArrayFilterEvent',
			ArrayFilterEvent::FEATURE_GET       => 'onArrayFilterEvent',
			ArrayFilterEvent::POST_LOCAL_START  => 'onArrayFilterEvent',
			ArrayFilterEvent::POST_LOCAL        => 'onArrayFilterEvent',
			ArrayFilterEvent::POST_LOCAL_END    => 'onArrayFilterEvent',
			ArrayFilterEvent::PHOTO_UPLOAD_FORM => 'onArrayFilterEvent',
			ArrayFilterEvent::NETWORK_TO_NAME   => 'onArrayFilterEvent',
			HtmlFilterEvent::HEAD               => 'onHtmlFilterEvent',
			HtmlFilterEvent::FOOTER             => 'onHtmlFilterEvent',
			HtmlFilterEvent::PAGE_HEADER        => 'onHtmlFilterEvent',
			HtmlFilterEvent::PAGE_CONTENT_TOP   => 'onHtmlFilterEvent',
			HtmlFilterEvent::PAGE_END           => 'onHtmlFilterEvent',
			HtmlFilterEvent::JOT_TOOL           => 'onHtmlFilterEvent',
		];

		$this->assertSame(
			$expected,
			HookEventBridge::getStaticSubscribedEvents()
		);

		foreach ($expected as $methodName) {
			$this->assertTrue(
				method_exists(HookEventBridge::class, $methodName),
				$methodName . '() is not defined'
			);

			$this->assertTrue(
				(new \ReflectionMethod(HookEventBridge::class, $methodName))->isStatic(),
				$methodName . '() is not static'
			);
		}
	}

	public static function getNamedEventData(): array
	{
		return [
			['test', 'test'],
			[Event::INIT, 'init_1'],
			[Event::HOME_INIT, 'home_init'],
		];
	}

	/**
	 * @dataProvider getNamedEventData
	 */
	public function testOnNamedEventCallsHook($name, $expected): void
	{
		$event = new Event($name);

		$reflectionProperty = new \ReflectionProperty(HookEventBridge::class, 'mockedCallHook');
		$reflectionProperty->setAccessible(true);

		$reflectionProperty->setValue(null, function (string $name, $data) use ($expected) {
			$this->assertSame($expected, $name);
			$this->assertSame('', $data);

			return $data;
		});

		HookEventBridge::onNamedEvent($event);
	}

	public static function getConfigLoadedEventData(): array
	{
		return [
			['test', 'test'],
			[ConfigLoadedEvent::CONFIG_LOADED, 'load_config'],
		];
	}

	/**
	 * @dataProvider getConfigLoadedEventData
	 */
	public function testOnConfigLoadedEventCallsHookWithCorrectValue($name, $expected): void
	{
		$config = $this->createStub(ConfigFileManager::class);

		$event = new ConfigLoadedEvent($name, $config);

		$reflectionProperty = new \ReflectionProperty(HookEventBridge::class, 'mockedCallHook');
		$reflectionProperty->setAccessible(true);

		$reflectionProperty->setValue(null, function (string $name, $data) use ($expected, $config) {
			$this->assertSame($expected, $name);
			$this->assertSame($config, $data);

			return $data;
		});

		HookEventBridge::onConfigLoadedEvent($event);
	}

	public static function getCollectRoutesEventData(): array
	{
		return [
			['test', 'test'],
			[CollectRoutesEvent::COLLECT_ROUTES, 'route_collection'],
		];
	}

	/**
	 * @dataProvider getCollectRoutesEventData
	 */
	public function testOnCollectRoutesEventCallsHookWithCorrectValue($name, $expected): void
	{
		$routeCollector = $this->createStub(RouteCollector::class);

		$event = new CollectRoutesEvent($name, $routeCollector);

		$reflectionProperty = new \ReflectionProperty(HookEventBridge::class, 'mockedCallHook');
		$reflectionProperty->setAccessible(true);

		$reflectionProperty->setValue(null, function (string $name, $data) use ($expected, $routeCollector) {
			$this->assertSame($expected, $name);
			$this->assertSame($routeCollector, $data);

			return $data;
		});

		HookEventBridge::onCollectRoutesEvent($event);
	}

	public static function getArrayFilterEventData(): array
	{
		return [
			['test', 'test'],
			[ArrayFilterEvent::APP_MENU, 'app_menu'],
			[ArrayFilterEvent::NAV_INFO, 'nav_info'],
			[ArrayFilterEvent::FEATURE_ENABLED, 'isEnabled'],
			[ArrayFilterEvent::FEATURE_GET, 'get'],
			[ArrayFilterEvent::POST_LOCAL_START, 'post_local_start'],
			[ArrayFilterEvent::POST_LOCAL, 'post_local'],
			[ArrayFilterEvent::POST_LOCAL_END, 'post_local_end'],
			[ArrayFilterEvent::PHOTO_UPLOAD_FORM, 'photo_upload_form'],
			[ArrayFilterEvent::NETWORK_TO_NAME, 'network_to_name'],
		];
	}

	/**
	 * @dataProvider getArrayFilterEventData
	 */
	public function testOnArrayFilterEventCallsHookWithCorrectValue($name, $expected): void
	{
		$event = new ArrayFilterEvent($name, ['original']);

		$reflectionProperty = new \ReflectionProperty(HookEventBridge::class, 'mockedCallHook');
		$reflectionProperty->setAccessible(true);

		$reflectionProperty->setValue(null, function (string $name, $data) use ($expected) {
			$this->assertSame($expected, $name);
			$this->assertSame(['original'], $data);

			return $data;
		});

		HookEventBridge::onArrayFilterEvent($event);
	}

	public static function getHtmlFilterEventData(): array
	{
		return [
			['test', 'test'],
			[HtmlFilterEvent::HEAD, 'head'],
			[HtmlFilterEvent::FOOTER, 'footer'],
			[HtmlFilterEvent::PAGE_HEADER, 'page_header'],
			[HtmlFilterEvent::PAGE_CONTENT_TOP, 'page_content_top'],
			[HtmlFilterEvent::PAGE_END, 'page_end'],
			[HtmlFilterEvent::JOT_TOOL, 'jot_tool'],
		];
	}

	/**
	 * @dataProvider getHtmlFilterEventData
	 */
	public function testOnHtmlFilterEventCallsHookWithCorrectValue($name, $expected): void
	{
		$event = new HtmlFilterEvent($name, 'original');

		$reflectionProperty = new \ReflectionProperty(HookEventBridge::class, 'mockedCallHook');
		$reflectionProperty->setAccessible(true);

		$reflectionProperty->setValue(null, function (string $name, $data) use ($expected) {
			$this->assertSame($expected, $name);
			$this->assertSame('original', $data);

			return $data;
		});

		HookEventBridge::onHtmlFilterEvent($event);
	}
}
