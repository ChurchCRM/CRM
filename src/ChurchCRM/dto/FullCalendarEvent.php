<?php


namespace ChurchCRM\dto;
use ChurchCRM\Event;
use ChurchCRM\Person;
use ChurchCRM\Family;


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
  public function createFromEvent(Event $CRMEvent) {
        $this->title = $CRMEvent->getTitle();
        $this->start = $CRMEvent->getStart("c");
        $this->end = $CRMEvent->getEnd("c");
        $this->allDay = $false;
        $this->id = $CRMEvent->getId();
  }
  
  public function createAnniversaryFromFamily(Family $CRMFamily) {
    
  }
  
  public function createBirthdayFromPerson(Person $CRMPerson) {
    
  }
}
