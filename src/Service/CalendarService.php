<?php

use ChurchCRM\PersonQuery;
use ChurchCRM\EventQuery;

class CalendarService
{

  private $baseURL;

  public function __construct()
  {
    $this->baseURL = $_SESSION['sRootPath'];
  }

  function getEventTypes()
  {
    $eventTypes = array();
    $eventType = array("Name" => "Event", "backgroundColor" =>"#f39c12" );
    array_push($eventTypes, $eventType);
    $eventType = array("Name" => "Birthday", "backgroundColor" =>"#f56954" );
    array_push($eventTypes, $eventType);
    return $eventTypes;
  }

  function getEvents()
  {
    $events = array();

    $curYear = date("Y");
    $curMonth = date("m");
    $peopleWithBirthDays = PersonQuery::create()
      ->filterByBirthMonth(array('min' => 1)) // have birthday month
      ->filterByBirthDay(array('min' => 1)) // have birthday day
      ->find();

    foreach ($peopleWithBirthDays as $person) {
      $year = $curYear;
      if ($person->getBirthMonth() < $curMonth) {
        $year = $year + 1;
      }
      $start = $year . "-" . $person->getBirthMonth() . "-" . $person->getBirthDay();
      $event = $this->createCalendarItem("birthday", $person->getFullName(), $start, "", $person->getViewURI($this->baseURL));
      array_push($events, $event);
    }


    $activeEvents = EventQuery::create()
      ->filterByInActive("false")
      ->orderByStart()
      ->find();
    foreach ($activeEvents as $evnt) {
      $event = $this->createCalendarItem("event", $evnt->getTitle(), $evnt->getStart("Y-m-d"), $evnt->getEnd("Y-m-d"), "");
      array_push($events, $event);
    }

    return $events;
  }


  function createCalendarItem($type, $title, $start, $end, $uri)
  {
    $event = array();
    switch ($type) {
      case "birthday":
        $event["backgroundColor"] = '#f56954';
        break;
      case "event":
        $event["backgroundColor"] = '#f39c12';
        break;
      default:
        $event["backgroundColor"] = '#eeeeee';
    }

    $event["title"] = $title;
    $event["start"] = $start;
    if ($end != "") {
      $event["end"] = $end;
    }
    if ($uri != "") {
      $event["url"] = $uri;
    }
    $event["allDay"] = true;
    return $event;
  }

}
