<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\ChurchInfoRequiredMiddleware;
use ChurchCRM\Slim\Middleware\CorsMiddleware;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\ManageGroupRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\dto\SystemURLs;
use Slim\Factory\AppFactory;

// base path for groups
$basePath = SlimUtils::getBasePath('/groups');

$app = AppFactory::create();
$app->setBasePath($basePath);

// Register routes FIRST before middleware
require __DIR__ . '/routes/dashboard.php';
require __DIR__ . '/routes/reports.php';
require __DIR__ . '/routes/sundayschool.php';
require __DIR__ . '/routes/view.php';

// Body parsing and routing middleware
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Error middleware - must be added BEFORE other middleware (LIFO execution order)
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
SlimUtils::setupErrorLogger($errorMiddleware);
SlimUtils::registerDefaultHtmlErrorHandler(
    $errorMiddleware,
    SystemURLs::getRootPath() . '/groups/dashboard',
    gettext('Return to Groups Dashboard')
);

// Auth middleware (LIFO - added last, runs first)
$app->add(new CorsMiddleware());
$app->add(ManageGroupRoleAuthMiddleware::class);
$app->add(new ChurchInfoRequiredMiddleware());
$app->add(AuthMiddleware::class);
$app->add(VersionMiddleware::class);

$app->run();
