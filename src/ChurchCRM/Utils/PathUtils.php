<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemURLs;

class PathUtils
{
    /**
     * Resolve $path and verify it is a real file located strictly inside
     * $containingDir — not merely string-prefixed by it, which would wrongly
     * accept a sibling directory (e.g. "/var/logs-secret" prefix-matches
     * "/var/logs"). Returns the resolved real path, or null if $path escapes
     * $containingDir (e.g. via "../" traversal), doesn't exist, or isn't a
     * regular file.
     */
    public static function resolveRealPathWithin(string $path, string $containingDir): ?string
    {
        $containingDirReal = realpath($containingDir);
        if ($containingDirReal === false) {
            return null;
        }

        $pathReal = realpath($path);
        if ($pathReal === false || !is_file($pathReal)) {
            return null;
        }

        if (!str_starts_with($pathReal, $containingDirReal . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $pathReal;
    }

    /**
     * Resolve $candidate against the app's document root and return the real,
     * on-disk path only if it exists as a regular .php file *inside* it. Returns
     * null for anything that escapes the document root (e.g. "../" traversal),
     * isn't a .php file, or doesn't resolve to a real file — so non-PHP files
     * (config, .env, images) can never be dumped raw via require().
     */
    public static function resolveSafeRequirePath(string $candidate): ?string
    {
        if (strtolower(pathinfo($candidate, PATHINFO_EXTENSION)) !== 'php') {
            return null;
        }

        $docRoot = SystemURLs::getDocumentRoot();

        return self::resolveRealPathWithin($docRoot . '/' . $candidate, $docRoot);
    }
}
