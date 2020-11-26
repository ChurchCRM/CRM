<?php


namespace ChurchCRM\Dashboard;

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
        $localeInfo = Bootstrapper::GetCurrentLocale();
        $data["name"] = $localeInfo->getName();
        $data["code"] = $localeInfo->getLocale();
        $data["countryFlagCode"] = strtolower($localeInfo->getCountryCode());

        $poLocalesFile = file_get_contents(SystemURLs::getDocumentRoot() . "/locale/poeditor.json");
        $poLocales = json_decode($poLocalesFile, true);
        $rawPOData = $poLocales["result"]["languages"];
        $data["poPerComplete"] = 0;
        $data["displayPerCompleted"] = false;
        if (!preg_match("#^en_(.*)$#i", $localeInfo->getLocale())) {
            foreach ($rawPOData as $poLocale) {
                if (strtolower($localeInfo->getPoLocaleId()) === strtolower($poLocale["code"])) {
                    $data["poPerComplete"] = $poLocale["percentage"];
                    $data["displayPerCompleted"] = true;
                    break;
                }
            }
        }

        return $data;
    }
}
