<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Friendica\Core\Hooks;

use Friendica\Core\Hook;
use Friendica\Event\ArrayFilterEvent;
use Friendica\Event\CollectRoutesEvent;
use Friendica\Event\ConfigLoadedEvent;
use Friendica\Event\Event;
use Friendica\Event\HtmlFilterEvent;
use Friendica\Event\NamedEvent;

/**
 * Bridge between the EventDispatcher and the Hook class.
 *
 * @internal Provides BC
 */
final class HookEventBridge
{
	/**
	 * @internal This allows us to mock the Hook call in tests.
	 *
	 * @var \Closure|null
	 */
	private static $mockedCallHook = null;

	/**
	 * This maps the new event names to the legacy Hook names.
	 */
	private static array $eventMapper = [
		Event::INIT                         => 'init_1',
		Event::HOME_INIT                    => 'home_init',
		ConfigLoadedEvent::CONFIG_LOADED    => 'load_config',
		CollectRoutesEvent::COLLECT_ROUTES  => 'route_collection',
		ArrayFilterEvent::APP_MENU          => 'app_menu',
		ArrayFilterEvent::NAV_INFO          => 'nav_info',
		ArrayFilterEvent::FEATURE_ENABLED   => 'isEnabled',
		ArrayFilterEvent::FEATURE_GET       => 'get',
		ArrayFilterEvent::POST_LOCAL_START  => 'post_local_start',
		ArrayFilterEvent::POST_LOCAL        => 'post_local',
		ArrayFilterEvent::POST_LOCAL_END    => 'post_local_end',
		ArrayFilterEvent::PHOTO_UPLOAD_FORM => 'photo_upload_form',
		ArrayFilterEvent::NETWORK_TO_NAME   => 'network_to_name',
		HtmlFilterEvent::HEAD               => 'head',
		HtmlFilterEvent::FOOTER             => 'footer',
		HtmlFilterEvent::PAGE_HEADER        => 'page_header',
		HtmlFilterEvent::PAGE_CONTENT_TOP   => 'page_content_top',
		HtmlFilterEvent::PAGE_END           => 'page_end',
	];

	/**
	 * @return array<string, string>
	 */
	public static function getStaticSubscribedEvents(): array
	{
		return [
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
		];
	}

	public static function onNamedEvent(NamedEvent $event): void
	{
		static::callHook($event->getName(), '');
	}

	public static function onConfigLoadedEvent(ConfigLoadedEvent $event): void
	{
		static::callHook($event->getName(), $event->getConfig());
	}

	public static function onCollectRoutesEvent(CollectRoutesEvent $event): void
	{
		$event->setRouteCollector(
			static::callHook($event->getName(), $event->getRouteCollector())
		);
	}

	public static function onArrayFilterEvent(ArrayFilterEvent $event): void
	{
		$event->setArray(
			static::callHook($event->getName(), $event->getArray())
		);
	}

	public static function onHtmlFilterEvent(HtmlFilterEvent $event): void
	{
		$event->setHtml(
			static::callHook($event->getName(), $event->getHtml())
		);
	}

	/**
	 * @param string|array|object $data
	 *
	 * @return string|array|object
	 */
	private static function callHook(string $name, $data)
	{
		// If possible, map the event name to the legacy Hook name
		$name = static::$eventMapper[$name] ?? $name;

		// Little hack to allow mocking the Hook call in tests.
		if (static::$mockedCallHook instanceof \Closure) {
			return (static::$mockedCallHook)->__invoke($name, $data);
		}

		Hook::callAll($name, $data);

		return $data;
	}
}
