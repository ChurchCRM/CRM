<?php

namespace ChurchCRM\Dashboard;

use ChurchCRM\Dashboard\DashboardItemInterface;
use ChurchCRM\EventQuery;
use ChurchCRM\PersonQuery;
use ChurchCRM\FamilyQuery;
use Propel\Runtime\ActiveQuery\Criteria;

class EventsDashboardItem implements DashboardItemInterface {
  
  public static function getDashboardItemName() {
    return "EventsCounters";
  }

  public static function getDashboardItemValue() {
    $activeEvents = array (
        "Events" => self::getNumberEventsOfToday(),
        "Birthdays" => self::getNumberBirthDates(),
        "Anniversaries" => self::getNumberAnniversaries()
    );

    return $activeEvents;
  }

  public static function shouldInclude($PageName) {
    return true; // this ID would be found on all pages.
  }
  
  private static function getEventsOfToday()
    {
        $start_date = date("Y-m-d ")." 00:00:00";
        $end_date = date('Y-m-d H:i:s', strtotime($start_date . ' +1 day'));

        $activeEvents = EventQuery::create()
            ->where("event_start <= '".$start_date ."' AND event_end >= '".$end_date."'") /* the large events */
            ->_or()->where("event_start>='".$start_date."' AND event_end <= '".$end_date."'") /* the events of the day */
            ->find();

        return  $activeEvents;
    }

    private static function getNumberEventsOfToday()
    {
        return count(self::getEventsOfToday());
    }

    private static function getBirthDates()
    {
        $peopleWithBirthDays = PersonQuery::create()
            ->filterByBirthMonth(date('m'))
            ->filterByBirthDay(date('d'))
            ->find();
        
        return $peopleWithBirthDays;
    }

    private static function getNumberBirthDates()
    {
        return count(self::getBirthDates());
    }

    private static function getAnniversaries()
    {
        $Anniversaries = FamilyQuery::create()
              ->filterByWeddingDate(['min' => '0001-00-00']) // a Wedding Date
              ->filterByDateDeactivated(null, Criteria::EQUAL) //Date Deactivated is null (active)
              ->find();
      
        $curDay = date('d');
        $curMonth = date('m');
  
        $families = [];
        foreach ($Anniversaries as $anniversary) {
            if ($anniversary->getWeddingMonth() == $curMonth && $curDay == $anniversary->getWeddingDay()) {
                $families[] = $anniversary;
            }
        }
    
        return $families;
    }

    private static function getNumberAnniversaries()
    {
        return count(self::getAnniversaries());
    }

}