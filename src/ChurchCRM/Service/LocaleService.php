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
     */
    public static function getAvailableSystemLocales(): array
    {
        $logger = LoggerUtils::getAppLogger();
        $availableLocales = [];

        if (function_exists('exec')) {
            $output = [];
            try {
                @exec('locale -a 2>/dev/null', $output, $returnCode);
                if ($returnCode === 0 && count($output) > 0) {
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
                    $logger->debug('System locales detected via locale command', [
                        'count' => count($availableLocales),
                    ]);

                    return $availableLocales;
                }
            } catch (\Exception $e) {
                $logger->debug('Error executing locale command', ['exception' => $e]);
            }
        }

        $commonLocales = [
            'C',
            'en_US', 'en_US.UTF-8', 'en_GB', 'en_GB.UTF-8',
            'de_DE', 'de_DE.UTF-8', 'fr_FR', 'fr_FR.UTF-8',
            'es_ES', 'es_ES.UTF-8', 'it_IT', 'it_IT.UTF-8',
            'pt_BR', 'pt_BR.UTF-8', 'pt_PT', 'pt_PT.UTF-8',
            'nl_NL', 'nl_NL.UTF-8', 'ja_JP', 'ja_JP.UTF-8',
            'zh_CN', 'zh_CN.UTF-8', 'zh_TW', 'zh_TW.UTF-8',
            'ko_KR', 'ko_KR.UTF-8', 'ru_RU', 'ru_RU.UTF-8',
            'pl_PL', 'pl_PL.UTF-8', 'ar_EG', 'ar_EG.UTF-8',
        ];

        $originalLocale = setlocale(LC_ALL, 0);

        foreach ($commonLocales as $locale) {
            $currentLocale = setlocale(LC_ALL, $locale);
            if ($currentLocale !== false && $currentLocale !== 'C') {
                $normalized = preg_replace('/(\.[^.]*)?(@.*)?$/', '', $locale);
                if (!empty($normalized)) {
                    $availableLocales[] = $normalized;
                }
            }
        }

        if ($originalLocale !== false) {
            setlocale(LC_ALL, $originalLocale);
        }

        $availableLocales = array_unique($availableLocales);
        $logger->debug('System locales detected via setlocale fallback', [
            'count' => count($availableLocales),
        ]);

        return $availableLocales;
    }

    /**
     * Raw contents of src/locale/locales.json keyed by display name.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getSupportedLocales(): array
    {
        $logger = LoggerUtils::getAppLogger();
        $documentRoot = self::resolveDocumentRoot();
        $localesFile = $documentRoot . '/locale/locales.json';

        try {
            if (!is_file($localesFile)) {
                $logger->warning('Locales file not found', ['file' => $localesFile]);

                return [];
            }

            $localesContent = file_get_contents($localesFile);
            if ($localesContent === false) {
                $logger->warning('Failed to read locales file', ['file' => $localesFile]);

                return [];
            }

            $locales = json_decode($localesContent, true, 512, JSON_THROW_ON_ERROR);

            return $locales ?? [];
        } catch (\Exception $e) {
            $logger->warning('Error loading supported locales', ['exception' => $e]);

            return [];
        }
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
