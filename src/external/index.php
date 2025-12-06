<?php

require_once '../Include/LoadConfig.php';
require_once __DIR__ . '/../vendor/autoload.php';

use ChurchCRM\Slim\Middleware\CorsMiddleware;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Slim\Factory\AppFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;

// Get base path by combining $sRootPath from Config.php with /external endpoint
// Examples: '' + '/external' = '/external' (root install)
//           '/churchcrm' + '/external' = '/churchcrm/external' (subdirectory install)
$basePath = SlimUtils::getBasePath('/external');


$container = new ContainerBuilder();
$container->compile();
AppFactory::setContainer($container);
$app = AppFactory::create();
$app->setBasePath($basePath);

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
require __DIR__ . '/routes/system.php';

// Run app
$app->run();
