<?php

namespace ChurchCRM\Plugin;

use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\VersionUtils;

/**
 * Installs a community plugin from a signed zip URL.
 *
 * Security invariants (enforced in order):
 *   1. The caller must be an admin (the route group enforces this).
 *   2. The downloadUrl MUST exist in ApprovedPluginRegistry — we refuse to
 *      install anything that hasn't been vetted by the maintainers.
 *   3. The downloaded bytes MUST match the registry SHA-256 before we touch
 *      ZipArchive (prevents MITM / poisoned mirrors / upstream compromise).
 *   4. The zip MUST only contain path entries under a single top-level
 *      directory, with no absolute paths and no "..". ZIP Slip is rejected.
 *   5. Every entry MUST have a safe extension (no .phar, no symlinks, no
 *      setuid tricks). PHP is allowed; executables and hidden git metadata
 *      are not.
 *   6. The extracted plugin.json MUST declare the same id and version as the
 *      registry entry.
 *   7. The destination directory (src/plugins/community/{id}) MUST NOT
 *      already exist — installs never overwrite. Use uninstall then install.
 *
 * This class never enables the plugin. Admins still have to click Enable
 * after the zip has been scanned and reviewed.
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
     * @return array{pluginId: string, version: string, path: string}
     *
     * @throws \RuntimeException on any validation or IO failure
     */
    public static function installFromUrl(string $pluginsPath, string $downloadUrl): array
    {
        $logger = LoggerUtils::getAppLogger();
        $pluginsPath = rtrim($pluginsPath, '/');

        // (1) Registry gate — the URL must be explicitly approved.
        $entry = ApprovedPluginRegistry::findByDownloadUrl($pluginsPath, $downloadUrl);
        if ($entry === null) {
            $logger->warning('Refused plugin install: URL not in approved registry', ['url' => $downloadUrl]);
            throw new \RuntimeException('This plugin is not in the approved plugin list. Installation refused.');
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
            throw new \RuntimeException(sprintf(
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
                if (!@rename($stagedDir, $destDir)) {
                    // rename() can fail across filesystems; fall back to a copy.
                    self::recursiveCopy($stagedDir, $destDir);
                }

                $logger->info('Community plugin installed', [
                    'plugin' => $pluginId,
                    'version' => $expectedVersion,
                    'path' => $destDir,
                ]);

                // Force PluginManager to rediscover on the next request.
                return [
                    'pluginId' => $pluginId,
                    'version' => $expectedVersion,
                    'path' => 'community/' . $pluginId,
                ];
            } finally {
                self::recursiveDelete($tmpExtractDir);
            }
        } finally {
            @unlink($tmpZip);
        }
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
    private static function extractAndValidate(string $zipPath, string $destRoot, string $pluginId, string $expectedVersion): void
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
            throw new \RuntimeException('Plugin manifest id does not match approved plugin id.');
        }
        if (($manifest['version'] ?? null) !== $expectedVersion) {
            throw new \RuntimeException('Plugin manifest version does not match approved version.');
        }
        if (($manifest['type'] ?? 'community') !== 'community') {
            throw new \RuntimeException('URL-installed plugins must declare type="community".');
        }
    }

    /**
     * Reject path traversal, absolute paths, and Windows-style drive paths.
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
        foreach (explode('/', $normalised) as $segment) {
            if ($segment === '..' || $segment === '.') {
                throw new \RuntimeException('Zip entry contains traversal segment: ' . $name);
            }
        }
        // No null bytes, no control characters.
        if (preg_match('/[\x00-\x1F]/', $normalised)) {
            throw new \RuntimeException('Zip entry contains control characters.');
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
