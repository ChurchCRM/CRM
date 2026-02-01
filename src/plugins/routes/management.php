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

    // Individual plugin settings page (if plugin has custom settings page)
    $group->get('/{pluginId}/settings', function (Request $request, Response $response, array $args): Response {
        $pluginId = $args['pluginId'];
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        // Initialize plugin system
        $pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';
        PluginManager::init($pluginsPath);

        $metadata = PluginManager::getPluginMetadata($pluginId);
        $plugin = PluginManager::getPlugin($pluginId);

        if ($metadata === null) {
            // Redirect to plugins list with error
            return $response
                ->withHeader('Location', SystemURLs::getRootPath() . '/plugins')
                ->withStatus(302);
        }

        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => sprintf(gettext('%s Settings'), $metadata->getName()),
            'plugin' => $metadata->toArray(),
            'isActive' => PluginManager::isPluginActive($pluginId),
            'isConfigured' => $plugin?->isConfigured() ?? false,
            'settingsSchema' => $plugin?->getSettingsSchema() ?? [],
        ];

        return $renderer->render($response, 'plugin-settings.php', $pageArgs);
    });
});
