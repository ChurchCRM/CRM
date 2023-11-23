<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

use ChurchCRM\Slim\Middleware\VersionMiddleware;
use Slim\Factory\AppFactory;

if (file_exists('../Include/Config.php')) {
    header('Location: ../');
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

$rootPath = str_replace('/setup/index.php', '', $_SERVER['SCRIPT_NAME']);
SystemURLs::init($rootPath, '', __DIR__ . '/../');
SystemConfig::init();

$app = AppFactory::create();
$app->setBasePath('/setup');

require __DIR__ . '/../Include/slim/error-handler.php';

$app->addRoutingMiddleware();
$app->add(new VersionMiddleware());
$container = $app->getContainer();

$app->addBodyParsingMiddleware();

require __DIR__ . '/routes/setup.php';

$app->run();
