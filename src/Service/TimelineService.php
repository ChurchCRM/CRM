<?php

require_once "NoteService.php";
require_once "PersonService.php";
require_once "EventService.php";

class TimelineService
{

  private $personService;
  private $noteService;
  private $eventService;

  public function __construct()
  {
    $this->personService = new PersonService();
    $this->noteService = new NoteService();
    $this->eventService = new EventService();
  }

  function getForFamily($familyID)
  {
    $timeline = array();

    $notes = $this->noteService->getNotesByFamily($familyID, $_SESSION['bAdmin'], $_SESSION['iUserID']);
    foreach ($notes as $note) {
      $item = $this->createTimeLineItem($note["type"], $note["lastUpdateDatetime"],
        "by " . $note["lastUpdateByName"], "", $note["text"],
        "NoteEditor.php?FamilyID=" . $familyID . "&NoteID=" . $note["id"],
        "NoteDelete.php?NoteID=" . $note["id"]);
      $timeline[$item["key"]] = $item;
    }

    krsort($timeline);

    $sortedTimeline = array();
    foreach ($timeline as $date => $item) {
      array_push($sortedTimeline, $item);
    }

    return $sortedTimeline;

  }
  
  function getForPerson($personID)
  {
    $timeline = array();

    $notes = $this->noteService->getNotesByPerson($personID, $_SESSION['bAdmin'], $_SESSION['iUserID']);
    foreach ($notes as $note) {
      $item = $this->createTimeLineItem($note["type"], $note["lastUpdateDatetime"],
        "by " . $note["lastUpdateByName"], "", $note["text"],
        "NoteEditor.php?PersonID=" . $personID . "&NoteID=" . $note["id"],
        "NoteDelete.php?NoteID=" . $note["id"]);
      $timeline[$item["key"]] = $item;
    }

    $events = $this->eventService->getEventsByPerson($personID);
    foreach ($events as $event) {
      $item = $this->createTimeLineItem("cal", $event["date"],
        $event["title"], "", $event["desc"], "", "");
      $timeline[$item["key"]] = $item;
    }

    krsort($timeline);

    $sortedTimeline = array();
    foreach ($timeline as $date => $item) {
      array_push($sortedTimeline,  $item);
    }

    return $sortedTimeline;

  }


  function createTimeLineItem($type, $datetime, $header, $headerLink, $text, $editLink = "", $deleteLink = "")
  {
    switch ($type) {
      case "create":
        $item["style"] = "fa-plus-circle bg-blue";
        break;
      case "edit":
        $item["style"] = "fa-pencil bg-blue";
        break;
      case "photo":
        $item["style"] = "fa-camera bg-green";
        break;
      case "cal":
        $item["style"] = "fa-calendar bg-green";
        break;
      case "verify":
        $item["style"] = "fa-check-circle-o bg-teal";
        break;
      default:
        $item["style"] = "fa-sticky-note bg-green";
        $item["editLink"] = $editLink;
        $item["deleteLink"] = $deleteLink;
    }
    $item["header"] = $header;
    $item["headerLink"] = $headerLink;
    $item["text"] = $text;

    $itemTime = strtotime($datetime);

    $item["datetime"] = $datetime;
    $item["key"] = $itemTime;

    return $item;
  }

}
