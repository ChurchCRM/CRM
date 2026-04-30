<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\LoggerUtils;

/**
 * Locale metadata service.
 *
 * Single source of truth for reading locales.json, detecting which GNU
 * locales are installed on the host OS, and producing the merged
 * "supported + system-available" view used by the admin debug page and
 * the setup wizard. Extracted from AppIntegrityService because locale
 * metadata is not a file-integrity concern.
 */
class LocaleService
{
    /**
     * Resolve the document root even when SystemURLs has not been
     * initialised yet (setup wizard path).
     */
    private static function resolveDocumentRoot(): string
    {
        $documentRoot = SystemURLs::getDocumentRoot();
        if (is_string($documentRoot) && $documentRoot !== '') {
            return $documentRoot;
        }

        $setupDocRoot = $GLOBALS['CHURCHCRM_SETUP_DOC_ROOT'] ?? null;
        if (is_string($setupDocRoot) && $setupDocRoot !== '') {
            return $setupDocRoot;
        }

        return dirname(__DIR__, 2);
    }

    /**
     * GNU locales installed on the host OS (used to decide whether a
     * user-chosen locale will actually work for date / number formatting).
     *
     * @return array<int, string>
     * @throws \RuntimeException if exec() is unavailable or locale -a fails
     */
    public static function getAvailableSystemLocales(): array
    {
        $logger = LoggerUtils::getAppLogger();

        if (!function_exists('exec')) {
            throw new \RuntimeException(
                'exec() is disabled — cannot detect system locales. Enable exec() or contact your hosting provider.'
            );
        }

        $output = [];
        @exec('locale -a 2>/dev/null', $output, $returnCode);

        if ($returnCode !== 0 || count($output) === 0) {
            throw new \RuntimeException(
                'locale -a failed (exit code ' . $returnCode . '). Ensure the locale command is available on this server.'
            );
        }

        $availableLocales = [];
        foreach ($output as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $locale = preg_replace('/(\.[^.]*)?(@.*)?$/', '', $line);
            if (!empty($locale)) {
                $availableLocales[] = $locale;
            }
        }

        $availableLocales = array_unique($availableLocales);
        $logger->debug('System locales detected', ['count' => count($availableLocales)]);

        return $availableLocales;
    }

    /**
     * Raw contents of src/locale/locales.json keyed by display name.
     *
     * @return array<string, array<string, mixed>>
     * @throws \RuntimeException if the locales file is missing or unreadable (broken installation)
     */
    public static function getSupportedLocales(): array
    {
        $documentRoot = self::resolveDocumentRoot();
        $localesFile = $documentRoot . '/locale/locales.json';

        if (!is_file($localesFile)) {
            throw new \RuntimeException('locales.json not found at ' . $localesFile . ' — installation is broken.');
        }

        $localesContent = file_get_contents($localesFile);
        if ($localesContent === false) {
            throw new \RuntimeException('Failed to read locales.json at ' . $localesFile . '.');
        }

        return json_decode($localesContent, true, 512, JSON_THROW_ON_ERROR) ?? [];
    }

    /**
     * Merged view used by the admin debug page and setup wizard: every
     * supported locale annotated with whether the host OS has it
     * installed, plus `nativeName` / `region` from locales.json for
     * grouped rendering.
     *
     * @return array{supportedLocales: array<int, array<string, mixed>>, availableSystemLocales: array<int, string>, systemLocaleSupportSummary: string, systemLocaleDetected: bool}
     */
    public static function getLocaleSetupInfo(): array
    {
        $logger = LoggerUtils::getAppLogger();
        $supportedLocales = self::getSupportedLocales();
        $availableLocales = self::getAvailableSystemLocales();

        $availableNormalized = array_map(static function ($locale) {
            return preg_replace('/(\.[^.]*)?(@.*)?$/', '', $locale);
        }, $availableLocales);
        $availableNormalized = array_unique($availableNormalized);

        $localeInfo = [];
        $systemSupported = 0;
        $totalSupported = 0;

        foreach ($supportedLocales as $languageName => $localeConfig) {
            $localeCode = $localeConfig['locale'] ?? '';
            $languageCode = $localeConfig['languageCode'] ?? '';
            $totalSupported++;

            $isAvailable = in_array($localeCode, $availableNormalized, true)
                || in_array($languageCode, $availableNormalized, true)
                || in_array(str_replace('_', '-', $localeCode), $availableNormalized, true);

            if ($isAvailable) {
                $systemSupported++;
            }

            $localeInfo[] = [
                'name' => $languageName,
                'nativeName' => $localeConfig['nativeName'] ?? $languageName,
                'region' => $localeConfig['region'] ?? '',
                'locale' => $localeCode,
                'languageCode' => $languageCode,
                'systemAvailable' => $isAvailable,
                'countryCode' => $localeConfig['countryCode'] ?? '',
            ];
        }

        $logger->info('Locale setup info generated', [
            'totalSupported' => $totalSupported,
            'systemSupported' => $systemSupported,
        ]);

        return [
            'supportedLocales' => $localeInfo,
            'availableSystemLocales' => $availableLocales,
            'systemLocaleSupportSummary' => $systemSupported . '/' . $totalSupported . ' locales available on system',
            'systemLocaleDetected' => count($availableLocales) > 0,
        ];
    }
}
