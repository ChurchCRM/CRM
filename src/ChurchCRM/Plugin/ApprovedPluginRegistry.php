<?php

namespace ChurchCRM\Plugin;

use ChurchCRM\Utils\LoggerUtils;

/**
 * Loads and queries the list of community plugins that ChurchCRM maintainers
 * have vetted and approved for installation via the URL-based installer.
 *
 * The registry is a flat JSON file (src/plugins/approved-plugins.json). Each
 * entry must specify:
 *   - id              (kebab-case plugin id, must match plugin.json)
 *   - name            (display name)
 *   - version         (semver, must match plugin.json)
 *   - downloadUrl     (HTTPS only)
 *   - sha256          (hex SHA-256 of the zip bytes)
 *   - minimumCRMVersion (optional, enforced on install)
 *   - author          (optional)
 *   - homepage        (optional HTTPS URL)
 *   - reviewedAt      (optional ISO-8601 date the maintainers reviewed the zip)
 *   - notes           (optional)
 *
 * This class is intentionally read-only — adding an approved plugin requires a
 * pull request that updates approved-plugins.json.
 */
class ApprovedPluginRegistry
{
    /** Default filename relative to the plugins directory. */
    public const FILENAME = 'approved-plugins.json';

    /** Required keys on every entry. */
    private const REQUIRED_KEYS = ['id', 'name', 'version', 'downloadUrl', 'sha256'];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    /** Path the current cache was loaded from. */
    private static ?string $cachePath = null;

    /**
     * Load all approved plugin entries, keyed by plugin id.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(string $pluginsPath): array
    {
        $registryPath = rtrim($pluginsPath, '/') . '/' . self::FILENAME;

        if (self::$cache !== null && self::$cachePath === $registryPath) {
            return self::$cache;
        }

        self::$cache = [];
        self::$cachePath = $registryPath;

        if (!is_file($registryPath) || !is_readable($registryPath)) {
            LoggerUtils::getAppLogger()->debug('Approved plugin registry missing', ['path' => $registryPath]);

            return [];
        }

        try {
            $raw = file_get_contents($registryPath);
            if ($raw === false) {
                return [];
            }
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            LoggerUtils::getAppLogger()->error('Failed to parse approved plugin registry', [
                'path' => $registryPath,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $entries = $data['plugins'] ?? [];
        if (!is_array($entries)) {
            return [];
        }

        foreach ($entries as $entry) {
            if (!is_array($entry) || !self::isValidEntry($entry)) {
                continue;
            }
            self::$cache[$entry['id']] = $entry;
        }

        return self::$cache;
    }

    /**
     * Look up a single approved plugin by id.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $pluginsPath, string $pluginId): ?array
    {
        $entries = self::all($pluginsPath);

        return $entries[$pluginId] ?? null;
    }

    /**
     * Look up an approved plugin by the download URL supplied by the user.
     * This lets the installer accept a URL-based install request and still
     * anchor the result to a vetted registry entry.
     *
     * @return array<string, mixed>|null
     */
    public static function findByDownloadUrl(string $pluginsPath, string $downloadUrl): ?array
    {
        foreach (self::all($pluginsPath) as $entry) {
            if (hash_equals((string) $entry['downloadUrl'], $downloadUrl)) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Reset the in-memory cache. Intended for tests or after the JSON file is
     * rewritten in the same request.
     */
    public static function reset(): void
    {
        self::$cache = null;
        self::$cachePath = null;
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

        return true;
    }
}
