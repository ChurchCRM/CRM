<?php

namespace ChurchCRM\Plugins\OpenLP;

use ChurchCRM\Utils\MiscUtils;

/**
 * OpenLPNotification sends alert messages to OpenLP presentation software.
 *
 * Uses OpenLP API v2 endpoint: POST /api/v2/plugins/alerts
 * API Documentation: https://gitlab.com/openlp/openlp/-/tree/master/openlp/core/api/versions/v2
 *
 * Note: OpenLP removed API v1 in January 2026. This class uses the current v2 API
 * which requires JSON body with {"text":"message"} format.
 */
class OpenLPNotification
{
    protected string $serverUrl;
    protected string $username;
    protected string $password;
    protected string $alertText = '';
    protected bool $allowSelfSigned = false;

    public function __construct(string $serverUrl, string $username, string $password, bool $allowSelfSigned = false)
    {
        // Remove trailing slash if present
        $this->serverUrl = rtrim($serverUrl, '/');
        $this->username = $username;
        $this->password = $password;
        $this->allowSelfSigned = $allowSelfSigned;

        // Validate URL to prevent SSRF (GHSA-hrfr-xg9w-hjm4)
        $this->validateServerUrl($this->serverUrl);
    }

    /**
     * Validate that a URL hostname can be resolved (basic SSRF mitigation).
     *
     * @param string $url The URL to validate
     * @throws \RuntimeException If URL format is invalid or hostname cannot be resolved
     */
    private function validateServerUrl(string $url): void
    {
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            throw new \RuntimeException('Invalid OpenLP server URL format');
        }

        $host = $parsed['host'];
        $scheme = $parsed['scheme'] ?? '';

        // Only allow http and https
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \RuntimeException('Invalid OpenLP server URL scheme (only http/https allowed)');
        }

        // If it's an IP address, accept it as-is
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return;
        }

        // For hostnames, verify they can be resolved
        $ip = @gethostbyname($host);
        if ($ip === $host) {
            throw new \RuntimeException('Unable to resolve OpenLP server hostname');
        }
    }

    public function setAlertText(string $text): void
    {
        $this->alertText = $text;
    }

    private function getAuthorizationHeader(): string
    {
        return 'Basic ' . base64_encode($this->username . ':' . $this->password);
    }

    /**
     * Send an alert to OpenLP using API v2.
     *
     * @return string The response from the OpenLP server
     * @throws \RuntimeException If the request fails
     */
    public function send(): string
    {
        // OpenLP API v2 uses POST /api/v2/plugins/alerts with JSON body
        $url = $this->serverUrl . '/api/v2/plugins/alerts';

        $payload = json_encode(['text' => $this->alertText]);

        $headers = [
            'http' => [
                'method'  => 'POST',
                'timeout' => 5,
                'header'  =>"Content-Type: application/json\r\n" .
"Accept: application/json\r\n",
                'content' => $payload,
            ],
        ];

        // Only disable TLS verification if explicitly configured for self-signed certs
        // Default: verification enabled for security
        if ($this->allowSelfSigned) {
            $headers['ssl'] = [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ];
        }

        // Add Basic Auth if credentials are configured
        if (!empty($this->username)) {
            $headers['http']['header'] .= 'Authorization: ' . $this->getAuthorizationHeader() ."\r\n";
        }

        $context = stream_context_create($headers);
        $response = file_get_contents($url, false, $context);

        MiscUtils::throwIfFailed($response);

        // API v2 returns empty body with 204 No Content on success
        return $response !== false ? $response : '';
    }
}
