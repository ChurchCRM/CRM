<?php

namespace ChurchCRM\Plugin;

use ChurchCRM\Remote\CentralServices;
use ChurchCRM\Utils\LoggerUtils;

/**
 * Queries the list of community plugins that ChurchCRM maintainers have vetted
 * and approved for installation via the URL-based installer.
 *
 * The registry lives at the URL defined in CentralServices::PLUGIN_REGISTRY_URL
 * (the External branch of the CRM repo). It is fetched lazily on first use
 * within a session and then stored in $_SESSION['RemotePluginRegistry'] for
 * the remainder of that session. There is no local fallback — if the remote
 * fetch fails, $_SESSION['RemotePluginRegistry'] is set to an empty array and
 * installs are refused until the registry can be fetched successfully in a
 * later session.
 *
 * Adding an approved plugin requires a PR to the External branch registry file.
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

    /**
     * Return all approved plugin entries keyed by plugin id.
     * Fetches the remote registry on the first call within a login session
     * (lazy — never at login time). Subsequent calls within the same session
     * return the cached result without a network round-trip.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        // Fetch once per login session. array_key_exists distinguishes "never
        // fetched" from "fetched but empty" so a failed fetch doesn't retry
        // on every request within the same session.
        if (!array_key_exists('RemotePluginRegistry', $_SESSION)) {
            self::fetchRemoteRegistry();
        }

        self::$cache = $_SESSION['RemotePluginRegistry'] ?? [];

        return self::$cache;
    }

    /**
     * Look up a single approved plugin by id.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $pluginId): ?array
    {
        return self::all()[$pluginId] ?? null;
    }

    /**
     * Look up an approved plugin by the download URL supplied by the user.
     *
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

    /**
     * Fetch the approved-plugins registry from the remote URL configured in
     * CentralServices::PLUGIN_REGISTRY_URL and store the validated entries in
     * the session. Always sets $_SESSION['RemotePluginRegistry'] (to [] on
     * failure) so the once-per-session gate in all() does not retry on error.
     */
    public static function fetchRemoteRegistry(): void
    {
        try {
            $contents = file_get_contents(self::REGISTRY_URL);
            if ($contents === false) {
                LoggerUtils::getAppLogger()->warning('Failed to fetch remote plugin registry', ['url' => self::REGISTRY_URL]);
                $_SESSION['RemotePluginRegistry'] = [];

                return;
            }
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            $entries = $data['plugins'] ?? [];
            if (!is_array($entries)) {
                $_SESSION['RemotePluginRegistry'] = [];

                return;
            }
            $validated = [];
            foreach ($entries as $entry) {
                if (is_array($entry) && self::isValidEntry($entry)) {
                    $validated[$entry['id']] = $entry;
                }
            }
            $_SESSION['RemotePluginRegistry'] = $validated;
            self::$cache = null;
        } catch (\Exception $e) {
            LoggerUtils::getAppLogger()->warning('Error processing remote plugin registry', ['error' => $e->getMessage()]);
            $_SESSION['RemotePluginRegistry'] = [];
        }
    }

    /**
     * Reset the in-memory cache. Intended for tests or after a remote refresh
     * in the same request.
     */
    public static function reset(): void
    {
        self::$cache = null;
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
