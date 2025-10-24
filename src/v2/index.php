<?php
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\Slim\Middleware\CorsMiddleware;
use Slim\Factory\AppFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require_once '../Include/Config.php';
require_once '../Include/Functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Use SlimUtils to get base path, default to /v2
$basePath = ChurchCRM\Slim\SlimUtils::getBasePath('/v2');


$container = new ContainerBuilder();
// Register custom error handlers

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->setBasePath($basePath);

// Add CORS middleware for browser API access
$app->addBodyParsingMiddleware();
$app->add(VersionMiddleware::class);
$app->add(AuthMiddleware::class);
$app->add(new CorsMiddleware());

// Add Slim error middleware for proper error handling
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
SlimUtils::setupErrorLogger($errorMiddleware);
\ChurchCRM\Slim\SlimUtils::registerDefaultJsonErrorHandler($errorMiddleware);

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
