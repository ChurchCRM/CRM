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
        $localesFile = file_get_contents(SystemURLs::getDocumentRoot() . "/locale/locales.json");
        $locales = json_decode($localesFile, true);
        foreach ($locales as $key => $value) {
            if ($value["locale"] == $this->locale) {
                $this->name = $key;
                $this->language = $value["languageCode"];
                $this->country = $value["countryCode"];
                $this->dataTables = $value["dataTables"];
                $this->poLocaleId = $value["poEditor"];
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

    public function getShortLocale()
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

    public function getThousandSeparator()
    {
        $sep = ',';
        if ($this->language == 'it_IT') {
            $sep = '.';
        }

        return $sep;
    }

    public function getLocaleArray()
    {
        $utfList = ['.utf8', '.UTF8', '.utf-8', '.UTF-8'];
        $localArray = [];
        array_push($localArray, $this->getLanguageCode());
        foreach ($utfList as $item) {
            array_push($localArray, $this->getLanguageCode() . $item);
        }

        return $localArray;
    }

    public function getLocaleInfo()
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
