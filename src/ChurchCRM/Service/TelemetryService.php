<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\VersionUtils;
use Monolog\LogRecord;

class TelemetryService
{
    // Hardcoded PostHog EU project credentials — not user-configurable.
    public const POSTHOG_KEY      = 'phc_vhMEHZai3exmp3sqqtJGyVY8Dfjn877bV7zQjV99sJZy';
    public const POSTHOG_ENDPOINT = 'https://eu.i.posthog.com';

    // Collection levels stored in sTelemetryLevel.
    public const LEVEL_NONE     = 'none';
    public const LEVEL_ERRORS   = 'errors';    // ERROR/CRITICAL + JS exceptions
    public const LEVEL_WARNINGS = 'warnings';  // WARNING+ + JS exceptions
    public const LEVEL_FULL     = 'full';      // warnings + page views + JS exceptions

    public static function getLevel(): string
    {
        $level = SystemConfig::getValue('sTelemetryLevel');
        return in_array($level, [self::LEVEL_ERRORS, self::LEVEL_WARNINGS, self::LEVEL_FULL], true)
            ? $level
            : self::LEVEL_NONE;
    }

    public static function isEnabled(): bool
    {
        return self::getLevel() !== self::LEVEL_NONE;
    }

    /** True only at 'full' level — page views are high-volume, opt-in extra. */
    public static function capturesPageViews(): bool
    {
        return self::getLevel() === self::LEVEL_FULL;
    }

    /**
     * Fire a page_view event. Only sent at LEVEL_FULL.
     * Pass the Slim route pattern (e.g. /people/person/{id}), never a raw URL.
     */
    public static function capturePageView(string $routePattern): void
    {
        if (!self::capturesPageViews()) {
            return;
        }
        self::capture('page_view', array_merge(self::baseProperties(), [
            'route' => $routePattern,
        ]));
    }

    /**
     * Forward a Monolog record as a log_error event.
     * Called by PostHogLogHandler — the handler pre-filters by Monolog level;
     * this method does the final level check and sends.
     */
    public static function captureLogEvent(LogRecord $record): void
    {
        if (!self::isEnabled()) {
            return;
        }

        // Strip query string so no record IDs reach PostHog.
        $rawUrl = $record->extra['url'] ?? '';
        $route  = $rawUrl !== '' ? explode('?', $rawUrl, 2)[0] : 'unknown';

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

    /** Properties attached to every server-side PostHog event. */
    private static function baseProperties(): array
    {
        return [
            '$lib'        => 'churchcrm-php',
            'crm_version' => VersionUtils::getInstalledVersion(),
            'locale'      => SystemConfig::getValue('sLanguage') ?: 'en_US',
            'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            'os_family'   => explode(' ', (string) php_uname('s'), 2)[0],
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

        $payload = json_encode([
            'api_key'     => self::POSTHOG_KEY,
            'event'       => $event,
            'distinct_id' => $distinctId,
            'properties'  => $properties,
        ]);

        try {
            $ch = curl_init(self::POSTHOG_ENDPOINT . '/capture/');
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
