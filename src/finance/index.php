<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\ChurchInfoRequiredMiddleware;
use ChurchCRM\Slim\Middleware\CorsMiddleware;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\dto\SystemURLs;
use Slim\Factory\AppFactory;

// base path for finance
$basePath = SlimUtils::getBasePath('/finance');

$app = AppFactory::create();
$app->setBasePath($basePath);

// Register routes FIRST before middleware
require __DIR__ . '/routes/dashboard.php';
require __DIR__ . '/routes/reports.php';
require __DIR__ . '/routes/pledges.php';

// Body parsing and routing middleware
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Error middleware - must be added BEFORE other middleware (LIFO execution order)
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
SlimUtils::setupErrorLogger($errorMiddleware);
SlimUtils::registerDefaultHtmlErrorHandler(
    $errorMiddleware,
    SystemURLs::getRootPath() . '/finance/',
    gettext('Return to Finance Dashboard')
);

// Auth middleware (LIFO - added last, runs first)
$app->add(new CorsMiddleware());
$app->add(FinanceRoleAuthMiddleware::class);
$app->add(new ChurchInfoRequiredMiddleware());
$app->add(AuthMiddleware::class);
$app->add(VersionMiddleware::class);

$app->run();
