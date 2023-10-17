<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/ChurchCRM',
        __DIR__ . '/Include',
        __DIR__ . '/Reports',
        __DIR__ . '/api',
        __DIR__ . '/email',
        __DIR__ . '/external',
        __DIR__ . '/kiosk',
        __DIR__ . '/members',
        __DIR__ . '/mysql',
        __DIR__ . '/session',
        __DIR__ . '/setup',
        __DIR__ . '/sundayschool',
        __DIR__ . '/v2',
    ]);

    // register a single rule
    $rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);

    // define sets of rules
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_56
    ]);
};
