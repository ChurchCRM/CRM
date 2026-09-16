<?php

namespace ChurchCRM\Portal;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use Twig\Environment;
use Twig\Error\Error as TwigError;
use Twig\Loader\FilesystemLoader;

/**
 * Compiles every template in a Member Portal theme and reports what is wrong
 * with it, so activation can refuse a theme that cannot render and the
 * administrator sees the exact file, line and message (design §3.2 / §3.3).
 *
 * Findings are rows of `{file, line, message, level}`:
 *   - `error`   — the theme cannot be activated (a template does not compile)
 *   - `warning` — the theme works, but something in it is unused or missing
 */
class ThemeValidator
{
    public const LEVEL_ERROR = 'error';
    public const LEVEL_WARNING = 'warning';

    /**
     * Assets a template may ask for with `theme_asset()`. Anything referenced
     * but absent from both the theme and the default theme is a warning.
     */
    private const THEME_ASSET_CALL = '/theme_asset\(\s*[\'"]([^\'"]+)[\'"]\s*\)/';

    /**
     * These two are optional by definition — the layout already guards them
     * with `portal.hasThemeCss` / `portal.hasThemeJs`, so their absence is the
     * normal case and not something to warn about.
     */
    private const OPTIONAL_ASSETS = ['theme.css', 'theme.js'];

    /**
     * @return array<int, array{file: string, line: int, message: string, level: string}>
     */
    public static function validate(string $name): array
    {
        if (!ThemeManager::isValidThemeName($name)) {
            return [self::finding('', 0, gettext('This is not a valid theme folder name.'), self::LEVEL_ERROR)];
        }

        $themePath = ThemeManager::getThemePath($name);
        if ($themePath === null) {
            return [self::finding('', 0, gettext('The theme folder was not found.'), self::LEVEL_ERROR)];
        }

        $findings = [];
        $templatesPath = ThemeManager::getTemplatesPath($name);
        $defaultTemplatesPath = ThemeManager::getDefaultTemplatesPath();

        if ($templatesPath !== null) {
            $twig = PortalTwig::createValidationEnvironment($templatesPath);
            foreach (self::listTemplates($templatesPath) as $relativePath) {
                $findings = array_merge(
                    $findings,
                    self::compileTemplate($twig, $relativePath),
                    self::checkOverrideIsStillUsed($relativePath, $defaultTemplatesPath)
                );
            }
        }

        return array_merge($findings, self::checkReferencedAssets($themePath, $templatesPath));
    }

    /**
     * Compile one template. A compile error is reported with the file and line
     * Twig itself gives us, so a designer can jump straight to the mistake.
     *
     * @return array<int, array{file: string, line: int, message: string, level: string}>
     */
    private static function compileTemplate(Environment $twig, string $relativePath): array
    {
        try {
            $twig->load($relativePath);
        } catch (TwigError $e) {
            return [self::finding($relativePath, max(0, $e->getTemplateLine()), $e->getRawMessage(), self::LEVEL_ERROR)];
        } catch (Throwable $e) {
            return [self::finding($relativePath, 0, $e->getMessage(), self::LEVEL_ERROR)];
        }

        return [];
    }

    /**
     * An override with no counterpart in the default theme is not wired to
     * anything: either the theme author mistyped the path, or core moved the
     * template. It is a warning, not an error — the theme still renders.
     *
     * @return array<int, array{file: string, line: int, message: string, level: string}>
     */
    private static function checkOverrideIsStillUsed(string $relativePath, string $defaultTemplatesPath): array
    {
        if (is_file($defaultTemplatesPath . '/' . $relativePath)) {
            return [];
        }

        return [self::finding(
            $relativePath,
            0,
            gettext('This version of ChurchCRM does not use a template at this path, so the override is never rendered.'),
            self::LEVEL_WARNING
        )];
    }

    /**
     * Every `theme_asset('…')` a template asks for must resolve, either in the
     * theme itself or in the default theme it falls back to.
     *
     * @return array<int, array{file: string, line: int, message: string, level: string}>
     */
    private static function checkReferencedAssets(string $themePath, ?string $templatesPath): array
    {
        if ($templatesPath === null) {
            return [];
        }

        $defaultPath = ThemeManager::getThemesRoot() . '/' . ThemeManager::DEFAULT_THEME;
        $findings = [];
        foreach (self::listTemplates($templatesPath) as $relativePath) {
            $source = (string) file_get_contents($templatesPath . '/' . $relativePath);
            if (preg_match_all(self::THEME_ASSET_CALL, $source, $matches) === 0) {
                continue;
            }
            foreach (array_unique($matches[1]) as $asset) {
                if (in_array($asset, self::OPTIONAL_ASSETS, true)) {
                    continue;
                }
                if (!ThemeAssetStreamer::isSafeRelativePath($asset)) {
                    $findings[] = self::finding(
                        $relativePath,
                        0,
                        sprintf(gettext('The asset path "%s" is not allowed.'), $asset),
                        self::LEVEL_ERROR
                    );
                    continue;
                }
                if (is_file($themePath . '/' . $asset) || is_file($defaultPath . '/' . $asset)) {
                    continue;
                }
                $findings[] = self::finding(
                    $relativePath,
                    0,
                    sprintf(gettext('This template asks for the file "%s", which the theme does not contain.'), $asset),
                    self::LEVEL_WARNING
                );
            }
        }

        return $findings;
    }

    /**
     * Every `*.twig` file under a theme's `templates/` folder, as paths relative
     * to that folder (`partials/nav.html.twig`, `errors/404.html.twig`, …).
     *
     * @return array<int, string>
     */
    public static function listTemplates(string $templatesPath): array
    {
        if (!is_dir($templatesPath)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($templatesPath, FilesystemIterator::SKIP_DOTS)
        );

        $templates = [];
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'twig') {
                continue;
            }
            $relativePath = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($templatesPath))), '/');
            $templates[] = $relativePath;
        }

        sort($templates);

        return $templates;
    }

    /**
     * @return array{file: string, line: int, message: string, level: string}
     */
    private static function finding(string $file, int $line, string $message, string $level): array
    {
        return [
            'file' => $file,
            'line' => $line,
            'message' => $message,
            'level' => $level,
        ];
    }

    /**
     * A loader that resolves a theme's templates first and the default theme's
     * second, so an override that extends `@default/...` compiles during
     * validation exactly as it will at render time.
     */
    public static function createLoader(string $templatesPath): FilesystemLoader
    {
        $defaultTemplatesPath = ThemeManager::getDefaultTemplatesPath();
        $paths = [$templatesPath];
        if ($templatesPath !== $defaultTemplatesPath && is_dir($defaultTemplatesPath)) {
            $paths[] = $defaultTemplatesPath;
        }

        $loader = new FilesystemLoader($paths);
        if (is_dir($defaultTemplatesPath)) {
            $loader->addPath($defaultTemplatesPath, 'default');
        }

        return $loader;
    }
}
