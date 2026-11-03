<?php

namespace ChurchCRM\Service;

use ChurchCRM\data\Countries;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ConfigQuery;
use ChurchCRM\Utils\VersionUtils;
use Monolog\LogRecord;
use Propel\Runtime\Propel;

class TelemetryService
{
    // Hardcoded PostHog project credentials — not user-configurable.
    public const POSTHOG_KEY      = 'phc_vhMEHZai3exmp3sqqtJGyVY8Dfjn877bV7zQjV99sJZy';
    public const POSTHOG_ENDPOINT = 'https://us.i.posthog.com';
    public const POSTHOG_CAPTURE_ENDPOINT = self::POSTHOG_ENDPOINT . '/i/v0/e/';
    private const FAILURE_DISTINCT_ID = 'churchcrm-aggregate-failures';

    // Collection levels stored in sTelemetryLevel.
    public const LEVEL_NONE     = 'none';
    public const LEVEL_ERRORS   = 'errors';    // ERROR/CRITICAL + JS exceptions
    public const LEVEL_WARNINGS = 'warnings';  // WARNING+ + JS exceptions
    public const LEVEL_FULL     = 'full';      // warnings + page views + JS exceptions

    private static array $installProfileCache = [];

    public static function resolveInstallProfile(string $storedProfile, bool $testOverride): string
    {
        if ($testOverride) {
            return 'test';
        }

        return in_array($storedProfile, ['production', 'demo', 'test'], true) ? $storedProfile : 'production';
    }

    public static function installProfile(): string
    {
        $testOverride = getenv('CHURCHCRM_DISABLE_POSTHOG_TELEMETRY') === '1' || getenv('PHP_TEST_DISPOSABLE_DB') === '1';
        try {
            $storedProfile = SystemConfig::isInitialized() ? SystemConfig::getValue('sInstallDataProfile') : '';
        } catch (\Throwable) {
            $storedProfile = '';
        }
        $storedProfile = is_string($storedProfile) ? $storedProfile : '';

        // Config can initialize during setup or change during a demo import in this request.
        $key = ($testOverride ? 'test:' : 'stored:') . $storedProfile;
        return self::$installProfileCache[$key] ??= self::resolveInstallProfile($storedProfile, $testOverride);
    }

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

    /** Record lifecycle milestones since telemetry was enabled. */
    public static function syncLifecycle(?callable $send = null): void
    {
        $level = self::getLevel();
        if ($level === self::LEVEL_NONE) {
            return;
        }

        $systemId = SystemConfig::getValue('sSystemID');
        if ($systemId === '') {
            return;
        }

        $connection = null;
        if ($send === null) {
            try {
                $connection = Propel::getConnection();
                $lockName = 'churchcrm-telemetry-' . substr(sha1($systemId), 0, 40);
                $lock = $connection->prepare('SELECT GET_LOCK(?, 0)');
                $lock->execute([$lockName]);
                if ((int) $lock->fetchColumn() !== 1) {
                    return;
                }
            } catch (\Throwable $e) {
                error_log('TelemetryService::syncLifecycle lock failed: ' . $e->getMessage());
                return;
            }
        }

        try {
            $currentVersion = VersionUtils::getInstalledVersion();
            $coveredVersion = $send === null
                ? (ConfigQuery::create()->findOneByName('sTelemetryLifecycleVersion')?->getValue() ?? '')
                : SystemConfig::getValue('sTelemetryLifecycleVersion');
            $lifecycleEvent = self::buildLifecycleEvent($level, $systemId, $coveredVersion, $currentVersion);
            if ($lifecycleEvent === null) {
                return;
            }

            $acknowledged = ($send ?? self::capture(...))($lifecycleEvent['event'], $lifecycleEvent['properties']);
            if ($acknowledged === false) {
                return;
            }
            SystemConfig::setValue('sTelemetryLifecycleVersion', $currentVersion);
        } finally {
            if ($connection !== null) {
                try {
                    $release = $connection->prepare('SELECT RELEASE_LOCK(?)');
                    $release->execute([$lockName]);
                } catch (\Throwable $e) {
                    error_log('TelemetryService::syncLifecycle lock release failed: ' . $e->getMessage());
                }
            }
        }
    }

    /** Build a lifecycle event only when an opted-in install has uncovered version state. */
    public static function buildLifecycleEvent(string $level, string $systemId, string $coveredVersion, string $currentVersion): ?array
    {
        if ($level === self::LEVEL_NONE || $systemId === '' || $coveredVersion === $currentVersion) {
            return null;
        }

        if ($coveredVersion === '') {
            return ['event' => 'install_observed', 'properties' => self::baseProperties()];
        }

        return [
            'event' => 'upgrade_completed',
            'properties' => array_merge(self::baseProperties(), [
                'from_version' => $coveredVersion,
                'to_version' => $currentVersion,
            ]),
        ];
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
            'route' => self::normalizePageRoute($routePattern, SystemURLs::getRootPath()),
        ]));
    }

    private static function normalizePageRoute(string $route, string $rootPath): string
    {
        $path = parse_url($route, PHP_URL_PATH);
        $route = is_string($path) && $path !== '' ? $path : '/';
        $rootPath = rtrim($rootPath, '/');

        if ($rootPath !== '' && ($route === $rootPath || str_starts_with($route, $rootPath . '/'))) {
            $route = substr($route, strlen($rootPath)) ?: '/';
        }

        return $route;
    }

    public static function captureUpgradeSqlDuration(string $version, int $durationMs, bool $completed): void
    {
        if (!self::isEnabled()) {
            return;
        }

        self::capture($completed ? 'upgrade_sql_completed' : 'upgrade_sql_failed', array_merge(self::baseProperties(), [
            'upgrade_version' => $version,
            'duration_ms' => $durationMs,
        ]));
    }

    public static function captureUpgradeSqlError(string $version, int $durationMs, string $databaseError): void
    {
        $databaseError = self::sanitizeDatabaseError($databaseError);

        self::capture('upgrade_sql_failed', array_merge(self::baseProperties(), [
            'upgrade_version' => $version,
            'duration_ms' => $durationMs,
            'database_error' => $databaseError,
            '$process_person_profile' => false,
        ]), self::FAILURE_DISTINCT_ID);
    }

    public static function captureInstallFailure(string $errorClass, ?string $databaseError = null): void
    {
        $properties = array_merge(self::baseProperties(), ['error_class' => $errorClass]);
        if ($databaseError !== null) {
            $properties['database_error'] = self::sanitizeDatabaseError($databaseError);
        }
        $properties['$process_person_profile'] = false;
        self::capture('install_failed', $properties, self::FAILURE_DISTINCT_ID);
    }

    public static function captureUpgradeFailure(string $version, string $errorClass): void
    {
        self::capture('upgrade_failed', array_merge(self::baseProperties(), [
            'upgrade_version' => $version,
            'error_class' => $errorClass,
            '$process_person_profile' => false,
        ]), self::FAILURE_DISTINCT_ID);
    }

    public static function captureUpgradeInterruption(string $version, int $elapsedMs): void
    {
        self::capture('upgrade_script_interrupted', array_merge(self::baseProperties(), [
            'upgrade_version' => $version,
            'elapsed_ms' => $elapsedMs,
            '$process_person_profile' => false,
        ]), self::FAILURE_DISTINCT_ID);
    }

    /**
     * Forward a Monolog record as a log_error event.
     * PostHogLogHandler filters by level before message scrubbing.
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
            'message'        => self::scrubLogMessage($record->message),
            'channel'        => $record->channel,
            'correlation_id' => $record->extra['correlation_id'] ?? null,
            'route'          => $route,
            // remote_ip is intentionally excluded — PII
        ]);

        self::capture('log_error', $properties);
    }

    public static function scrubLogMessage(string $message): string
    {
        $message = preg_replace('/SQLSTATE\[[^\]]*\].*$/s', '[database error]', $message) ?? '';
        $message = preg_replace('/[A-Z0-9.!#$%&\x27*+\/=?^_`{|}~-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $message) ?? '';

        return mb_substr($message, 0, 500);
    }

    /** Properties attached to every server-side PostHog event. */
    private static function baseProperties(): array
    {
        $properties = [
            '$lib'        => 'churchcrm-php',
            '$geoip_disable' => true,
            'crm_version' => VersionUtils::getInstalledVersion(),
            'install_profile' => self::installProfile(),
            'locale'      => SystemConfig::isInitialized() ? (SystemConfig::getValue('sLanguage') ?: 'en_US') : 'en_US',
            'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            'os_family'   => explode(' ', (string) php_uname('s'), 2)[0],
            'install_path_type' => empty(SystemURLs::getRootPath()) ? 'root' : 'subdirectory',
        ];

        $churchCountry = self::churchCountry();
        if ($churchCountry !== null) {
            $properties['church_country'] = $churchCountry;
        }

        return $properties;
    }

    /** The church's self-declared country (sChurchCountry only), or null when empty or not a known ISO code. */
    private static function churchCountry(): ?string
    {
        try {
            $country = SystemConfig::isInitialized() ? Countries::toISO((string) SystemConfig::getValue('sChurchCountry')) : '';

            return array_key_exists($country, Countries::getNames()) ? $country : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function sanitizeDatabaseError(string $databaseError): string
    {
        $databaseError = preg_replace("/'[^']*'/", "'[redacted]'", $databaseError) ?? '';
        $databaseError = preg_replace('/\b[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}\b/', '[redacted]', $databaseError) ?? '';
        $databaseError = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[redacted]', $databaseError) ?? '';

        return mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $databaseError) ?? '', 0, 500);
    }

    /**
     * Fire-and-forget HTTP POST to PostHog /capture.
     * 1-second timeout so a PostHog outage never slows down page loads.
     */
    private static function capture(string $event, array $properties, ?string $distinctId = null): bool
    {
        if (in_array(self::installProfile(), ['test'], true)) {
            return true;
        }

        $distinctId ??= SystemConfig::isInitialized() ? SystemConfig::getValue('sSystemID') : '';
        if ($distinctId === '') {
            return false;
        }

        $payload = json_encode([
            'api_key'     => self::POSTHOG_KEY,
            'event'       => $event,
            'distinct_id' => $distinctId,
            'properties'  => $properties,
        ]);

        try {
            $ch = curl_init(self::POSTHOG_CAPTURE_ENDPOINT);
            if ($ch === false) {
                return false;
            }
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 1,
                CURLOPT_NOSIGNAL       => 1,
            ]);
            $response = curl_exec($ch);
            $httpStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $acknowledged = self::receivedCaptureAcknowledgement($response, $httpStatus);
            if (!$acknowledged) {
                $error = $response === false ? curl_error($ch) : 'HTTP ' . $httpStatus;
                error_log('TelemetryService::capture failed: ' . $error);
            }
            curl_close($ch);
            return $acknowledged;
        } catch (\Throwable $e) {
            error_log('TelemetryService::capture failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function receivedCaptureAcknowledgement(string|false $response, int $httpStatus): bool
    {
        return $response !== false && $httpStatus === 200;
    }
}
