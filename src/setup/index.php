<?php

use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\Slim\Middleware\CorsMiddleware;
use Slim\Factory\AppFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;

if (file_exists('../Include/Config.php')) {
    header('Location: ../');
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

// Detect base path from server variables - no Config.php dependency
// For /churchcrm/setup/index.php -> SCRIPT_NAME = /churchcrm/setup/index.php
// For /setup/index.php -> SCRIPT_NAME = /setup/index.php
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/setup/index.php';
$scriptName = str_replace('\\', '/', $scriptName);
$basePath = dirname($scriptName); // Gets /churchcrm/setup or /setup

// Calculate root path (parent of setup directory)
// /churchcrm/setup -> /churchcrm
// /setup -> ''
$rootPath = dirname($basePath);
if ($rootPath === '/' || $rootPath === '.') {
    $rootPath = '';
}

// Fallback detection when SCRIPT_NAME lacks the installation prefix (common with subdir installs)
if ($rootPath === '') {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $requestPath = parse_url($requestUri, PHP_URL_PATH) ?? '';
    if ($requestPath !== '') {
        $requestPath = rtrim($requestPath, '/');
        if ($requestPath !== '') {
            $candidate = preg_replace('#/setup(?:/.*)?$#', '', $requestPath);
            if ($candidate !== null && $candidate !== $requestPath) {
                $candidate = rtrim($candidate, '/');
                if ($candidate !== '') {
                    $rootPath = $candidate;
                }
            }
        }
    }
}

if ($rootPath !== '' && $rootPath[0] !== '/') {
    $rootPath = '/' . $rootPath;
}

// Store paths in global for template access (no SystemURLs available)
$GLOBALS['CHURCHCRM_SETUP_ROOT_PATH'] = $rootPath;
$GLOBALS['CHURCHCRM_SETUP_DOC_ROOT'] = dirname(__DIR__);

$container = new ContainerBuilder();
$container->compile();
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
