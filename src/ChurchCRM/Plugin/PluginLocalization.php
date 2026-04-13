<?php

namespace ChurchCRM\Plugin;

use ChurchCRM\Utils\LoggerUtils;

/**
 * Plugin-local translation loader.
 *
 * Community plugins are **never** run through the main ChurchCRM POeditor
 * workflow. Instead, each plugin ships its own translations inside its
 * directory and the PluginManager wires them in at boot. This class holds
 * the two sides of that wiring:
 *
 * 1. {@see bindPhpDomains()} — for every loaded plugin that has a
 *    `locale/textdomain/{xx_YY}/LC_MESSAGES/{pluginId}.mo` file, bind a
 *    dedicated gettext textdomain whose name matches the plugin id. Plugin
 *    PHP code then calls `dgettext('my-plugin', 'Hello')` (or
 *    `_t('my-plugin', 'Hello')` via the helper in the skill) to translate.
 *    The default `messages` textdomain is untouched — POeditor never sees
 *    community strings.
 *
 * 2. {@see collectJsResources()} — for every loaded plugin that has a
 *    `locale/i18n/{xx_YY}.json` file, read it and return a
 *    `[pluginId => keyValueMap]` array. PluginManager::getPluginsClientConfig()
 *    embeds this under `window.CRM.plugins.{pluginId}.i18n` so plugin
 *    frontend code can look up strings without needing i18next changes.
 *
 * Directory layout a plugin must use:
 *
 *   plugins/community/my-plugin/
 *     locale/
 *       textdomain/
 *         de_DE/LC_MESSAGES/my-plugin.mo
 *         es_ES/LC_MESSAGES/my-plugin.mo
 *       i18n/
 *         de_DE.json
 *         es_ES.json
 *
 * Neither directory is required — plugins that have no translations simply
 * do not ship a `locale/` folder and this class is a no-op for them.
 */
class PluginLocalization
{
    /**
     * Bind one gettext textdomain per loaded plugin.
     *
     * Called from PluginManager after active plugins have been discovered
     * but after Bootstrapper has already bound the main `messages` domain.
     * Safe to call multiple times — gettext rebinds idempotently.
     *
     * @param array<string, PluginMetadata> $metadataByPluginId
     */
    public static function bindPhpDomains(array $metadataByPluginId): void
    {
        if (!function_exists('bindtextdomain')) {
            return;
        }

        foreach ($metadataByPluginId as $pluginId => $metadata) {
            $localeDir = self::textdomainDir($metadata);
            if ($localeDir === null) {
                continue;
            }

            // Plugin textdomain is the plugin id. Reject anything that is
            // not a well-formed kebab-case slug so the id can never collide
            // with the default `messages` domain.
            if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $pluginId) || $pluginId === 'messages') {
                LoggerUtils::getAppLogger()->warning('Refused to bind plugin textdomain (unsafe id)', [
                    'plugin' => $pluginId,
                ]);
                continue;
            }

            try {
                bindtextdomain($pluginId, $localeDir);
                if (function_exists('bind_textdomain_codeset')) {
                    bind_textdomain_codeset($pluginId, 'UTF-8');
                }
                LoggerUtils::getAppLogger()->debug('Bound plugin textdomain', [
                    'plugin' => $pluginId,
                    'dir' => $localeDir,
                ]);
            } catch (\Throwable $e) {
                LoggerUtils::getAppLogger()->warning('Failed to bind plugin textdomain', [
                    'plugin' => $pluginId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Collect per-plugin i18next resources for the given locale.
     *
     * Returns a map of `pluginId => [key => value]` for every loaded plugin
     * that ships `locale/i18n/{locale}.json`. Missing locales fall back to
     * `en_US.json` and then to an empty array.
     *
     * @param array<string, PluginMetadata> $metadataByPluginId
     * @return array<string, array<string, string>>
     */
    public static function collectJsResources(array $metadataByPluginId, string $locale): array
    {
        $resources = [];

        foreach ($metadataByPluginId as $pluginId => $metadata) {
            $data = self::readI18nFile($metadata, $locale);
            if ($data === null && $locale !== 'en_US') {
                $data = self::readI18nFile($metadata, 'en_US');
            }
            if (is_array($data) && $data !== []) {
                $resources[$pluginId] = $data;
            }
        }

        return $resources;
    }

    /**
     * Resolve the gettext textdomain directory for a plugin, or null if the
     * plugin does not ship PHP translations.
     */
    private static function textdomainDir(PluginMetadata $metadata): ?string
    {
        $candidate = rtrim($metadata->getPath(), '/') . '/locale/textdomain';

        return is_dir($candidate) ? $candidate : null;
    }

    /**
     * Read a plugin's i18next JSON file for the given locale. Returns null
     * if the file does not exist, or a decoded array on success.
     *
     * Rejects files that exceed 512 KB so a malicious or typo-ridden plugin
     * cannot balloon the client config payload.
     *
     * @return array<string, string>|null
     */
    private static function readI18nFile(PluginMetadata $metadata, string $locale): ?array
    {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{1,15}$/', $locale)) {
            return null;
        }

        $path = rtrim($metadata->getPath(), '/') . '/locale/i18n/' . $locale . '.json';
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $size = filesize($path);
        if ($size === false || $size > 512 * 1024) {
            LoggerUtils::getAppLogger()->warning('Plugin i18n file too large, skipping', [
                'plugin' => $metadata->getId(),
                'path' => $path,
                'size' => $size,
            ]);

            return null;
        }

        try {
            $raw = file_get_contents($path);
            if ($raw === false) {
                return null;
            }
            $decoded = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            LoggerUtils::getAppLogger()->warning('Plugin i18n file is not valid JSON', [
                'plugin' => $metadata->getId(),
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        // Only accept flat key/value string maps — nested structures are a
        // footgun for plugin authors and a parser complication we do not
        // need. Plugins that want namespaces should prefix their keys.
        $clean = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
