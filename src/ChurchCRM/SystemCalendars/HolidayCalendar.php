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
use ChurchCRM\data\Countries;
use ChurchCRM\data\Country;
use ChurchCRM\dto\SystemConfig;

class HolidayCalendar implements SystemCalendar {
 
  public static function isAvailable() {
    $systemCountry = Countries::getCountryByName(SystemConfig::getValue("sChurchCountry"));
    if (!empty($systemCountry))
    {
      return $systemCountry->getCountryNameYasumi() !== null;   
    }
    
  }
  
  public function getAccessToken() {
    return false;
  }

  public function getBackgroundColor() {
    return "6dfff5";
  }
  
  public function getForegroundColor() {
    return "000000";
  }

  public function getId() {
    return 2;
  }

  public function getName() {
    return gettext("Holidays");
  }
    
  public function getEvents($start,$end) {
    $Country = Countries::getCountryByName(SystemConfig::getValue("sChurchCountry"));
    $year = date('Y');
    $holidays = Yasumi::create($Country->getCountryNameYasumi(),$year);
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
