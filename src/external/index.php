<?php

use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\Slim\Middleware\CorsMiddleware;
use Slim\Factory\AppFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require_once '../Include/Config.php';
require_once __DIR__ . '/../vendor/autoload.php';

$rootPath = str_replace('/external/index.php', '', $_SERVER['SCRIPT_NAME']);


$container = new ContainerBuilder();
$container->compile();
AppFactory::setContainer($container);
$app = AppFactory::create();
$app->setBasePath($rootPath . '/external');

// Add Slim error middleware for proper error handling and logging
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
SlimUtils::registerDefaultJsonErrorHandler($errorMiddleware);

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$app->add(VersionMiddleware::class);
$app->add(new CorsMiddleware());

// routes
require __DIR__ . '/routes/register.php';
require __DIR__ . '/routes/verify.php';
require __DIR__ . '/routes/calendar.php';

// Run app
$app->run();
