<?php


namespace ChurchCRM\dto;
use ChurchCRM\Event;
use ChurchCRM\Person;
use ChurchCRM\Family;
use ChurchCRM\Calendar;


class FullCalendarEvent {
  
  //the properties of this DTO are designed to align with the JSON object
  //expected by FullCalendar JS: https://fullcalendar.io/docs/event_data/Event_Object/
  
  
  public $title;
  public $start;
  public $backgroundColor;
  public $end;
  public $allDay;
  public $url;
  public $id;
  
  public function __construct() {
    return $this;
  }
  public function createFromEvent(Event $CRMEvent, Calendar $CRMCalendar) {
        $this->title = $CRMEvent->getTitle();
        $this->start = $CRMEvent->getStart("c");
        $this->end = $CRMEvent->getEnd("c");
        $this->allDay = $false;
        $this->id = $CRMEvent->getId();
        $this->backgroundColor = "#".$CRMCalendar->getBackgroundColor();
        $this->foregroundColor = "#".$CRMCalendar->getForegroundColor();
  }
}
