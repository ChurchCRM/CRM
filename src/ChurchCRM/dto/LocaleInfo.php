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
}
