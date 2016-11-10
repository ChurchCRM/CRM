<?php

namespace ChurchCRM;

use ChurchCRM\Base\Person as BasePerson;
use ChurchCRM\UserQuery;
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

  protected $baseURL;
  protected $showGravatar;

  public function applyDefaultValues()
  {
    parent::applyDefaultValues();
    $this->baseURL = $_SESSION['sRootPath'];
    $this->showGravatar = $_SESSION['$sEnableGravatarPhotos'];
  }

  function getFullName()
  {
    return $this->getFirstName() . " " . $this->getLastName();
  }

  function isMale()
  {
    return $this->getGender() == 1;
  }

  function isFemale()
  {
    return $this->getGender() == 2;
  }

  function hideAge()
  {
    return $this->getFlags() == 1 || $this->getBirthYear() == "" || $this->getBirthYear() == "0";
  }

  function getBirthDate()
  {
    if (!is_null($this->getBirthDay()) && $this->getBirthDay() != "" &&
      !is_null($this->getBirthMonth()) && $this->getBirthMonth() != ""
    ) {

      $birthYear = $this->getBirthYear();
      if ($this->hideAge()) {
        $birthYear = 1900;
      }

      return date_create($birthYear . "-" . $this->getBirthMonth() . "-" . $this->getBirthDay());
    }

    return date_create();
  }

  function getViewURI()
  {
    return $this->baseURL . "/PersonView.php?PersonID=" . $this->getId();
  }

  function getUploadedPhoto()
  {
    $validextensions = array("jpeg", "jpg", "png");
    $hasFile = false;
    while (list(, $ext) = each($validextensions)) {
      $photoFile = dirname(__FILE__) . "/../../../Images/Person/thumbnails/" . $this->getId() . "." . $ext;
      if (file_exists($photoFile)) {
        $hasFile = true;
        $photoFile = $this->baseURL . "/Images/Person/thumbnails/" . $this->getId() . "." . $ext;
        break;
      }
    }

    if ($hasFile) {
      return $photoFile;
    } else {
      return "";
    }
  }

  function getPhoto()
  {
    $photoFile = $this->getUploadedPhoto();
    if ($photoFile == "") {
      $photoFile = $this->getGravatar();
      if ($photoFile == "") {
        $photoFile = $this->getDefaultPhoto();
      }
    }
    return $photoFile;
  }

  function getFamilyRole()
  {

    $roleId = $this->getFmrId();
    if (isset($roleId) && $roleId !== 0) {
      $familyRole = ListOptionQuery::create()->filterById(2)->filterByOptionId($roleId)->findOne();
      return $familyRole;
    }
    return null;
  }

  function getFamilyRoleName()
  {
    $roleName = "";
    $role = $this->getFamilyRole();
    if (!is_null($role)) {
      $roleName = $this->getFamilyRole()->getOptionName();
    }
    return $roleName;
  }

  function getDefaultPhoto()
  {
    $photoFile = $this->baseURL . "/Images/Person/man-128.png";
    $isChild = "Child" == $this->getFamilyRoleName();
    if ($this->isMale() && $isChild) {
      $photoFile = $this->baseURL . "/Images/Person/kid_boy-128.png";
    } else if ($this->isFemale() && $isChild) {
      $photoFile = $this->baseURL . "/Images/Person/kid_girl-128.png";
    } else if ($this->isFemale() && !$isChild) {
      $photoFile = $this->baseURL . "/Images/Person/woman-128.png";
    }
    return $photoFile;
  }


  function getGravatar($s = 60, $d = '404', $r = 'g', $img = false, $atts = array())
  {
    if ($this->showGravatar && $this->getEmail() != "") {
      $url = 'http://www.gravatar.com/avatar/';
      $url .= md5(strtolower(trim($this->getEmail())));
      $url .= "?s=$s&d=$d&r=$r";

      $headers = @get_headers($url);
      if (strpos($headers[0], '404') === false) {
        return $url;
      }
    }
    return "";
  }

  public
  function postInsert(ConnectionInterface $con = null)
  {
    $this->createTimeLineNote(true);
  }

  public
  function postUpdate(ConnectionInterface $con = null)
  {
    $this->createTimeLineNote(false);
  }

  private
  function createTimeLineNote($new)
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

  public
  function isUser()
  {
    $user = UserQuery::create()->findPk($this->getId());
    return !is_null($user);
  }

  public
  function getOtherFamilyMembers()
  {
    $familyMembers = $this->getFamily()->getPeople();
    $otherFamilyMembers = array();
    foreach ($familyMembers as $member) {
      if ($member->getId() != $this->getId()) {
        array_push($otherFamilyMembers, $member);
      }
    }
    return $otherFamilyMembers;
  }

}
