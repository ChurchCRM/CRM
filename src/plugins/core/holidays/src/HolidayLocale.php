<?php

namespace ChurchCRM\Plugins\Holidays;

use Yasumi\Yasumi;

/**
 * Maps a ChurchCRM locale (locales.json `locale`, e.g. "de_DE") to a locale
 * Yasumi accepts, so holiday titles follow the reader's language.
 *
 * Resolution order:
 *  1. The locale itself when Yasumi knows it (most ChurchCRM locales).
 *  2. A Yasumi spelling of the same locale (zh_CN → zh_Hans_CN, tl_PH → fil_PH).
 *  3. The bare language ("pt_AO" → "pt").
 *  4. English: en_US. Yasumi also falls back to English for any holiday
 *     that has no title in the resolved language.
 */
final class HolidayLocale
{
    public const FALLBACK = 'en_US';

    /** ChurchCRM locales that Yasumi spells differently. */
    private const ALIASES = [
        'zh_CN' => 'zh_Hans_CN',
        'zh_TW' => 'zh_Hant_TW',
        'tl_PH' => 'fil_PH',
    ];

    public static function resolve(?string $locale): string
    {
        // Accept "de-DE", "de_DE.UTF-8" and "de_DE@euro" as well as "de_DE".
        $locale = str_replace('-', '_', preg_replace('/[.@].*$/', '', trim((string) $locale)) ?? '');
        if ($locale === '') {
            return self::FALLBACK;
        }

        $locale = self::ALIASES[$locale] ?? $locale;
        $supported = Yasumi::getAvailableLocales();
        if (in_array($locale, $supported, true)) {
            return $locale;
        }

        $language = strtolower(strtok($locale, '_') ?: '');
        if ($language !== '' && in_array($language, $supported, true)) {
            return $language;
        }

        return self::FALLBACK;
    }

    /**
     * The locale plus each parent, most specific first: "zh_Hant_TW" → ["zh_Hant_TW", "zh_Hant", "zh"].
     *
     * @return string[]
     */
    public static function chain(string $locale): array
    {
        $chain = [];
        $parts = explode('_', $locale);
        while ($parts !== []) {
            $chain[] = implode('_', $parts);
            array_pop($parts);
        }

        return $chain;
    }
}
