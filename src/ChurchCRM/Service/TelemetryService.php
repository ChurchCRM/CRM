<?php

namespace ChurchCRM\Service;

use ChurchCRM\data\Countries;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ConfigQuery;
use ChurchCRM\Utils\SystemIdentity;
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
    public const LEVEL_DEFAULT = 'default';
    public const LEVEL_FULL = 'full';

    // The shared development system ID, planted into Include/SystemID.php by
    // scripts/ci/docker-config.js and seeded by cypress/data/seed.sql: an install
    // carrying it is a dev/CI system, never a church, whatever its stored profile says.
    private const DEV_SYSTEM_ID = 'dev-churchcrm-testing';

    // Environment variable a channel that cannot write config itself (Docker, a
    // hosting panel) uses to name the install source at install time.
    private const INSTALL_SOURCE_ENV_VAR = 'CHURCHCRM_INSTALL_SOURCE';

    private static array $installProfileCache = [];
    private static ?string $logDedupeDirectory = null;

    /** Public demo hosts (Softaculous) run real installs without our demo data; classify them by host. */
    public static function isKnownDemoHost(string $host): bool
    {
        $host = strtolower(explode(':', $host, 2)[0]);

        return $host === 'softaculous.com' || str_ends_with($host, '.softaculous.com');
    }

    public static function resolveInstallProfile(string $storedProfile, bool $testOverride, bool $demoHost = false, bool $devSystemId = false): string
    {
        // CI sets CI or CONTINUOUS_INTEGRATION. A church server does not, so that process is a test install.
        if ($testOverride || $devSystemId || self::isContinuousIntegration()) {
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
        $devSystemId = SystemIdentity::read() === self::DEV_SYSTEM_ID;
        $continuousIntegration = self::isContinuousIntegration();
        $key = ($testOverride ? 'test:' : 'stored:') . $storedProfile . ($demoHost ? ':host' : '') . ($devSystemId ? ':devid' : '') . ($continuousIntegration ? ':ci' : '');
        return self::$installProfileCache[$key] ??= self::resolveInstallProfile($storedProfile, $testOverride, $demoHost, $devSystemId);
    }

    /** True when this process is a CI runner. Empty, 0, and false are not. */
    private static function isContinuousIntegration(): bool
    {
        foreach (['CI', 'CONTINUOUS_INTEGRATION'] as $name) {
            $value = self::environmentValue($name);
            if ($value !== null && !in_array(strtolower($value), ['', '0', 'false'], true)) {
                return true;
            }
        }

        return false;
    }

    private static function environmentValue(string $name): ?string
    {
        $value = getenv($name);
        if (!is_string($value)) {
            foreach ([$_ENV, $_SERVER] as $source) {
                if (isset($source[$name]) && is_string($source[$name])) {
                    $value = $source[$name];
                    break;
                }
            }
        }

        return is_string($value) ? trim($value) : null;
    }

    /**
     * Stamp the install source from the CHURCHCRM_INSTALL_SOURCE environment variable.
     *
     * Called from Bootstrapper until a marker exists: on the first bootstrap of a new
     * install, before the first lifecycle event, or on the first request after an
     * existing install gains the variable (such as a Docker image upgrade). A
     * marker an installer wrote itself (Softaculous writes sInstallSource directly)
     * always wins and is never overwritten: this is one-time install provenance, not
     * runtime configuration, and nothing reads the variable after the stamp.
     */
    public static function stampInstallSourceFromEnvironment(): void
    {
        try {
            if (!SystemConfig::isInitialized()) {
                return;
            }
            $stored = SystemConfig::getValue('sInstallSource');
            if (is_string($stored) && trim($stored) !== '') {
                return;
            }
            $source = self::environmentValue(self::INSTALL_SOURCE_ENV_VAR);
            if ($source === null || $source === '') {
                return;
            }
            SystemConfig::setValue('sInstallSource', $source);
        } catch (\Throwable) {
            // Provenance must never break bootstrap.
        }
    }

    public static function getLevel(): string
    {
        return SystemConfig::getValue('sTelemetryLevel') === self::LEVEL_FULL
            ? self::LEVEL_FULL
            : self::LEVEL_DEFAULT;
    }

    public static function isEnabled(): bool
    {
        return true;
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
        if (self::installProfile() === 'test') {
            return;
        }

        $systemId = self::installId() ?? '';
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
        if (self::installProfile() === 'test' || $systemId === '' || $coveredVersion === $currentVersion) {
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
        $route = is_string($path) && $path !== '' ? rawurldecode($path) : '/';
        $rootPath = rtrim($rootPath, '/');

        if ($rootPath !== '' && ($route === $rootPath || str_starts_with($route, $rootPath . '/'))) {
            $route = substr($route, strlen($rootPath)) ?: '/';
        }

        return preg_replace('#(?<=/)[0-9]+(?=/|$)#', '{id}', $route) ?? '/';
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

    /** Fixed codes only: a free-text reason could carry details of a church's installation. */
    private const INSTALL_FAILURE_REASONS = [
        'schema_import_failed',
        'database_unreachable',
        'database_host_unreachable',
        'database_login_rejected',
        'database_not_found',
        'config_not_writable',
        'config_invalid',
    ];

    /** The MySQL error number behind a failed connection, or 0 when none is given. */
    public static function databaseErrorCode(\Throwable $e): int
    {
        if ($e instanceof \PDOException && isset($e->errorInfo[1])) {
            return (int) $e->errorInfo[1];
        }

        return preg_match('/\[(\d{3,4})\]/', $e->getMessage(), $matches) === 1 ? (int) $matches[1] : 0;
    }

    /** Maps a MySQL client or server error number to a fixed failure reason. */
    public static function databaseFailureReason(int $errorCode): string
    {
        return match (true) {
            $errorCode === 1045 => 'database_login_rejected',
            // 1044 is access denied to that database after the password was accepted. A user granted
            // only their own database gets 1044, not 1049, when the name is wrong.
            in_array($errorCode, [1044, 1049], true) => 'database_not_found',
            in_array($errorCode, [2001, 2002, 2003, 2005, 2006, 2013], true) => 'database_host_unreachable',
            default => 'database_unreachable',
        };
    }

    /**
     * A failed first install has no configuration yet, but it has the system ID file, so its attempts and the
     * install that finally succeeds share one ID. Sent at every level.
     */
    public static function captureInstallFailure(string $reason, ?string $serverVersion = null, ?int $databaseErrorCode = null): void
    {
        $installId = self::installId();
        if ($installId === null) {
            return;
        }
        $properties = array_merge(self::lifecycleProperties(VersionUtils::getInstalledVersion()), [
            'failure_reason' => in_array($reason, self::INSTALL_FAILURE_REASONS, true) ? $reason : 'other',
        ]);
        if ($databaseErrorCode !== null) {
            $properties['database_error_code'] = $databaseErrorCode;
        }
        if ($serverVersion !== null && $serverVersion !== '') {
            // The caller may know the attempted server version even when no
            // connection is available here (e.g. the setup wizard's own probe
            // succeeded before a later step failed).
            $properties = array_merge($properties, self::databaseProperties($serverVersion));
        }
        self::capture('install_failed', $properties, $installId);
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
        $installId = self::installId();
        sort($failedRequirements);
        if ($installId === null || !self::firstLogToday('requirements|' . implode('|', $failedRequirements))) {
            return;
        }
        self::capture('requirements_failed', array_merge(self::lifecycleProperties(VersionUtils::getInstalledVersion()), [
            'failed_requirements' => $failedRequirements,
        ]), $installId);
    }

    /**
     * What an install is missing: release files that were not uploaded, files that do not match the
     * release, and named checks (PHP extensions, writable folders, locale). Sent at every level, once
     * per distinct list per UTC day. Paths stay repo-relative. No hashes and no install path.
     *
     * @param string[] $missingFiles
     * @param string[] $changedFiles
     * @param string[] $unmetChecks
     */
    public static function captureInstallGaps(array $missingFiles, array $changedFiles, array $unmetChecks): void
    {
        $missingFiles = self::relativeReportPaths($missingFiles);
        $changedFiles = self::relativeReportPaths($changedFiles);
        $unmetChecks = array_values(array_unique(array_filter(array_map(
            static fn ($name): string => is_string($name) ? mb_substr(trim($name), 0, 80) : '',
            $unmetChecks
        ))));
        sort($missingFiles);
        sort($changedFiles);
        sort($unmetChecks);
        if ($missingFiles === [] && $changedFiles === [] && $unmetChecks === []) {
            return;
        }
        $installId = '';
        try {
            if (SystemConfig::isInitialized()) {
                $installId = (string) SystemConfig::getValue('sSystemID');
            }
        } catch (\Throwable) {
            $installId = '';
        }
        if ($installId === '') {
            $installId = self::unconfiguredInstallId() ?? '';
        }
        $key = 'install_gaps|' . implode('|', $missingFiles) . '|' . implode('|', $changedFiles) . '|' . implode('|', $unmetChecks);
        if ($installId === '' || !self::firstLogToday($key)) {
            return;
        }
        self::capture('install_gaps', array_merge(self::lifecycleProperties(VersionUtils::getInstalledVersion()), [
            'missing_files' => $missingFiles,
            'changed_files' => $changedFiles,
            'unmet_checks' => $unmetChecks,
        ]), $installId);
    }

    /** @param string[] $paths
     *  @return string[]
     */
    private static function relativeReportPaths(array $paths): array
    {
        $safe = [];
        foreach ($paths as $path) {
            if (!is_string($path)) {
                continue;
            }
            $path = str_replace('\\', '/', trim($path));
            if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || strlen($path) > 180) {
                continue;
            }
            $safe[] = $path;
        }

        return array_values(array_unique($safe));
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
        $installId = self::installId();
        if ($installId === null) {
            return;
        }
        self::capture('version_mismatch', array_merge(self::lifecycleProperties(VersionUtils::getInstalledVersion()), [
            'db_version' => $dbVersion,
            'mismatch_state' => $state,
        ]), $installId);
    }

    /** The system ID file, written from sSystemID when lost; the document root hash only when the file cannot be kept. */
    private static function installId(): ?string
    {
        if (self::installProfile() === 'test') {
            return null;
        }
        try {
            $storedId = SystemConfig::isInitialized() ? (string) SystemConfig::getValue('sSystemID') : '';
        } catch (\Throwable) {
            $storedId = '';
        }

        return SystemIdentity::ensure($storedId) ?? self::unconfiguredInstallId();
    }

    /**
     * Hashed from the document root's device and inode, which hold no server name or path, so repeated
     * attempts on a server that cannot write the system ID file still count as one installation.
     */
    private static function unconfiguredInstallId(): ?string
    {
        if (self::installProfile() === 'test') {
            return null;
        }
        // Setup runs before SystemURLs::init. The fallback is the src directory, which is the
        // document root Bootstrapper records, so a wizard failure and a later boot failure share an id.
        $root = SystemURLs::getDocumentRoot();
        if (!is_string($root) || $root === '') {
            $root = dirname(__DIR__, 2);
        }
        $stat = @stat($root);

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

        $message = self::scrubLogMessage($record->message);
        if (!self::firstLogToday($record->level->name . '|' . $message)) {
            return;
        }

        $properties = array_merge(self::baseProperties(), [
            'level'          => $record->level->name,
            'message'        => $message,
            'channel'        => $record->channel,
            'correlation_id' => $record->extra['correlation_id'] ?? null,
            // Raw request paths and remote_ip can identify church records or users.
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
        return array_replace(self::baseProperties(), ['crm_version' => $version]);
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

    /** System information shared by server and browser telemetry. */
    public static function baseProperties(): array
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

        $installSource = self::installSource();
        if ($installSource !== null) {
            $properties['install_source'] = $installSource;
        }

        try {
            $serverVersion = (string) Propel::getConnection()->getAttribute(\PDO::ATTR_SERVER_VERSION);
            $properties += self::databaseProperties($serverVersion);
        } catch (\Throwable) {
            // Database properties are best-effort: an event must still send
            // when no connection is available (e.g. an early-boot log error).
        }

        return $properties;
    }

    /**
     * The install channel recorded by the installer (the sInstallSource config value), or null
     * when no installer set one. The value is reported as stored, so any installer or hosting
     * provider that records its own value is attributed the same way.
     */
    public static function installSource(): ?string
    {
        try {
            $source = SystemConfig::isInitialized() ? SystemConfig::getValue('sInstallSource') : null;
        } catch (\Throwable) {
            return null;
        }

        $source = is_string($source) ? trim($source) : '';

        return $source !== '' ? $source : null;
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

        $distinctId ??= self::installId() ?? '';
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
