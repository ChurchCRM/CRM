<?php

namespace ChurchCRM\Dashboard;

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemURLs;

class CurrentLocaleMetadata implements DashboardItemInterface
{
    public static function getDashboardItemName(): string
    {
        return 'PageLocale';
    }

    public static function shouldInclude(string $PageName): bool
    {
        return true;
    }

    public static function getDashboardItemValue(): array
    {
        $localeInfo = Bootstrapper::getCurrentLocale();
        $data['name'] = $localeInfo->getName();
        $data['code'] = $localeInfo->getLocale();
        $data['countryFlagCode'] = strtolower($localeInfo->getCountryCode());

        $poLocalesFile = file_get_contents(SystemURLs::getDocumentRoot() . '/locale/poeditor.json');
        $poLocales = json_decode($poLocalesFile, true, 512, JSON_THROW_ON_ERROR);
        $rawPOData = $poLocales['result']['languages'];
        $data['poPerComplete'] = 0;
        $data['displayPerCompleted'] = !str_starts_with($localeInfo->getLocale(), "en");
        foreach ($rawPOData as $poLocale) {
            if (strtolower($localeInfo->getPoLocaleId()) === strtolower($poLocale['code'])) {
                $data['poPerComplete'] = $poLocale['percentage'];
                break;
            }
        }

        return $data;
    }
}
