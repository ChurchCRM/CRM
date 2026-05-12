<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\LoggerUtils;
use Monolog\LogRecord;

class TelemetryService
{
    public static function isEnabled(): bool
    {
        return SystemConfig::getBooleanValue('bEnableTelemetry');
    }

    /**
     * Fire a page_view event. Pass the Slim route pattern (e.g. /people/person/{id}),
     * never a raw URL, so no record IDs reach PostHog.
     */
    public static function capturePageView(string $routePattern): void
    {
        if (!self::isEnabled()) {
            return;
        }
        self::capture('page_view', array_merge(self::baseProperties(), [
            'route' => $routePattern,
        ]));
    }

    /**
     * Forward a Monolog WARNING+ record as a log_error event.
     * Called by PostHogLogHandler.
     */
    public static function captureLogEvent(LogRecord $record): void
    {
        if (!self::isEnabled()) {
            return;
        }

        // Strip query string from the URL stored in extra so no record IDs leak.
        $rawUrl = $record->extra['url'] ?? '';
        $route  = $rawUrl !== '' ? strtok($rawUrl, '?') : 'unknown';

        $properties = array_merge(self::baseProperties(), [
            'level'          => $record->level->name,
            'message'        => $record->message,
            'channel'        => $record->channel,
            'correlation_id' => $record->extra['correlation_id'] ?? null,
            'route'          => $route,
            // remote_ip is intentionally excluded — PII
        ]);

        self::capture('log_error', $properties);
    }

    /**
     * Properties attached to every server-side PostHog event.
     */
    private static function baseProperties(): array
    {
        return [
            '$lib'        => 'churchcrm-php',
            'crm_version' => \ChurchCRM\Utils\VersionUtils::getInstalledVersion(),
            'locale'      => SystemConfig::getValue('sLanguage') ?: 'en_US',
            'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            'os_family'   => strtok((string) php_uname('s'), ' '),
        ];
    }

    /**
     * Fire-and-forget HTTP POST to PostHog /capture.
     * 1-second timeout so a PostHog outage never slows down page loads.
     */
    private static function capture(string $event, array $properties): void
    {
        $distinctId = SystemConfig::getValue('sSystemID');
        if ($distinctId === '') {
            return;
        }

        $endpoint = rtrim(SystemConfig::getValue('sPostHogEndpoint') ?: 'https://eu.i.posthog.com', '/');
        $apiKey   = SystemConfig::getValue('sPostHogKey');
        if ($apiKey === '') {
            return;
        }

        $payload = json_encode([
            'api_key'     => $apiKey,
            'event'       => $event,
            'distinct_id' => $distinctId,
            'properties'  => $properties,
        ]);

        try {
            $ch = curl_init($endpoint . '/capture/');
            if ($ch === false) {
                return;
            }
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 1,
                CURLOPT_NOSIGNAL       => 1,
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            error_log('TelemetryService::capture failed: ' . $e->getMessage());
        }
    }
}
