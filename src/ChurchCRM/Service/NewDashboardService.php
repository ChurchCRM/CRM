<?php

namespace ChurchCRM\Service;

use ChurchCRM\Dashboard\ClassificationDashboardItem;
use ChurchCRM\Dashboard\CurrentLocaleMetadata;
use ChurchCRM\Dashboard\EventsMenuItems;
use ChurchCRM\Dashboard\SystemUpdateMenuItem;

class NewDashboardService
{
    public static function getDashboardItems($PageName)
    {
        $DashboardItems = [
            EventsMenuItems::class,
            ClassificationDashboardItem::class,
            CurrentLocaleMetadata::class,
            SystemUpdateMenuItem::class,
        ];
        $ReturnValues = [];
        foreach ($DashboardItems as $DashboardItem) {
            if ($DashboardItem::shouldInclude($PageName)) {
                array_push($ReturnValues, $DashboardItem);
            }
        }

        return $ReturnValues;
    }

    public static function getValues($PageName)
    {
        $ReturnValues = [];
        foreach (self::getDashboardItems($PageName) as $DashboardItem) {
            $ReturnValues[$DashboardItem::getDashboardItemName()] = $DashboardItem::getDashboardItemValue();
        }

        return $ReturnValues;
    }
}
