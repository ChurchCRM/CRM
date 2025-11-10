<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Slim\Middleware\CorsMiddleware;
use Slim\Factory\AppFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;

if (file_exists('../Include/Config.php')) {
    header('Location: ../');
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

// Use SlimUtils to get base path for routing, but assets are in parent directory
$basePath = ChurchCRM\Slim\SlimUtils::getBasePath('/setup');
// Initialize SystemURLs with parent directory root (where assets actually are)
$parentRootPath = str_replace('/setup', '', $basePath);
SystemURLs::init($parentRootPath, '', __DIR__ . '/../');
SystemConfig::init();


$container = new ContainerBuilder();
$container->compile();
// Register custom error handlers
AppFactory::setContainer($container);
$app = AppFactory::create();
$app->setBasePath($basePath);

// Add Slim error middleware for proper error handling and logging
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
SlimUtils::setupErrorLogger($errorMiddleware);
SlimUtils::registerDefaultJsonErrorHandler($errorMiddleware);

// Add CORS middleware for browser API access
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$app->add(VersionMiddleware::class);
$app->add(new CorsMiddleware());

require __DIR__ . '/routes/setup.php';

$app->run();
