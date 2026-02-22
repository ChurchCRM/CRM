<?php

namespace ChurchCRM\Plugins\Vonage;

use ChurchCRM\Plugin\AbstractPlugin;
use Vonage\Client;
use Vonage\Client\Credentials\Basic;
use Vonage\Client\Exception\Request as VonageRequestException;
use Vonage\Client\Exception\Server as VonageServerException;
use Vonage\SMS\Message\SMS;

/**
 * Vonage SMS Plugin.
 *
 * Provides SMS messaging capabilities using the Vonage API (formerly Nexmo):
 * - Send SMS notifications to members
 * - Kiosk check-in parent alerts
 * - Integration with the core notification system
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
        $this->apiKey = $this->getConfigValue('apiKey');
        $this->apiSecret = $this->getConfigValue('apiSecret');
        $this->fromNumber = $this->getConfigValue('fromNumber');

        if ($this->isConfigured()) {
            $this->initializeClient();
        }

        $this->log('Vonage SMS plugin booted', 'debug');
    }

    public function activate(): void
    {
        $this->log('Vonage SMS plugin activated', 'debug');
    }

    public function deactivate(): void
    {
        $this->log('Vonage SMS plugin deactivated', 'debug');
    }

    public function uninstall(): void
    {
        // Nothing to clean up
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->apiSecret) && !empty($this->fromNumber);
    }

    public function getMenuItems(): array
    {
        return [];
    }

    public function getSettingsSchema(): array
    {
        return [
            [
                'key'  => 'apiKey',
                'label' => gettext('Vonage API Key'),
                'type' => 'text',
                'help' => gettext('From dashboard.vonage.com'),
            ],
            [
                'key'  => 'apiSecret',
                'label' => gettext('Vonage API Secret'),
                'type' => 'password',
            ],
            [
                'key'  => 'fromNumber',
                'label' => gettext('From Phone Number'),
                'type' => 'text',
                'help' => gettext('Include country code, e.g. +14155551234 (US) or +447911123456 (UK)'),
            ],
        ];
    }

    // =========================================================================
    // Connection Testing (called by generic plugin management API)
    // =========================================================================

    /**
     * Test the connection using the settings supplied in the request body.
     *
     * The `apiSecret` field may be omitted when testing already-saved settings
     * (the password field is never returned to the browser); in that case the
     * currently stored secret is used as a fallback.
     *
     * Called by POST /plugins/api/plugins/vonage/test.
     *
     * @param array $settings Keys: apiKey, apiSecret (optional), fromNumber
     *
     * @return array{success: bool, message: string, details?: array<string, mixed>}
     */
    public function testWithSettings(array $settings): array
    {
        $apiKey     = $settings['apiKey']     ?? '';
        $apiSecret  = $settings['apiSecret']  ?? '';
        $fromNumber = $settings['fromNumber'] ?? '';

        // Fall back to the saved secret when the form omits it (password field behaviour)
        if (empty($apiSecret)) {
            $apiSecret = $this->apiSecret ?? '';
        }

        return $this->validateSettings($apiKey, $apiSecret, $fromNumber);
    }

    /**
     * Validate Vonage settings by making a real API call (account balance check).
     *
     * Accepts settings that may not yet be saved, so the admin can test before
     * committing. Uses the Vonage Account API to confirm credential validity —
     * simply constructing a client or SMS object does NOT verify credentials.
     *
     * @param string $apiKey     Vonage API Key
     * @param string $apiSecret  Vonage API Secret
     * @param string $fromNumber Sender phone number (any format; normalised internally)
     *
     * @return array{success: bool, message: string, details?: array<string, mixed>}
     */
    public function validateSettings(string $apiKey, string $apiSecret, string $fromNumber): array
    {
        // Require all three fields
        $missing = array_filter([
            empty($apiKey)     ? 'API Key'     : null,
            empty($apiSecret)  ? 'API Secret'  : null,
            empty($fromNumber) ? 'From Number' : null,
        ]);

        if (!empty($missing)) {
            return [
                'success' => false,
                'message' => sprintf(gettext('Missing required settings: %s'), implode(', ', $missing)),
            ];
        }

        // Normalise and structurally validate the from number
        $normalizedFrom = $this->formatPhoneNumberE164($fromNumber);
        if ($normalizedFrom === null) {
            return [
                'success' => false,
                'message' => sprintf(
                    gettext('Invalid "From Number": "%s". Include the country code, e.g. +14155551234 (US) or +447911123456 (UK).'),
                    $fromNumber
                ),
            ];
        }

        try {
            $credentials = new Basic($apiKey, $apiSecret);
            $testClient  = new Client($credentials);

            // account()->getBalance() is the lightest call that actually verifies credentials.
            // Constructing an SMS object alone makes no API call and proves nothing.
            $balance = $testClient->account()->getBalance();

            $this->log('Vonage settings validated successfully', 'info', [
                'apiKey'      => substr($apiKey, 0, 4) . '****',
                'fromNumber'  => $normalizedFrom,
                'balance'     => $balance->getBalance(),
            ]);

            return [
                'success' => true,
                'message' => sprintf(
                    gettext('Connected! Account balance: €%.2f. Ready to send SMS.'),
                    $balance->getBalance()
                ),
                'details' => [
                    'from_number' => $normalizedFrom,
                    'balance'     => $balance->getBalance(),
                    'auto_reload' => $balance->getAutoReload(),
                ],
            ];
        } catch (VonageRequestException $e) {
            // 4xx from Vonage API — almost always bad credentials
            $this->log('Vonage settings validation failed (auth): ' . $e->getMessage(), 'error');

            return [
                'success' => false,
                'message' => gettext('Invalid API credentials. Please verify your API Key and API Secret.'),
            ];
        } catch (VonageServerException $e) {
            $this->log('Vonage settings validation failed (server): ' . $e->getMessage(), 'error');

            return [
                'success' => false,
                'message' => gettext('Vonage server error. Please try again later.'),
            ];
        } catch (\Throwable $e) {
            $this->log('Vonage settings validation failed: ' . $e->getMessage(), 'error');

            return [
                'success' => false,
                'message' => sprintf(gettext('Validation failed: %s'), $e->getMessage()),
            ];
        }
    }

    // =========================================================================
    // Phone Number Formatting & Validation
    // =========================================================================

    /**
     * Normalise a phone number to E.164 format (+[country][number]).
     *
     * Strips all formatting characters (spaces, dashes, parentheses, dots)
     * and prepends '+' if not already present. No country code is assumed —
     * the number must already include one. Returns null if the result is not
     * a structurally valid E.164 number (7–15 digits, non-zero first digit).
     *
     * Examples:
     * - "+14155551234"    -> "+14155551234"  (US, already E.164)
     * - "14155551234"     -> "+14155551234"  (US, digits with country code)
     * - "+44 7911 123456" -> "+447911123456" (UK, formatted)
     * - "447911 123 456"  -> "+447911123456" (UK, partial formatting)
     * - "555-1234"        -> null            (too short / no country code)
     *
     * @param string $phone Raw phone number in any format
     *
     * @return string|null Normalised E.164 number, or null if structurally invalid
     */
    public function formatPhoneNumberE164(string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Strip everything except digits
        $numeric = preg_replace('/\D/', '', $phone);

        // E.164 allows 7–15 digits total (country code + subscriber number)
        if (empty($numeric) || !preg_match('/^\d{7,15}$/', $numeric)) {
            return null;
        }

        // Detect NANP (North American Numbering Plan) numbers stored without a country code.
        // 10 digits → US/Canada number missing the leading 1 (e.g. 3851415437 = Utah area code 385)
        // 11 digits starting with 1 → US/Canada number with country code already present
        if (strlen($numeric) === 10) {
            $formatted = '+1' . $numeric;
        } elseif (strlen($numeric) === 11 && $numeric[0] === '1') {
            $formatted = '+' . $numeric;
        } else {
            $formatted = '+' . $numeric;
        }

        return $this->isValidE164($formatted) ? $formatted : null;
    }

    /**
     * Return true if $phone is a structurally valid E.164 number.
     *
     * E.164: '+' followed by 7–15 digits, first digit non-zero.
     */
    public function isValidE164(string $phone): bool
    {
        return preg_match('/^\+[1-9]\d{6,14}$/', $phone) === 1;
    }

    // =========================================================================
    // SMS Sending
    // =========================================================================

    /**
     * Initialise the Vonage client with saved credentials.
     */
    private function initializeClient(): void
    {
        try {
            $credentials  = new Basic($this->apiKey, $this->apiSecret);
            $this->client = new Client($credentials);
        } catch (\Throwable $e) {
            $this->log('Failed to initialise Vonage client: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Send a single SMS message.
     *
     * The SDK's send() method automatically retries on HTTP 429 (ThrottleException),
     * so no retry logic is needed here.
     *
     * @param string $to      Recipient phone number (any format; normalised internally)
     * @param string $message Message text
     *
     * @return bool True if Vonage accepted the message (status 0)
     *
     * @see https://developer.vonage.com/en/messaging/sms/code-snippets/send-an-sms
     */
    public function sendSMS(string $to, string $message): bool
    {
        if (!$this->isConfigured() || $this->client === null) {
            $this->log('SMS not configured or client not initialised', 'warning');

            return false;
        }

        $formattedTo = $this->isValidE164($to) ? $to : $this->formatPhoneNumberE164($to);
        if ($formattedTo === null) {
            $this->log('SMS not sent: invalid phone number', 'warning', ['to' => $to]);

            return false;
        }

        try {
            $sms      = new SMS($formattedTo, $this->fromNumber, $message);
            $response = $this->client->sms()->send($sms);

            // Collection iterates over message parts (usually just one for short messages)
            $sent = $response->current();
            if ($sent->getStatus() === 0) {
                $this->log("SMS sent to $formattedTo", 'info', [
                    'messageId'        => $sent->getMessageId(),
                    'remainingBalance' => $sent->getRemainingBalance(),
                    'network'          => $sent->getNetwork(),
                ]);

                return true;
            }

            $this->log('SMS delivery failed', 'warning', [
                'to'        => $formattedTo,
                'status'    => $sent->getStatus(),
                'messageId' => $sent->getMessageId(),
            ]);
        } catch (VonageRequestException $e) {
            // 4xx — bad number, blocked, etc.
            $this->log('SMS rejected: ' . $e->getMessage(), 'error', ['to' => $formattedTo]);
        } catch (VonageServerException $e) {
            // 5xx — Vonage side issue
            $this->log('Vonage server error sending SMS: ' . $e->getMessage(), 'error', ['to' => $formattedTo]);
        } catch (\Throwable $e) {
            $this->log('SMS send exception: ' . $e->getMessage(), 'error', [
                'to'        => $formattedTo,
                'exception' => get_class($e),
            ]);
        }

        return false;
    }

    /**
     * Send the same message to multiple recipients.
     *
     * @param string[] $recipients Phone numbers in any format
     * @param string   $message    Message text
     *
     * @return array<string, bool> Keyed by phone number → success
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
     * Convenience wrapper used by the core Notification system.
     *
     * Prepends $title to $message and truncates to 160 chars (single SMS segment).
     */
    public function sendNotification(string $to, string $title, string $message): bool
    {
        $fullMessage = $title . ': ' . $message;

        if (strlen($fullMessage) > 160) {
            $fullMessage = substr($fullMessage, 0, 157) . '...';
        }

        return $this->sendSMS($to, $fullMessage);
    }
}
