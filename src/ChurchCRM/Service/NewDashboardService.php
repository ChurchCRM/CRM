<?php

namespace ChurchCRM\Service;

use ChurchCRM\Dashboard\ClassificationDashboardItem;
use ChurchCRM\Dashboard\CurrentLocaleMetadata;
use ChurchCRM\Dashboard\DashboardItemInterface;
use ChurchCRM\Dashboard\EventsMenuItems;
use ChurchCRM\Dashboard\SystemUpdateMenuItem;

class NewDashboardService
{
    /**
     * @return DashboardItemInterface[]
     */
    public static function getDashboardItems($PageName): array
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
                $ReturnValues[] = $DashboardItem;
            }
        }

        return $ReturnValues;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function getValues($PageName): array
    {
        $ReturnValues = [];
        foreach (self::getDashboardItems($PageName) as $DashboardItem) {
            $ReturnValues[$DashboardItem::getDashboardItemName()] = $DashboardItem::getDashboardItemValue();
        }

        return $ReturnValues;
    }
}
