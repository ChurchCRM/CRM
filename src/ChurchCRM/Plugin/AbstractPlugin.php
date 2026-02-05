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
 * Plugins can only access their own config values using the
 * getConfigValue() and setConfigValue() methods, which enforce
 * the plugin.{pluginId}.{key} prefix.
 */
abstract class AbstractPlugin implements PluginInterface
{
    protected string $basePath = '';

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

    public function isConfigured(): bool
    {
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
     * Helper to log plugin messages.
     */
    protected function log(string $message, string $level = 'info', array $context = []): void
    {
        $context['plugin'] = $this->getId();
        LoggerUtils::getAppLogger()->$level($message, $context);
    }
}
