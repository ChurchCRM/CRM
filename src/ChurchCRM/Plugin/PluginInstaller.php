<?php

namespace ChurchCRM\Plugin;

use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\VersionUtils;

/**
 * Installs, uninstalls, and quarantines community plugins.
 *
 * Two install paths are supported:
 *
 * 1. **Verified install** — {@see installFromUrl()}. The URL must appear
 *    in ApprovedPluginRegistry. ChurchCRM maintainers have already
 *    reviewed the zip and pinned its SHA-256. This is the preferred path
 *    and is what the admin UI exposes by default.
 *
 * 2. **Unverified install** — {@see installUnverifiedFromUrl()}. The URL
 *    does NOT have to be in the registry, but the admin must supply the
 *    SHA-256 themselves (from the plugin author's release notes or from
 *    an out-of-band trust channel). This exists so community plugin
 *    developers can test their own plugins end-to-end and so admins can
 *    run experimental or bleeding-edge plugins that aren't ready for
 *    the allowlist yet. Unverified plugins are flagged via
 *    `plugin.{id}.unverified = "1"` in SystemConfig, which
 *    PluginManager surfaces on every admin screen.
 *
 * Every install — verified or unverified — enforces the same runtime
 * safety invariants:
 *
 *   1. HTTPS only, with bounded (20 MB) download.
 *   2. SHA-256 of the downloaded bytes must match the supplied digest
 *      before ZipArchive is touched.
 *   3. Zip walk rejects ZIP Slip, absolute paths, drive letters, control
 *      bytes, >2000 entries, >80 MB uncompressed, disallowed extensions
 *      (`.phar`, `.sh`, `.exe`, `.so`, `.dll`, …), and hidden files
 *      except `.editorconfig` / `.gitattributes`.
 *   4. Exactly one top-level directory whose name equals the plugin id.
 *   5. Extracted plugin.json must declare the same id, and for verified
 *      installs the same version, as the approved entry.
 *   6. Destination community/{id} must not already exist — installs
 *      never overwrite. Use {@see uninstall()} first.
 *
 * Uninstall ({@see uninstall()}) removes the plugin directory, clears
 * `plugin.{id}.*` config keys, and calls the plugin's `uninstall()`
 * lifecycle hook. It refuses to touch core plugins.
 *
 * Neither install path enables the plugin. Admins still have to click
 * Enable after reviewing the extracted files.
 */
class PluginInstaller
{
    /** Hard cap on zip size (bytes) to avoid accidental DoS. */
    public const MAX_ZIP_BYTES = 20 * 1024 * 1024; // 20 MB

    /** Hard cap on uncompressed size to avoid zip bombs. */
    public const MAX_UNCOMPRESSED_BYTES = 80 * 1024 * 1024; // 80 MB

    /** Hard cap on file count inside the zip. */
    public const MAX_ENTRIES = 2000;

    /** Allowed file extensions inside a community plugin zip. */
    private const ALLOWED_EXTENSIONS = [
        'php', 'js', 'mjs', 'ts', 'json', 'css', 'html', 'twig', 'md', 'txt',
        'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico',
        'woff', 'woff2', 'ttf', 'eot',
        'yml', 'yaml', 'xml', 'xliff', 'po', 'mo',
        'sql', 'lock',
    ];

    /** Extensions that are always rejected, even if they look harmless. */
    private const DENIED_EXTENSIONS = ['phar', 'phtml', 'pht', 'sh', 'bat', 'cmd', 'exe', 'so', 'dll'];

    /**
     * Install an approved community plugin from its published zip URL.
     *
     * @param string $pluginsPath Absolute path to src/plugins
     * @param string $downloadUrl The downloadUrl from the approved registry
     *
     * @return array{pluginId: string, version: string, path: string, verified: bool}
     *
     * @throws PluginAlreadyInstalledException if community/{id} already exists
     * @throws \RuntimeException               on any other validation or IO failure
     */
    public static function installFromUrl(string $pluginsPath, string $downloadUrl): array
    {
        $logger = LoggerUtils::getAppLogger();
        $pluginsPath = rtrim($pluginsPath, '/');

        // (1) Registry gate — the URL must be explicitly approved.
        $entry = ApprovedPluginRegistry::findByDownloadUrl($downloadUrl);
        if ($entry === null) {
            $logger->warning(
                'Refused plugin install: URL missing from approved registry or approved registry unavailable/stale',
                ['url' => $downloadUrl]
            );
            throw new \RuntimeException(
                'This plugin could not be verified against the approved plugin list. '
                . 'The URL may not be approved, or the approved plugin registry may be unavailable or stale. '
                . 'Please refresh the registry and retry.'
            );
        }

        $pluginId = (string) $entry['id'];
        $expectedSha = strtolower((string) $entry['sha256']);
        $expectedVersion = (string) $entry['version'];

        // (2) CRM version check — refuse early if the host doesn't meet the
        // plugin's declared minimum.
        if (!empty($entry['minimumCRMVersion'])) {
            $installed = VersionUtils::getInstalledVersion();
            if (version_compare($installed, (string) $entry['minimumCRMVersion'], '<')) {
                throw new \RuntimeException(sprintf(
                    'Plugin requires ChurchCRM %s or newer (installed: %s).',
                    $entry['minimumCRMVersion'],
                    $installed
                ));
            }
        }

        // (3) Destination check — never overwrite.
        $destDir = $pluginsPath . '/community/' . $pluginId;
        if (is_dir($destDir)) {
            throw new PluginAlreadyInstalledException(sprintf(
                'A plugin is already installed at %s. Uninstall it before reinstalling.',
                'community/' . $pluginId
            ));
        }

        // (4) Download to a temporary file.
        $tmpZip = self::downloadToTempFile($downloadUrl);

        try {
            // (5) Size guard.
            $zipBytes = filesize($tmpZip);
            if ($zipBytes === false || $zipBytes <= 0) {
                throw new \RuntimeException('Downloaded zip is empty.');
            }
            if ($zipBytes > self::MAX_ZIP_BYTES) {
                throw new \RuntimeException(sprintf(
                    'Plugin zip is too large (%d bytes, max %d).',
                    $zipBytes,
                    self::MAX_ZIP_BYTES
                ));
            }

            // (6) Checksum verification — MUST happen before ZipArchive.
            $actualSha = hash_file('sha256', $tmpZip);
            if ($actualSha === false || !hash_equals($expectedSha, strtolower($actualSha))) {
                $logger->error('Plugin install checksum mismatch', [
                    'plugin' => $pluginId,
                    'expected' => $expectedSha,
                    'actual' => $actualSha,
                ]);
                throw new \RuntimeException('Plugin zip checksum does not match the approved registry. Installation refused.');
            }

            // (7) Structural validation (paths, extensions, ZIP Slip, bombs).
            $tmpExtractDir = self::makeTempDir();
            try {
                self::extractAndValidate($tmpZip, $tmpExtractDir, $pluginId, $expectedVersion);

                // (8) Atomic move into community/.
                $stagedDir = $tmpExtractDir . '/' . $pluginId;
                if (!is_dir($stagedDir)) {
                    throw new \RuntimeException('Zip did not contain a top-level directory named ' . $pluginId);
                }

                self::ensureDir(dirname($destDir));
                self::moveStagedToDest($stagedDir, $destDir);

                // Record install provenance so boot-time registry sync can
                // later detect if this plugin is still approved.
                self::recordProvenance($pluginId, [
                    'source' => 'registry',
                    'downloadUrl' => $downloadUrl,
                    'sha256' => $expectedSha,
                    'version' => $expectedVersion,
                    'installedAt' => date('c'),
                ]);
                self::clearUnverifiedFlag($pluginId);
                self::clearQuarantine($pluginId);

                $logger->info('Community plugin installed (verified)', [
                    'plugin' => $pluginId,
                    'version' => $expectedVersion,
                    'path' => $destDir,
                ]);

                // Force PluginManager to rediscover on the next request.
                return [
                    'pluginId' => $pluginId,
                    'version' => $expectedVersion,
                    'path' => 'community/' . $pluginId,
                    'verified' => true,
                ];
            } finally {
                self::recursiveDelete($tmpExtractDir);
            }
        } finally {
            @unlink($tmpZip);
        }
    }

    /**
     * Install a community plugin from an arbitrary HTTPS zip URL,
     * **without** requiring the URL to be in ApprovedPluginRegistry.
     *
     * The admin must supply the expected SHA-256 themselves. Every other
     * safety invariant (TLS, size cap, zip-slip, extension allowlist,
     * manifest cross-check, never-overwrite) still applies.
     *
     * Use cases:
     *
     *   - A community plugin developer iterating on their own plugin,
     *     installing successive release builds from their own CI.
     *   - A ChurchCRM admin running an experimental plugin that isn't
     *     on the approved list yet.
     *   - A private/internal plugin that will never be submitted to the
     *     public allowlist (custom integrations for one parish).
     *
     * Installed plugins are flagged as unverified in SystemConfig
     * (`plugin.{id}.unverified = "1"`). PluginManager::getAllPlugins()
     * surfaces this so the admin UI can render a clear banner. An
     * unverified plugin can still be enabled by an admin, but the UI
     * should make it visually distinct from approved plugins.
     *
     * @param string $pluginsPath  Absolute path to src/plugins
     * @param string $downloadUrl  HTTPS URL to the plugin zip
     * @param string $expectedSha  Hex SHA-256 the caller expects (case-insensitive)
     * @param string $declaredId   Plugin id the caller says the zip contains.
     *                             Must match the top-level directory name
     *                             and plugin.json id.
     *
     * @return array{pluginId: string, version: string, path: string, verified: bool}
     *
     * @throws \RuntimeException on any validation or IO failure
     */
    public static function installUnverifiedFromUrl(
        string $pluginsPath,
        string $downloadUrl,
        string $expectedSha,
        string $declaredId
    ): array {
        $logger = LoggerUtils::getAppLogger();
        $pluginsPath = rtrim($pluginsPath, '/');

        // Input sanitisation. These are the only values we trust the
        // caller for, so validate them up front.
        if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $declaredId) || $declaredId === 'messages') {
            throw new \RuntimeException('Invalid plugin id. Must be kebab-case, not reserved.');
        }
        $expectedSha = strtolower(trim($expectedSha));
        if (!preg_match('/^[a-f0-9]{64}$/', $expectedSha)) {
            throw new \RuntimeException('SHA-256 must be a 64-character hexadecimal string.');
        }

        // If the admin happens to point us at a URL that IS in the
        // registry, short-circuit into the verified path so they get
        // the full risk/permissions display.
        $registryEntry = ApprovedPluginRegistry::findByDownloadUrl($downloadUrl);
        if ($registryEntry !== null) {
            $logger->info('Unverified install short-circuited into verified path', [
                'url' => $downloadUrl,
                'registryId' => $registryEntry['id'] ?? null,
            ]);

            return self::installFromUrl($pluginsPath, $downloadUrl);
        }

        // Never overwrite an existing install.
        $destDir = $pluginsPath . '/community/' . $declaredId;
        if (is_dir($destDir)) {
            throw new PluginAlreadyInstalledException(sprintf(
                'A plugin is already installed at %s. Uninstall it before reinstalling.',
                'community/' . $declaredId
            ));
        }

        $tmpZip = self::downloadToTempFile($downloadUrl);

        try {
            $zipBytes = filesize($tmpZip);
            if ($zipBytes === false || $zipBytes <= 0) {
                throw new \RuntimeException('Downloaded zip is empty.');
            }
            if ($zipBytes > self::MAX_ZIP_BYTES) {
                throw new \RuntimeException(sprintf(
                    'Plugin zip is too large (%d bytes, max %d).',
                    $zipBytes,
                    self::MAX_ZIP_BYTES
                ));
            }

            $actualSha = hash_file('sha256', $tmpZip);
            if ($actualSha === false || !hash_equals($expectedSha, strtolower($actualSha))) {
                $logger->warning('Unverified install checksum mismatch', [
                    'plugin' => $declaredId,
                    'expected' => $expectedSha,
                    'actual' => $actualSha,
                ]);
                throw new \RuntimeException('Plugin zip checksum does not match the supplied SHA-256. Installation refused.');
            }

            $tmpExtractDir = self::makeTempDir();
            try {
                // Version cross-check is skipped for unverified installs
                // — the admin has not declared an expected version. Pass
                // null to extractAndValidate().
                self::extractAndValidate($tmpZip, $tmpExtractDir, $declaredId, null);

                $stagedDir = $tmpExtractDir . '/' . $declaredId;
                if (!is_dir($stagedDir)) {
                    throw new \RuntimeException('Zip did not contain a top-level directory named ' . $declaredId);
                }

                // Read the installed version for provenance tracking /
                // UI display. The extractAndValidate() step already
                // confirmed plugin.json exists and has a matching id.
                $manifestPath = $stagedDir . '/plugin.json';
                $manifest = json_decode((string) @file_get_contents($manifestPath), true);
                $installedVersion = is_array($manifest) ? (string) ($manifest['version'] ?? 'unknown') : 'unknown';

                self::ensureDir(dirname($destDir));
                self::moveStagedToDest($stagedDir, $destDir);

                self::recordProvenance($declaredId, [
                    'source' => 'unverified-url',
                    'downloadUrl' => $downloadUrl,
                    'sha256' => $expectedSha,
                    'version' => $installedVersion,
                    'installedAt' => date('c'),
                ]);
                self::setUnverifiedFlag($declaredId);
                self::clearQuarantine($declaredId);

                $logger->warning('Community plugin installed (UNVERIFIED)', [
                    'plugin' => $declaredId,
                    'version' => $installedVersion,
                    'url' => $downloadUrl,
                    'path' => $destDir,
                ]);

                return [
                    'pluginId' => $declaredId,
                    'version' => $installedVersion,
                    'path' => 'community/' . $declaredId,
                    'verified' => false,
                ];
            } finally {
                self::recursiveDelete($tmpExtractDir);
            }
        } finally {
            @unlink($tmpZip);
        }
    }

    /**
     * Remove a community plugin from disk and from SystemConfig.
     *
     * Steps, in order:
     *   1. Refuse if the plugin id is empty, reserved, or points at a
     *      core plugin directory. Core plugins are never deleted.
     *   2. Disable the plugin (calls deactivate() + clears
     *      plugin.{id}.enabled).
     *   3. Call the plugin's uninstall() lifecycle hook so it can clean
     *      up any external state (webhooks it registered with a
     *      third-party service, for example).
     *   4. Recursively delete src/plugins/community/{id}.
     *   5. Clear every plugin.{id}.* key from SystemConfig — this is
     *      what removes stored credentials, enablement state, and any
     *      settings the plugin wrote.
     *
     * Refuses to touch anything under src/plugins/core/.
     *
     * @return array{pluginId: string, removedKeys: list<string>}
     */
    public static function uninstall(string $pluginsPath, string $pluginId): array
    {
        $logger = LoggerUtils::getAppLogger();
        $pluginsPath = rtrim($pluginsPath, '/');

        if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $pluginId)) {
            throw new \RuntimeException('Invalid plugin id.');
        }

        // Refuse core plugins.
        $corePath = $pluginsPath . '/core/' . $pluginId;
        if (is_dir($corePath)) {
            throw new \RuntimeException(
                'Core plugins cannot be uninstalled. Disable them instead.'
            );
        }

        $communityPath = $pluginsPath . '/community/' . $pluginId;
        // It's OK for the directory to be missing — config keys may
        // still need cleanup if a previous uninstall was interrupted.

        // Call the lifecycle hooks if the plugin can still be loaded.
        try {
            $plugin = PluginManager::getPlugin($pluginId);
            if ($plugin !== null) {
                try {
                    $plugin->deactivate();
                } catch (\Throwable $e) {
                    $logger->warning('Plugin deactivate() threw during uninstall', [
                        'plugin' => $pluginId,
                        'error' => $e->getMessage(),
                    ]);
                }
                try {
                    $plugin->uninstall();
                } catch (\Throwable $e) {
                    $logger->warning('Plugin uninstall() threw; continuing', [
                        'plugin' => $pluginId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Plugin load itself failed — still continue with removal.
            $logger->warning('Could not load plugin during uninstall', [
                'plugin' => $pluginId,
                'error' => $e->getMessage(),
            ]);
        }

        // Delete on-disk files.
        if (is_dir($communityPath)) {
            self::recursiveDelete($communityPath);
        }

        // Clear all plugin.{id}.* config keys.
        $removedKeys = self::clearPluginConfig($pluginId);

        $logger->info('Community plugin uninstalled', [
            'plugin' => $pluginId,
            'path' => $communityPath,
            'clearedConfigKeys' => $removedKeys,
        ]);

        return [
            'pluginId' => $pluginId,
            'removedKeys' => $removedKeys,
        ];
    }

    /**
     * Record install provenance in SystemConfig as plugin.{id}.provenance
     * (stored as a JSON blob). PluginManager uses this at boot to
     * reconcile installed plugins against the current approved registry.
     *
     * @param array<string, scalar|null> $data
     */
    private static function recordProvenance(string $pluginId, array $data): void
    {
        try {
            \ChurchCRM\dto\SystemConfig::setValue(
                "plugin.{$pluginId}.provenance",
                (string) json_encode($data)
            );
        } catch (\Throwable $e) {
            LoggerUtils::getAppLogger()->warning('Could not record plugin provenance', [
                'plugin' => $pluginId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function setUnverifiedFlag(string $pluginId): void
    {
        try {
            \ChurchCRM\dto\SystemConfig::setValue("plugin.{$pluginId}.unverified", '1');
        } catch (\Throwable $e) {
            LoggerUtils::getAppLogger()->warning('Could not set unverified flag', [
                'plugin' => $pluginId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function clearUnverifiedFlag(string $pluginId): void
    {
        try {
            \ChurchCRM\dto\SystemConfig::setValue("plugin.{$pluginId}.unverified", '0');
        } catch (\Throwable $e) {
            // Non-fatal — unverified state is advisory.
        }
    }

    private static function clearQuarantine(string $pluginId): void
    {
        try {
            \ChurchCRM\dto\SystemConfig::setValue("plugin.{$pluginId}.quarantined", '');
            \ChurchCRM\dto\SystemConfig::setValue("plugin.{$pluginId}.quarantineReason", '');
        } catch (\Throwable $e) {
            // Non-fatal.
        }
    }

    /**
     * Remove every `plugin.{pluginId}.*` row from SystemConfig.
     *
     * We rely on Propel directly here because SystemConfig doesn't
     * expose a prefix-delete API. This is the one place in the plugin
     * system that touches config storage without going through
     * SystemConfig::setValue().
     *
     * @return list<string> The keys that were removed.
     */
    private static function clearPluginConfig(string $pluginId): array
    {
        $prefix = "plugin.{$pluginId}.";
        $removed = [];
        try {
            // config_cfg.cfg_name is exposed as `Name` via Propel
            // (phpName="Name" in orm/schema.xml). Use filterByName with
            // Criteria::LIKE for the prefix match.
            $rows = \ChurchCRM\model\ChurchCRM\ConfigQuery::create()
                ->filterByName($prefix . '%', \Propel\Runtime\ActiveQuery\Criteria::LIKE)
                ->find();

            foreach ($rows as $row) {
                $removed[] = (string) $row->getName();
                $row->delete();
            }
        } catch (\Throwable $e) {
            LoggerUtils::getAppLogger()->warning('Could not clear plugin config rows', [
                'plugin' => $pluginId,
                'error' => $e->getMessage(),
            ]);
        }

        return $removed;
    }

    /**
     * Download the URL to a temporary file, verifying TLS, following
     * redirects only inside HTTPS, and bounding response size.
     */
    private static function downloadToTempFile(string $url): string
    {
        if (!preg_match('/^https:\/\//i', $url)) {
            throw new \RuntimeException('Plugin downloads must use HTTPS.');
        }
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('cURL is required to install plugins by URL.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'ccrm_plugin_');
        if ($tmp === false) {
            throw new \RuntimeException('Failed to allocate temporary file for plugin download.');
        }

        $fh = fopen($tmp, 'wb');
        if ($fh === false) {
            @unlink($tmp);
            throw new \RuntimeException('Failed to open temporary file for plugin download.');
        }

        $maxBytes = self::MAX_ZIP_BYTES;
        $bytesReceived = 0;
        $tooLarge = false;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 4);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ChurchCRM/' . VersionUtils::getInstalledVersion());
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($_ch, string $data) use ($fh, &$bytesReceived, &$tooLarge, $maxBytes): int {
            $bytesReceived += strlen($data);
            if ($bytesReceived > $maxBytes) {
                $tooLarge = true;

                return 0; // abort download
            }
            $written = fwrite($fh, $data);

            return $written === false ? 0 : $written;
        });

        $ok = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fh);

        if ($tooLarge) {
            @unlink($tmp);
            throw new \RuntimeException('Plugin zip exceeds the ' . self::MAX_ZIP_BYTES . ' byte download limit.');
        }
        if ($ok === false || $errno !== 0) {
            @unlink($tmp);
            throw new \RuntimeException('Plugin download failed: ' . ($error ?: 'unknown cURL error'));
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            @unlink($tmp);
            throw new \RuntimeException('Plugin download failed: HTTP ' . $httpCode);
        }

        return $tmp;
    }

    /**
     * Open the zip, enforce structural invariants, and extract to $destRoot.
     * After this method returns, $destRoot/{pluginId}/ contains the payload.
     */
    private static function extractAndValidate(string $zipPath, string $destRoot, string $pluginId, ?string $expectedVersion): void
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('The PHP zip extension is required to install plugins.');
        }

        $zip = new \ZipArchive();
        $openResult = $zip->open($zipPath);
        if ($openResult !== true) {
            throw new \RuntimeException('Could not open plugin zip (code ' . (int) $openResult . ').');
        }

        try {
            if ($zip->numFiles > self::MAX_ENTRIES) {
                throw new \RuntimeException('Plugin zip has too many files (' . $zip->numFiles . ').');
            }

            $totalUncompressed = 0;
            $topLevel = null;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    throw new \RuntimeException('Unable to stat zip entry ' . $i . '.');
                }

                $name = (string) $stat['name'];
                $totalUncompressed += (int) $stat['size'];

                if ($totalUncompressed > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new \RuntimeException('Plugin zip uncompressed size exceeds the limit (possible zip bomb).');
                }

                // Reject symlink entries before extractTo() materialises them.
                // Unix mode lives in the high 16 bits of external_attr when
                // the entry was added on a Unix host; 0xA000 == S_IFLNK.
                if (isset($stat['external_attr'])) {
                    $unixMode = ((int) $stat['external_attr'] >> 16) & 0xFFFF;
                    if (($unixMode & 0xF000) === 0xA000) {
                        throw new \RuntimeException('Zip entry is a symlink: ' . $name);
                    }
                }

                self::assertSafeZipEntry($name);

                // Every entry must live under a single top-level directory.
                $firstSegment = explode('/', $name, 2)[0];
                if ($topLevel === null) {
                    $topLevel = $firstSegment;
                } elseif ($topLevel !== $firstSegment) {
                    throw new \RuntimeException('Plugin zip must contain exactly one top-level directory.');
                }

                // Only validate extensions on files, not directory entries.
                if (!str_ends_with($name, '/')) {
                    self::assertAllowedExtension($name);
                }
            }

            if ($topLevel === null || $topLevel === '') {
                throw new \RuntimeException('Plugin zip is empty.');
            }
            if ($topLevel !== $pluginId) {
                throw new \RuntimeException(sprintf(
                    'Plugin zip top-level directory "%s" does not match approved plugin id "%s".',
                    $topLevel,
                    $pluginId
                ));
            }

            self::ensureDir($destRoot);
            if (!$zip->extractTo($destRoot)) {
                throw new \RuntimeException('Failed to extract plugin zip.');
            }
        } finally {
            $zip->close();
        }

        // Post-extract: verify plugin.json matches the registry entry.
        $manifestPath = $destRoot . '/' . $pluginId . '/plugin.json';
        if (!is_file($manifestPath)) {
            throw new \RuntimeException('Plugin zip is missing plugin.json.');
        }

        try {
            $manifest = json_decode((string) file_get_contents($manifestPath), true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Plugin manifest is not valid JSON: ' . $e->getMessage());
        }

        if (!is_array($manifest)) {
            throw new \RuntimeException('Plugin manifest must be a JSON object.');
        }
        if (($manifest['id'] ?? null) !== $pluginId) {
            throw new \RuntimeException('Plugin manifest id does not match declared plugin id.');
        }
        // Version cross-check only applies to verified (registry) installs.
        // Unverified installs pass null here because the admin has not
        // committed to a specific version number up front.
        if ($expectedVersion !== null && ($manifest['version'] ?? null) !== $expectedVersion) {
            throw new \RuntimeException('Plugin manifest version does not match approved version.');
        }
        if (($manifest['type'] ?? 'community') !== 'community') {
            throw new \RuntimeException('URL-installed plugins must declare type="community".');
        }

        // Defence in depth: even after the per-entry symlink check, walk
        // the extracted tree and reject any symlinks the kernel created.
        // A future ZipArchive change or a quirk in a non-Unix-host zip
        // could otherwise sneak past the in-zip mode check.
        self::assertNoSymlinksUnder($destRoot . '/' . $pluginId);
    }

    /**
     * Reject path traversal, absolute paths, Windows-style drive paths, and
     * hidden segments (names beginning with `.` other than the documented
     * documentation files allowed by {@see assertAllowedExtension}).
     */
    private static function assertSafeZipEntry(string $name): void
    {
        if ($name === '') {
            throw new \RuntimeException('Zip entry has empty name.');
        }
        // Normalise backslashes for the check; extractTo will translate them
        // on Windows hosts but validation must still run.
        $normalised = str_replace('\\', '/', $name);

        if (str_starts_with($normalised, '/')) {
            throw new \RuntimeException('Zip entry uses absolute path: ' . $name);
        }
        if (preg_match('/^[a-zA-Z]:\//', $normalised)) {
            throw new \RuntimeException('Zip entry uses drive-letter path: ' . $name);
        }
        // Reject `..`, `.`, and hidden directory segments. The leaf-file
        // hidden-name allowlist (.editorconfig, .gitattributes) is enforced
        // separately by assertAllowedExtension(); here we only block hidden
        // *directories* from sneaking through the dir-entry skip path.
        $segments = explode('/', $normalised);
        $lastIndex = count($segments) - 1;
        foreach ($segments as $i => $segment) {
            if ($segment === '..' || $segment === '.') {
                throw new \RuntimeException('Zip entry contains traversal segment: ' . $name);
            }
            // Skip empty trailing segment from "dir/" entries.
            if ($segment === '') {
                continue;
            }
            // Block hidden directory segments (anything not the leaf name).
            if ($i !== $lastIndex && $segment[0] === '.') {
                throw new \RuntimeException('Zip entry contains hidden directory segment: ' . $name);
            }
        }
        // No null bytes, no control characters.
        if (preg_match('/[\x00-\x1F]/', $normalised)) {
            throw new \RuntimeException('Zip entry contains control characters.');
        }
    }

    /**
     * Recursively walk an extracted plugin tree and throw if any entry is
     * a symlink. Belt-and-braces companion to the in-zip mode check.
     */
    private static function assertNoSymlinksUnder(string $root): void
    {
        if (!is_dir($root)) {
            return;
        }
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iter as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isLink()) {
                throw new \RuntimeException('Extracted plugin contains a symlink: ' . $item->getPathname());
            }
        }
    }

    private static function assertAllowedExtension(string $name): void
    {
        $basename = basename($name);
        // Refuse hidden files that aren't plainly documentation.
        if ($basename !== '' && $basename[0] === '.' && !in_array($basename, ['.editorconfig', '.gitattributes'], true)) {
            throw new \RuntimeException('Zip entry is a hidden file: ' . $name);
        }

        $ext = strtolower((string) pathinfo($basename, PATHINFO_EXTENSION));
        if ($ext === '') {
            // Allow extension-less LICENSE / README / Makefile style files.
            if (in_array(strtoupper($basename), ['LICENSE', 'README', 'CHANGELOG', 'NOTICE'], true)) {
                return;
            }
            throw new \RuntimeException('Zip entry has no extension: ' . $name);
        }
        if (in_array($ext, self::DENIED_EXTENSIONS, true)) {
            throw new \RuntimeException('Zip entry has disallowed extension: ' . $name);
        }
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \RuntimeException('Zip entry extension not permitted: ' . $name);
        }
    }

    private static function makeTempDir(): string
    {
        $base = sys_get_temp_dir() . '/ccrm_plugin_' . bin2hex(random_bytes(8));
        self::ensureDir($base);

        return $base;
    }

    private static function ensureDir(string $path): void
    {
        if (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) {
            throw new \RuntimeException('Failed to create directory: ' . $path);
        }
    }

    /**
     * Move the staged extraction directory into its final community/{id}
     * location. Tries `rename()` first; if that fails (typically because
     * the temp dir and the install dir are on different filesystems) falls
     * back to a recursive copy. If anything throws during the copy fallback
     * the partial destination is removed so the install remains atomic from
     * the caller's perspective — a future install attempt won't be blocked
     * by a half-populated directory and the on-disk state stays clean.
     */
    private static function moveStagedToDest(string $stagedDir, string $destDir): void
    {
        if (@rename($stagedDir, $destDir)) {
            return;
        }
        try {
            self::recursiveCopy($stagedDir, $destDir);
        } catch (\Throwable $e) {
            self::recursiveDelete($destDir);
            throw $e;
        }
    }

    private static function recursiveCopy(string $from, string $to): void
    {
        self::ensureDir($to);
        /** @var \RecursiveIteratorIterator<\RecursiveDirectoryIterator> $iter */
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iter as $item) {
            /** @var \SplFileInfo $item */
            $target = $to . '/' . $iter->getSubPathname();
            // Refuse to follow symlinks during copy. PHP's copy() resolves
            // them, which would let a malicious zip read arbitrary files
            // through the cross-filesystem copy fallback path.
            if ($item->isLink()) {
                throw new \RuntimeException('Refusing to copy symlink during install: ' . $item->getPathname());
            }
            if ($item->isDir()) {
                self::ensureDir($target);
            } else {
                if (!@copy($item->getPathname(), $target)) {
                    throw new \RuntimeException('Failed to copy plugin file: ' . $item->getPathname());
                }
            }
        }
    }

    private static function recursiveDelete(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path)) {
                @unlink($path);
            }

            return;
        }
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($path);
    }
}
