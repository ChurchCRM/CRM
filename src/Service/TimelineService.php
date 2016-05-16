<?php

require_once "NoteService.php";
require_once "PersonService.php";

class TimelineService
{

  private $personService;
  private $noteService;

  public function __construct()
  {
    $this->personService = new PersonService();
    $this->noteService = new NoteService();
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

    //usort($timeline, "sortFunction");
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
      case "note":
        $item["style"] = "fa-sticky-note bg-green";
        break;
      default:
        $item["style"] = "fa-gear bg-yellow";
    }
    $item["datetime"] = $datetime;
    $item["header"] = $header;
    $item["headerLink"] = $headerLink;
    $item["text"] = $text;
    $item["editLink"] = $editLink;
    $item["deleteLink"] = $deleteLink;

    return $item;
  }

  function sortFunction($a, $b)
  {
    if ($a[1] == $b[1]) return 0;
    return strtotime($a[1]) - strtotime($b[1]);
  }

}
