<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Plugin management API routes.
 *
 * Provides AJAX endpoints for:
 * - Listing plugins
 * - Enabling/disabling plugins
 * - Getting plugin status
 *
 * These routes are under /plugins/api/ and require admin role.
 */

// Get all plugins
$group->get('/plugins', function (Request $request, Response $response): Response {
    try {
        $pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';
        PluginManager::init($pluginsPath);

        $plugins = PluginManager::getAllPlugins();

        return SlimUtils::renderJSON($response, [
            'success' => true,
            'data' => $plugins,
        ]);
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Failed to list plugins'),
            [],
            500,
            $e,
            $request
        );
    }
});

// Get single plugin details
$group->get('/plugins/{pluginId}', function (Request $request, Response $response, array $args): Response {
    try {
        $pluginId = $args['pluginId'];
        $pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';
        PluginManager::init($pluginsPath);

        $metadata = PluginManager::getPluginMetadata($pluginId);
        if ($metadata === null) {
            return SlimUtils::renderErrorJSON(
                $response,
                gettext('Plugin not found'),
                [],
                404
            );
        }

        $plugin = PluginManager::getPlugin($pluginId);

        return SlimUtils::renderJSON($response, [
            'success' => true,
            'data' => [
                'metadata' => $metadata->toArray(),
                'isActive' => PluginManager::isPluginActive($pluginId),
                'isConfigured' => $plugin?->isConfigured() ?? false,
            ],
        ]);
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Failed to get plugin details'),
            [],
            500,
            $e,
            $request
        );
    }
});

// Enable a plugin
$group->post('/plugins/{pluginId}/enable', function (Request $request, Response $response, array $args): Response {
    try {
        $pluginId = $args['pluginId'];
        $pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';
        PluginManager::init($pluginsPath);

        PluginManager::enablePlugin($pluginId);

        LoggerUtils::getAppLogger()->info("Plugin enabled: $pluginId");

        return SlimUtils::renderJSON($response, [
            'success' => true,
            'message' => gettext('Plugin enabled successfully'),
        ]);
    } catch (\RuntimeException $e) {
        // Dependency or version errors
        return SlimUtils::renderErrorJSON(
            $response,
            $e->getMessage(),
            [],
            400,
            $e,
            $request
        );
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Failed to enable plugin'),
            [],
            500,
            $e,
            $request
        );
    }
});

// Disable a plugin
$group->post('/plugins/{pluginId}/disable', function (Request $request, Response $response, array $args): Response {
    try {
        $pluginId = $args['pluginId'];
        $pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';
        PluginManager::init($pluginsPath);

        PluginManager::disablePlugin($pluginId);

        LoggerUtils::getAppLogger()->info("Plugin disabled: $pluginId");

        return SlimUtils::renderJSON($response, [
            'success' => true,
            'message' => gettext('Plugin disabled successfully'),
        ]);
    } catch (\RuntimeException $e) {
        // Dependency errors (other plugins depend on this one)
        return SlimUtils::renderErrorJSON(
            $response,
            $e->getMessage(),
            [],
            400,
            $e,
            $request
        );
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Failed to disable plugin'),
            [],
            500,
            $e,
            $request
        );
    }
});

// Update plugin settings
$group->post('/plugins/{pluginId}/settings', function (Request $request, Response $response, array $args): Response {
    try {
        $pluginId = $args['pluginId'];
        $body = $request->getParsedBody();
        $settings = $body['settings'] ?? [];

        if (empty($settings)) {
            return SlimUtils::renderErrorJSON(
                $response,
                gettext('No settings provided'),
                [],
                400
            );
        }

        $pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';
        PluginManager::init($pluginsPath);

        $metadata = PluginManager::getPluginMetadata($pluginId);
        if ($metadata === null) {
            return SlimUtils::renderErrorJSON(
                $response,
                gettext('Plugin not found'),
                [],
                404
            );
        }

        $updated = [];
        $failed = [];

        foreach ($settings as $key => $value) {
            if (PluginManager::updatePluginSetting($pluginId, $key, $value)) {
                $updated[] = $key;
            } else {
                $failed[] = $key;
            }
        }

        if (!empty($failed)) {
            return SlimUtils::renderJSON($response, [
                'success' => false,
                'message' => gettext('Some settings could not be saved'),
                'updated' => $updated,
                'failed' => $failed,
            ]);
        }

        LoggerUtils::getAppLogger()->info("Plugin settings updated: $pluginId", ['settings' => array_keys($settings)]);

        return SlimUtils::renderJSON($response, [
            'success' => true,
            'message' => gettext('Settings saved successfully'),
            'updated' => $updated,
        ]);
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Failed to save settings'),
            [],
            500,
            $e,
            $request
        );
    }
});
