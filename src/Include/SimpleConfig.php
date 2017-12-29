<?php

use ChurchCRM\dto\LocaleInfo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

if (SystemConfig::getValue('sTimeZone')) {
    date_default_timezone_set(SystemConfig::getValue('sTimeZone'));
}

$localeInfo = new LocaleInfo(SystemConfig::getValue('sLanguage'));
setlocale(LC_ALL, $localeInfo->getLocale());

// Get numeric and monetary locale settings.
$aLocaleInfo = $localeInfo->getLocaleInfo();

// This is needed to avoid some bugs in various libraries like fpdf.
// http://www.velanhotels.com/fpdf/FAQ.htm#6
setlocale(LC_NUMERIC, 'C');

$domain = 'messages';
$sLocaleDir = SystemURLs::getDocumentRoot() . '/locale/textdomain';

bind_textdomain_codeset($domain, 'UTF-8');
bindtextdomain($domain, $sLocaleDir);
textdomain($domain);
