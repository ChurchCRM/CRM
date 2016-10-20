<?php

namespace ChurchCRM\dto;


class LocaleInfo
{
  var $locale;
  var $language;
  var $country;

  public function __construct($locale)
  {
    $this->locale = $locale;
    if (strpos($locale, "_")) {
      $items = explode("_", $locale);
      $this->language = $items[0];
      $this->country = $items[1];
    }
    if ($locale == "zh_CN") {
      $this->language = "zh-CN";
    } else if ($locale == "zh_TW") {
      $this->language = "zh-TW";
    } else if ($locale == "pt_BR") {
      $this->language = "pt-BR";
    }
  }

  function getLocale()
  {
    return $this->locale;
  }

  function getLanguageCode()
  {
    return $this->language;
  }

  function getCountryCode()
  {
    return $this->country;
  }

  function getThousandSeparator()
  {
    $sep = ',';
    if ($this->language == 'it_IT') {
      $sep = ".";
    }
    return $sep;
  }

  function getLocaleArray()
  {
    $utfList = array(".utf8", ".UTF8", ".utf-8", ".UTF-8");
    $localArray = array();
    array_push($localArray, $this->getLanguageCode());
    foreach ($utfList as $item) {
      array_push($localArray, $this->getLanguageCode() . $item);
    }
    return $localArray;
  }

  function getLocaleInfo()
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
