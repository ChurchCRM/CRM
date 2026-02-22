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

    // Note: getVersion() is inherited from AbstractPlugin and reads from plugin.json

    public function boot(): void
    {
        $this->log('OpenLP plugin booted', 'debug');
    }

    public function activate(): void
    {
        $this->log('OpenLP plugin activated', 'debug');
    }

    public function deactivate(): void
    {
        $this->log('OpenLP plugin deactivated', 'debug');
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
            [
                'key' => 'allowSelfSigned',
                'label' => gettext('Allow Self-Signed Certificates'),
                'type' => 'boolean',
                'default' => false,
                'help' => gettext('Enable this only for local network servers with self-signed SSL certificates. Disables certificate verification.'),
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
     * Check if self-signed certificates are allowed.
     * Only enable for local network servers with self-signed certs.
     */
    public function getAllowSelfSigned(): bool
    {
        return $this->getBooleanConfigValue('allowSelfSigned');
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
     * Test OpenLP connectivity using the provided settings.
     *
     * Makes a lightweight GET request to /api/v2/core/version without
     * sending a visible alert to the projector screen.
     * Falls back to saved password when the field is omitted.
     *
     * {@inheritdoc}
     */
    public function testWithSettings(array $settings): array
    {
        $serverUrl = rtrim($settings['serverUrl'] ?? $this->getConfigValue('serverUrl'), '/');
        $username = $settings['username'] ?? $this->getConfigValue('username');

        $password = $settings['password'] ?? '';
        if (empty($password)) {
            $password = $this->getConfigValue('password');
        }

        $allowSelfSigned = isset($settings['allowSelfSigned'])
            ? filter_var($settings['allowSelfSigned'], FILTER_VALIDATE_BOOLEAN)
            : $this->getAllowSelfSigned();

        if (empty($serverUrl)) {
            return ['success' => false, 'message' => gettext('Server URL is required.')];
        }

        if (!filter_var($serverUrl, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => gettext('Invalid server URL format.')];
        }

        try {
            $url = $serverUrl . '/api/v2/core/version';

            $httpOptions = [
                'method'        => 'GET',
                'timeout'       => 5,
                'header'        => "Accept: application/json\r\n",
                'ignore_errors' => true,
            ];

            if (!empty($username)) {
                $auth = 'Basic ' . base64_encode($username . ':' . $password);
                $httpOptions['header'] .= "Authorization: $auth\r\n";
            }

            $contextOptions = ['http' => $httpOptions];

            if ($allowSelfSigned) {
                $contextOptions['ssl'] = [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ];
            }

            $context  = stream_context_create($contextOptions);
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                return [
                    'success' => false,
                    'message' => sprintf(
                        gettext('Cannot connect to OpenLP at %s. Check the server URL and that OpenLP is running.'),
                        $serverUrl
                    ),
                ];
            }

            $data    = json_decode($response, true);
            $version = $data['version'] ?? null;

            if ($version !== null) {
                return [
                    'success' => true,
                    'message' => sprintf(gettext('Connected to OpenLP! Version: %s'), $version),
                    'details' => ['version' => $version],
                ];
            }

            // Server responded but no version in body — still reachable
            return [
                'success' => true,
                'message' => gettext('Connected to OpenLP server successfully.'),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => gettext('Failed to connect to OpenLP server.'),
            ];
        }
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
            $this->getPassword(),
            $this->getAllowSelfSigned()
        );
        $notification->setAlertText($text);

        return $notification->send();
    }
}
