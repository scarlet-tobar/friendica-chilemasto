<?php
/**
 * SPDX-FileCopyrightText: 2010 - 2024 the Friendica project
 *
 * SPDX-License-Identifier: CC0-1.0
 */

declare(strict_types=1);

return \Rector\Config\RectorConfig::configure()
	->withPaths([
		__DIR__ . '/config',
		__DIR__ . '/mod',
		__DIR__ . '/src',
		__DIR__ . '/static',
		__DIR__ . '/tests',
		__DIR__ . '/view',
	])
	->withIndent("\t", 4)
	->withPhpVersion(70400)
	->withPhpLevel(9)
	// ->withTypeCoverageLevel(0)
	// ->withDeadCodeLevel(0)
	// ->withCodeQualityLevel(0)
	->withRules([
		\Rector\Php71\Rector\List_\ListToArrayDestructRector::class,
	])
	->withSets([
		\Rector\Set\ValueObject\LevelSetList::UP_TO_PHP_54,
		\Rector\Set\ValueObject\DowngradeLevelSetList::DOWN_TO_PHP_74,
	])
;
