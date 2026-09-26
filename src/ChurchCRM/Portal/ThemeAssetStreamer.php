<?php

namespace ChurchCRM\Portal;

use ChurchCRM\dto\SystemURLs;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Streams a theme's static files at `GET /portal/theme/{name}/{path}`.
 *
 * `Include/` is deny-all at the web-server level on purpose (GHSA-mp2w-4q3r-ppx7)
 * and must stay that way, so theme assets are served by the application instead
 * (decision P5). Only the allow-listed extensions ever come out, only from a real
 * theme folder, and never through a path that escapes it — which also means a
 * theme's templates and any stray file in it are not web-readable.
 */
class ThemeAssetStreamer
{
    /** One year, matching the `?v=<filemtime>` query the templates append. */
    public const CACHE_MAX_AGE = 31536000;

    /**
     * The only extensions a theme can publish. Deliberately short: stylesheets,
     * scripts, images and fonts. No PHP, no templates, no JSON.
     *
     * @var array<string, string>
     */
    private const ALLOWED_TYPES = [
        'css' => 'text/css',
        'js' => 'text/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
    ];

    /**
     * A relative path inside a theme folder: no leading slash, no drive letter,
     * no `.` or `..` segment, no NUL byte, no backslash.
     */
    public static function isSafeRelativePath(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')) {
            return false;
        }
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1) {
            return false;
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    /**
     * The extension's content type, or null when the extension is not published.
     */
    public static function getContentType(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return self::ALLOWED_TYPES[$extension] ?? null;
    }

    /**
     * Resolve one theme asset to an absolute path on disk, or null when the
     * theme, the path or the extension is not acceptable.
     */
    public static function resolve(string $themeName, string $path): ?string
    {
        if (!self::isSafeRelativePath($path) || self::getContentType($path) === null) {
            return null;
        }

        $themePath = ThemeManager::getThemePath($themeName);
        if ($themePath === null) {
            return null;
        }

        $candidate = $themePath . '/' . $path;
        $realCandidate = realpath($candidate);
        $realThemePath = realpath($themePath);
        if ($realCandidate === false || $realThemePath === false || !is_file($realCandidate)) {
            return null;
        }

        // Belt and braces: even with a clean relative path, a symlink inside the
        // theme folder must not be able to point outside it.
        if (!str_starts_with($realCandidate, $realThemePath . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realCandidate;
    }

    /**
     * The URL a template uses for a theme file, cache-busted with the file's
     * modification time. When the active theme does not carry the file but the
     * default theme does, the default theme's URL is returned instead.
     */
    public static function getAssetUrl(string $themeName, string $path): string
    {
        $resolved = self::resolve($themeName, $path);
        $resolvedTheme = $themeName;
        if ($resolved === null && $themeName !== ThemeManager::DEFAULT_THEME) {
            $resolved = self::resolve(ThemeManager::DEFAULT_THEME, $path);
            $resolvedTheme = ThemeManager::DEFAULT_THEME;
        }

        $url = SystemURLs::getRootPath() . '/portal/theme/' . rawurlencode($resolvedTheme) . '/'
            . implode('/', array_map('rawurlencode', explode('/', $path)));

        if ($resolved === null) {
            return $url;
        }

        return $url . '?v=' . filemtime($resolved);
    }

    /**
     * Whether the active theme (or the default theme it falls back to) actually
     * carries the file — templates use this to skip an optional `theme.js`.
     */
    public static function hasAsset(string $themeName, string $path): bool
    {
        if (self::resolve($themeName, $path) !== null) {
            return true;
        }

        return $themeName !== ThemeManager::DEFAULT_THEME
            && self::resolve(ThemeManager::DEFAULT_THEME, $path) !== null;
    }

    /**
     * Write the asset onto the response with its content type, a strong-ish
     * validator built from modification time and size, and a long cache
     * lifetime; answer 304 when the caller already holds that version.
     *
     * The caller is responsible for turning a null resolution into a 404 — this
     * method only ever sees a file it is allowed to serve.
     */
    public static function stream(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $themeName,
        string $path,
        string $absolutePath
    ): ResponseInterface {
        $modifiedTime = (int) filemtime($absolutePath);
        $size = (int) filesize($absolutePath);
        $etag = '"' . dechex($modifiedTime) . '-' . dechex($size) . '"';

        $response = $response
            ->withHeader('Content-Type', (string) self::getContentType($path))
            ->withHeader('ETag', $etag)
            ->withHeader('Cache-Control', 'public, max-age=' . self::CACHE_MAX_AGE)
            ->withHeader('X-Content-Type-Options', 'nosniff');

        if (self::etagMatches($request->getHeaderLine('If-None-Match'), $etag)) {
            return $response->withStatus(304);
        }

        $response->getBody()->write((string) file_get_contents($absolutePath));

        return $response
            ->withHeader('Content-Length', (string) $size)
            ->withStatus(200);
    }

    /**
     * `If-None-Match` may carry a list and weak validators (`W/"…"`).
     *
     * It may also carry a transfer-coding suffix: when mod_deflate compresses a
     * response it appends `-gzip` to the ETag it sends, so the browser's next
     * request validates against `"…-gzip"` while this class still computes
     * `"…"`. Comparing without the suffix is what makes 304 actually happen
     * behind a compressing Apache.
     */
    private static function etagMatches(string $headerValue, string $etag): bool
    {
        if ($headerValue === '') {
            return false;
        }
        if (trim($headerValue) === '*') {
            return true;
        }

        foreach (explode(',', $headerValue) as $candidate) {
            if (self::normalizeEtag($candidate) === self::normalizeEtag($etag)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeEtag(string $etag): string
    {
        $etag = trim($etag);
        if (str_starts_with($etag, 'W/')) {
            $etag = substr($etag, 2);
        }
        $etag = trim($etag, '"');

        foreach (['-gzip', '-br', '-deflate'] as $suffix) {
            if (str_ends_with($etag, $suffix)) {
                return substr($etag, 0, -strlen($suffix));
            }
        }

        return $etag;
    }
}
