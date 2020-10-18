<?php


namespace ChurchCRM\Dashboard;

use ChurchCRM\dto\LocaleInfo;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Bootstrapper;

class CurrentLocaleMetadata implements DashboardItemInterface
{

    public static function getDashboardItemName()
    {
        return "PageLocale";
    }

    public static function shouldInclude($PageName)
    {
        return true;
    }

    public static function getDashboardItemValue()
    {
        $localeInfo = new LocaleInfo(Bootstrapper::GetCurrentLocale()->getLocale());
        $data["name"] = $localeInfo->getName();
        $data["code"] = $localeInfo->getLocale();
        $data["countryFlagCode"] = strtolower($localeInfo->getCountryCode());

        $poLocalesFile = file_get_contents(SystemURLs::getDocumentRoot() . "/locale/poeditor.json");
        $poLocales = json_decode($poLocalesFile, true);
        $rawPOData = $poLocales["result"]["languages"];
        foreach ($rawPOData as $poLocale) {
            if ($localeInfo->getPoLocaleId() == $poLocale["code"]) {
                $data["poPerComplete"] = $poLocale["percentage"];
                $data["poLastUpdated"] = $poLocale["updated"];
                break;
            }
        }

        return $data;
    }
}
