<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

/**
 * Plugin management routes.
 *
 * Provides admin UI for:
 * - Viewing installed plugins
 * - Enabling/disabling plugins
 * - Viewing plugin settings
 */
$app->group('', function (RouteCollectorProxy $group): void {
    // Plugin list page - handle both /plugins and /plugins/
    $group->get('[/]', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        // Initialize plugin system
        $pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';
        PluginManager::init($pluginsPath);

        $plugins = PluginManager::getAllPlugins();

        // Separate core and community plugins
        $corePlugins = array_filter($plugins, fn ($p) => $p['type'] === 'core');
        $communityPlugins = array_filter($plugins, fn ($p) => $p['type'] !== 'core');

        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Plugin Management'),
            'corePlugins' => array_values($corePlugins),
            'communityPlugins' => array_values($communityPlugins),
        ];

        return $renderer->render($response, 'management.php', $pageArgs);
    });

    // Redirect individual plugin settings to main page (settings are now inline)
    $group->get('/{pluginId}/settings', function (Request $request, Response $response, array $args): Response {
        // Redirect to main plugins page - settings are displayed inline
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/plugins#' . $args['pluginId'])
            ->withStatus(302);
    });
});
