<?php

namespace ChurchCRM\Plugin;

use ChurchCRM\Remote\CentralServices;
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
 *   - risk            ("low" | "medium" | "high" — see plugin-security-scan.md)
 *   - riskSummary     (one-sentence human-readable summary of the worst-case
 *                      capability the plugin exercises)
 *   - permissions     (array of capability tags, e.g. "network.outbound",
 *                      "db.write", "fs.write", "secrets.store", "ui.inject",
 *                      "cron", "hooks.person", "hooks.financial"; see the
 *                      capability inventory in plugin-security-scan.md)
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

    /** @see CentralServices::PLUGIN_REGISTRY_URL */
    public const REGISTRY_URL = CentralServices::PLUGIN_REGISTRY_URL;

    /** Required keys on every entry. */
    private const REQUIRED_KEYS = ['id', 'name', 'version', 'downloadUrl', 'sha256', 'risk', 'riskSummary'];

    /** Allowed values for the `risk` field. Ordered low → high for display. */
    public const RISK_LEVELS = ['low', 'medium', 'high'];

    /**
     * Capability tags that may appear in the `permissions` array. Review-time
     * classification is enforced by keeping this list short — anything not in
     * here must be added to the list in the same PR that uses it, so the
     * reviewer has to look at plugin-security-scan.md before approving.
     */
    public const KNOWN_PERMISSIONS = [
        'network.outbound',   // plugin makes outbound HTTP(S) calls
        'network.inbound',    // plugin exposes new HTTP routes
        'db.read',            // plugin reads from ChurchCRM tables
        'db.write',           // plugin writes to ChurchCRM tables
        'fs.read',            // plugin reads from the filesystem outside its own dir
        'fs.write',           // plugin writes to the filesystem outside its own dir
        'secrets.store',      // plugin stores credentials / API keys in its config
        'ui.inject',          // plugin injects HTML/JS/CSS into core pages
        'cron',               // plugin runs on a schedule
        'hooks.person',       // plugin listens for PERSON_* hooks (PII reach)
        'hooks.family',       // plugin listens for FAMILY_* hooks (PII reach)
        'hooks.financial',    // plugin listens for DONATION_* / DEPOSIT_* hooks
        'hooks.email',        // plugin listens for EMAIL_* hooks
        'email.send',         // plugin sends email on behalf of the church
        'sms.send',           // plugin sends SMS on behalf of the church
        'calendar.register',  // plugin contributes SystemCalendar instances via systemcalendars.register hook
    ];

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

        // IO failure or JSON corruption on a registry that exists is
        // distinct from "registry missing": the file is there but
        // unreadable or malformed. Throw so callers (admin UI, install
        // endpoint) can return a 500 instead of silently treating a
        // corrupt registry as an empty allowlist.
        $raw = @file_get_contents($registryPath);
        if ($raw === false) {
            $message = sprintf('Approved plugin registry exists but could not be read: %s', $registryPath);
            LoggerUtils::getAppLogger()->error($message);
            self::$cache = null;
            self::$cachePath = null;
            throw new \RuntimeException($message);
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $message = 'Approved plugin registry contains invalid JSON: ' . $e->getMessage();
            LoggerUtils::getAppLogger()->error($message, ['path' => $registryPath]);
            self::$cache = null;
            self::$cachePath = null;
            throw new \RuntimeException($message, 0, $e);
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

        // Remote entries (fetched at login, stored in session) take precedence
        // over the local fallback file so the registry can be updated without a
        // CRM release.
        if (!empty($_SESSION['RemotePluginRegistry']) && is_array($_SESSION['RemotePluginRegistry'])) {
            self::$cache = array_merge(self::$cache, $_SESSION['RemotePluginRegistry']);
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
     * Fetch the approved-plugins registry from the remote URL configured in
     * sPluginRegistryURL and store the validated entries in the session.
     * Called once at login by AuthenticationManager — never in the render path.
     */
    public static function fetchRemoteRegistry(): void
    {
        try {
            $contents = file_get_contents(self::REGISTRY_URL);
            if ($contents === false) {
                LoggerUtils::getAppLogger()->warning('Failed to fetch remote plugin registry', ['url' => self::REGISTRY_URL]);

                return;
            }
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            $entries = $data['plugins'] ?? [];
            if (!is_array($entries)) {
                return;
            }
            $validated = [];
            foreach ($entries as $entry) {
                if (is_array($entry) && self::isValidEntry($entry)) {
                    $validated[$entry['id']] = $entry;
                }
            }
            $_SESSION['RemotePluginRegistry'] = $validated;
            self::$cache = null; // invalidate so next all() call merges fresh data
            self::$cachePath = null;
        } catch (\Exception $e) {
            LoggerUtils::getAppLogger()->warning('Error processing remote plugin registry', ['error' => $e->getMessage()]);
        }
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

        if (!in_array(strtolower((string) $entry['risk']), self::RISK_LEVELS, true)) {
            LoggerUtils::getAppLogger()->warning('Approved plugin entry rejected (invalid risk level)', [
                'entry' => $entry['id'],
                'risk' => $entry['risk'] ?? null,
            ]);

            return false;
        }

        // permissions is optional but, if present, must be an array of known tags.
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
