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


// Initialize plugin system
$pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';
PluginManager::init($pluginsPath);

// base path for plugin module
$basePath = SlimUtils::getBasePath('/plugins');

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

// Public plugin status endpoint - no admin required
// Used by PersonView.js/FamilyView.js to check if MailChimp tab should be shown
$app->get('/status/{pluginId}', function (Request $request, \Psr\Http\Message\ResponseInterface $response, array $args) {
    $pluginId = $args['pluginId'];
    $plugin = PluginManager::getPlugin($pluginId);

    return SlimUtils::renderJSON($response, [
        'success' => true,
        'isActive' => PluginManager::isPluginActive($pluginId),
        'isConfigured' => $plugin?->isConfigured() ?? false,
    ]);
});

// Register routes from all active plugins
// Only enabled plugins have their routes loaded (system-wide security)
// These routes use the plugin's own permission settings, not admin-only
PluginManager::registerPluginRoutes($app);

// Body parsing and routing middleware
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Error middleware - use config-driven display (false in production)
$displayErrors = \ChurchCRM\dto\SystemConfig::debugEnabled();
$errorMiddleware = $app->addErrorMiddleware($displayErrors, true, true);
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
        $logger->debug('Plugin 404 redirect', ['path' => $request->getUri()->getPath()]);
        $response = $app->getResponseFactory()->createResponse(302);

        return $response->withHeader('Location', \ChurchCRM\dto\SystemURLs::getRootPath() . '/');
    }

    $logger->error('Plugin error', [
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
    ]);

    $response = $app->getResponseFactory()->createResponse();

    // Use standardized error response - don't expose raw exception messages
    return SlimUtils::renderErrorJSON($response, gettext('An error occurred processing the plugin request'), [], 500, $exception, $request);
});

// Auth middleware (LIFO - added last, runs first)
// Note: AdminRoleAuthMiddleware is applied to specific route groups above, not globally
// Order: Version added first (runs last), Auth second, CORS last (runs first)
$app->add(VersionMiddleware::class);
$app->add(AuthMiddleware::class);
$app->add(new CorsMiddleware());

$app->run();
