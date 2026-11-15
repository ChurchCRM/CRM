<?php

namespace ChurchCRM\Service;

use ChurchCRM\data\Countries;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ConfigQuery;
use ChurchCRM\Utils\VersionUtils;
use Monolog\LogRecord;
use Propel\Runtime\Propel;

require_once __DIR__ . '/../../Include/PostHogProject.php';

class TelemetryService
{
    // The shared PostHog project (src/Include/PostHogProject.php) — not user-configurable.
    public const POSTHOG_KEY      = \POSTHOG_PROJECT_KEY;
    public const POSTHOG_ENDPOINT = \POSTHOG_PROJECT_HOST;
    public const POSTHOG_CAPTURE_ENDPOINT = self::POSTHOG_ENDPOINT . '/i/v0/e/';

    // Collection levels stored in sTelemetryLevel.
    public const LEVEL_NONE     = 'none';
    public const LEVEL_INSTALL  = 'install';
    public const LEVEL_ERRORS   = 'errors';    // WARNING+ + JS exceptions
    public const LEVEL_FULL     = 'full';      // warnings + page views + JS exceptions

    private static array $installProfileCache = [];
    private static ?string $logDedupeDirectory = null;

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
            return ['event' => 'install_observed', 'properties' => self::lifecycleProperties($currentVersion)];
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
        self::captureUpgradeFailure($version, 'sql_error');
        if (!self::isEnabled()) {
            return;
        }
        $databaseError = self::sanitizeDatabaseError($databaseError);

        self::capture('upgrade_sql_failed', array_merge(self::baseProperties(), [
            'upgrade_version' => $version,
            'duration_ms' => $durationMs,
            'database_error' => $databaseError,
        ]));
    }

    /**
     * A failed first install has no configuration and no installation ID yet. Its ID is hashed from the
     * document root's device and inode, which hold no server name or path, so repeated attempts on one
     * server count as one installation. Sent at every level.
     */
    public static function captureInstallFailure(string $reason): void
    {
        $installId = self::unconfiguredInstallId();
        if ($installId === null) {
            return;
        }
        self::capture('install_failed', array_merge(self::lifecycleProperties(VersionUtils::getInstalledVersion()), [
            'failure_reason' => in_array($reason, ['schema_import_failed'], true) ? $reason : 'other',
        ]), $installId);
    }

    /**
     * The requirements check runs before configuration loads, so it cannot read the telemetry level: like
     * install failures it is sent at every level. Names are the fixed English labels from AppIntegrityService.
     * Each set of failures is reported once per UTC day.
     *
     * @param string[] $failedRequirements
     */
    public static function captureRequirementsFailure(array $failedRequirements): void
    {
        $installId = self::unconfiguredInstallId();
        sort($failedRequirements);
        if ($installId === null || !self::firstLogToday('requirements|' . implode('|', $failedRequirements))) {
            return;
        }
        self::capture('requirements_failed', array_merge(self::lifecycleProperties(VersionUtils::getInstalledVersion()), [
            'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            'failed_requirements' => $failedRequirements,
        ]), $installId);
    }

    /** Fixed states for version_mismatch: the failed upgrade page and the database-newer-than-software page. */
    public const VERSION_MISMATCH_STATES = ['upgrade_failed', 'database_newer'];

    /**
     * Counts installations whose database and application versions are out of sync. Sent at every level, like
     * upgrade_failed, so the count is complete; each state and version pair is reported once per UTC day.
     */
    public static function captureVersionMismatch(string $dbVersion, string $state): void
    {
        if (self::installProfile() === 'test' || !in_array($state, self::VERSION_MISMATCH_STATES, true)
            || !self::firstLogToday('version_mismatch|' . $state . '|' . $dbVersion)) {
            return;
        }
        $systemId = SystemConfig::isInitialized() ? SystemConfig::getValue('sSystemID') : '';
        self::capture('version_mismatch', array_merge(self::lifecycleProperties(VersionUtils::getInstalledVersion()), [
            'db_version' => $dbVersion,
            'mismatch_state' => $state,
        ]), $systemId !== '' ? $systemId : self::unconfiguredInstallId());
    }

    /**
     * An installation ID for events sent before configuration exists: hashed from the document root's device
     * and inode, which hold no server name or path, so repeated attempts on one server count as one installation.
     */
    private static function unconfiguredInstallId(): ?string
    {
        if (self::installProfile() === 'test') {
            return null;
        }
        $stat = @stat((string) SystemURLs::getDocumentRoot());

        return $stat === false ? null : 'install-' . substr(sha1($stat['dev'] . '|' . $stat['ino']), 0, 32);
    }

    /** Fixed codes only: a free-text reason could carry details of a church's installation. */
    public const UPGRADE_FAILURE_REASONS = ['no_upgrade_path', 'script_interrupted', 'sql_error'];

    /** Sent at every level so failed upgrades get fixed. */
    public static function captureUpgradeFailure(string $version, string $reason, ?string $upgradeScript = null): void
    {
        if (self::installProfile() === 'test') {
            return;
        }
        try {
            $fromVersion = VersionUtils::getDBVersion();
        } catch (\Throwable) {
            try {
                $fromVersion = SystemConfig::getValue('sTelemetryLifecycleVersion') ?: null;
            } catch (\Throwable) {
                $fromVersion = null;
            }
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

        $properties = array_merge(self::lifecycleProperties(VersionUtils::getInstalledVersion()), [
            'from_version' => $fromVersion,
            'failure_reason' => $reason,
            'upgrade_version' => $version,
        ]);
        $script = self::sanitizeUpgradeScript($upgradeScript);
        if ($script !== null) {
            $properties['upgrade_script'] = $script;
        }

        self::capture('upgrade_failed', $properties);
    }

    /**
     * Repo-relative upgrade script paths only (e.g. mysql/upgrade/7.8.4.sql).
     * Anything else — a server path, a traversal, an empty value — is dropped
     * so no detail of a church's installation ever leaves it.
     */
    private static function sanitizeUpgradeScript(?string $upgradeScript): ?string
    {
        if ($upgradeScript === null || $upgradeScript === '') {
            return null;
        }
        $script = ltrim(str_replace('\\', '/', $upgradeScript), '/');
        if (!str_starts_with($script, 'mysql/upgrade/') || str_contains($script, '..')) {
            return null;
        }

        return $script;
    }

    public static function captureUpgradeInterruption(string $version, int $elapsedMs): void
    {
        self::captureUpgradeFailure($version, 'script_interrupted');
        if (!self::isEnabled()) {
            return;
        }
        self::capture('upgrade_script_interrupted', array_merge(self::baseProperties(), [
            'upgrade_version' => $version,
            'elapsed_ms' => $elapsedMs,
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
        $route  = $rawUrl !== '' ? self::normalizePageRoute($rawUrl, SystemURLs::getRootPath()) : 'unknown';

        $message = self::scrubLogMessage($record->message);
        if (!self::firstLogToday($record->level->name . '|' . $message)) {
            return;
        }

        $properties = array_merge(self::baseProperties(), [
            'level'          => $record->level->name,
            'message'        => $message,
            'channel'        => $record->channel,
            'correlation_id' => $record->extra['correlation_id'] ?? null,
            'route'          => $route,
            // remote_ip is intentionally excluded — PII
        ]);

        self::capture('log_error', $properties);
    }

    /**
     * A misconfigured server can log the same warning on every request; send each message once per UTC day.
     * ponytail: one marker file per message per day in the temp directory; the OS temp cleaner removes old ones.
     */
    private static function firstLogToday(string $key): bool
    {
        $directory = self::$logDedupeDirectory ??= sys_get_temp_dir() . '/churchcrm-telemetry';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            return true;
        }
        $marker = $directory . '/' . sha1(gmdate('Y-m-d') . '|' . self::installProfile() . '|' . (SystemConfig::isInitialized() ? self::getLevel() : '') . '|' . $key);
        $handle = @fopen($marker, 'x');
        if ($handle === false) {
            return !file_exists($marker);
        }
        fclose($handle);

        return true;
    }

    public static function scrubLogMessage(string $message): string
    {
        $message = preg_replace('/SQLSTATE\[[^\]]*\].*$/s', '[database error]', $message) ?? '';
        $message = preg_replace('/[A-Z0-9.!#$%&\x27*+\/=?^_`{|}~-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $message) ?? '';

        return mb_substr(self::scrubHostDetails($message), 0, 500);
    }

    /** Remove web addresses, this server's names, its install path and IP addresses, so no event identifies the church. */
    public static function scrubHostDetails(string $text): string
    {
        $text = preg_replace('#\b[a-z][a-z0-9+.-]*://[^\s\'"<>]+#i', '[url]', $text) ?? '';
        $known = [];
        foreach ([$_SERVER['HTTP_HOST'] ?? '', $_SERVER['SERVER_NAME'] ?? '', php_uname('n')] as $host) {
            $host = explode(':', (string) $host, 2)[0];
            if (strlen($host) > 3) {
                $known[$host] = '[host]';
            }
        }
        try {
            $documentRoot = (string) SystemURLs::getDocumentRoot();
            if (strlen($documentRoot) > 1) {
                $known = [$documentRoot => '[path]'] + $known;
            }
        } catch (\Throwable) {
        }

        return preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[ip]', strtr($text, $known)) ?? '';
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
        // MySQL quotes the conflicting value; cut at the first quote so a value containing a quote cannot survive.
        $databaseError = preg_replace('/[\'"`].*$/s', '[redacted]', $databaseError) ?? '';
        $databaseError = preg_replace('/\b[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}\b/', '[redacted]', $databaseError) ?? '';
        $databaseError = self::scrubHostDetails($databaseError);

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

        // Every event describes an installation, never a person.
        $properties['$process_person_profile'] = false;
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
