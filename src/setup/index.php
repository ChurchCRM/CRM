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
// Note: Setup runs before Config.php exists, so use lightweight error handler
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Simple error handler for setup (no database/logging dependencies)
$errorMiddleware->setDefaultErrorHandler(function (
    \Psr\Http\Message\ServerRequestInterface $request,
    \Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) {
    $response = new \Slim\Psr7\Response();
    
    // Determine HTTP status code
    $statusCode = 500;
    if ($exception instanceof \Slim\Exception\HttpNotFoundException) {
        $statusCode = 404;
    } elseif ($exception instanceof \Slim\Exception\HttpMethodNotAllowedException) {
        $statusCode = 405;
    }
    
    // Build error response with request details for debugging
    $errorData = [
        'error' => $exception->getMessage(),
        'code' => $exception->getCode(),
        'request' => [
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath()
        ]
    ];
    
    if ($displayErrorDetails) {
        $errorData['file'] = $exception->getFile();
        $errorData['line'] = $exception->getLine();
        $errorData['trace'] = $exception->getTraceAsString();
    }
    
    $response->getBody()->write(json_encode($errorData, JSON_PRETTY_PRINT));
    return $response->withStatus($statusCode)->withHeader('Content-Type', 'application/json');
});

// Add CORS middleware for browser API access
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$app->add(VersionMiddleware::class);
$app->add(new CorsMiddleware());

require __DIR__ . '/routes/setup.php';

$app->run();
