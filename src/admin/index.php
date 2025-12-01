<?php

require_once '../Include/LoadConfig.php';
require_once __DIR__ . '/../vendor/autoload.php';

use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\CorsMiddleware;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;

// base path for admin
$basePath = SlimUtils::getBasePath('/admin');

$container = new ContainerBuilder();
$container->compile();

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->setBasePath($basePath);

// Register routes FIRST before middleware
require __DIR__ . '/routes/dashboard.php';
require __DIR__ . '/routes/api/demo.php';
require __DIR__ . '/routes/api/database.php';
require __DIR__ . '/routes/api/orphaned-files.php';
require __DIR__ . '/routes/system.php';

// Body parsing and routing middleware
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Error middleware - must be added BEFORE other middleware (LIFO execution order)
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
SlimUtils::setupErrorLogger($errorMiddleware);

// Custom error handler for HTML pages
$errorMiddleware->setDefaultErrorHandler(function (
    Request $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($app) {
    $logger = LoggerUtils::getAppLogger();
    
    if ($exception instanceof HttpNotFoundException) {
        $logger->info('Admin 404 redirect', ['path' => $request->getUri()->getPath()]);
        $response = $app->getResponseFactory()->createResponse(302);
        return $response->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/');
    }
    
    $logger->error('Admin error', [
        'exception' => get_class($exception),
        'message' => $exception->getMessage()
    ]);
    
    $response = $app->getResponseFactory()->createResponse(500);
    return SlimUtils::renderJSON($response, [
        'success' => false,
        'error' => $exception->getMessage()
    ]);
});

// Auth middleware (LIFO - added last, runs first)
$app->add(new CorsMiddleware());
$app->add(AdminRoleAuthMiddleware::class);
$app->add(AuthMiddleware::class);
$app->add(VersionMiddleware::class);

$app->run();
