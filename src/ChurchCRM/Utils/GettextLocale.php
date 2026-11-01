<?php

namespace ChurchCRM\Utils;

class GettextLocale
{
    private const MESSAGES_FALLBACKS = ['en_US.UTF-8', 'en_US.utf8', 'en_GB.UTF-8', 'en_GB.utf8'];

    /**
     * GNU gettext reads the catalog named by LANGUAGE but ignores LANGUAGE while LC_MESSAGES is C/POSIX
     * (C.UTF-8 included). A host without the requested OS locale would show every string in English.
     *
     * @return string|false|null the LC_MESSAGES locale chosen instead, false when none is installed, null when the OS locale was set
     */
    public static function enableCatalog(string $locale, bool $osLocaleSet): string|false|null
    {
        putenv('LANGUAGE=' . $locale);

        return $osLocaleSet ? null : setlocale(LC_MESSAGES, ...self::MESSAGES_FALLBACKS);
    }
}
