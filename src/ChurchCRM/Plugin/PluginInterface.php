<?php

namespace ChurchCRM\Plugin;

/**
 * Interface that all ChurchCRM plugins must implement.
 *
 * Plugins extend ChurchCRM functionality through hooks, filters,
 * menu items, and settings panels.
 */
interface PluginInterface
{
    /**
     * Get the unique plugin identifier (slug).
     * Should be lowercase with hyphens, e.g., 'mailchimp-integration'.
     */
    public function getId(): string;

    /**
     * Get the human-readable plugin name.
     */
    public function getName(): string;

    /**
     * Get the plugin description.
     */
    public function getDescription(): string;

    /**
     * Get the plugin version (semver format).
     */
    public function getVersion(): string;

    /**
     * Get the plugin author name.
     */
    public function getAuthor(): string;

    /**
     * Get the plugin author URL (optional).
     */
    public function getAuthorUrl(): ?string;

    /**
     * Get the minimum ChurchCRM version required.
     */
    public function getMinimumCRMVersion(): string;

    /**
     * Get the plugin dependencies (other plugin IDs).
     *
     * @return string[]
     */
    public function getDependencies(): array;

    /**
     * Called when the plugin is activated.
     * Use for database migrations, initial setup, etc.
     */
    public function activate(): void;

    /**
     * Called when the plugin is deactivated.
     * Use for cleanup that should happen when disabled.
     */
    public function deactivate(): void;

    /**
     * Called on every request when the plugin is active.
     * Register hooks, filters, menu items, etc. here.
     */
    public function boot(): void;

    /**
     * Called when the plugin is uninstalled (deleted).
     * Use for permanent cleanup: drop tables, delete files, etc.
     */
    public function uninstall(): void;

    /**
     * Get the plugin type: 'core' or 'community'.
     */
    public function getType(): string;

    /**
     * Get the plugin's settings page URL (if any).
     */
    public function getSettingsUrl(): ?string;

    /**
     * Check if the plugin is properly configured and ready to use.
     */
    public function isConfigured(): bool;
}
