<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\ApprovedPluginRegistry;
use ChurchCRM\Plugin\PluginInstaller;
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

/**
 * @OA\Get(
 *     path="/plugins/api/approved",
 *     operationId="listApprovedPlugins",
 *     summary="List community plugins approved for URL install",
 *     description="Returns the entries from src/plugins/approved-plugins.json. Only plugins in this list can be installed by URL.",
 *     tags={"Plugins"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Approved plugin list"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Forbidden — Admin role required")
 * )
 */
$group->get('/approved', function (Request $request, Response $response): Response {
    try {
        $pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';
        $entries = array_values(ApprovedPluginRegistry::all($pluginsPath));

        return SlimUtils::renderJSON($response, [
            'success' => true,
            'data' => $entries,
        ]);
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Failed to load approved plugin list'),
            [],
            500,
            $e,
            $request
        );
    }
});

/**
 * @OA\Post(
 *     path="/plugins/api/plugins/install",
 *     operationId="installPluginFromUrl",
 *     summary="Install a community plugin from an approved download URL",
 *     description="Downloads the zip, verifies its SHA-256 against approved-plugins.json, validates the archive, and extracts it into src/plugins/community/{id}. The plugin is NOT enabled automatically — admins must review and click Enable.",
 *     tags={"Plugins"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true,
 *         @OA\JsonContent(type="object",
 *             @OA\Property(property="downloadUrl", type="string", description="HTTPS URL to the plugin zip. Must match an approved entry exactly.")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Plugin installed (not yet enabled)"),
 *     @OA\Response(response=400, description="Validation failure (unknown URL, checksum mismatch, unsafe zip)"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=403, description="Forbidden — Admin role required"),
 *     @OA\Response(response=409, description="Plugin already installed"),
 *     @OA\Response(response=500, description="Install failed")
 * )
 */
$group->post('/plugins/install', function (Request $request, Response $response): Response {
    $body = $request->getParsedBody();
    $downloadUrl = is_array($body) ? (string) ($body['downloadUrl'] ?? '') : '';

    if ($downloadUrl === '') {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('A downloadUrl is required to install a plugin.'),
            [],
            400
        );
    }

    $pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';

    try {
        $result = PluginInstaller::installFromUrl($pluginsPath, $downloadUrl);

        // Force rediscovery so the new plugin appears in the management UI.
        PluginManager::reset();
        PluginManager::init($pluginsPath);

        LoggerUtils::getAppLogger()->info('Community plugin installed via URL', [
            'plugin' => $result['pluginId'],
            'version' => $result['version'],
        ]);

        return SlimUtils::renderJSON($response, [
            'success' => true,
            'message' => gettext('Plugin installed. Review it and click Enable to activate.'),
            'data' => $result,
        ]);
    } catch (\RuntimeException $e) {
        // Expected validation failures — map to 4xx so the UI can show the message.
        $status = str_contains($e->getMessage(), 'already installed') ? 409 : 400;
        LoggerUtils::getAppLogger()->warning('Plugin install rejected', [
            'url' => $downloadUrl,
            'error' => $e->getMessage(),
        ]);

        return SlimUtils::renderErrorJSON(
            $response,
            $e->getMessage(),
            [],
            $status
        );
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Failed to install plugin'),
            [],
            500,
            $e,
            $request
        );
    }
});
