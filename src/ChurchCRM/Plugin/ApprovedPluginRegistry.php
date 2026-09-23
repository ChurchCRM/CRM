<?php

namespace ChurchCRM\Plugin;

use ChurchCRM\Remote\CentralServices;
use ChurchCRM\Utils\LoggerUtils;

/**
 * Queries the list of community plugins that ChurchCRM maintainers have vetted
 * and approved for installation via the URL-based installer.
 *
 * The registry lives at CentralServices::PLUGIN_REGISTRY_URL (Notifications
 * branch). It is fetched lazily on first use within a session and stored in
 * $_SESSION['RemotePluginRegistry']. There is no local copy in the release
 * zip — if the remote fetch fails, the list is empty until a later session
 * can reach the URL.
 *
 * Adding an approved plugin is a commit on the Notifications branch.
 * See .agents/skills/churchcrm/plugin-security-scan.md for the review checklist.
 */
class ApprovedPluginRegistry
{
    /** @see CentralServices::PLUGIN_REGISTRY_URL */
    public const REGISTRY_URL = CentralServices::PLUGIN_REGISTRY_URL;

    /** Required keys on every entry. */
    private const REQUIRED_KEYS = ['id', 'name', 'version', 'downloadUrl', 'sha256', 'risk', 'riskSummary'];

    /** Allowed values for the `risk` field. Ordered low → high for display. */
    public const RISK_LEVELS = ['low', 'medium', 'high'];

    public const KNOWN_PERMISSIONS = [
        'network.outbound',
        'network.inbound',
        'db.read',
        'db.write',
        'fs.read',
        'fs.write',
        'secrets.store',
        'ui.inject',
        'cron',
        'hooks.person',
        'hooks.family',
        'hooks.financial',
        'hooks.email',
        'email.send',
        'sms.send',
        'calendar.register',
    ];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        if (!array_key_exists('RemotePluginRegistry', $_SESSION)) {
            self::fetchRemoteRegistry();
        }

        self::$cache = $_SESSION['RemotePluginRegistry'] ?? [];

        return self::$cache;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $pluginId): ?array
    {
        return self::all()[$pluginId] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByDownloadUrl(string $downloadUrl): ?array
    {
        foreach (self::all() as $entry) {
            if (hash_equals((string) $entry['downloadUrl'], $downloadUrl)) {
                return $entry;
            }
        }

        return null;
    }

    public static function fetchRemoteRegistry(): void
    {
        try {
            $_SESSION['RemotePluginRegistry'] = self::loadAndValidateFromUrl(self::REGISTRY_URL);
            self::$cache = null;
        } catch (\Exception $e) {
            LoggerUtils::getAppLogger()->warning('Error processing remote plugin registry', ['error' => $e->getMessage()]);
            $_SESSION['RemotePluginRegistry'] = [];
        }
    }

    public static function reset(): void
    {
        self::$cache = null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function loadAndValidateFromUrl(string $url): array
    {
        $contents = @file_get_contents($url);
        if ($contents === false) {
            LoggerUtils::getAppLogger()->warning('Failed to fetch remote plugin registry', ['url' => $url]);

            return [];
        }

        return self::parseAndValidate($contents, $url);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function parseAndValidate(string $contents, string $source): array
    {
        $data = json_decode($contents, true, 512);
        $entries = $data['plugins'] ?? [];
        if (!is_array($entries)) {
            LoggerUtils::getAppLogger()->warning('Plugin registry JSON missing plugins array', ['source' => $source]);

            return [];
        }
        $validated = [];
        foreach ($entries as $entry) {
            if (is_array($entry) && self::isValidEntry($entry)) {
                $validated[$entry['id']] = $entry;
            }
        }

        return $validated;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function isValidEntry(array $entry): bool
    {
        foreach (self::REQUIRED_KEYS as $key) {
            if (!isset($entry[$key]) || !is_string($entry[$key]) || $entry[$key] === '') {
                LoggerUtils::getAppLogger()->warning('Approved plugin entry missing key', [
                    'key' => $key,
                    'entry' => $entry['id'] ?? '(unknown)',
                ]);

                return false;
            }
        }

        if (!preg_match('/^https:\/\//i', (string) $entry['downloadUrl'])) {
            LoggerUtils::getAppLogger()->warning('Approved plugin entry rejected (non-HTTPS downloadUrl)', [
                'entry' => $entry['id'],
            ]);

            return false;
        }

        if (!preg_match('/^[a-f0-9]{64}$/i', (string) $entry['sha256'])) {
            LoggerUtils::getAppLogger()->warning('Approved plugin entry rejected (invalid sha256)', [
                'entry' => $entry['id'],
            ]);

            return false;
        }

        if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', (string) $entry['id'])) {
            LoggerUtils::getAppLogger()->warning('Approved plugin entry rejected (invalid id)', [
                'entry' => $entry['id'],
            ]);

            return false;
        }

        if (!in_array(strtolower((string) $entry['risk']), self::RISK_LEVELS, true)) {
            LoggerUtils::getAppLogger()->warning('Approved plugin entry rejected (invalid risk level)', [
                'entry' => $entry['id'],
                'risk' => $entry['risk'] ?? null,
            ]);

            return false;
        }

        if (isset($entry['permissions'])) {
            if (!is_array($entry['permissions'])) {
                LoggerUtils::getAppLogger()->warning('Approved plugin entry rejected (permissions not an array)', [
                    'entry' => $entry['id'],
                ]);

                return false;
            }
            foreach ($entry['permissions'] as $perm) {
                if (!is_string($perm) || !in_array($perm, self::KNOWN_PERMISSIONS, true)) {
                    LoggerUtils::getAppLogger()->warning('Approved plugin entry rejected (unknown permission tag)', [
                        'entry' => $entry['id'],
                        'permission' => $perm,
                    ]);

                    return false;
                }
            }
        }

        return true;
    }
}
