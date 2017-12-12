<?php

/******************************************************
// Philippe Logel : 
// I re-put the code at the right place it was : 
// Menu events should be in MenuEventsCount.php
// It's important for a new dev
// It was my code ...
// Last this code was two times in different parts
******************************************************/

namespace ChurchCRM\Dashboard;

use ChurchCRM\Dashboard\DashboardItemInterface;
use ChurchCRM\EventQuery;
use ChurchCRM\PersonQuery;
use ChurchCRM\FamilyQuery;
use ChurchCRM\dto\MenuEventsCount;
use Propel\Runtime\ActiveQuery\Criteria;

class EventsDashboardItem implements DashboardItemInterface {
  
  public static function getDashboardItemName() {
    return "EventsCounters";
  }

  public static function getDashboardItemValue() {
    $activeEvents = array (
        "Events" => MenuEventsCount::getNumberEventsOfToday(),
        "Birthdays" => MenuEventsCount::getNumberBirthDates(),
        "Anniversaries" => MenuEventsCount::getNumberAnniversaries()
    );

    return $activeEvents;
  }

  public static function shouldInclude($PageName) {
    return true; // this ID would be found on all pages.
  }
}
