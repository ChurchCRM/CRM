<?php

namespace ChurchCRM\dto;


class LocaleInfo
{
  var $language;

  public function __construct($language) {
      $this->language = $language;
  }

  function getLocale() {

  }

  function getLanguageCode() {
    return $this->language;
  }

  function getCountryCode() {
    return $this->language;
  }

  function getThousandSeparator() {
    $sep = ',';
    if ($this->language == 'it_IT') {
      $sep = ".";
    }
    return $sep;
  }

  function getLocaleArray() {
    $utfList = array(".utf8", ".UTF8", ".utf-8", ".UTF-8");
    $localArray = array();
    array_push($localArray, $this->getLanguageCode());
    foreach ($utfList as $item) {
      array_push($localArray, $this->getLanguageCode(). $item);
    }
    return $localArray;
  }

  function getLocaleInfo() {
    $localeInfo = localeconv();
    // patch some missing data for Italian.  This shouldn't be necessary!
    if ($this->language == 'it_IT') {
      $localeInfo['thousands_sep'] = '.';
      $localeInfo['frac_digits'] = '2';
    }
    return $localeInfo;
  }

}
