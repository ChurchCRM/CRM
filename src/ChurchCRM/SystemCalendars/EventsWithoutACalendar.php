<?php

namespace ChurchCRM\SystemCalendars;

use ChurchCRM\Interfaces\SystemCalendar;
use ChurchCRM\PersonQuery;
use Propel\Runtime\Collection\ObjectCollection;
use ChurchCRM\Event;
use ChurchCRM\Calendar;
use Propel\Runtime\ActiveQuery\Criteria;

class EventsWithoutACalendar implements SystemCalendar {
 
  public static function isAvailable() {
    return true;
  }
  
  public function getAccessToken() {
    return false;
  }

  public function getBackgroundColor() {
    return "FF0000";
  }
  
  public function getForegroundColor() {
    return "FFFFFF";
  }

  public function getId() {
    return 3;
  }

  public function getName() {
    return gettext("Events without a Calendar");
  }
    
  public function getEvents() {    
    $events = \ChurchCRM\Base\EventQuery::Create()
            ->leftJoinWithCalendarEvent()
            
             ->where('calendar_events.event_id IS NULL')
            ->find();
    return $events;
  }
  
  public function getEventById($Id) {
    $people = PersonQuery::create()
            ->filterByBirthDay('', Criteria::NOT_EQUAL)
            ->filterById($Id)
            ->find();
    return $this->peopleCollectionToEvents($people);  
  }

}
