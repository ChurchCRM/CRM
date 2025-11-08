<?php

require_once '../Include/Config.php';
require_once '../Include/Functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\CorsMiddleware;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Slim\Factory\AppFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;

// Get base path by combining $sRootPath from Config.php with /v2 endpoint
// Examples: '' + '/v2' = '/v2' (root install)
//           '/churchcrm' + '/v2' = '/churchcrm/v2' (subdirectory install)
$basePath = SlimUtils::getBasePath('/v2');


$container = new ContainerBuilder();
// Register custom error handlers

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->setBasePath($basePath);

// Add Slim error middleware for proper error handling
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
SlimUtils::setupErrorLogger($errorMiddleware);
SlimUtils::registerDefaultJsonErrorHandler($errorMiddleware);

// Add CORS middleware for browser API access
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$app->add(VersionMiddleware::class);
$app->add(AuthMiddleware::class);
$app->add(new CorsMiddleware());

require __DIR__ . '/routes/common/mvc-helper.php';
require __DIR__ . '/routes/admin/admin.php';
require __DIR__ . '/routes/user.php';
require __DIR__ . '/routes/people.php';
require __DIR__ . '/routes/family.php';
require __DIR__ . '/routes/person.php';
require __DIR__ . '/routes/email.php';
require __DIR__ . '/routes/calendar.php';
require __DIR__ . '/routes/cart.php';
require __DIR__ . '/routes/user-current.php';
require __DIR__ . '/routes/root.php';

// Run app
$app->run();
