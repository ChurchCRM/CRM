<?php

namespace ChurchCRM\Plugin;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\LoggerUtils;

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
     *
     * @param string $key Setting key (without prefix)
     * @return string Config value or empty string if not set
     */
    protected function getConfigValue(string $key): string
    {
        $fullKey = $this->getConfigPrefix() . $key;
        return SystemConfig::getValue($fullKey) ?? '';
    }

    /**
     * Get a boolean config value for this plugin.
     *
     * Automatically prefixes the key with plugin.{pluginId}.
     * Plugins can only access their own config values.
     *
     * @param string $key Setting key (without prefix)
     * @return bool Config value as boolean
     */
    protected function getBooleanConfigValue(string $key): bool
    {
        $fullKey = $this->getConfigPrefix() . $key;
        return SystemConfig::getBooleanValue($fullKey);
    }

    /**
     * Set a config value for this plugin.
     *
     * Automatically prefixes the key with plugin.{pluginId}.
     * Plugins can only modify their own config values.
     *
     * @param string $key   Setting key (without prefix)
     * @param string $value Value to set
     */
    protected function setConfigValue(string $key, string $value): void
    {
        $fullKey = $this->getConfigPrefix() . $key;
        SystemConfig::setValue($fullKey, $value);
    }

    /**
     * Check if this plugin is enabled.
     *
     * Convenience method to check the plugin.{pluginId}.enabled config.
     */
    protected function isEnabled(): bool
    {
        return $this->getBooleanConfigValue('enabled');
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
        LoggerUtils::getAppLogger()->info("Plugin '{$this->getId()}' activated");
    }

    public function deactivate(): void
    {
        LoggerUtils::getAppLogger()->info("Plugin '{$this->getId()}' deactivated");
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
     * Helper to log plugin messages.
     */
    protected function log(string $message, string $level = 'info', array $context = []): void
    {
        $context['plugin'] = $this->getId();
        LoggerUtils::getAppLogger()->$level($message, $context);
    }
}
