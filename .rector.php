<?php
/**
 * SPDX-FileCopyrightText: 2010 - 2024 the Friendica project
 *
 * SPDX-License-Identifier: CC0-1.0
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php71\Rector\List_\ListToArrayDestructRector;

return RectorConfig::configure()
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
	->withPhpLevel(3)
	->withDowngradeSets(php74: true)
	// ->withPhp53Sets()
	// ->withPhpSets()
	// ->withTypeCoverageLevel(0)
	// ->withDeadCodeLevel(0)
	// ->withCodeQualityLevel(0)
	->withRules([
		ListToArrayDestructRector::class,
	])
	->withSkip([
		\Rector\DowngradePhp80\Rector\FuncCall\DowngradeSubstrFalsyRector::class,
		\Rector\DowngradePhp80\Rector\FuncCall\DowngradeArrayFilterNullableCallbackRector::class,
		\Rector\DowngradePhp82\Rector\FuncCall\DowngradeIteratorCountToArrayRector::class,
	])
;
