<?php

namespace ChurchCRM\Portal;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

/**
 * Discovery, metadata, validation and activation of Member Portal themes.
 *
 * A theme is a folder under `src/Include/themes/`. The folder name is the
 * theme's id and — unless an optional `theme.json` supplies a `name` — its
 * display name, shown exactly as spelled. Every file inside is optional.
 *
 * `Include/` survives an upgrade and is deny-all at the web-server level, which
 * is why themes live there and why their assets are streamed by the application
 * (see ThemeAssetStreamer) instead of being served directly.
 *
 * See .agents/skills/churchcrm/member-portal-design.md §3 (decisions P3–P8).
 */
class ThemeManager
{
    /** Web/document-root-relative folder that holds every theme. */
    public const THEMES_DIRECTORY = '/Include/themes';

    /** The system theme, shipped in core and refreshed on every upgrade. */
    public const DEFAULT_THEME = 'default';

    /** Name of the configuration item that holds the active theme's folder name. */
    public const ACTIVE_THEME_CONFIG = 'sMemberPortalTheme';

    /** Optional metadata file inside a theme folder. */
    public const METADATA_FILE = 'theme.json';

    /**
     * Absolute path of the folder that holds every theme.
     */
    public static function getThemesRoot(): string
    {
        return rtrim(SystemURLs::getDocumentRoot(), '/\\') . self::THEMES_DIRECTORY;
    }

    /**
     * A theme id is a single folder name: letters, digits, dot, dash, underscore.
     * Anything else (a separator, a traversal segment, an absolute path) is refused
     * before it is ever concatenated onto a filesystem path.
     */
    public static function isValidThemeName(string $name): bool
    {
        if ($name === '' || $name === '.' || $name === '..') {
            return false;
        }

        return preg_match('/^[A-Za-z0-9._-]+$/', $name) === 1;
    }

    /**
     * Absolute path of one theme folder, or null when the name is not a legal
     * theme id or the folder does not exist.
     */
    public static function getThemePath(string $name): ?string
    {
        if (!self::isValidThemeName($name)) {
            return null;
        }

        $path = self::getThemesRoot() . '/' . $name;

        return is_dir($path) ? $path : null;
    }

    public static function themeExists(string $name): bool
    {
        return self::getThemePath($name) !== null;
    }

    /**
     * Absolute path of a theme's `templates/` folder, or null when the theme has
     * no template overrides (a colour-only theme is the common case).
     */
    public static function getTemplatesPath(string $name): ?string
    {
        $themePath = self::getThemePath($name);
        if ($themePath === null) {
            return null;
        }

        $templatesPath = $themePath . '/templates';

        return is_dir($templatesPath) ? $templatesPath : null;
    }

    /**
     * Absolute path of the default theme's `templates/` folder. This is the only
     * template tree that is guaranteed to exist: it ships with the release.
     */
    public static function getDefaultTemplatesPath(): string
    {
        return self::getThemesRoot() . '/' . self::DEFAULT_THEME . '/templates';
    }

    /**
     * Metadata for one theme: id, display name and the optional theme.json fields.
     *
     * @return array{id: string, name: string, author: string, description: string, path: string}
     */
    public static function getThemeMetadata(string $name): array
    {
        $meta = [
            'id' => $name,
            'name' => $name,
            'author' => '',
            'description' => '',
            'path' => (string) self::getThemePath($name),
        ];

        $metadataFile = $meta['path'] . '/' . self::METADATA_FILE;
        if ($meta['path'] === '' || !is_file($metadataFile)) {
            return $meta;
        }

        $decoded = json_decode((string) file_get_contents($metadataFile), true);
        if (!is_array($decoded)) {
            return $meta;
        }

        foreach (['name', 'author', 'description'] as $field) {
            if (isset($decoded[$field]) && is_string($decoded[$field]) && trim($decoded[$field]) !== '') {
                $meta[$field] = trim($decoded[$field]);
            }
        }

        return $meta;
    }

    /**
     * Every theme folder on disk: the default theme first, the rest ordered by
     * display name. A folder is a theme by existing; nothing is hidden or skipped.
     *
     * @return array<int, array{id: string, name: string, author: string, description: string, path: string}>
     */
    public static function listThemes(): array
    {
        $root = self::getThemesRoot();
        if (!is_dir($root)) {
            return [];
        }

        $entries = scandir($root);
        if ($entries === false) {
            return [];
        }

        $default = null;
        $others = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || !self::isValidThemeName($entry)) {
                continue;
            }
            if (!is_dir($root . '/' . $entry)) {
                continue;
            }
            $meta = self::getThemeMetadata($entry);
            if ($entry === self::DEFAULT_THEME) {
                $default = $meta;
            } else {
                $others[] = $meta;
            }
        }

        usort($others, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $default === null ? $others : array_merge([$default], $others);
    }

    /**
     * The configured theme's folder name. The value is returned as configured
     * even when the folder is missing: a broken configuration is an error state
     * the portal reports (design §3.3), never a silent fallback to `default`.
     */
    public static function getActiveThemeName(): string
    {
        $configured = trim((string) SystemConfig::getValue(self::ACTIVE_THEME_CONFIG));

        return $configured === '' ? self::DEFAULT_THEME : $configured;
    }

    /**
     * Metadata of the configured theme.
     *
     * @throws ThemeException when the configured folder is not a theme on disk
     */
    public static function getActiveTheme(): array
    {
        $name = self::getActiveThemeName();
        if (!self::themeExists($name)) {
            throw ThemeException::themeFolderNotFound($name);
        }

        return self::getThemeMetadata($name);
    }

    /**
     * Compile every template in the theme and report what is wrong with it.
     *
     * @return array<int, array{file: string, line: int, message: string, level: string}>
     */
    public static function validate(string $name): array
    {
        return ThemeValidator::validate($name);
    }

    /**
     * Make a theme the active one. Activation runs the validator first and
     * refuses on any error-level finding, so a theme that cannot render is
     * never made live. Warnings are returned to the caller and allowed.
     *
     * @return array<int, array{file: string, line: int, message: string, level: string}> the findings
     *
     * @throws ThemeException when validation produced error-level findings
     */
    public static function activate(string $name): array
    {
        $findings = self::validate($name);
        $errors = array_values(array_filter(
            $findings,
            static fn (array $finding): bool => $finding['level'] === ThemeValidator::LEVEL_ERROR
        ));

        if ($errors !== []) {
            throw ThemeException::activationRefused($name, $errors);
        }

        SystemConfig::setValue(self::ACTIVE_THEME_CONFIG, $name);

        return $findings;
    }
}
