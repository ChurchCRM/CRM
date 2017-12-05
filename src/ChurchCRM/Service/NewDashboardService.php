<?php

namespace ChurchCRM\Service;
class NewDashboardService
{
  
  public static function getDashboardItems() {
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
        array_push($ReturnValues, $DashboardItem);
      }
    }
    return $ReturnValues;
    
  }
  public static function getValues($PageName) {
    $ReturnValues = array ();
    Foreach (self::getDashboardItems() as $DashboardItem) {
        $thisItem = array($DashboardItem::getDashboardItemName() => $DashboardItem::getDashboardItemValue());
        array_push($ReturnValues,$thisItem);
    }
    return $ReturnValues;
  }
  
  
  public static function getRenderCode($PageName) {
    
    $jsFunctions = array();
    Foreach (self::getDashboardItems() as $DashboardItem) {
        $itemRenderer = $DashboardItem::getDashboardItemName().": function(data) {". $DashboardItem::getDashboardItemRenderer() ."}";
        array_push($jsFunctions,$itemRenderer);
    }
    $code = "window.CRM.dashboard={";
    $code .= join(", ",$jsFunctions);
    $code .= "};";
    
    return $code;
  }
}