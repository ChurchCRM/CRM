<?php

namespace ChurchCRM\Service;

use ChurchCRM\PersonQuery;
use ChurchCRM\EventQuery;
use Propel\Runtime\ActiveQuery\Criteria;

class CalendarService
{

  function getEventTypes()
  {
    $eventTypes = array();
    array_push($eventTypes, array("Name" => "Event", "backgroundColor" =>"#f39c12" ));
    array_push($eventTypes, array("Name" => "Birthday", "backgroundColor" =>"#f56954" ));
    return $eventTypes;
  }

  function getEvents($start, $end)
  {
    $events = array();

    $startDate = date_create($start);
    $endDate = date_create($end);

    $startYear = $endYear = '1900';
    $endsNextYear = false;
    if($endDate->format('Y') > $startDate->format('Y')) {
      $endYear = '1901';
      $endsNextYear = true;
    }

    $firstYear = $startDate->format("Y");

    $perBirthDayThisYear = "STR_TO_DATE(CONCAT('1900-', 
      per.per_BirthMonth, '-', per.per_BirthDay), '%Y-%m-%d')";
    $perBirthDayNextYear = "STR_TO_DATE(CONCAT('1901-', 
      per.per_BirthMonth, '-', per.per_BirthDay), '%Y-%m-%d')";
    $dateRange = [
      $startDate->format($startYear . '-m-d'),
      $endDate->format($endYear . '-m-d')
    ];

    $peopleWithBirthDays = PersonQuery::create('per')
      ->condition('thisYear', $perBirthDayThisYear . ' BETWEEN ? AND ?', $dateRange)
      ->condition('nextYear', $perBirthDayNextYear . ' BETWEEN ? AND ?', $dateRange)
      ->combine(['thisYear', 'nextYear'], Criteria::LOGICAL_OR, 'birthDates')
      ->condition('greaterThanZero', 'per_BirthDay > 0 AND per_BirthMonth > 0')
      ->where(['greaterThanZero', 'birthDates'], Criteria::LOGICAL_AND)
      ->find();

    foreach ($peopleWithBirthDays as $person) {
      $year = $firstYear;
      if ($person->getBirthMonth() == 1 && $endsNextYear) {
        $year = $firstYear + 1;
      }

      $start = date_create($year . '-' .
        $person->getBirthMonth() . '-' . $person->getBirthDay());

      $event = $this->createCalendarItem("birthday",
        $person->getFullName(), $start->format(DATE_ATOM), "", $person->getViewURI());
      array_push($events, $event);
    }

    $activeEvents = EventQuery::create()
      ->filterByInActive("false")
      ->orderByStart()
      ->find();
    foreach ($activeEvents as $evnt) {
      $event = $this->createCalendarItem("event",
        $evnt->getTitle(), $evnt->getStart("Y-m-d"), $evnt->getEnd("Y-m-d"), "");
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
