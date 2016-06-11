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

  function getForPerson($personID)
  {
    $timeline = array();


    $notes = $this->noteService->getNotesByPerson($personID, $_SESSION['bAdmin'], $_SESSION['iUserID']);
    foreach ($notes as $note) {
      array_push($timeline, $this->createTimeLineItem($note["type"], $note["lastUpdateDatetime"],
        "by " . $note["lastUpdateByName"], "", $note["text"],
        "NoteEditor.php?PersonID=" . $personID . "&NoteID=" . $note["id"],
        "NoteDelete.php?NoteID=" . $note["id"]));
    }

    $events = $this->eventService->getEventsByPerson($personID);
    foreach ($events as $event) {
      array_push($timeline, $this->createTimeLineItem("cal", $event["date"],
        $event["title"], "", $event["desc"], "", ""));
    }

    uasort($timeline, function($a, $b) {return $a[1] - $b[1]; } );

    return $timeline;

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
      case "note":
        $item["style"] = "fa-sticky-note bg-green";
        break;
      case "cal":
        $item["style"] = "fa-calendar bg-green";
        break;
      default:
        $item["style"] = "fa-gear bg-yellow";
    }
    $item["header"] = $header;
    $item["headerLink"] = $headerLink;
    $item["text"] = $text;
    $item["editLink"] = $editLink;
    $item["deleteLink"] = $deleteLink;

    $itemTime = strtotime($datetime);

    $item["datetime"] = $datetime;
    $key = $itemTime;

    return [$key => $item];
  }

}
