<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\CorsMiddleware;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\FinanceRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;

// base path for finance
$basePath = SlimUtils::getBasePath('/finance');

$app = AppFactory::create();
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
        $logger->info('Finance 404 redirect', ['path' => $request->getUri()->getPath()]);
        $response = $app->getResponseFactory()->createResponse(302);
        return $response->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/finance/');
    }
    
    // Log full error details server-side for debugging
    $logger->error('Finance error', [
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    
    // Return generic message to client to avoid exposing sensitive internals
    $response = $app->getResponseFactory()->createResponse(500);
    return SlimUtils::renderJSON($response, [
        'success' => false,
        'error' => gettext('An unexpected error occurred. Please contact your administrator.')
    ]);
});

// Auth middleware (LIFO - added last, runs first)
$app->add(new CorsMiddleware());
$app->add(FinanceRoleAuthMiddleware::class);
$app->add(AuthMiddleware::class);
$app->add(VersionMiddleware::class);

$app->run();
