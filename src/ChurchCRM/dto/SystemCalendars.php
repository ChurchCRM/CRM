<?php

namespace ChurchCRM\dto;
use ChurchCRM\SystemCalendars\BirthdaysCalendar;
use ChurchCRM\SystemCalendars\AnniversariesCalendar;
use ChurchCRM\Interfaces\SystemCalendar;
use Propel\Runtime\Collection\ObjectCollection;
use ChurchCRM\Calendar;

class SystemCalendars {

  private static function getCalendars() {
    $calendars = [];
    array_push($calendars, new BirthdaysCalendar());
    array_push($calendars, new AnniversariesCalendar());
    return $calendars;
  }
  
  public static function getCalendarList() {
    $calendars = new ObjectCollection();
    $calendars->setModel("ChurchCRM\\Calendar");
    foreach(self::getCalendars() as $calendar) {
      $calendars->push(self::toPropelCalendar($calendar));
    }
    return $calendars;
  }
   
  public static function getCalendarById($id) {
    $requestedCalendar = null;
    foreach(self::getCalendars() as $calendar)
    {
      if ($calendar->getId() == $id){
        $requestedCalendar = $calendar;
        break;
      }
    }
    return $requestedCalendar;
  }
  
  public static function toPropelCalendar(SystemCalendar $calendar) {
    $procalendar =  new Calendar();
    $procalendar->setId($calendar->getId());
    $procalendar->setName($calendar->getName());
    $procalendar->setAccessToken($calendar->getAccessToken());
    $procalendar->setBackgroundColor($calendar->getBackgroundColor());
    $procalendar->setForegroundColor($calendar->getForegroundColor());
    return $procalendar;
  }
  
}
