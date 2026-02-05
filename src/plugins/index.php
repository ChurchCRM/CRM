<?php

/**
 * Plugin Entry Point.
 *
 * Handles:
 * 1. Plugin management routes (admin UI for managing plugins) - requires admin role
 * 2. Plugin-provided routes (each enabled plugin can register its own routes) - uses plugin permissions
 *
 * URL Structure:
 * - /plugins/management/... - Admin UI for managing plugins (admin only)
 * - /plugins/api/... - Plugin management API (admin only)
 * - /plugins/{plugin-name}/... - Plugin-specific routes (uses plugin's permission settings)
 */

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\CorsMiddleware;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Symfony\Component\DependencyInjection\ContainerBuilder;

// Initialize plugin system
$pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';
PluginManager::init($pluginsPath);

// base path for plugin module
$basePath = SlimUtils::getBasePath('/plugins');

$container = new ContainerBuilder();
$container->compile();

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->setBasePath($basePath);

// Register plugin management routes (admin UI) - with admin middleware
$app->group('/management', function (RouteCollectorProxy $group): void {
    require __DIR__ . '/routes/management.php';
})->add(AdminRoleAuthMiddleware::class);

// Register plugin management API routes - with admin middleware
$app->group('/api', function (RouteCollectorProxy $group): void {
    require __DIR__ . '/routes/api/management.php';
})->add(AdminRoleAuthMiddleware::class);

// Register routes from all active plugins
// Only enabled plugins have their routes loaded (system-wide security)
// These routes use the plugin's own permission settings, not admin-only
PluginManager::registerPluginRoutes($app);

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
        $logger->info('Plugin 404 redirect', ['path' => $request->getUri()->getPath()]);
        $response = $app->getResponseFactory()->createResponse(302);

        return $response->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/');
    }

    $logger->error('Plugin error', [
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
    ]);

    $response = $app->getResponseFactory()->createResponse(500);

    return SlimUtils::renderJSON($response, [
        'success' => false,
        'error' => $exception->getMessage(),
    ]);
});

// Auth middleware (LIFO - added last, runs first)
// Note: AdminRoleAuthMiddleware is applied to specific route groups above, not globally
$app->add(new CorsMiddleware());
$app->add(AuthMiddleware::class);
$app->add(VersionMiddleware::class);

$app->run();
