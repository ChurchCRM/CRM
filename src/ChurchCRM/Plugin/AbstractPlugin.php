<?php

namespace ChurchCRM\Plugin;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\SQLUtils;
use PDO;
use Propel\Runtime\Propel;

/**
 * Abstract base class for ChurchCRM plugins.
 *
 * Provides common functionality and sensible defaults
 * for plugin implementations.
 *
 * Plugin metadata (version, author, etc.) is read from plugin.json
 * to ensure a single source of truth.
 *
 * Plugins can only access their own config values using the
 * getConfigValue() and setConfigValue() methods, which enforce
 * the plugin.{pluginId}.{key} prefix.
 */
abstract class AbstractPlugin implements PluginInterface
{
    protected string $basePath = '';

    /**
     * Cached plugin manifest data from plugin.json.
     */
    private ?array $manifest = null;

    public function __construct(string $basePath = '')
    {
        $this->basePath = $basePath;
    }

    /**
     * Get the base filesystem path of this plugin.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Load and cache the plugin manifest from plugin.json.
     *
     * @return array The manifest data or empty array if not found
     */
    protected function getManifest(): array
    {
        if ($this->manifest === null) {
            $manifestPath = $this->basePath . '/plugin.json';
            if (file_exists($manifestPath)) {
                $content = file_get_contents($manifestPath);
                $this->manifest = json_decode($content, true) ?? [];
            } else {
                $this->manifest = [];
            }
        }
        return $this->manifest;
    }

    /**
     * Get the plugin version from plugin.json.
     *
     * This is the single source of truth for the version.
     */
    public function getVersion(): string
    {
        return $this->getManifest()['version'] ?? '0.0.0';
    }

    // =========================================================================
    // Plugin Config Access (Sandboxed to plugin.{pluginId}.* keys only)
    // =========================================================================

    /**
     * Get the config key prefix for this plugin.
     *
     * All plugin config keys use format: plugin.{pluginId}.{settingKey}
     */
    protected function getConfigPrefix(): string
    {
        return 'plugin.' . $this->getId() . '.';
    }

    /**
     * Get a config value for this plugin.
     *
     * Automatically prefixes the key with plugin.{pluginId}.
     * Plugins can only access their own config values.
     * Returns empty string if config key doesn't exist (graceful degradation).
     *
     * @param string $key Setting key (without prefix)
     * @return string Config value or empty string if not set
     */
    protected function getConfigValue(string $key): string
    {
        try {
            $fullKey = $this->getConfigPrefix() . $key;
            return SystemConfig::getValue($fullKey) ?? '';
        } catch (\Throwable $e) {
            // Config key doesn't exist - return empty string
            return '';
        }
    }

    /**
     * Get a boolean config value for this plugin.
     *
     * Automatically prefixes the key with plugin.{pluginId}.
     * Plugins can only access their own config values.
     * Returns false if config key doesn't exist (graceful degradation).
     *
     * @param string $key Setting key (without prefix)
     * @return bool Config value as boolean
     */
    protected function getBooleanConfigValue(string $key): bool
    {
        try {
            $fullKey = $this->getConfigPrefix() . $key;
            return SystemConfig::getBooleanValue($fullKey);
        } catch (\Throwable $e) {
            // Config key doesn't exist - return false
            return false;
        }
    }

    /**
     * Set a config value for this plugin.
     *
     * Automatically prefixes the key with plugin.{pluginId}.
     * Plugins can only modify their own config values.
     * Silently fails if config key doesn't exist (graceful degradation).
     *
     * @param string $key   Setting key (without prefix)
     * @param string $value Value to set
     */
    protected function setConfigValue(string $key, string $value): void
    {
        try {
            $fullKey = $this->getConfigPrefix() . $key;
            SystemConfig::setValue($fullKey, $value);
        } catch (\Throwable $e) {
            // Config key doesn't exist - log but don't crash
            LoggerUtils::getAppLogger()->warning(
                'Failed to set plugin config',
                ['plugin' => $this->getId(), 'key' => $key, 'error' => $e->getMessage()]
            );
        }
    }

    /**
     * Check if this plugin is enabled.
     *
     * Convenience method to check the plugin.{pluginId}.enabled config.
     * Returns false if config key doesn't exist (graceful degradation).
     */
    public function isEnabled(): bool
    {
        try {
            return $this->getBooleanConfigValue('enabled');
        } catch (\Throwable $e) {
            // Config key doesn't exist - plugin is not enabled
            return false;
        }
    }

    // =========================================================================
    // Plugin-Owned Database Schema
    // =========================================================================

    /**
     * Absolute path to this plugin's schema file, or null if it owns no tables.
     *
     * Declared in plugin.json as a path relative to the plugin directory:
     *
     *     "schemaFile": "schema/install.sql"
     *
     * A plugin's tables are its own responsibility, not a core release
     * migration's: core upgrade scripts only run when a database crosses the
     * version they are attached to, so a plugin that shipped inside an already
     * released version would never provision itself on databases already at
     * that version. Applying the schema on enable removes the coupling.
     */
    public function getSchemaFile(): ?string
    {
        $relativePath = $this->getManifest()['schemaFile'] ?? null;
        if (!is_string($relativePath) || trim($relativePath) === '') {
            return null;
        }

        // Keep the manifest from reaching outside its own plugin directory.
        if (str_contains($relativePath, '..')) {
            LoggerUtils::getAppLogger()->error(
                'Plugin schemaFile must stay inside the plugin directory',
                ['plugin' => $this->getId(), 'schemaFile' => $relativePath]
            );
            return null;
        }

        $schemaPath = $this->basePath . '/' . ltrim($relativePath, '/');

        return file_exists($schemaPath) ? $schemaPath : null;
    }

    /**
     * Tables this plugin cannot run without, from plugin.json `requiredTables`.
     *
     * Used to verify that the schema actually landed before the plugin is
     * marked enabled, and to explain a broken install to the administrator.
     *
     * @return string[]
     */
    public function getRequiredTables(): array
    {
        $tables = $this->getManifest()['requiredTables'] ?? [];
        if (!is_array($tables)) {
            return [];
        }

        return array_values(array_filter($tables, static fn ($table): bool => is_string($table) && $table !== ''));
    }

    /**
     * Apply this plugin's schema file, if it has one.
     *
     * Runs on every enable, so the schema file must be idempotent
     * (CREATE TABLE IF NOT EXISTS, ALTER guarded by information_schema, ...).
     *
     * This executes DDL as the runtime database user. A deployment that grants
     * the application DML only will fail here — the same privilege the in-app
     * upgrader already needs, but now reachable from the plugin page too.
     *
     * @throws \RuntimeException If the schema cannot be applied
     */
    public function installSchema(): void
    {
        $schemaFile = $this->getSchemaFile();
        if ($schemaFile === null) {
            return;
        }

        try {
            SQLUtils::sqlImport($schemaFile, Propel::getWriteConnection('default'));
        } catch (\Throwable $e) {
            LoggerUtils::getAppLogger()->error(
                'Unable to apply plugin schema',
                [
                    'plugin' => $this->getId(),
                    'schemaFile' => basename($schemaFile),
                    'error' => $e->getMessage(),
                ]
            );

            throw new \RuntimeException(
                "Unable to apply schema for plugin '{$this->getId()}': " . $e->getMessage(),
                0,
                $e
            );
        }

        LoggerUtils::getAppLogger()->info(
            'Plugin schema applied',
            ['plugin' => $this->getId(), 'schemaFile' => basename($schemaFile)]
        );
    }

    /**
     * Required tables that are absent from the database.
     *
     * Throws rather than answering "nothing is missing" when the database
     * cannot be questioned. This is a verification gate, and a gate that
     * cannot verify must fail closed: an empty array here is what lets
     * enablePlugin() record the plugin as enabled.
     *
     * @return string[]
     *
     * @throws \RuntimeException If the database cannot be queried
     */
    public function getMissingTables(): array
    {
        $requiredTables = $this->getRequiredTables();
        if ($requiredTables === []) {
            return [];
        }

        try {
            $connection = Propel::getReadConnection('default');
            $placeholders = implode(', ', array_fill(0, count($requiredTables), '?'));
            $statement = $connection->prepare(
                'SELECT TABLE_NAME FROM information_schema.TABLES'
                . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (' . $placeholders . ')'
            );
            $statement->execute($requiredTables);
            $presentTables = array_flip($statement->fetchAll(PDO::FETCH_COLUMN));
        } catch (\Throwable $e) {
            LoggerUtils::getAppLogger()->error(
                'Unable to verify plugin tables',
                ['plugin' => $this->getId(), 'error' => $e->getMessage()]
            );

            throw new \RuntimeException(
                "Unable to verify tables for plugin '{$this->getId()}': " . $e->getMessage(),
                0,
                $e
            );
        }

        return array_values(array_filter(
            $requiredTables,
            static fn (string $table): bool => !isset($presentTables[$table])
        ));
    }

    public function getAuthor(): string
    {
        return 'ChurchCRM Team';
    }

    public function getAuthorUrl(): ?string
    {
        return null;
    }

    public function getMinimumCRMVersion(): string
    {
        return '5.0.0';
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getType(): string
    {
        return 'community';
    }

    public function getSettingsUrl(): ?string
    {
        return null;
    }

    /**
     * Get the settings schema for this plugin.
     *
     * Default returns an empty array (no settings). Override in subclass
     * to define plugin-specific settings.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSettingsSchema(): array
    {
        return [];
    }

    /**
     * Check if the plugin is properly configured.
     *
     * By default, checks that all required settings have values.
     * Override in subclass for custom configuration validation.
     */
    public function isConfigured(): bool
    {
        $settings = $this->getSettingsSchema();
        foreach ($settings as $setting) {
            if (!empty($setting['required'])) {
                $value = $this->getConfigValue($setting['key'] ?? '');
                if (empty($value)) {
                    return false;
                }
            }
        }
        return true;
    }

    public function activate(): void
    {
        LoggerUtils::getAppLogger()->debug("Plugin '{$this->getId()}' activated");
    }

    public function deactivate(): void
    {
        LoggerUtils::getAppLogger()->debug("Plugin '{$this->getId()}' deactivated");
    }

    public function uninstall(): void
    {
        LoggerUtils::getAppLogger()->info("Plugin '{$this->getId()}' uninstalled");
    }

    /**
     * Get any configuration error message.
     * Override in subclass to provide specific error messages.
     */
    public function getConfigurationError(): ?string
    {
        return null;
    }

    /**
     * Get HTML/JavaScript content to inject into the page <head>.
     * Override in subclass to add head content.
     */
    public function getHeadContent(): string
    {
        return '';
    }

    /**
     * Get HTML/JavaScript content to inject before closing </body>.
     * Override in subclass to add footer content.
     */
    public function getFooterContent(): string
    {
        return '';
    }

    /**
     * Get plugin help content.
     * Loads help from help.json file in the plugin directory.
     * Override in subclass to provide dynamic/localized help.
     *
     * @return array Help content with optional 'summary', 'sections', and 'links'
     */
    public function getHelp(): array
    {
        return $this->loadHelpFromJson();
    }

    /**
     * Load help content from help.json file in the plugin directory.
     *
     * @return array Help content or empty array if not found
     */
    protected function loadHelpFromJson(): array
    {
        $helpFile = $this->basePath . '/help.json';

        if (!file_exists($helpFile)) {
            return [
                'summary' => '',
                'sections' => [],
                'links' => [],
            ];
        }

        $content = file_get_contents($helpFile);
        $help = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($help)) {
            $this->log('Failed to parse help.json', 'warning', [
                'file' => $helpFile,
                'error' => json_last_error_msg(),
            ]);

            return [
                'summary' => '',
                'sections' => [],
                'links' => [],
            ];
        }

        return [
            'summary' => $help['summary'] ?? '',
            'sections' => $help['sections'] ?? [],
            'links' => $help['links'] ?? [],
        ];
    }

    /**
     * Get client-side configuration for this plugin.
     *
     * Default implementation returns empty array (no client config).
     * Override in subclass to provide plugin-specific client config.
     *
     * @return array Configuration for client-side use
     */
    public function getClientConfig(): array
    {
        return [];
    }

    /**
     * Get menu items to add to the navigation.
     *
     * Default implementation returns empty array (no menu items).
     * Override in subclass to provide plugin-specific menu items.
     *
     * Each menu item should be an array with:
     * - 'parent': Parent menu key (e.g., 'admin', 'email', 'people')
     * - 'label': Display text (use gettext() for i18n)
     * - 'url': Relative URL path
     * - 'icon': Optional FontAwesome icon class
     * - 'permission': Optional. Shows the item only to users holding this permission;
     *   administrators always see it. Valid names: 'bAdmin', 'bAll', 'bAddRecords',
     *   'bEditRecords', 'bDeleteRecords', 'bManageGroups', 'bFinance', 'bNotes', or a
     *   per-user setting name such as 'bEmailMailto'. An unrecognized name hides the item
     *   from non-administrators (fails closed). Omit the key to show the item to every
     *   signed-in user. This only controls menu visibility and is NOT access control: the
     *   plugin's routes must still carry the matching ChurchCRM\Slim\Middleware\Request\Auth\*
     *   middleware (AdminRoleAuthMiddleware, FinanceRoleAuthMiddleware, ...).
     *
     * Example: ['parent' => 'admin', 'label' => gettext('My Plugin'),
     *           'url' => 'plugins/my-plugin/dashboard', 'permission' => 'bAdmin']
     *
     * @return array<int, array{parent: string, label: string, url: string, icon?: string, permission?: string}>
     */
    public function getMenuItems(): array
    {
        return [];
    }

    /**
     * Test the plugin connection using the provided settings.
     *
     * Override in plugins that support connection testing (declare "hasTest": true
     * in plugin.json). The settings array contains the raw form values keyed by
     * setting key (e.g. 'apiKey', 'fromNumber').
     *
     * Password fields may be absent from the settings array when the admin has
     * not changed the value — implementations should fall back to the saved value
     * in that case.
     *
     * @param array $settings Raw setting values from the admin form
     *
     * @return array{success: bool, message: string, details?: array<string, mixed>}
     */
    public function testWithSettings(array $settings): array
    {
        return [
            'success' => false,
            'message' => gettext('This plugin does not support connection testing.'),
        ];
    }

    /**
     * Helper to log plugin messages.
     */
    protected function log(string $message, string $level = 'info', array $context = []): void
    {
        $context['plugin'] = $this->getId();
        LoggerUtils::getAppLogger()->$level($message, $context);
    }
}
