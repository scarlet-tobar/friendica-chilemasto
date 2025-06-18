<?php

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
    // ->withPhpSets()
    // ->withTypeCoverageLevel(0)
    // ->withDeadCodeLevel(0)
    // ->withCodeQualityLevel(0)
    ->withRules([
        ListToArrayDestructRector::class,
    ])
;
