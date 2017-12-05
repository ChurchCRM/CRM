<?php

namespace ChurchCRM\Service;
class NewDashboardService
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
  
  
  public static function getRenderCode($PageName) {
    $code = "window.CRM.dashboard={";
    $code .= "EventsCounters: function(data) { console.log(data); document.getElementById('BirthdateNumber').innerText=data.Birthdays;
      document.getElementById('AnniversaryNumber').innerText=data.Anniversaries;
      document.getElementById('EventsNumber').innerText=data.Events; }";
    
    $code .= "};";
    
    return $code;
  }
}