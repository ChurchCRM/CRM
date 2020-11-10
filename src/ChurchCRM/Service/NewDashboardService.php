<?php

namespace ChurchCRM\Service;
class NewDashboardService
{

    public static function getDashboardItems($PageName)
    {
        $DashboardItems = array(
            "ChurchCRM\Dashboard\EventsMenuItems",
            "ChurchCRM\Dashboard\ClassificationDashboardItem",
            "ChurchCRM\Dashboard\CurrentLocaleMetadata"
        );
        $ReturnValues = array();
        foreach ($DashboardItems as $DashboardItem) {
            if ($DashboardItem::shouldInclude($PageName)) {
                array_push($ReturnValues, $DashboardItem);
            }
        }
        return $ReturnValues;
    }

    public static function getValues($PageName)
    {
        $ReturnValues = array();
        foreach (self::getDashboardItems($PageName) as $DashboardItem) {
            $ReturnValues[$DashboardItem::getDashboardItemName()] = $DashboardItem::getDashboardItemValue();
        }
        return $ReturnValues;
    }

}
