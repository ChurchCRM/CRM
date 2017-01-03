<?php

namespace ChurchCRM\Service;

use ChurchCRM\EventAttendQuery;
use ChurchCRM\Note;
use ChurchCRM\NoteQuery;
use ChurchCRM\PersonQuery;


class TimelineService
{
    /* @var $currentUser \ChurchCRM\User */
  private $currentUser;

  public function __construct()
  {
    $this->currentUser = $_SESSION['user'];
  }

  function getForFamily($familyID)
  {
        $timeline = [];
    $familyNotes = NoteQuery::create()->findByFamId($familyID);
    foreach ($familyNotes as $dbNote) {
      $item = $this->noteToTimelineItem($dbNote);
      if (!is_null($item)) {
        $timeline[$item["key"]] = $item;
      }
    }

    krsort($timeline);

        $sortedTimeline = [];
    foreach ($timeline as $date => $item) {
      array_push($sortedTimeline, $item);
    }

    return $sortedTimeline;
  }

  private function eventsForPerson($personID) {
        $timeline = [];
    $eventsByPerson = EventAttendQuery::create()->findByPersonId($personID);
    foreach ($eventsByPerson as $personEvent) {
      $event = $personEvent->getEvent();
      $item = $this->createTimeLineItem("cal", $event->getStart('Y-m-d h:i:s'), $event->getTitle(), "",
        $event->getDesc(), "", "");
      $timeline[$item["key"]] = $item;
    }
    return $timeline;
  }

  private function notesForPerson($personID, $noteType) {
        $timeline = [];
    $personQuery = NoteQuery::create()
      ->filterByPerId($personID);
    if($noteType != null) {
      $personQuery->filterByType($noteType);
    }
    foreach ($personQuery->find() as $dbNote) {
      $item = $this->noteToTimelineItem($dbNote);
      if (!is_null($item)) {
        $timeline[$item["key"]] = $item;
      }
    }
    return $timeline;
  }

  private function sortTimeline($timeline) {
    krsort($timeline);

        $sortedTimeline = [];
    foreach ($timeline as $date => $item) {
      array_push($sortedTimeline, $item);
    }

    return $sortedTimeline;
  }

  function getNotesForPerson($personID) {
    $timeline = $this->notesForPerson($personID, 'note');
    return $this->sortTimeline($timeline);
  }

  function getForPerson($personID)
  {
    $timeline = array_merge(
      $this->notesForPerson($personID, null),
      $this->eventsForPerson($personID)
    );

    return $this->sortTimeline($timeline);
  }

  /**
   * @param $dbNote Note
   * @return mixed|null
   */
  function noteToTimelineItem($dbNote)
  {
    $item = NULL;
    if ($this->currentUser->isAdmin() || $dbNote->isVisable($this->currentUser->getPersonId())) {
      $displayEditedBy = gettext("Unknown");
      if ($dbNote->getDisplayEditedBy() == -1) {
        $displayEditedBy = gettext("Self Registration");
      } else if ($dbNote->getDisplayEditedBy() == -2) {
        $displayEditedBy = gettext("Self Verification");
      } else {
        $editor = PersonQuery::create()->findPk($dbNote->getDisplayEditedBy());
        if ($editor != null) {
          $displayEditedBy = $editor->getFullName();
        }
      }
      $item = $this->createTimeLineItem($dbNote->getType(), $dbNote->getDisplayEditedDate(),
        gettext("by") . " " . $displayEditedBy, "", $dbNote->getText(),
        $dbNote->getEditLink(), $dbNote->getDeleteLink());

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
