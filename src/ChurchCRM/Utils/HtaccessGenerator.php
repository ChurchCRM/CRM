<?php

namespace ChurchCRM\Utils;

/**
 * Writes the correct RewriteBase into every routing .htaccess under the document root.
 *
 * Why: an in-app upgrade replaces the .htaccess files shipped in the release, wiping any
 * manual RewriteBase that subdirectory installs need (#10563). Regenerating it from the
 * configured $sRootPath after install/upgrade makes routing survive upgrades.
 */
class HtaccessGenerator
{
    /** Module directories (relative to the document root) whose .htaccess has an active RewriteEngine On. '' = root. */
    public const ROUTING_MODULES = [
        '',
        'admin',
        'api',
        'event',
        'external',
        'finance',
        'fundraiser',
        'groups',
        'kiosk',
        'ministries',
        'people',
        'plugins',
        'portal',
        'session',
        'setup',
        'v2',
    ];

    /**
     * @return array<string, bool> relative module path => whether the file is up to date
     */
    public static function generateAll(string $rootPath, ?string $docRoot = null): array
    {
        $results = [];
        $rootPath = self::normalizeRootPath($rootPath);
        if ($rootPath === null) {
            self::log('warning', 'HtaccessGenerator: invalid root path, RewriteBase not generated');

            return $results;
        }

        $docRoot ??= dirname(__DIR__, 2);
        foreach (self::ROUTING_MODULES as $module) {
            $results[$module] = self::generateFor($docRoot, $module, $rootPath);
        }

        return $results;
    }

    /**
     * Returns the root path without trailing slash ('' for web root), or null if it is unsafe.
     */
    public static function normalizeRootPath(string $rootPath): ?string
    {
        $rootPath = rtrim($rootPath, '/');
        if ($rootPath === '') {
            return '';
        }
        if (!preg_match('#^/[a-zA-Z0-9_\-./]*$#', $rootPath) || preg_match('#(^|/)\.\.?(/|$)|//#', $rootPath)) {
            return null;
        }

        return $rootPath;
    }

    public static function buildRewriteBase(string $normalizedRootPath, string $module): string
    {
        return $normalizedRootPath . '/' . $module;
    }

    /**
     * Pure transformation: strip existing RewriteBase lines (commented or not) and insert a fresh one
     * right after the first RewriteEngine On. Returns null when the content has no RewriteEngine On.
     */
    public static function applyRewriteBase(string $content, string $rewriteBase): ?string
    {
        $eol = str_contains($content, "\r\n") ? "\r\n" : "\n";
        $lines = preg_split('/\r\n|\n/', $content);
        $lines = array_values(array_filter($lines, fn ($l) => !preg_match('/^\s*#?\s*RewriteBase(\s|$)/i', $l)));

        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*RewriteEngine\s+On\s*$/i', $line)) {
                array_splice($lines, $i + 1, 0, ['RewriteBase ' . $rewriteBase]);

                return implode($eol, $lines);
            }
        }

        return null;
    }

    private static function generateFor(string $docRoot, string $module, string $rootPath): bool
    {
        $file = rtrim($docRoot, '/\\') . ($module === '' ? '' : '/' . $module) . '/.htaccess';
        try {
            if (!is_file($file) || !is_readable($file)) {
                self::log('warning', 'HtaccessGenerator: skipped (missing or unreadable) ' . $file);

                return false;
            }
            $content = file_get_contents($file);
            $updated = $content === false ? null : self::applyRewriteBase($content, self::buildRewriteBase($rootPath, $module));
            if ($updated === null) {
                self::log('warning', 'HtaccessGenerator: no RewriteEngine On found in ' . $file);

                return false;
            }
            if ($updated === $content) {
                return true;
            }
            if (!is_writable($file)) {
                self::log('warning', 'HtaccessGenerator: not writable ' . $file);

                return false;
            }
            if (file_put_contents($file, $updated, LOCK_EX) === false) {
                self::log('error', 'HtaccessGenerator: failed writing ' . $file);

                return false;
            }
            self::log('info', 'HtaccessGenerator: updated RewriteBase in ' . $file);

            return true;
        } catch (\Throwable $e) {
            self::log('error', 'HtaccessGenerator: ' . $e->getMessage());

            return false;
        }
    }

    private static function log(string $level, string $message): void
    {
        try {
            LoggerUtils::getAppLogger()?->$level($message);
        } catch (\Throwable) {
            // Logging may be unavailable during setup (no Config.php yet); never fatal.
        }
    }
}
