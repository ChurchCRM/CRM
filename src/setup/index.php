<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/../logs/external.log');

if (file_exists('../Include/Config.php')) {
    header('Location: ../index.php');
} else {
    require_once __DIR__.'/../vendor/autoload.php';

    $rootPath = str_replace('/setup/index.php', '', $_SERVER['SCRIPT_NAME']);
    SystemURLs::init($rootPath, '', __DIR__.'/../');
    SystemConfig::init();

    $app = new \Slim\App();
    $container = $app->getContainer();
    if (SystemConfig::debugEnabled()) {
        $app->addErrorMiddleware(true, true, true);
    }

    require __DIR__.'/../Include/slim/error-handler.php';

    require __DIR__.'/routes/setup.php';

    $app->run();
}
