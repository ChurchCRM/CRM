<?php

namespace ChurchCRM\Plugin;

use ChurchCRM\Utils\LoggerUtils;

/**
 * Abstract base class for ChurchCRM plugins.
 *
 * Provides common functionality and sensible defaults
 * for plugin implementations.
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
     * Helper to log plugin messages.
     */
    protected function log(string $message, string $level = 'info', array $context = []): void
    {
        $context['plugin'] = $this->getId();
        LoggerUtils::getAppLogger()->$level($message, $context);
    }
}
