<?php

namespace ChurchCRM\Plugins\Vonage;

use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Utils\LoggerUtils;
use Vonage\Client;
use Vonage\Client\Credentials\Basic;
use Vonage\SMS\Message\SMS;

/**
 * Vonage SMS Plugin.
 *
 * Provides SMS messaging capabilities using the Vonage API (formerly Nexmo):
 * - Send SMS notifications to members
 * - Bulk SMS for event reminders
 * - Integration with notification system
 *
 * Requires vonage/client ^4.3.0 (PHP 8.1+)
 *
 * @see https://developer.vonage.com/messaging/sms/overview
 * @see https://github.com/Vonage/vonage-php-sdk-core
 */
class VonagePlugin extends AbstractPlugin
{
    private ?Client $client = null;
    private ?string $apiKey = null;
    private ?string $apiSecret = null;
    private ?string $fromNumber = null;

    public function getId(): string
    {
        return 'vonage';
    }

    public function getName(): string
    {
        return 'Vonage SMS';
    }

    public function getDescription(): string
    {
        return 'Send SMS notifications using Vonage API.';
    }

    public function boot(): void
    {
        // Load configuration using sandboxed config access
        $this->apiKey = $this->getConfigValue('apiKey');
        $this->apiSecret = $this->getConfigValue('apiSecret');
        $this->fromNumber = $this->getConfigValue('fromNumber');

        // Initialize Vonage client if configured
        if ($this->isConfigured()) {
            $this->initializeClient();
        }

        $this->log('Vonage SMS plugin booted');
    }

    public function activate(): void
    {
        $this->log('Vonage SMS plugin activated');
    }

    public function deactivate(): void
    {
        $this->log('Vonage SMS plugin deactivated');
    }

    public function uninstall(): void
    {
        // Nothing to clean up
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->apiSecret) && !empty($this->fromNumber);
    }

    public function registerRoutes($routeCollector): void
    {
        $routeCollector->group('/sms', function ($group) {
            $group->post('/send', [$this, 'handleSendSMS']);
            $group->post('/send-bulk', [$this, 'handleSendBulkSMS']);
        });
    }

    public function getMenuItems(): array
    {
        // SMS doesn't need menu items - it's used via notification system
        return [];
    }

    public function getSettingsSchema(): array
    {
        return [
            [
                'key' => 'apiKey',
                'label' => gettext('Vonage API Key'),
                'type' => 'text',
                'help' => gettext('From dashboard.vonage.com'),
            ],
            [
                'key' => 'apiSecret',
                'label' => gettext('Vonage API Secret'),
                'type' => 'password',
            ],
            [
                'key' => 'fromNumber',
                'label' => gettext('From Phone Number'),
                'type' => 'text',
                'help' => gettext('E.164 format, e.g., +14155551234'),
            ],
        ];
    }

    // =========================================================================
    // SMS Methods
    // =========================================================================

    /**
     * Initialize the Vonage client.
     *
     * Uses Basic authentication with API Key and Secret.
     *
     * @see https://github.com/Vonage/vonage-php-sdk-core#usage
     */
    private function initializeClient(): void
    {
        try {
            $credentials = new Basic($this->apiKey, $this->apiSecret);
            $this->client = new Client($credentials);
        } catch (\Throwable $e) {
            $this->log('Failed to initialize Vonage client: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Send an SMS message using the Vonage SMS API.
     *
     * Uses Vonage\SMS\Message\SMS class for constructing the message
     * and Vonage\SMS\Client for sending.
     *
     * @param string $to      Recipient phone number (E.164 format, e.g., +14155551234)
     * @param string $message Message content (max 160 chars for single SMS)
     *
     * @return bool Success status
     *
     * @see https://developer.vonage.com/messaging/sms/code-snippets/send-an-sms
     */
    public function sendSMS(string $to, string $message): bool
    {
        if (!$this->isConfigured() || $this->client === null) {
            $this->log('SMS not configured or client not initialized', 'warning');

            return false;
        }

        try {
            // Create SMS message object
            // Vonage SDK v4.x: SMS(to, from, message)
            $sms = new SMS($to, $this->fromNumber, $message);

            // Send via the SMS client
            $response = $this->client->sms()->send($sms);

            // Check response - Collection of SentSMS objects
            $sentMessage = $response->current();
            if ($sentMessage->getStatus() === 0) {
                $this->log("SMS sent successfully to $to", 'info', [
                    'messageId' => $sentMessage->getMessageId(),
                    'remainingBalance' => $sentMessage->getRemainingBalance(),
                ]);

                return true;
            }

            $this->log('SMS delivery failed', 'warning', [
                'to' => $to,
                'status' => $sentMessage->getStatus(),
            ]);
        } catch (\Throwable $e) {
            $this->log('SMS exception: ' . $e->getMessage(), 'error', [
                'to' => $to,
                'exception' => get_class($e),
            ]);
        }

        return false;
    }

    /**
     * Send SMS to multiple recipients.
     *
     * @param array  $recipients Array of phone numbers in E.164 format
     * @param string $message    Message content
     *
     * @return array<string, bool> Results per recipient phone number
     */
    public function sendBulkSMS(array $recipients, string $message): array
    {
        $results = [];

        foreach ($recipients as $phone) {
            $results[$phone] = $this->sendSMS($phone, $message);
        }

        return $results;
    }

    /**
     * Send a notification (used by notification system).
     *
     * @param string $to      Phone number in E.164 format
     * @param string $title   Notification title (will be prepended to message)
     * @param string $message Notification message
     *
     * @return bool Success status
     */
    public function sendNotification(string $to, string $title, string $message): bool
    {
        $fullMessage = $title . ': ' . $message;

        // Truncate to SMS length limit (160 chars for single SMS)
        if (strlen($fullMessage) > 160) {
            $fullMessage = substr($fullMessage, 0, 157) . '...';
        }

        return $this->sendSMS($to, $fullMessage);
    }

    // =========================================================================
    // API Route Handlers
    // =========================================================================

    /**
     * Handle single SMS send request.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface      $response
     *
     * @return mixed Response with JSON
     */
    public function handleSendSMS($request, $response): mixed
    {
        $body = $request->getParsedBody();
        $to = $body['to'] ?? null;
        $message = $body['message'] ?? null;

        if (empty($to) || empty($message)) {
            return $response->withJson([
                'success' => false,
                'message' => gettext('Phone number and message required'),
            ], 400);
        }

        $success = $this->sendSMS($to, $message);

        return $response->withJson([
            'success' => $success,
            'message' => $success ? gettext('SMS sent') : gettext('Failed to send SMS'),
        ]);
    }

    /**
     * Handle bulk SMS send request.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface      $response
     *
     * @return mixed Response with JSON
     */
    public function handleSendBulkSMS($request, $response): mixed
    {
        $body = $request->getParsedBody();
        $recipients = $body['recipients'] ?? [];
        $message = $body['message'] ?? null;

        if (empty($recipients) || empty($message)) {
            return $response->withJson([
                'success' => false,
                'message' => gettext('Recipients and message required'),
            ], 400);
        }

        $results = $this->sendBulkSMS($recipients, $message);
        $successCount = count(array_filter($results));

        return $response->withJson([
            'success' => $successCount > 0,
            'data' => [
                'total' => count($recipients),
                'sent' => $successCount,
                'failed' => count($recipients) - $successCount,
                'details' => $results,
            ],
        ]);
    }
}
