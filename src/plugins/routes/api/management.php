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

        return SlimUtils::renderJSON($response, [
            'success' => true,
            'message' => gettext('Plugin enabled successfully'),
        ]);
    } catch (\RuntimeException $e) {
        // Dependency or version errors - use generic message
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Plugin cannot be enabled due to dependency or version requirements'),
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

        return SlimUtils::renderJSON($response, [
            'success' => true,
            'message' => gettext('Plugin disabled successfully'),
        ]);
    } catch (\RuntimeException $e) {
        // Dependency errors (other plugins depend on this one) - use generic message
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Plugin cannot be disabled because other plugins depend on it'),
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

        LoggerUtils::getAppLogger()->debug("Plugin settings updated: $pluginId", ['settings' => array_keys($settings)]);

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

// Test plugin connection with the settings provided in the request body.
// Password fields may be omitted — the plugin falls back to its saved value.
$group->post('/plugins/{pluginId}/test', function (Request $request, Response $response, array $args): Response {
    try {
        $pluginId = $args['pluginId'];
        $body     = $request->getParsedBody();
        $settings = $body['settings'] ?? [];

        $pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';
        PluginManager::init($pluginsPath);

        $plugin = PluginManager::getPlugin($pluginId);
        if ($plugin === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Plugin not found or not active'), [], 404);
        }

        $result     = $plugin->testWithSettings($settings);
        $statusCode = ($result['success'] ?? false) ? 200 : 400;

        return SlimUtils::renderJSON($response, $result, $statusCode);
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON($response, gettext('Connection test failed'), [], 500, $e, $request);
    }
});

// Reset plugin settings (clear all values)
$group->post('/plugins/{pluginId}/reset', function (Request $request, Response $response, array $args): Response {
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

        // Get all settings for this plugin and clear them
        $settings = $metadata->getSettings();
        $cleared = [];
        $failed = [];

        foreach ($settings as $setting) {
            $key = $setting['key'] ?? null;
            if ($key === null) {
                continue;
            }

            // Clear the setting by setting it to empty string
            if (PluginManager::updatePluginSetting($pluginId, $key, '')) {
                $cleared[] = $key;
            } else {
                $failed[] = $key;
            }
        }

        if (!empty($failed)) {
            return SlimUtils::renderJSON($response, [
                'success' => false,
                'message' => gettext('Some settings could not be reset'),
                'cleared' => $cleared,
                'failed' => $failed,
            ]);
        }

        LoggerUtils::getAppLogger()->info("Plugin settings reset: $pluginId", ['cleared' => $cleared]);

        return SlimUtils::renderJSON($response, [
            'success' => true,
            'message' => gettext('Settings reset successfully'),
            'cleared' => $cleared,
        ]);
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Failed to reset settings'),
            [],
            500,
            $e,
            $request
        );
    }
});
