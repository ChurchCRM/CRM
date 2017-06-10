<?php

namespace ChurchCRM\dto;

class LocaleInfo
{
    public $locale;
    public $language;
    public $country;

    public function __construct($locale)
    {
        $this->locale = $locale;
        if (strpos($locale, '_')) {
            $items = explode('_', $locale);
            $this->language = $items[0];
            $this->country = $items[1];
        }
        if ($locale == 'zh_CN') {
            $this->language = 'zh-CN';
        } elseif ($locale == 'zh_TW') {
            $this->language = 'zh-TW';
        } elseif ($locale == 'pt_BR') {
            $this->language = 'pt-BR';
        }
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function getLanguageCode()
    {
        return $this->language;
    }

    public function getCountryCode()
    {
        return $this->country;
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
            array_push($localArray, $this->getLanguageCode().$item);
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
