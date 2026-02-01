<?php

namespace ChurchCRM\Plugins\OpenLP;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Utils\LoggerUtils;

/**
 * OpenLP Integration Plugin.
 *
 * Sends alerts and notifications to OpenLP presentation software.
 * OpenLP is commonly used in churches for displaying lyrics and announcements.
 *
 * @see https://openlp.org/
 */
class OpenLPPlugin extends AbstractPlugin
{
    private ?string $serverUrl = null;

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
        return 'Display notifications on OpenLP presentation software.';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function boot(): void
    {
        $this->serverUrl = SystemConfig::getValue('sOLPURL');

        // Normalize URL
        if ($this->serverUrl !== null) {
            $this->serverUrl = rtrim($this->serverUrl, '/');
        }

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
        return !empty($this->serverUrl);
    }

    public function registerRoutes($routeCollector): void
    {
        $routeCollector->group('/openlp', function ($group) {
            $group->post('/alert', [$this, 'handleSendAlert']);
            $group->get('/status', [$this, 'handleGetStatus']);
        });
    }

    public function getMenuItems(): array
    {
        return [];
    }

    public function getSettingsSchema(): array
    {
        return [
            [
                'key' => 'sOLPURL',
                'label' => gettext('OpenLP Server URL'),
                'type' => 'text',
                'help' => gettext('e.g., http://192.168.1.100:4316'),
            ],
        ];
    }

    // =========================================================================
    // OpenLP API Methods
    // =========================================================================

    /**
     * Send an alert to OpenLP.
     *
     * @param string $message Alert message to display
     *
     * @return bool Success status
     */
    public function sendAlert(string $message): bool
    {
        if (!$this->isConfigured()) {
            $this->log('OpenLP not configured', 'warning');

            return false;
        }

        try {
            $url = $this->serverUrl . '/api/alert';
            $data = json_encode(['text' => $message]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data),
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 || $httpCode === 204) {
                $this->log("OpenLP alert sent: $message");

                return true;
            }

            $this->log("OpenLP alert failed with HTTP $httpCode: $response", 'warning');
        } catch (\Throwable $e) {
            $this->log('OpenLP exception: ' . $e->getMessage(), 'error');
        }

        return false;
    }

    /**
     * Check if OpenLP server is reachable.
     *
     * @return array Status information
     */
    public function getStatus(): array
    {
        if (!$this->isConfigured()) {
            return [
                'configured' => false,
                'reachable' => false,
                'message' => 'OpenLP URL not configured',
            ];
        }

        try {
            $url = $this->serverUrl . '/api/poll';

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);

                return [
                    'configured' => true,
                    'reachable' => true,
                    'message' => 'OpenLP server connected',
                    'data' => $data,
                ];
            }

            return [
                'configured' => true,
                'reachable' => false,
                'message' => "OpenLP server returned HTTP $httpCode",
            ];
        } catch (\Throwable $e) {
            return [
                'configured' => true,
                'reachable' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send a notification (used by notification system).
     *
     * @param string $title   Notification title
     * @param string $message Notification message
     *
     * @return bool Success status
     */
    public function sendNotification(string $title, string $message): bool
    {
        $fullMessage = $title . ': ' . $message;

        return $this->sendAlert($fullMessage);
    }

    // =========================================================================
    // API Route Handlers
    // =========================================================================

    public function handleSendAlert($request, $response): mixed
    {
        $body = $request->getParsedBody();
        $message = $body['message'] ?? null;

        if (empty($message)) {
            return $response->withJson([
                'success' => false,
                'message' => 'Message required',
            ], 400);
        }

        $success = $this->sendAlert($message);

        return $response->withJson([
            'success' => $success,
            'message' => $success ? 'Alert sent' : 'Failed to send alert',
        ]);
    }

    public function handleGetStatus($request, $response): mixed
    {
        $status = $this->getStatus();

        return $response->withJson([
            'success' => true,
            'data' => $status,
        ]);
    }
}
