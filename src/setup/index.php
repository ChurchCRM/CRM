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

// Detect the base path from the request URI
// For /churchcrm/setup/ -> base path is /churchcrm/setup
// For /setup/ -> base path is /setup
$requestUri = $_SERVER['REQUEST_URI'] ?? '/setup';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/setup/index.php';

// Extract base path by removing everything after /setup in the script path
// /home/.../churchcrm/setup/index.php -> /churchcrm/setup
$basePath = dirname($scriptName); // Gets /churchcrm/setup or /setup

// Calculate parent root path for SystemURLs (where assets are)
// /churchcrm/setup -> /churchcrm
// /setup -> ''
$parentRootPath = dirname($basePath);
if ($parentRootPath === '/' || $parentRootPath === '.') {
    $parentRootPath = '';
}

// Debug logging - remove after testing
error_log("Setup Debug - SCRIPT_NAME: " . $scriptName);
error_log("Setup Debug - basePath: " . $basePath);
error_log("Setup Debug - parentRootPath: " . $parentRootPath);
error_log("Setup Debug - documentRoot: " . dirname(__DIR__));

// Initialize SystemURLs with parent directory root path and physical directory
SystemURLs::init($parentRootPath, '', dirname(__DIR__));
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
