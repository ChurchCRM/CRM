<?php

namespace ChurchCRM\Plugins\OpenLP;

use ChurchCRM\Plugin\AbstractPlugin;

/**
 * OpenLP Integration Plugin.
 *
 * A config-only plugin that enables OpenLP integration in ChurchCRM.
 * The actual OpenLP communication is handled by OpenLPNotification class
 * which is used by the core Notification system.
 *
 * Currently used for:
 * - Kiosk check-in notifications: When a child is checked into Sunday School,
 *   an alert can be sent to the OpenLP projector display.
 *
 * This plugin exposes:
 * - enabled: Whether OpenLP is enabled
 * - serverUrl: URL to the OpenLP server
 * - username: Optional authentication username
 * - password: Optional authentication password
 *
 * @see ChurchCRM\Plugins\OpenLP\OpenLPNotification for the actual API integration
 * @see ChurchCRM\dto\Notification for the core notification orchestration
 * @see https://openlp.org/
 */
class OpenLPPlugin extends AbstractPlugin
{
    public function getId(): string
    {
        return 'openlp';
    }

    public function getName(): string
    {
        return 'OpenLP Integration';
    }

    public function getDescription(): string
    {
        return 'Display notifications on OpenLP presentation software during worship services.';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function boot(): void
    {
        $this->log('OpenLP plugin booted');
    }

    public function activate(): void
    {
        $this->log('OpenLP plugin activated');
    }

    public function deactivate(): void
    {
        $this->log('OpenLP plugin deactivated');
    }

    public function uninstall(): void
    {
        // Nothing to clean up
    }

    public function isConfigured(): bool
    {
        $serverUrl = $this->getConfigValue('serverUrl');
        return !empty($serverUrl);
    }

    public function registerRoutes($routeCollector): void
    {
        // No custom routes - OpenLP communication handled by OpenLPNotification class
    }

    public function getMenuItems(): array
    {
        return [];
    }

    public function getSettingsSchema(): array
    {
        return [
            [
                'key' => 'serverUrl',
                'label' => gettext('OpenLP Server URL'),
                'type' => 'text',
                'required' => true,
                'help' => gettext('URL to your OpenLP server (e.g., http://192.168.1.100:4316)'),
            ],
            [
                'key' => 'username',
                'label' => gettext('Username'),
                'type' => 'text',
                'help' => gettext('Optional - only required if OpenLP authentication is enabled'),
            ],
            [
                'key' => 'password',
                'label' => gettext('Password'),
                'type' => 'password',
                'help' => gettext('Optional - only required if OpenLP authentication is enabled'),
            ],
        ];
    }

    /**
     * Get the configured OpenLP server URL.
     */
    public function getServerUrl(): string
    {
        $url = $this->getConfigValue('serverUrl');
        return $url ? rtrim($url, '/') : '';
    }

    /**
     * Get the configured username for OpenLP authentication.
     */
    public function getUsername(): string
    {
        return $this->getConfigValue('username');
    }

    /**
     * Get the configured password for OpenLP authentication.
     */
    public function getPassword(): string
    {
        return $this->getConfigValue('password');
    }

    /**
     * Get client-side configuration for this plugin.
     * This is exposed to JavaScript via window.CRM.plugins.openlp
     *
     * @return array Configuration for client-side use
     */
    public function getClientConfig(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'configured' => $this->isConfigured(),
            // Don't expose serverUrl/credentials to client for security
        ];
    }

    /**
     * Send an alert to OpenLP projector.
     *
     * This is the main entry point for sending projector notifications.
     * Currently called by the Notification class when processing kiosk
     * check-in events (e.g., child checked into Sunday School).
     *
     * @param string $text The alert text to display
     *
     * @return string Response from OpenLP server
     *
     * @throws \RuntimeException If the plugin is not configured or request fails
     */
    public function sendAlert(string $text): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('OpenLP plugin is not configured');
        }

        $notification = new OpenLPNotification(
            $this->getServerUrl(),
            $this->getUsername(),
            $this->getPassword()
        );
        $notification->setAlertText($text);

        return $notification->send();
    }
}
