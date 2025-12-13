<?php

namespace ChurchCRM\dto;

class LocaleInfo
{
    public $systemLocale;
    public $locale;
    public $language;
    public $country;
    public $dataTables;
    private $name;
    private $poLocaleId;
    private $localeConfig;
    private $translationData;

    public function __construct($locale, $userLocale)
    {
        $this->systemLocale = $locale;
        $this->locale = $locale;
        if (!empty($userLocale)) {
            $this->locale = $userLocale->getValue();
        }
        $localesFile = file_get_contents(SystemURLs::getDocumentRoot() . '/locale/locales.json');
        $locales = json_decode($localesFile, true, 512, JSON_THROW_ON_ERROR);
        foreach ($locales as $key => $value) {
            if ($value['locale'] == $this->locale) {
                $this->name = $key;
                $this->language = $value['languageCode'];
                $this->country = $value['countryCode'];
                $this->dataTables = $value['dataTables'];
                $this->poLocaleId = $value['poEditor'];
                $this->localeConfig = $value;  // Store full config for later use
            }
        }
    }

    /**
     * @return mixed
     */
    public function getSystemLocale()
    {
        return $this->systemLocale;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getPoLocaleId()
    {
        return $this->poLocaleId;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function getShortLocale(): string
    {
        return substr($this->getLocale(), 0, 2);
    }

    public function getLanguageCode()
    {
        return $this->language;
    }

    public function getCountryCode()
    {
        return $this->country;
    }

    public function getDataTables()
    {
        return $this->dataTables;
    }

    public function getThousandSeparator(): string
    {
        $sep = ',';
        if ($this->language == 'it_IT') {
            $sep = '.';
        }

        return $sep;
    }

    public function getLocaleArray(): array
    {
        $utfList = ['.utf8', '.UTF8', '.utf-8', '.UTF-8'];
        $localArray = [];
        $localArray[] = $this->getLanguageCode();
        foreach ($utfList as $item) {
            $localArray[] = $this->getLanguageCode() . $item;
        }

        return $localArray;
    }

    /**
     * @return array<string, string>
     */
    public function getLocaleInfo(): array
    {
        $localeInfo = localeconv();
        // patch some missing data for Italian.  This shouldn't be necessary!
        if ($this->language == 'it_IT') {
            $localeInfo['thousands_sep'] = '.';
            $localeInfo['frac_digits'] = '2';
        }

        return $localeInfo;
    }

    /**
     * Get the full locale configuration array for JavaScript
     * @return array<string, mixed>
     */
    public function getLocaleConfigArray(): array
    {
        return $this->localeConfig ?? [];
    }

    /**
     * Get lowercase country code for flag icons (e.g., 'us', 'de', 'fr')
     */
    public function getCountryFlagCode(): string
    {
        return strtolower($this->country ?? 'us');
    }

    /**
     * Check if translation completion percentage should be displayed
     * (only for non-English locales)
     */
    public function shouldShowTranslationPercentage(): bool
    {
        return !str_starts_with($this->locale ?? 'en', 'en');
    }

    /**
     * Get the translation completion percentage from POEditor.
     * Returns 100 if locale is English or not found.
     */
    public function getTranslationPercentage(): int
    {
        if (!$this->shouldShowTranslationPercentage()) {
            return 100;
        }

        // Load translation data once and cache it
        if ($this->translationData === null) {
            $this->translationData = $this->loadTranslationData();
        }

        // Return cached percentage
        foreach ($this->translationData as $poLocale) {
            if (strtolower($this->poLocaleId ?? '') === strtolower($poLocale['code'])) {
                return (int) $poLocale['percentage'];
            }
        }

        return 0;
    }

    /**
     * Load translation data from POEditor file with error handling.
     * Returns empty array on error to prevent crashes.
     * @return array<int, array<string, mixed>>
     */
    private function loadTranslationData(): array
    {
        $poeditorPath = SystemURLs::getDocumentRoot() . '/locale/poeditor.json';
        $poLocalesFile = @file_get_contents($poeditorPath);
        
        if ($poLocalesFile === false) {
            // File missing or unreadable; return empty array
            return [];
        }

        try {
            $poLocales = json_decode($poLocalesFile, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // Invalid JSON; return empty array
            return [];
        }

        if (!isset($poLocales['result']['languages']) || !is_array($poLocales['result']['languages'])) {
            return [];
        }

        return $poLocales['result']['languages'];
    }

    /**
     * Check if translation badge should be shown (non-English and < 90% complete)
     */
    public function shouldShowTranslationBadge(): bool
    {
        return $this->shouldShowTranslationPercentage() && $this->getTranslationPercentage() < 90;
    }
}
