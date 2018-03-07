<?php

namespace ChurchCRM\SystemCalendars;

use ChurchCRM\Interfaces\SystemCalendar;
use ChurchCRM\FamilyQuery;
use Propel\Runtime\Collection\ObjectCollection;
use ChurchCRM\Event;
use ChurchCRM\Calendar;
use Yasumi\Yasumi;
use Yasumi\Holiday;
use Propel\Runtime\ActiveQuery\Criteria;

class HolidayCalendar implements SystemCalendar {
 
  public function getAccessToken() {
    return false;
  }

  public function getBackgroundColor() {
    return "000000";
  }
  
  public function getForegroundColor() {
    return "FFFFFF";
  }

  public function getId() {
    return 2;
  }

  public function getName() {
    return gettext("Holidays");
  }
    
  public function getEvents() {
    $yasholidays = Yasumi::create('USA',2018);
    $holidays = new \Yasumi\Filters\OfficialHolidaysFilter($yasholidays->getIterator());
    $events = new ObjectCollection();
    $events->setModel("ChurchCRM\\Event");
   
    foreach ($holidays as $holiday){
      $event = $this->yasumiHolidayToEvent($holiday);
      $events->push($event);
    }
    return $events;       
  }
  
  public function getEventById($Id) {
    return false;
  }
  
  private function yasumiHolidayToEvent(Holiday $holiday){
    $id = crc32($holiday->getName().$holiday->getTimestamp());
    $holidayEvent = new Event();
    $holidayEvent->setId($id);
    $holidayEvent->setEditable(false);
    $holidayEvent->setTitle($holiday->getName());
    $holidayEvent->setStart($holiday->getTimestamp());
    return $holidayEvent;
  }
}
