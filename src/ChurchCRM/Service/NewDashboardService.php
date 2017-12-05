<?php

namespace ChurchCRM\Service;
class DashboardService
{
  public static function getValues($PageName) {
    $DashboardItems = array (
       "ChurchCRM\Dashboard\EventsDashboardItem",
       "ChurchCRM\Dashboard\ClassificationDashboardItem",
       "ChurchCRM\Dashboard\FamilyDashboardItem",
       "ChurchCRM\Dashboard\GroupsDashboardItem",
       "ChurchCRM\Dashboard\PersonDashboardItem",
       "ChurchCRM\Dashboard\PersonDemographicDashboardItem",
    );
    $ReturnValues = array ();
    Foreach ($DashboardItems as $DashboardItem) {
      if ($DashboardItem::shouldInclude($PageName)){
        $thisItem = array($DashboardItem::getDashboardItemName() => $DashboardItem::getDashboardItemValue());
        array_push($ReturnValues,$thisItem);
      }
    }
    return $ReturnValues;
  }
}