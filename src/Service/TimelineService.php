<?php

require_once "EventService.php";

use ChurchCRM\NoteQuery;
use ChurchCRM\PersonQuery;

class TimelineService
{
  private $baseURL;
  private $currentUser;
  private $currentUserIsAdmin;
  private $eventService;

  public function __construct()
  {
    $this->eventService = new EventService();
    $this->currentUser = $_SESSION['iUserID'];
    $this->currentUserIsAdmin = $_SESSION['bAdmin'];
    $this->baseURL = $_SESSION['sRootPath'];
  }

  function getForFamily($familyID)
  {
    $timeline = array();
    $familyNotes = NoteQuery::create()->findByFamId($familyID);
    foreach ($familyNotes as $dbNote) {
      $item = $this->noteToTimelineItem($dbNote);
      if (!is_null($item)) {
        $timeline[$item["key"]] = $item;
      }
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
    $personNotes = NoteQuery::create()->findByPerId($personID);
    foreach ($personNotes as $dbNote) {
      $item = $this->noteToTimelineItem($dbNote);
      if (!is_null($item)) {
        $timeline[$item["key"]] = $item;
      }
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
      array_push($sortedTimeline, $item);
    }

    return $sortedTimeline;
  }


  function noteToTimelineItem($dbNote)
  {
    $item = NULL;
    if ($this->currentUserIsAdmin || $dbNote->isVisable($this->currentUser)) {
      $displayEditedBy = "unknown?";
      $editor = PersonQuery::create()->findPk($dbNote->getDisplayEditedBy());
      if ($editor != null) {
        $displayEditedBy = $editor->getFullName();
      }
      $item = $this->createTimeLineItem($dbNote->getType(), $dbNote->getDisplayEditedDate(),
        "by " . $displayEditedBy, "", $dbNote->getText(),
        $dbNote->getEditLink($this->baseURL), $dbNote->getDeleteLink($this->baseURL));

    }
    return $item;
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

    $item["datetime"] = $datetime;
    $item["key"] = $datetime;

    return $item;
  }

}
