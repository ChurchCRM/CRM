<?php

require_once '../Include/LoadConfig.php';
require_once __DIR__ . '/../vendor/autoload.php';

use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\CorsMiddleware;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Slim\Factory\AppFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;

// base path for admin
$basePath = SlimUtils::getBasePath('/admin');

$container = new ContainerBuilder();
$container->compile();

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->setBasePath($basePath);

// Error middleware
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
SlimUtils::setupErrorLogger($errorMiddleware);
SlimUtils::registerDefaultJsonErrorHandler($errorMiddleware);

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$app->add(new CorsMiddleware());
$app->add(AdminRoleAuthMiddleware::class);
$app->add(AuthMiddleware::class);
$app->add(VersionMiddleware::class);

require __DIR__ . '/routes/api/demo.php';
require __DIR__ . '/routes/maintenance.php';

$app->run();
