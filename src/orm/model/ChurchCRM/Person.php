<?php

namespace ChurchCRM;

use ChurchCRM\Base\Person as BasePerson;
use ChurchCRM\Note;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Skeleton subclass for representing a row from the 'person_per' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class Person extends BasePerson
{

  public function postInsert(ConnectionInterface $con = null)
  {
    $this->createTimeLineNote(true);
  }

  public function postUpdate(ConnectionInterface $con = null)
  {
    $this->createTimeLineNote(false);
  }

  private function createTimeLineNote($new)
  {
    $note = new Note();
    $note->setPerId($this->getId());

    if ($new) {
      $note->setText("Created");
      $note->setType("create");
      $note->setEnteredBy($this->getEnteredBy());
      $note->setDateLastEdited($this->getDateEntered());
    } else {
      $note->setText("Updated");
      $note->setType("edit");
      $note->setEnteredBy($this->getEditedBy());
      $note->setDateLastEdited($this->getDateLastEdited());
    }

    $note->save();
  }

}
