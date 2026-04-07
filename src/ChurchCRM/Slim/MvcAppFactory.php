<?php

namespace ChurchCRM\Slim;

use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\ChurchInfoRequiredMiddleware;
use ChurchCRM\Slim\Middleware\CorsMiddleware;
use Slim\App;
use Slim\Factory\AppFactory;

/**
 * Factory for creating Slim 4 applications that serve HTML (MVC) pages.
 *
 * Centralises the boilerplate shared by every MVC entry-point: base path,
 * body parsing, routing, error handling, and the standard middleware stack.
 *
 * API-only entry-points (src/api/index.php) should NOT use this factory —
 * they have a different error handler and middleware set.
 */
class MvcAppFactory
{
    /**
     * Create and configure a Slim application for an MVC module.
     *
     * @param string      $endpoint       The URL prefix for this module (e.g. '/admin', '/finance')
     * @param array{
     *     dashboardUrl?:   string,
     *     dashboardText?:  string,
     *     roleMiddleware?: class-string|null,
     * } $config Module-specific configuration:
     *   - dashboardUrl:  path (relative to root) for the error-page "go back" button
     *   - dashboardText: label for the "go back" button
     *   - roleMiddleware: FQCN of a role-auth middleware (e.g. AdminRoleAuthMiddleware::class)
     */
    public static function create(string $endpoint, array $config = []): App
    {
        $dashboardUrl = $config['dashboardUrl'] ?? $endpoint . '/';
        $dashboardText = $config['dashboardText'] ?? gettext('Return to Dashboard');
        $roleMiddleware = $config['roleMiddleware'] ?? null;

        $app = AppFactory::create();
        $app->setBasePath(SlimUtils::getBasePath($endpoint));

        // Body parsing and routing middleware
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        // Error middleware — must be added BEFORE other middleware (LIFO execution order)
        $errorMiddleware = $app->addErrorMiddleware(true, true, true);
        SlimUtils::registerDefaultHtmlErrorHandler($errorMiddleware, $dashboardUrl, $dashboardText);

        // Standard middleware stack (LIFO — last added runs first)
        // Execution order: AuthMiddleware → ChurchInfoRequiredMiddleware → [RoleAuth] → CorsMiddleware
        $app->add(new CorsMiddleware());
        if ($roleMiddleware !== null) {
            $app->add($roleMiddleware);
        }
        $app->add(new ChurchInfoRequiredMiddleware());
        $app->add(AuthMiddleware::class);

        return $app;
    }
}
