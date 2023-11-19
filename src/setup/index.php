<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use Monolog\Logger;
use Selective\BasePath\BasePathMiddleware;
use Slim\Container;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';


$rootPath = str_replace('/setup/index.php', '', $_SERVER['SCRIPT_NAME']);
SystemURLs::init($rootPath, '', __DIR__ . "/../");
SystemConfig::init();

$app = AppFactory::create();
$app->setBasePath('/setup/');

SystemConfig::getValue("sLogLevel", Logger::DEBUG);
require __DIR__ . '/../Include/slim/error-handler.php';

$app->addRoutingMiddleware();
$app->add(new VersionMiddleware());
$container = $app->getContainer();

$app->addBodyParsingMiddleware();

require __DIR__ . '/routes/setup.php';

$app->run();
