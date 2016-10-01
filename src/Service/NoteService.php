<?php

namespace ChurchCRM\Service;

use ChurchCRM\Note;

class NoteService
{

  function addNote($personID, $familyID, $private, $text, $type = "system")
  {
    $note = new Note();
    $note->setPerId($personID);
    $note->setFamId($familyID);
    $note->setPrivate($private);
    $note->setText($text);
    $note->setType($type);
    $note->setDateEntered(new \DateTime());
    $note->setEnteredBy($_SESSION['iUserID']);
    $note->save();
  }

}
