<?php

namespace ChurchCRM\SystemCalendars;

use ChurchCRM\Interfaces\SystemCalendar;
use ChurchCRM\PersonQuery;
use ChurchCRM\EventQuery;
use Propel\Runtime\ActiveQuery\Criteria;

class UnpinnedEvents implements SystemCalendar {
 
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
    return gettext("Unpinned Events");
  }
    
  public function getEvents($start,$end) {
    $Events = EventQuery::create()
        ->filterByStart(array("min" => $start))
        ->filterByEnd(array("max" => $end))
        ->useCalendarEventQuery(null,\Propel\Runtime\ActiveQuery\Criteria::LEFT_JOIN)
          ->filterByCalendarId(null)
        ->endUse()
        ->find();
    return $Events;
  }
  
  public function getEventById($Id) {
    $Event = EventQuery::create()
      ->findOneById($Id);
    return $Event;
  }
  
}
