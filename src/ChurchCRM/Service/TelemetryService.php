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

    // Collection levels stored in sTelemetryLevel.
    public const LEVEL_NONE     = 'none';
    public const LEVEL_INSTALL  = 'install';
    public const LEVEL_ERRORS   = 'errors';    // WARNING+ + JS exceptions
    public const LEVEL_FULL     = 'full';      // warnings + page views + JS exceptions

    private static array $installProfileCache = [];

    /** Public demo hosts (Softaculous) run real installs without our demo data; classify them by host. */
    public static function isKnownDemoHost(string $host): bool
    {
        $host = strtolower(explode(':', $host, 2)[0]);

        return $host === 'softaculous.com' || str_ends_with($host, '.softaculous.com');
    }

    public static function resolveInstallProfile(string $storedProfile, bool $testOverride, bool $demoHost = false): string
    {
        if ($testOverride) {
            return 'test';
        }
        if ($demoHost && $storedProfile !== 'test') {
            return 'demo';
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
        $demoHost = self::isKnownDemoHost((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $key = ($testOverride ? 'test:' : 'stored:') . $storedProfile . ($demoHost ? ':host' : '');
        return self::$installProfileCache[$key] ??= self::resolveInstallProfile($storedProfile, $testOverride, $demoHost);
    }

    public static function getLevel(): string
    {
        $level = SystemConfig::getValue('sTelemetryLevel');
        $level = $level === 'warnings' ? self::LEVEL_ERRORS : $level;
        return in_array($level, [self::LEVEL_INSTALL, self::LEVEL_ERRORS, self::LEVEL_FULL], true)
            ? $level
            : self::LEVEL_NONE;
    }

    public static function isEnabled(): bool
    {
        return in_array(self::getLevel(), [self::LEVEL_ERRORS, self::LEVEL_FULL], true);
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
        if ($level === self::LEVEL_NONE || self::installProfile() === 'test') {
            return;
        }

        $systemId = SystemConfig::getValue('sSystemID');
        if ($systemId === '') {
            return;
        }

        // Skip the lock and queries on the common request: nothing is due.
        if (SystemConfig::getValue('sTelemetryLifecycleVersion') === VersionUtils::getInstalledVersion()
            && SystemConfig::getValue('sTelemetryHeartbeatDate') === gmdate('Y-m-d')) {
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
            if ($lifecycleEvent !== null) {
                $acknowledged = ($send ?? self::capture(...))($lifecycleEvent['event'], $lifecycleEvent['properties']);
                if ($acknowledged === false) {
                    return;
                }
                SystemConfig::setValue('sTelemetryLifecycleVersion', $currentVersion);
            }

            // One heartbeat per UTC day lets PostHog count active installs (seen in the last 28 days).
            $today = gmdate('Y-m-d');
            $lastHeartbeat = $send === null
                ? (ConfigQuery::create()->findOneByName('sTelemetryHeartbeatDate')?->getValue() ?? '')
                : SystemConfig::getValue('sTelemetryHeartbeatDate');
            if ($lastHeartbeat !== $today
                && ($send ?? self::capture(...))('heartbeat', self::lifecycleProperties($currentVersion)) !== false) {
                SystemConfig::setValue('sTelemetryHeartbeatDate', $today);
            }
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

    /** Build a lifecycle event for an installation with uncovered version state. */
    public static function buildLifecycleEvent(string $level, string $systemId, string $coveredVersion, string $currentVersion): ?array
    {
        if (!in_array($level, [self::LEVEL_INSTALL, self::LEVEL_ERRORS, self::LEVEL_FULL], true)
            || self::installProfile() === 'test' || $systemId === '' || $coveredVersion === $currentVersion) {
            return null;
        }

        if ($coveredVersion === '') {
            return ['event' => 'install_completed', 'properties' => self::lifecycleProperties($currentVersion)];
        }

        return [
            'event' => 'upgrade_completed',
            'properties' => array_merge(self::lifecycleProperties($currentVersion), [
                'from_version' => $coveredVersion,
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
        self::captureUpgradeFailure($version, '');
        if (!self::isEnabled()) {
            return;
        }
        $databaseError = self::sanitizeDatabaseError($databaseError);

        self::capture('upgrade_sql_failed', array_merge(self::baseProperties(), [
            'upgrade_version' => $version,
            'duration_ms' => $durationMs,
            'database_error' => $databaseError,
            '$process_person_profile' => false,
        ]));
    }

    public static function captureInstallFailure(string $errorClass, ?string $databaseError = null): void
    {
        if (self::installProfile() === 'test' || !SystemConfig::isInitialized() || !self::isEnabled()) {
            return;
        }
        $properties = array_merge(self::baseProperties(), ['error_class' => $errorClass]);
        if ($databaseError !== null) {
            $properties['database_error'] = self::sanitizeDatabaseError($databaseError);
        }
        $properties['$process_person_profile'] = false;
        self::capture('install_failed', $properties);
    }

    /** Fixed codes only: a free-text reason could carry details of a church's installation. */
    public const UPGRADE_FAILURE_REASONS = ['no_upgrade_path', 'script_interrupted'];

    public static function captureUpgradeFailure(string $version, string $reason): void
    {
        if (self::getLevel() === self::LEVEL_NONE || self::installProfile() === 'test') {
            return;
        }
        try {
            $fromVersion = VersionUtils::getDBVersion();
        } catch (\Throwable) {
            $fromVersion = SystemConfig::getValue('sTelemetryLifecycleVersion') ?: null;
        }
        $reason = in_array($reason, self::UPGRADE_FAILURE_REASONS, true) ? $reason : 'other';

        // A failing install is retried on every request; report each from-version and reason once per UTC day.
        $dedupeKey = gmdate('Y-m-d') . '|' . $fromVersion . '|' . $reason;
        try {
            if (SystemConfig::getValue('sTelemetryUpgradeFailureKey') === $dedupeKey) {
                return;
            }
            SystemConfig::setValue('sTelemetryUpgradeFailureKey', $dedupeKey);
        } catch (\Throwable) {
            // The upgrade may be failing because the configuration table is unusable; report anyway.
        }

        self::capture('upgrade_failed', array_merge(self::lifecycleProperties(VersionUtils::getInstalledVersion()), [
            'from_version' => $fromVersion,
            'failure_reason' => $reason,
        ]));
    }

    public static function captureUpgradeInterruption(string $version, int $elapsedMs): void
    {
        self::captureUpgradeFailure($version, '');
        if (!self::isEnabled()) {
            return;
        }
        self::capture('upgrade_script_interrupted', array_merge(self::baseProperties(), [
            'upgrade_version' => $version,
            'elapsed_ms' => $elapsedMs,
            '$process_person_profile' => false,
        ]));
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

    private static function lifecycleProperties(string $version): array
    {
        $properties = [
            'crm_version' => $version,
            'install_profile' => self::installProfile(),
            'church_country' => self::churchCountry(),
        ];
        try {
            $serverVersion = (string) Propel::getConnection()->getAttribute(\PDO::ATTR_SERVER_VERSION);
            $properties += self::databaseProperties($serverVersion);
        } catch (\Throwable) {
            // Database failure reporting must also work when the connection is unavailable.
        }
        return $properties;
    }

    public static function databaseProperties(string $serverVersion): array
    {
        $database = DatabaseVersionService::parse($serverVersion);
        if ($database === null) {
            return [];
        }
        return [
            'database_engine' => strtolower($database['engine']),
            'database_version' => implode('.', array_slice(explode('.', $database['version']), 0, 2)),
        ];
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

        if (in_array($event, ['install_completed', 'upgrade_completed', 'upgrade_failed', 'heartbeat'], true)) {
            $properties['$process_person_profile'] = false;
        }
        $payload = json_encode([
            'api_key'     => self::POSTHOG_KEY,
            'event'       => $event,
            'distinct_id' => $distinctId,
            'properties'  => $properties + ['$geoip_disable' => true],
        ]);

        try {
            $ch = curl_init(self::POSTHOG_CAPTURE_ENDPOINT . '?ip=0'); // ip=0: PostHog must not store this server's address
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
