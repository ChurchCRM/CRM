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

    /**
     * Get any configuration error message for display in the plugin management UI.
     * Return null if there are no errors.
     */
    public function getConfigurationError(): ?string;

    /**
     * Get HTML/JavaScript content to inject into the page <head>.
     * Called only for active plugins on logged-in user pages.
     *
     * @return string HTML/JS content (should include script/style tags)
     */
    public function getHeadContent(): string;

    /**
     * Get HTML/JavaScript content to inject before the closing </body> tag.
     * Called only for active plugins on logged-in user pages.
     *
     * @return string HTML/JS content (should include script tags)
     */
    public function getFooterContent(): string;

    /**
     * Get the plugin help content for display in the UI.
     *
     * Returns an array with:
     * - 'summary': Brief help text (plain text or simple HTML)
     * - 'sections': Optional array of help sections with 'title' and 'content'
     * - 'links': Optional array of external links with 'label' and 'url'
     *
     * @return array Help content array
     */
    public function getHelp(): array;

    /**
     * Get client-side configuration for this plugin.
     *
     * Returns an array of settings to expose to JavaScript via window.CRM.plugins.{pluginId}
     * Only called for active plugins. Use this for config that client-side code needs.
     *
     * Example return value:
     *   ['enabled' => true, 'apiKey' => 'xyz', 'defaultImage' => 'mp']
     *
     * @return array Configuration key-value pairs for client-side use
     */
    public function getClientConfig(): array;

    /**
     * Get the settings schema for this plugin.
     *
     * Returns an array of setting definitions, each with at minimum:
     * - 'key': Setting key (without plugin prefix)
     * - 'label': Human-readable label
     * - 'type': Field type ('text', 'password', 'boolean', 'select')
     * - 'required' (optional): Whether the setting is required
     * - 'help' (optional): Help text shown below the field
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSettingsSchema(): array;

    /**
     * Get menu items to add to the navigation.
     *
     * Each item is an array with keys:
     * - 'parent': Parent menu key (e.g., 'admin', 'email', 'people')
     * - 'label': Display text
     * - 'url': Relative URL path
     * - 'icon': Optional FontAwesome icon class
     * - 'permission': Optional permission required
     *
     * @return array<int, array{parent: string, label: string, url: string, icon?: string, permission?: string}>
     */
    public function getMenuItems(): array;
}
