<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\ChurchInfoRequiredMiddleware;
use ChurchCRM\Slim\Middleware\CorsMiddleware;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Slim\Factory\AppFactory;

// base path for people
$basePath = SlimUtils::getBasePath('/people');

$app = AppFactory::create();
$app->setBasePath($basePath);

// Register routes FIRST before middleware
require __DIR__ . '/routes/dashboard.php';

// Body parsing and routing middleware
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Error middleware - must be added BEFORE other middleware (LIFO execution order)
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
SlimUtils::setupErrorLogger($errorMiddleware);
SlimUtils::registerDefaultHtmlErrorHandler(
    $errorMiddleware,
    SystemURLs::getRootPath() . '/people/dashboard',
    gettext('Return to People Dashboard')
);

// Auth middleware (LIFO - added last, runs first)
$app->add(new CorsMiddleware());
$app->add(new ChurchInfoRequiredMiddleware());
$app->add(AuthMiddleware::class);
$app->add(VersionMiddleware::class);

$app->run();
