<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * @OA\Get(
 *     path="/plugins/api/plugins",
 *     operationId="listPlugins",
 *     summary="List all available plugins",
 *     tags={"Plugins"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Plugin list",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="success", type="boolean"),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Forbidden — Admin role required"),
 *     @OA\Response(response=500, description="Error listing plugins")
 * )
 */
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

/**
 * @OA\Get(
 *     path="/plugins/api/plugins/{pluginId}",
 *     operationId="getPlugin",
 *     summary="Get plugin details",
 *     tags={"Plugins"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="pluginId", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(
 *         response=200,
 *         description="Plugin details",
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="success", type="boolean"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="metadata", type="object"),
 *                 @OA\Property(property="isActive", type="boolean"),
 *                 @OA\Property(property="isConfigured", type="boolean")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Forbidden — Admin role required"),
 *     @OA\Response(response=404, description="Plugin not found")
 * )
 */
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

/**
 * @OA\Post(
 *     path="/plugins/api/plugins/{pluginId}/enable",
 *     operationId="enablePlugin",
 *     summary="Enable a plugin",
 *     tags={"Plugins"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="pluginId", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="Plugin enabled"),
 *     @OA\Response(response=400, description="Plugin cannot be enabled (dependency or version issue)"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Forbidden — Admin role required"),
 *     @OA\Response(response=500, description="Error enabling plugin")
 * )
 */
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

/**
 * @OA\Post(
 *     path="/plugins/api/plugins/{pluginId}/disable",
 *     operationId="disablePlugin",
 *     summary="Disable a plugin",
 *     tags={"Plugins"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="pluginId", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="Plugin disabled"),
 *     @OA\Response(response=400, description="Plugin cannot be disabled (other plugins depend on it)"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Forbidden — Admin role required"),
 *     @OA\Response(response=500, description="Error disabling plugin")
 * )
 */
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

/**
 * @OA\Post(
 *     path="/plugins/api/plugins/{pluginId}/settings",
 *     operationId="updatePluginSettings",
 *     summary="Update plugin settings",
 *     tags={"Plugins"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="pluginId", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(required=true,
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="settings", type="object", description="Key-value map of settings to update")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Settings updated"),
 *     @OA\Response(response=400, description="No settings provided or plugin not found"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Forbidden — Admin role required"),
 *     @OA\Response(response=404, description="Plugin not found")
 * )
 */
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

/**
 * @OA\Post(
 *     path="/plugins/api/plugins/{pluginId}/test",
 *     operationId="testPluginConnection",
 *     summary="Test plugin connection with provided settings",
 *     description="Password fields may be omitted — the plugin falls back to its saved value.",
 *     tags={"Plugins"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="pluginId", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\RequestBody(required=false,
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="settings", type="object", description="Settings to test with (partial override)")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Connection successful"),
 *     @OA\Response(response=400, description="Connection test failed"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Forbidden — Admin role required"),
 *     @OA\Response(response=404, description="Plugin not found or not active")
 * )
 */
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

/**
 * @OA\Post(
 *     path="/plugins/api/plugins/{pluginId}/reset",
 *     operationId="resetPluginSettings",
 *     summary="Reset all settings for a plugin",
 *     tags={"Plugins"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="pluginId", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="Settings reset"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Forbidden — Admin role required"),
 *     @OA\Response(response=404, description="Plugin not found")
 * )
 */
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
