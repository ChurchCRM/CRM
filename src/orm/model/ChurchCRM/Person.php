<?php

namespace ChurchCRM;

use ChurchCRM\Base\Person as BasePerson;
use ChurchCRM\Base\UserQuery;
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
  function getFullName()
  {
    return $this->getFirstName() . " " . $this->getLastName();
  }

  function hideAge()
  {
    return $this->getFlags() == 1;
  }

  function getViewURI($baseURL, $addToCart = false)
  {
    $url = $baseURL . "/PersonView.php?PersonID=" . $this->getId();

    if ($addToCart) {
      $url = $url . "&AddToPeopleCart=" . $this->getId();
    }

    return $url;
  }

  function isMale()
  {
    return $this->getGender() == 1;
  }

  function isFemale()
  {
    return $this->getGender() == 2;
  }

  function getDefaultPhoto($baseURL, $famRole)
  {
    $photoFile = $baseURL . "/Images/Person/man-128.png";
    if ($this->isMale() && $famRole == "Child") {
      $photoFile = $baseURL . "/Images/Person/kid_boy-128.png";
    } else if ($this->isFemale() && $famRole != "Child") {
      $photoFile = $baseURL . "/Images/Person/woman-128.png";
    } else if ($this->isFemale() && $famRole == "Child") {
      $photoFile = $baseURL . "/Images/Person/kid_girl-128.png";
    }

    return $photoFile;
  }


  function getGravatar($s = 60, $d = '404', $r = 'g', $img = false, $atts = array())
  {
    if ($this->getEmail() != "") {
    }
    $url = 'http://www.gravatar.com/avatar/';
    $url .= md5(strtolower(trim($this->getEmail())));
    $url .= "?s=$s&d=$d&r=$r";

    $headers = @get_headers($url);
    if (strpos($headers[0], '404') === false) {
      return $url;
    }
    return "";
  }

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

  public function isUser() {
    $user = UserQuery::create()->findPk($this->getId());
    return !is_null($user);
  }

}
