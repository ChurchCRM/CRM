<?php

namespace ChurchCRM\Plugins\ExternalBackup;

use ChurchCRM\Backup\BackupJob;
use ChurchCRM\Backup\BackupType;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Utils\LoggerUtils;

/**
 * External Backup Plugin.
 *
 * Provides WebDAV backup functionality for ChurchCRM databases.
 * Supports automatic scheduled backups and manual remote backups.
 */
class ExternalBackupPlugin extends AbstractPlugin
{
    private static ?ExternalBackupPlugin $instance = null;

    public function __construct(string $basePath = '')
    {
        parent::__construct($basePath);
        self::$instance = $this;
    }

    public static function getInstance(): ?ExternalBackupPlugin
    {
        return self::$instance;
    }

    public function getId(): string
    {
        return 'external-backup';
    }

    public function getName(): string
    {
        return gettext('External Backup (WebDAV)');
    }

    public function getDescription(): string
    {
        return gettext('Automatically backup your ChurchCRM database to external cloud storage via WebDAV.');
    }

    public function boot(): void
    {
        // Plugin boots but doesn't register hooks - functionality called directly from SystemService
    }

    public function activate(): void
    {
        // No activation tasks needed
    }

    public function deactivate(): void
    {
        // Keep settings when deactivated
    }

    public function uninstall(): void
    {
        // Settings preserved for potential re-activation
    }

    /**
     * Check if the plugin is properly configured.
     */
    public function isConfigured(): bool
    {
        $endpoint = $this->getConfigValue('endpoint');
        $username = $this->getConfigValue('username');
        $password = $this->getConfigValue('password');

        // Basic required field check
        if (empty($endpoint) || empty($username) || empty($password)) {
            return false;
        }

        // Validate endpoint is a proper URL
        if (!$this->isValidEndpointUrl($endpoint)) {
            return false;
        }

        return true;
    }

    /**
     * Validate that the endpoint is a proper HTTPS URL.
     */
    private function isValidEndpointUrl(string $endpoint): bool
    {
        // Must be a valid URL
        if (filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        // Must use https (or http for local dev)
        $scheme = parse_url($endpoint, PHP_URL_SCHEME);
        if (!in_array($scheme, ['https', 'http'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Get configuration error message if not configured.
     */
    public function getConfigurationError(): ?string
    {
        $endpoint = $this->getConfigValue('endpoint');
        $username = $this->getConfigValue('username');
        $password = $this->getConfigValue('password');

        if (empty($endpoint)) {
            return gettext('WebDAV endpoint URL is required.');
        }

        if (!$this->isValidEndpointUrl($endpoint)) {
            return gettext('Invalid endpoint URL. Must be a full URL starting with https:// (e.g., https://cloud.example.com/remote.php/dav/files/user/backups/)');
        }

        if (empty($username)) {
            return gettext('WebDAV username is required.');
        }

        if (empty($password)) {
            return gettext('WebDAV password is required.');
        }

        return null;
    }

    // =========================================================================
    // Backup Configuration Accessors
    // =========================================================================

    /**
     * Get the WebDAV endpoint URL.
     */
    public function getEndpoint(): string
    {
        return $this->getConfigValue('endpoint');
    }

    /**
     * Get the WebDAV username.
     */
    public function getUsername(): string
    {
        return $this->getConfigValue('username');
    }

    /**
     * Get the WebDAV password.
     */
    public function getPassword(): string
    {
        return $this->getConfigValue('password');
    }

    /**
     * Get the auto-backup interval in hours.
     */
    public function getAutoInterval(): int
    {
        $interval = $this->getConfigValue('autoInterval');

        return $interval ? (int) $interval : 0;
    }

    /**
     * Get the last backup timestamp.
     */
    public function getLastBackupTimestamp(): string
    {
        return $this->getConfigValue('lastBackupTimestamp') ?: '';
    }

    /**
     * Set the last backup timestamp.
     */
    public function setLastBackupTimestamp(string $timestamp): void
    {
        $this->setConfigValue('lastBackupTimestamp', $timestamp);
    }

    // =========================================================================
    // Backup Operations
    // =========================================================================

    /**
     * Check if automatic backups should run based on interval.
     */
    public function shouldRunAutomaticBackup(): bool
    {
        if (!$this->isEnabled() || !$this->isConfigured()) {
            return false;
        }

        $interval = $this->getAutoInterval();
        if ($interval <= 0) {
            return false;
        }

        $lastBackup = $this->getLastBackupTimestamp();
        if (empty($lastBackup)) {
            return true; // No previous backup, run one now
        }

        return $this->isTimerThresholdExceeded($lastBackup, $interval);
    }

    /**
     * Check if the timer threshold has been exceeded.
     */
    private function isTimerThresholdExceeded(string $lastTimestamp, int $thresholdHours): bool
    {
        if (empty($lastTimestamp)) {
            return true;
        }

        $now = new \DateTime();
        $last = \DateTime::createFromFormat(SystemConfig::getValue('sDateFilenameFormat'), $lastTimestamp);

        if ($last === false) {
            // If we can't parse the timestamp, assume threshold exceeded
            return true;
        }

        $diff = ($now->getTimestamp() - $last->getTimestamp()) / 3600; // Convert to hours

        return $diff >= $thresholdHours;
    }

    /**
     * Execute an automatic backup.
     *
     * @return bool True if backup was successful
     */
    public function executeAutomaticBackup(): bool
    {
        if (!$this->shouldRunAutomaticBackup()) {
            return false;
        }

        $logger = LoggerUtils::getAppLogger();
        $logger->info('External Backup Plugin: Starting automatic backup. Last backup: ' . $this->getLastBackupTimestamp());

        try {
            $baseName = preg_replace('/[^a-zA-Z0-9\-_]/', '', SystemConfig::getValue('sChurchName'))
                . '-' . date(SystemConfig::getValue('sDateFilenameFormat'));

            $backup = new BackupJob($baseName, BackupType::FULL_BACKUP);
            $backup->execute();

            $result = $backup->copyToWebDAV(
                $this->getEndpoint(),
                $this->getUsername(),
                $this->getPassword()
            );

            if ($result) {
                $now = new \DateTime();
                $this->setLastBackupTimestamp($now->format(SystemConfig::getValue('sDateFilenameFormat')));
                $logger->info('External Backup Plugin: Automatic backup completed successfully');
            }

            return $result;
        } catch (\Exception $e) {
            $logger->warning('External Backup Plugin: Automatic backup failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Execute a manual remote backup.
     *
     * @param string $backupType The type of backup to create
     *
     * @return array Result with status and any error message
     */
    public function executeManualBackup(string $backupType): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => gettext('WebDAV backups are not correctly configured. Please ensure endpoint, username, and password are set.'),
            ];
        }

        $logger = LoggerUtils::getAppLogger();
        $logger->info('External Backup Plugin: Starting manual remote backup');

        try {
            $baseName = preg_replace('/[^a-zA-Z0-9\-_]/', '', SystemConfig::getValue('sChurchName'))
                . '-' . date(SystemConfig::getValue('sDateFilenameFormat'));

            $backup = new BackupJob($baseName, $backupType);
            $backup->execute();

            $copyStatus = $backup->copyToWebDAV(
                $this->getEndpoint(),
                $this->getUsername(),
                $this->getPassword()
            );

            if ($copyStatus) {
                $now = new \DateTime();
                $this->setLastBackupTimestamp($now->format(SystemConfig::getValue('sDateFilenameFormat')));
            }

            return [
                'success' => $copyStatus,
                'copyStatus' => $copyStatus,
                'message' => $copyStatus
                    ? gettext('Backup generated and copied to remote server')
                    : gettext('Backup created but failed to copy to remote server'),
            ];
        } catch (\Exception $e) {
            $logger->error('External Backup Plugin: Manual backup failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => gettext('Remote backup failed. Check server logs for details.'),
            ];
        }
    }

    /**
     * Test the WebDAV connection using saved settings.
     *
     * Called by the plugin-specific route: POST /plugins/external-backup/api/test
     *
     * @return array Result with status and message
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => gettext('Please configure endpoint, username, and password first.'),
            ];
        }

        return $this->performPropfind($this->getEndpoint(), $this->getUsername(), $this->getPassword());
    }

    /**
     * Test the WebDAV connection using settings provided from the management UI form.
     *
     * Falls back to the saved password when the field is omitted
     * (i.e. the admin has not re-typed it).
     *
     * {@inheritdoc}
     */
    public function testWithSettings(array $settings): array
    {
        $endpoint = $settings['endpoint'] ?? $this->getConfigValue('endpoint');
        $username = $settings['username'] ?? $this->getConfigValue('username');

        $password = $settings['password'] ?? '';
        if (empty($password)) {
            // Only fall back to the saved password when testing the same endpoint.
            // Sending stored credentials to an attacker-controlled endpoint would leak them.
            $savedEndpoint = (string) $this->getConfigValue('endpoint');
            if ($savedEndpoint !== '' && $endpoint === $savedEndpoint) {
                $password = $this->getConfigValue('password');
            }
        }

        if (empty($endpoint)) {
            return ['success' => false, 'message' => gettext('WebDAV Endpoint URL is required.')];
        }

        if (!$this->isValidEndpointUrl($endpoint)) {
            return ['success' => false, 'message' => gettext('Invalid endpoint URL. Must be a full URL starting with https://')];
        }

        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => gettext('Username and password are required.')];
        }

        return $this->performPropfind($endpoint, $username, $password);
    }

    /**
     * Execute a WebDAV PROPFIND request to verify connectivity and credentials.
     *
     * Uses HTTP 207 (Multi-Status) as the success indicator per the WebDAV spec.
     *
     * @return array{success: bool, message: string}
     */
    private function performPropfind(string $endpoint, string $username, string $password): array
    {
        LoggerUtils::getAppLogger()->debug('External Backup Plugin: Testing WebDAV connection', ['endpoint' => $endpoint]);

        try {
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PROPFIND');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Depth: 0']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);

            if (!empty($error)) {
                return ['success' => false, 'message' => gettext('Connection failed') . ': ' . $error];
            }

            // HTTP 207 Multi-Status is the WebDAV success response for PROPFIND
            if ($httpCode === 207 || $httpCode === 200) {
                return ['success' => true, 'message' => gettext('Connection successful! WebDAV endpoint is accessible.')];
            }

            if ($httpCode === 401 || $httpCode === 403) {
                return ['success' => false, 'message' => gettext('Authentication failed. Please check username and password.')];
            }

            return ['success' => false, 'message' => gettext('Connection failed with HTTP code') . ': ' . $httpCode];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => gettext('Connection test failed') . ': ' . $e->getMessage()];
        }
    }

    /**
     * Get status information for display.
     */
    public function getStatus(): array
    {
        $interval = $this->getAutoInterval();

        return [
            'enabled' => $this->isEnabled(),
            'configured' => $this->isConfigured(),
            'endpoint' => $this->getEndpoint(),
            'scheduledBackupInterval' => $interval,
            'lastBackup' => $this->getLastBackupTimestamp(),
            'scheduledBackupsEnabled' => $interval > 0,
        ];
    }
}
