<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    // Process all PHP files in src/ directory
    $rectorConfig->paths([
        __DIR__ . '/src',
    ]);
    
    // Skip generated code and vendor libraries
    $rectorConfig->skip([
        __DIR__ . '/src/ChurchCRM/model/ChurchCRM/Base',
        __DIR__ . '/src/ChurchCRM/model/ChurchCRM/Map',
        __DIR__ . '/src/vendor',
        __DIR__ . '/src/locale/vendor',
    ]);

    // Register a single rule
    $rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);

    // Define sets of rules
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_74,
        SetList::GMAGICK_TO_IMAGICK,
        SetList::TYPE_DECLARATION,
        SetList::INSTANCEOF,
        SetList::DEAD_CODE,
        SetList::PRIVATIZATION,
    ]);
};
