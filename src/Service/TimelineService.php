<?php

require_once "NoteService.php";
require_once "PersonService.php";
require_once "EventService.php";

use ChurchCRM\NoteQuery;
use ChurchCRM\PersonQuery;

class TimelineService
{

  private $personService;
  private $eventService;

  public function __construct()
  {
    $this->personService = new PersonService();
    $this->eventService = new EventService();
  }

  function getForFamily($familyID)
  {
    $timeline = array();
    $rawNotes = NoteQuery::create()->findByFamId($familyID);
    $notes = $this->convertNotes($rawNotes, $_SESSION['bAdmin']);
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
    $rawNotes = NoteQuery::create()->findByPerId($personID);
    $notes = $this->convertNotes($rawNotes, $_SESSION['bAdmin']);
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

  function convertNotes($notes, $admin)
  {
    $notesArray = array();
    foreach ($notes as $rawNote) {
      // if the user is not admin, ensure the note is not private or it is created by current user
      if ($admin || $rawNote->isVisable($_SESSION['iUserID'])) {

        $note['id'] = $rawNote->getId();
        $note['private'] = $rawNote->getPrivate();
        $note['text'] = $rawNote->getText();
        $note['type'] = $rawNote->getType();

        if ($rawNote->getDateLastEdited() != null) {
          $note['lastUpdateDatetime'] = $rawNote->getDateLastEdited()->format('Y-m-d H:i:s');
          $note['lastUpdateById'] = $rawNote->getEditedBy();
        } else {
          $note['lastUpdateDatetime'] = $rawNote->getDateEntered()->format('Y-m-d H:i:s');
          $note['lastUpdateById'] = $rawNote->getEnteredBy();
        }

        $person = PersonQuery::create()->findPk($note['lastUpdateById']);

        if ($person != null) {
          $note['lastUpdateByName'] = $person->getFullName();
        } else {
          $note['lastUpdateByName'] = "unknown?";
        }
        array_push($notesArray, $note);
      }
    }
    return $notesArray;
  }


}
