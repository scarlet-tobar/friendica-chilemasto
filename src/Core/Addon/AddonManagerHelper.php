<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Friendica\Core\Addon;

use Friendica\Core\Config\Capability\IManageConfigValues;
use Friendica\Util\Profiler;

/**
 * helper functions to handle addons
 *
 * @internal
 */
final class AddonManagerHelper implements AddonHelper
{
	private string $addonPath;

	private IManageConfigValues $config;

	private Profiler $profiler;

	/** @var string[] */
	private array $addons = [];

	/** @deprecated */
	private AddonHelper $proxy;

	public function __construct(
		string $addonPath,
		IManageConfigValues $config,
		Profiler $profiler
	) {
		$this->addonPath = $addonPath;
		$this->config    = $config;
		$this->profiler  = $profiler;

		$this->proxy = new AddonProxy($addonPath);
	}
	/**
	 * Returns the absolute path to the addon folder
	 *
	 * e.g. `/var/www/html/addon`
	 */
	public function getAddonPath(): string
	{
		return $this->addonPath;
	}

	/**
	 * Returns the list of available addons.
	 *
	 * This list is made from scanning the addon/ folder.
	 * Unsupported addons are excluded unless they already are enabled or system.show_unsupported_addon is set.
	 *
	 * @return string[]
	 */
	public function getAvailableAddons(): array
	{
		$dirs = scandir($this->getAddonPath());

		if (!is_array($dirs)) {
			return [];
		}

		$files = [];

		foreach ($dirs as $dirname) {
			if (in_array($dirname, ['.', '..'])) {
				continue;
			}

			if (!is_dir($this->getAddonPath() . '/' . $dirname)) {
				continue;
			}

			$files[] = $dirname;
		}

		$addons = [];

		foreach ($files as $addonId) {
			$addonInfo = $this->getAddonInfo($addonId);

			if (
				$this->config->get('system', 'show_unsupported_addons')
				|| strtolower($addonInfo->getStatus()) !== 'unsupported'
				|| $this->isAddonEnabled($addonId)
			) {
				$addons[] = $addonId;
			}
		}

		return $addons;
	}

	/**
	 * Installs an addon.
	 *
	 * @param string $addonId name of the addon
	 *
	 * @return bool true on success or false on failure
	 */
	public function installAddon(string $addonId): bool
	{
		return $this->proxy->installAddon($addonId);
	}

	/**
	 * Uninstalls an addon.
	 *
	 * @param string $addonId name of the addon
	 */
	public function uninstallAddon(string $addonId): void
	{
		$this->proxy->uninstallAddon($addonId);
	}

	/**
	 * Load addons.
	 *
	 * @internal
	 */
	public function loadAddons(): void
	{
		$this->addons = array_keys(array_filter($this->config->get('addons') ?? []));
	}

	/**
	 * Reload (uninstall and install) all updated addons.
	 */
	public function reloadAddons(): void
	{
		$this->proxy->reloadAddons();
	}

	/**
	 * Get the comment block of an addon as value object.
	 */
	public function getAddonInfo(string $addonId): AddonInfo
	{
		$default = [
			'id'   => $addonId,
			'name' => $addonId,
		];

		if (!is_file($this->getAddonPath() . "/$addonId/$addonId.php")) {
			return AddonInfo::fromArray($default);
		}

		$this->profiler->startRecording('file');

		$raw = file_get_contents($this->getAddonPath() . "/$addonId/$addonId.php");

		$this->profiler->stopRecording();

		return AddonInfo::fromString($addonId, $raw);
	}

	/**
	 * Checks if the provided addon is enabled
	 */
	public function isAddonEnabled(string $addonId): bool
	{
		return in_array($addonId, $this->addons);
	}

	/**
	 * Returns a list with the IDs of the enabled addons
	 *
	 * @return string[]
	 */
	public function getEnabledAddons(): array
	{
		return $this->addons;
	}

	/**
	 * Returns a list with the IDs of the non-hidden enabled addons
	 *
	 * @return string[]
	 */
	public function getVisibleEnabledAddons(): array
	{
		$visible_addons = [];
		$addons         = array_filter($this->config->get('addons') ?? []);

		foreach ($addons as $name => $data) {
			$visible_addons[] = $name;
		}

		return $visible_addons;
	}

	/**
	 * Returns a list with the IDs of the enabled addons that provides admin settings.
	 *
	 * @return string[]
	 */
	public function getEnabledAddonsWithAdminSettings(): array
	{
		$addons_admin = [];
		$addons       = array_filter($this->config->get('addons') ?? []);

		ksort($addons);

		foreach ($addons as $name => $data) {
			if (array_key_exists('admin', $data) && $data['admin'] === true) {
				$addons_admin[] = $name;
			}
		}

		return $addons_admin;
	}
}
