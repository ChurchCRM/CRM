<?php

namespace ChurchCRM\Plugin;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\LoggerUtils;

/**
 * Manages ChurchCRM plugins: discovery, loading, activation, and lifecycle.
 *
 * Plugins can be:
 * - Core plugins: Shipped with ChurchCRM (src/plugins/core/)
 * - Community plugins: Third-party extensions (src/plugins/community/)
 *
 * Plugin state is stored in SystemConfig with prefixed keys (plugin.{id}.enabled).
 */
class PluginManager
{
    /**
     * Base path to plugins directory.
     */
    private static string $pluginsPath = '';

    /**
     * Discovered plugin metadata.
     *
     * @var array<string, PluginMetadata>
     */
    private static array $discoveredPlugins = [];

    /**
     * Currently loaded plugin instances.
     *
     * @var array<string, PluginInterface>
     */
    private static array $loadedPlugins = [];

    /**
     * Whether the manager has been initialized.
     */
    private static bool $initialized = false;

    /**
     * Initialize the plugin system.
     *
     * @param string $pluginsPath Base path to plugins directory
     */
    public static function init(string $pluginsPath): void
    {
        if (self::$initialized) {
            return;
        }

        self::$pluginsPath = rtrim($pluginsPath, '/');
        self::discoverPlugins();
        self::loadActivePlugins();
        self::$initialized = true;

        LoggerUtils::getAppLogger()->debug('Plugin system initialized', [
            'discovered' => count(self::$discoveredPlugins),
            'active' => count(self::$loadedPlugins),
        ]);
    }

    /**
     * Discover all plugins from the filesystem.
     *
     * Scans src/plugins/core/ and src/plugins/community/ directories
     * for plugin.json manifest files.
     */
    public static function discoverPlugins(): void
    {
        self::$discoveredPlugins = [];

        $directories = ['core', 'community'];

        foreach ($directories as $type) {
            $typePath = self::$pluginsPath . '/' . $type;

            if (!is_dir($typePath)) {
                continue;
            }

            foreach (new \DirectoryIterator($typePath) as $dir) {
                if ($dir->isDot() || !$dir->isDir()) {
                    continue;
                }

                try {
                    $manifestPath = $dir->getPathname() . '/plugin.json';
                    $metadata = PluginMetadata::fromJsonFile($manifestPath);

                    if ($metadata !== null && $metadata->isValid()) {
                        self::$discoveredPlugins[$metadata->getId()] = $metadata;

                        LoggerUtils::getAppLogger()->debug("Discovered plugin: {$metadata->getId()}", [
                            'type' => $metadata->getType(),
                            'version' => $metadata->getVersion(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    LoggerUtils::getAppLogger()->warning(
                        'Failed to load plugin manifest: ' . $dir->getFilename(),
                        ['error' => $e->getMessage(), 'path' => $dir->getPathname()]
                    );
                }
            }
        }
    }

    /**
     * Load and boot all active plugins.
     */
    private static function loadActivePlugins(): void
    {
        foreach (self::$discoveredPlugins as $pluginId => $metadata) {
            if (self::isPluginActive($pluginId)) {
                try {
                    self::loadPlugin($pluginId);
                } catch (\Throwable $e) {
                    LoggerUtils::getAppLogger()->error(
                        "Failed to load plugin: $pluginId",
                        ['exception' => $e->getMessage()]
                    );
                }
            }
        }
    }

    /**
     * Load a single plugin by ID.
     */
    private static function loadPlugin(string $pluginId): ?PluginInterface
    {
        if (isset(self::$loadedPlugins[$pluginId])) {
            return self::$loadedPlugins[$pluginId];
        }

        $metadata = self::$discoveredPlugins[$pluginId] ?? null;
        if ($metadata === null) {
            return null;
        }

        // Register plugin autoloader
        self::registerPluginAutoloader($metadata);

        $mainClass = $metadata->getMainClass();
        if (!class_exists($mainClass)) {
            LoggerUtils::getAppLogger()->error(
                "Plugin main class not found: $mainClass",
                ['plugin' => $pluginId]
            );

            return null;
        }

        // Instantiate the plugin
        $plugin = new $mainClass($metadata->getPath());

        if (!$plugin instanceof PluginInterface) {
            LoggerUtils::getAppLogger()->error(
                "Plugin class does not implement PluginInterface",
                ['plugin' => $pluginId, 'class' => $mainClass]
            );

            return null;
        }

        // Boot the plugin
        $plugin->boot();

        self::$loadedPlugins[$pluginId] = $plugin;

        LoggerUtils::getAppLogger()->info("Plugin loaded: $pluginId");

        return $plugin;
    }

    /**
     * Register PSR-4 autoloader for a plugin.
     */
    private static function registerPluginAutoloader(PluginMetadata $metadata): void
    {
        $pluginPath = $metadata->getPath();
        $srcPath = $pluginPath . '/src';

        if (!is_dir($srcPath)) {
            return;
        }

        // Simple PSR-4 autoloader registration
        // The namespace is derived from the main class
        $mainClass = $metadata->getMainClass();
        $lastSeparator = strrpos($mainClass, '\\');
        $namespace = $lastSeparator !== false ? substr($mainClass, 0, $lastSeparator + 1) : '';

        if (!empty($namespace)) {
            spl_autoload_register(function ($class) use ($namespace, $srcPath) {
                if (strpos($class, $namespace) === 0) {
                    $relativeClass = substr($class, strlen($namespace));
                    $file = $srcPath . '/' . str_replace('\\', '/', $relativeClass) . '.php';

                    if (file_exists($file)) {
                        require_once $file;
                    }
                }
            });
        }
    }

    /**
     * Check if a plugin is active.
     *
     * Checks the SystemConfig key plugin.{pluginId}.enabled
     */
    public static function isPluginActive(string $pluginId): bool
    {
        $enabledKey = "plugin.{$pluginId}.enabled";
        return SystemConfig::getBooleanValue($enabledKey);
    }

    /**
     * Enable a plugin.
     *
     * @throws \RuntimeException If dependencies are not met
     */
    public static function enablePlugin(string $pluginId): bool
    {
        $metadata = self::$discoveredPlugins[$pluginId] ?? null;
        if ($metadata === null) {
            throw new \RuntimeException("Plugin not found: $pluginId");
        }

        // Check dependencies
        foreach ($metadata->getDependencies() as $depId) {
            if (!self::isPluginActive($depId)) {
                throw new \RuntimeException(
                    "Plugin '$pluginId' requires '$depId' to be active"
                );
            }
        }

        // Check ChurchCRM version
        $crmVersion = $_SESSION['sSoftwareInstalledVersion'] ?? '5.0.0';
        if (version_compare($crmVersion, $metadata->getMinimumCRMVersion(), '<')) {
            throw new \RuntimeException(
                "Plugin '$pluginId' requires ChurchCRM {$metadata->getMinimumCRMVersion()} or higher"
            );
        }

        // Load the plugin
        $plugin = self::loadPlugin($pluginId);
        if ($plugin === null) {
            return false;
        }

        // Call activate hook
        $plugin->activate();

        // Save state to SystemConfig
        $enabledKey = "plugin.{$pluginId}.enabled";
        SystemConfig::setValue($enabledKey, '1');

        LoggerUtils::getAppLogger()->info("Plugin enabled: $pluginId");

        return true;
    }

    /**
     * Disable a plugin.
     *
     * @throws \RuntimeException If other plugins depend on this one
     */
    public static function disablePlugin(string $pluginId): bool
    {
        // Check if other plugins depend on this one
        $dependents = self::getPluginDependents($pluginId);
        if (!empty($dependents)) {
            throw new \RuntimeException(
                "Cannot disable '$pluginId': required by " . implode(', ', $dependents)
            );
        }

        // Call deactivate hook
        $plugin = self::$loadedPlugins[$pluginId] ?? null;
        if ($plugin !== null) {
            $plugin->deactivate();
        }

        // Remove from loaded plugins
        unset(self::$loadedPlugins[$pluginId]);

        // Save state to SystemConfig
        $enabledKey = "plugin.{$pluginId}.enabled";
        SystemConfig::setValue($enabledKey, '0');

        LoggerUtils::getAppLogger()->info("Plugin disabled: $pluginId");

        return true;
    }

    /**
     * Get plugins that depend on the given plugin.
     *
     * @return string[] Plugin IDs that depend on $pluginId
     */
    public static function getPluginDependents(string $pluginId): array
    {
        $dependents = [];

        foreach (self::$discoveredPlugins as $id => $metadata) {
            if (self::isPluginActive($id) && in_array($pluginId, $metadata->getDependencies(), true)) {
                $dependents[] = $id;
            }
        }

        return $dependents;
    }

    /**
     * Get a loaded plugin instance by ID.
     */
    public static function getPlugin(string $pluginId): ?PluginInterface
    {
        return self::$loadedPlugins[$pluginId] ?? null;
    }

    /**
     * Get metadata for a discovered plugin.
     */
    public static function getPluginMetadata(string $pluginId): ?PluginMetadata
    {
        return self::$discoveredPlugins[$pluginId] ?? null;
    }

    /**
     * Get all discovered plugins with their status.
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     version: string,
     *     author: string,
     *     type: string,
     *     isActive: bool,
     *     isConfigured: bool,
     *     settingsUrl: ?string
     * }>
     */
    public static function getAllPlugins(): array
    {
        $result = [];

        foreach (self::$discoveredPlugins as $id => $metadata) {
            try {
                $plugin = self::$loadedPlugins[$id] ?? null;
                $isActive = self::isPluginActive($id);

                $result[] = [
                    'id' => $id,
                    'name' => $metadata->getName(),
                    'description' => $metadata->getDescription(),
                    'version' => $metadata->getVersion(),
                    'author' => $metadata->getAuthor(),
                    'authorUrl' => $metadata->getAuthorUrl(),
                    'type' => $metadata->getType(),
                    'isActive' => $isActive,
                    'isConfigured' => $plugin?->isConfigured() ?? false,
                    'settingsUrl' => $metadata->getSettingsUrl(),
                    'settings' => self::getPluginSettingsWithValues($id),
                    'help' => $plugin?->getHelp() ?? $metadata->getHelp(),
                    'hasError' => false,
                    'errorMessage' => null,
                ];
            } catch (\Throwable $e) {
                // Plugin has an error - still show it in the list but mark as errored
                LoggerUtils::getAppLogger()->warning(
                    "Error loading plugin info: $id",
                    ['error' => $e->getMessage()]
                );

                $result[] = [
                    'id' => $id,
                    'name' => $metadata->getName() ?? $id,
                    'description' => $metadata->getDescription() ?? '',
                    'version' => $metadata->getVersion() ?? 'unknown',
                    'author' => $metadata->getAuthor() ?? 'Unknown',
                    'authorUrl' => null,
                    'type' => $metadata->getType() ?? 'community',
                    'isActive' => false,
                    'isConfigured' => false,
                    'settingsUrl' => null,
                    'hasError' => true,
                    'errorMessage' => $e->getMessage(),
                ];
            }
        }

        // Sort: core plugins first, then by name
        usort($result, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'core' ? -1 : 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $result;
    }

    /**
     * Get all active plugin instances.
     *
     * @return array<string, PluginInterface>
     */
    public static function getActivePlugins(): array
    {
        return self::$loadedPlugins;
    }

    /**
     * Get the plugins base path.
     */
    public static function getPluginsPath(): string
    {
        return self::$pluginsPath;
    }

    /**
     * Reset the plugin manager (useful for testing).
     */
    public static function reset(): void
    {
        self::$discoveredPlugins = [];
        self::$loadedPlugins = [];
        self::$initialized = false;
    }

    /**
     * Get plugin settings with current values from SystemConfig.
     *
     * Maps plugin setting keys to their corresponding SystemConfig keys.
     */
    public static function getPluginSettingsWithValues(string $pluginId): array
    {
        $metadata = self::$discoveredPlugins[$pluginId] ?? null;
        if ($metadata === null) {
            return [];
        }

        // Mapping of plugin setting keys to SystemConfig keys
        $configKeyMap = self::getPluginConfigKeyMap($pluginId);

        $settings = [];
        foreach ($metadata->getSettings() as $setting) {
            $settingKey = $setting['key'] ?? '';
            $configKey = $configKeyMap[$settingKey] ?? null;

            $currentValue = '';
            if ($configKey !== null) {
                $currentValue = SystemConfig::getValue($configKey) ?? '';
            }

            $settings[] = array_merge($setting, [
                'configKey' => $configKey,
                'value' => $currentValue,
            ]);
        }

        return $settings;
    }

    /**
     * Get the mapping of plugin setting keys to SystemConfig keys.
     *
     * All plugin config keys use the format: plugin.{pluginId}.{settingKey}
     * This method dynamically generates the mapping based on the plugin's settings schema.
     */
    private static function getPluginConfigKeyMap(string $pluginId): array
    {
        $metadata = self::$discoveredPlugins[$pluginId] ?? null;
        if ($metadata === null) {
            return [];
        }

        $map = [];
        foreach ($metadata->getSettings() as $setting) {
            $key = $setting['key'] ?? '';
            if (!empty($key)) {
                // Generate the SystemConfig key using plugin prefix
                $map[$key] = "plugin.{$pluginId}.{$key}";
            }
        }

        return $map;
    }

    /**
     * Update a plugin setting value in SystemConfig.
     *
     * The setting key is automatically prefixed with plugin.{pluginId}.
     */
    public static function updatePluginSetting(string $pluginId, string $settingKey, string $value): bool
    {
        // Generate the full config key
        $configKey = "plugin.{$pluginId}.{$settingKey}";

        try {
            SystemConfig::setValue($configKey, $value);
            LoggerUtils::getAppLogger()->info(
                "Updated plugin setting: $pluginId.$settingKey",
                ['configKey' => $configKey]
            );
            return true;
        } catch (\Throwable $e) {
            LoggerUtils::getAppLogger()->error(
                "Failed to update plugin setting: $pluginId.$settingKey",
                ['error' => $e->getMessage()]
            );
            return false;
        }
    }

    // =========================================================================
    // Head/Footer Content Injection
    // =========================================================================

    /**
     * Get combined head content from all active plugins.
     *
     * Called by Header.php to inject plugin content into <head>.
     *
     * @return string Combined HTML/JS content from all active plugins
     */
    public static function getPluginHeadContent(): string
    {
        $content = '';

        foreach (self::$loadedPlugins as $plugin) {
            try {
                $pluginContent = $plugin->getHeadContent();
                if (!empty($pluginContent)) {
                    $content .= "\n<!-- Plugin: {$plugin->getId()} -->\n";
                    $content .= $pluginContent;
                }
            } catch (\Throwable $e) {
                LoggerUtils::getAppLogger()->error(
                    "Error getting head content from plugin: {$plugin->getId()}",
                    ['error' => $e->getMessage()]
                );
            }
        }

        return $content;
    }

    /**
     * Get combined footer content from all active plugins.
     *
     * Called by Footer.php to inject plugin content before </body>.
     *
     * @return string Combined HTML/JS content from all active plugins
     */
    public static function getPluginFooterContent(): string
    {
        $content = '';

        foreach (self::$loadedPlugins as $plugin) {
            try {
                $pluginContent = $plugin->getFooterContent();
                if (!empty($pluginContent)) {
                    $content .= "\n<!-- Plugin: {$plugin->getId()} -->\n";
                    $content .= $pluginContent;
                }
            } catch (\Throwable $e) {
                LoggerUtils::getAppLogger()->error(
                    "Error getting footer content from plugin: {$plugin->getId()}",
                    ['error' => $e->getMessage()]
                );
            }
        }

        return $content;
    }

    /**
     * Get combined client-side configuration from all active plugins.
     *
     * Called by Header.php to expose plugin configs to JavaScript via window.CRM.plugins
     * Each plugin's getClientConfig() return value is included under its plugin ID key.
     *
     * Example output:
     *   ['gravatar' => ['enabled' => true, 'defaultImage' => 'mp'], 'analytics' => ['trackingId' => 'G-XXX']]
     *
     * @return array<string, array> Plugin configs keyed by plugin ID
     */
    public static function getPluginsClientConfig(): array
    {
        $configs = [];

        foreach (self::$loadedPlugins as $pluginId => $plugin) {
            try {
                $config = $plugin->getClientConfig();
                if (!empty($config)) {
                    $configs[$pluginId] = $config;
                }
            } catch (\Throwable $e) {
                LoggerUtils::getAppLogger()->error(
                    "Error getting client config from plugin: {$pluginId}",
                    ['error' => $e->getMessage()]
                );
            }
        }

        return $configs;
    }
}
