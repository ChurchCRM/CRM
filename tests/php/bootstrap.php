<?php

declare(strict_types=1);

use Tester\Environment;

define('APP_ROOT', getenv('PHP_TEST_APP_ROOT') ?: dirname(__DIR__, 2) . '/src');

foreach ([__DIR__ . '/vendor/autoload.php', APP_ROOT . '/vendor/autoload.php'] as $autoload) {
    if (!is_file($autoload)) {
        throw new RuntimeException('Run npm run build:php and npm run test:php:install before PHP tests.');
    }
    require_once $autoload;
}

Environment::setup();
date_default_timezone_set('UTC');
