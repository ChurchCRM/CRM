<?php

namespace ChurchCRM;

use ChurchCRM\Base\Note as BaseNote;

/**
 * Skeleton subclass for representing a row from the 'note_nte' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class Note extends BaseNote
{

  function setEntered($enteredBy) {
      $this->setDateEntered(new \DateTime());
      $this->setEnteredBy($enteredBy);
  }

  function getEditLink($rootPath)
  {
    $url = $rootPath . "/NoteEditor.php?NoteID=" . $this->getId() . "&";

    if ($this->getPerId() != "") {
      $url = $url . "PersonID=" . $this->getPerId();
    } else {
      $url = $url . "FamilyID=" . $this->getFamId();
    }
    return $url;
  }

  function getDeleteLink($rootPath)
  {
    return $rootPath . "/NoteDelete.php?NoteID=" . $this->getId();
  }


  function getDisplayEditedDate()
  {
    if ($this->getDateLastEdited() != "") {
      return $this->getDateLastEdited('Y-m-d h:i:s');
    } else {
      return $this->getDateEntered('Y-m-d h:i:s');
    }
  }

  function getDisplayEditedBy()
  {
    if ($this->getEditedBy() != "") {
      return $this->getEditedBy();
    } else {
      return $this->getEnteredBy();
    }
  }

  function isPrivate()
  {
    return $this->getPrivate() != "0";
  }

  function isVisable($personId)
  {
    return !$this->isPrivate() || $this->getPrivate() == $personId;
  }


}
