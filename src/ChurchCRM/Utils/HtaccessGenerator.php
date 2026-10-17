<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\LoggerUtils;

/**
 * Generates the correct RewriteBase directive in all routing .htaccess files
 * based on the configured $sRootPath.
 *
 * This prevents in-app upgrades from breaking subdirectory installs that
 * require a manual RewriteBase (see #10563).
 *
 * Routing files are discovered by scanning for .htaccess files that contain
 * "RewriteEngine On". Deny-only files (cli/, logs/, Include/, Images/*, etc.)
 * are skipped automatically because they do not enable the rewrite engine.
 *
 * Security notes:
 * - Only writes to .htaccess files found under the document root that already
 *   contain RewriteEngine On.
 * - The RewriteBase value is strictly sanitized (no .., no newlines, limited charset).
 * - Failures are logged and non-fatal.
 */
class HtaccessGenerator
{
    /**
     * Generate RewriteBase in all routing .htaccess files.
     *
     * @param string|null $rootPath Override for $sRootPath (used during setup before SystemURLs is fully ready)
     * @param string|null $docRoot  Override for document root
     */
    public static function generateAll(?string $rootPath = null, ?string $docRoot = null): void
    {
        $logger = LoggerUtils::getAppLogger();

        if ($rootPath === null) {
            $rootPath = SystemURLs::getRootPath() ?? '';
        }
        if ($docRoot === null) {
            $docRoot = SystemURLs::getDocumentRoot() ?? '';
        }

        if ($docRoot === '' || !is_dir($docRoot)) {
            $logger->warning('HtaccessGenerator: document root not available, skipping');
            return;
        }

        $safeRoot = self::sanitizeRootPath($rootPath);
        if ($safeRoot === null) {
            $logger->warning('HtaccessGenerator: $sRootPath failed sanitization, using /', [
                'original' => $rootPath,
            ]);
            $safeRoot = '/';
        }

        $files = self::findRoutingHtaccessFiles($docRoot);
        foreach ($files as $file) {
            $relDir = self::relativeModuleDir($docRoot, $file);
            $rewriteBase = $safeRoot === '/'
                ? ($relDir !== '' ? '/' . $relDir : '/')
                : rtrim($safeRoot, '/') . ($relDir !== '' ? '/' . $relDir : '');

            self::writeRewriteBase($file, $rewriteBase, $logger);
        }
    }

    /**
     * Find every .htaccess under $docRoot that enables the rewrite engine.
     *
     * @return string[] absolute paths
     */
    private static function findRoutingHtaccessFiles(string $docRoot): array
    {
        $found = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($docRoot, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $info) {
            if (!$info->isFile() || $info->getFilename() !== '.htaccess') {
                continue;
            }

            $path = $info->getPathname();
            $content = @file_get_contents($path);
            if ($content !== false && preg_match('/RewriteEngine\s+On/i', $content)) {
                $found[] = $path;
            }
        }

        sort($found);
        return $found;
    }

    /**
     * Relative directory of an .htaccess file from the document root.
     * Empty string for the root .htaccess.
     */
    private static function relativeModuleDir(string $docRoot, string $htaccessPath): string
    {
        $dir = dirname($htaccessPath);
        $rel = ltrim(substr($dir, strlen(rtrim($docRoot, '/'))), '/');
        return $rel === '.' ? '' : $rel;
    }

    /**
     * Sanitize a root path for use as RewriteBase.
     * Allows only a leading / followed by safe characters. Rejects .., newlines, absolute paths, etc.
     */
    private static function sanitizeRootPath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '' || $path === '/') {
            return '/';
        }

        // Must start with / and contain only safe chars. No .. segments.
        if (!preg_match('#^/[a-zA-Z0-9_./-]*$#', $path)) {
            return null;
        }
        if (str_contains($path, '..') || str_contains($path, "\0") || str_contains($path, "\n") || str_contains($path, "\r")) {
            return null;
        }

        // Collapse multiple slashes and remove trailing slash
        $path = preg_replace('#/+#', '/', $path);
        return rtrim($path, '/') ?: '/';
    }

    private static function writeRewriteBase(string $file, string $rewriteBase, $logger): void
    {
        if (!is_file($file) || !is_writable($file)) {
            $logger->warning('HtaccessGenerator: file not writable', ['file' => $file]);
            return;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            $logger->error('HtaccessGenerator: failed to read file', ['file' => $file]);
            return;
        }

        // Remove any existing RewriteBase lines (commented or not)
        $content = preg_replace('/^\s*#?\s*RewriteBase\s+.*$/m', '', $content);

        // Insert a fresh RewriteBase immediately after RewriteEngine On
        $directive = "RewriteBase {$rewriteBase}\n";
        $newContent = preg_replace(
            '/(RewriteEngine\s+On\s*\n)/i',
            "$1\n{$directive}",
            $content,
            1
        );

        if ($newContent === null || $newContent === $content) {
            $logger->warning('HtaccessGenerator: failed to insert RewriteBase', ['file' => $file]);
            return;
        }

        if (@file_put_contents($file, $newContent) === false) {
            $logger->error('HtaccessGenerator: write failed', ['file' => $file]);
        } else {
            $logger->info('HtaccessGenerator: set RewriteBase', [
                'file' => $file,
                'RewriteBase' => $rewriteBase,
            ]);
        }
    }
}
