<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Plugin management routes.
 *
 * Provides admin UI for:
 * - Viewing installed plugins
 * - Enabling/disabling plugins
 * - Viewing plugin settings
 *
 * These routes are under /plugins/management/ and require admin role.
 */

// Plugin list page - handle both /plugins/management and /plugins/management/
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

// Individual plugin settings page - redirect to management with hash to expand the plugin
$group->get('/{pluginId}', function (Request $request, Response $response, array $args): Response {
    $pluginId = $args['pluginId'];
    $pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';
    PluginManager::init($pluginsPath);

    $metadata = PluginManager::getPluginMetadata($pluginId);
    if ($metadata === null) {
        // Plugin not found, redirect to list
        return $response
            ->withHeader('Location', SystemURLs::getRootPath() . '/plugins/management')
            ->withStatus(302);
    }

    // Redirect to management page with hash to auto-expand this plugin's card
    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/plugins/management#plugin-' . $pluginId)
        ->withStatus(302);
});
