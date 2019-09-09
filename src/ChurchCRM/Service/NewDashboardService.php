<?php

namespace ChurchCRM\Service;
class NewDashboardService
{
  
  public static function getDashboardItems($PageName) {
     $DashboardItems = array (
       "ChurchCRM\Dashboard\EventsDashboardItem",
       "ChurchCRM\Dashboard\ClassificationDashboardItem",
       "ChurchCRM\Dashboard\FamilyDashboardItem",
       "ChurchCRM\Dashboard\GroupsDashboardItem",
       "ChurchCRM\Dashboard\PersonDashboardItem",
       // "ChurchCRM\Dashboard\PersonDemographicDashboardItem",
    );
    $ReturnValues = array ();
    Foreach ($DashboardItems as $DashboardItem) {
      if ($DashboardItem::shouldInclude($PageName)){
        array_push($ReturnValues, $DashboardItem);
      }
    }
    return $ReturnValues;
    
  }
  public static function getValues($PageName) {
    $ReturnValues = array ();
    Foreach (self::getDashboardItems($PageName) as $DashboardItem) {
        $ReturnValues[$DashboardItem::getDashboardItemName()] = $DashboardItem::getDashboardItemValue();
    }
    return $ReturnValues;
  }

}