<?php

namespace ChurchCRM;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Base\Family as BaseFamily;
use Propel\Runtime\Connection\ConnectionInterface;
use ChurchCRM\dto\Photo;
use ChurchCRM\Utils\GeoUtils;

/**
 * Skeleton subclass for representing a row from the 'family_fam' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Family extends BaseFamily implements iPhoto
{

    public function getAddress()
    {
        $address = [];
        if (!empty($this->getAddress1())) {
            $tmp = $this->getAddress1();
            if (!empty($this->getAddress2())) {
                $tmp = $tmp.' '.$this->getAddress2();
            }
            array_push($address, $tmp);
        }

        if (!empty($this->getCity())) {
            array_push($address, $this->getCity().',');
        }

        if (!empty($this->getState())) {
            array_push($address, $this->getState());
        }

        if (!empty($this->getZip())) {
            array_push($address, $this->getZip());
        }
        if (!empty($this->getCountry())) {
            array_push($address, $this->getCountry());
        }

        return implode(' ', $address);
    }

    public function getViewURI()
    {
        return SystemURLs::getRootPath().'/FamilyView.php?FamilyID='.$this->getId();
    }

    public function getWeddingDay()
    {
        if (!is_null($this->getWeddingdate()) && $this->getWeddingdate() != '') {
            $day = $this->getWeddingdate()->format('d');

            return $day;
        }

        return '';
    }

    public function getWeddingMonth()
    {
        if (!is_null($this->getWeddingdate()) && $this->getWeddingdate() != '') {
            $month = $this->getWeddingdate()->format('m');

            return $month;
        }

        return '';
    }

    public function postInsert(ConnectionInterface $con = null)
    {
        $this->createTimeLineNote(true);
    }

    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->createTimeLineNote(false);
    }


  public function getPeopleSorted() {
    $familyMembersParents = array_merge($this->getHeadPeople(), $this->getSpousePeople());
    $familyMembersChildren = $this->getChildPeople();
    $familyMembersOther = $this->getOtherPeople();
    return array_merge($familyMembersParents, $familyMembersChildren, $familyMembersOther);
  }

  public function getHeadPeople() {
    return $this->getPeopleByRole("sDirRoleHead");
  }

  public function getSpousePeople() {
    return $this->getPeopleByRole("sDirRoleSpouse");
  }

  public function getChildPeople() {
    return $this->getPeopleByRole("sDirRoleChild");
  }

  public function getOtherPeople() {
    $roleIds = array_merge (explode(",", SystemConfig::getValue("sDirRoleHead")), explode(",",
      SystemConfig::getValue("sDirRoleSpouse")),
      explode(",", SystemConfig::getValue("sDirRoleChild")));
    $foundPeople = array();
    foreach ($this->getPeople() as $person) {
      if (!in_array($person->getFmrId(), $roleIds)) {
        array_push($foundPeople, $person);
      }
    }
    return $foundPeople;
  }

  private function getPeopleByRole($roleConfigName) {
    $roleIds = explode(",", SystemConfig::getValue($roleConfigName));
    $foundPeople = array();
    foreach ($this->getPeople() as $person) {
      if (in_array($person->getFmrId(), $roleIds)) {
          array_push($foundPeople, $person);
      }
    }
    return $foundPeople;
  }

  public function getEmails() {
    $emails = array();
    foreach ($this->getPeople() as $person) {
      $email = $person->getEmail();
      if ($email != null) {
        array_push($emails, $email);
      }
      $email = $person->getWorkEmail();
      if ($email != null) {
        array_push($emails, $email);
      }
    }
    return $emails;
  }

    private function createTimeLineNote($new)
    {
      $note = new Note();
      $note->setFamId($this->getId());

      if ($new) {
          $note->setText('Created');
          $note->setType('create');
          $note->setEnteredBy($this->getEnteredBy());
          $note->setDateLastEdited($this->getDateEntered());
      } else {
          $note->setText('Updated');
          $note->setType('edit');
          $note->setEnteredBy($this->getEditedBy());
          $note->setDateLastEdited($this->getDateLastEdited());
      }

      $note->save();
    }

    /**
     * Figure out how to address a family for correspondence.
     *
     * Put the name if there is only one individual in the family.
     * Put two first names and the last name when there are exactly two people in the family
     * (e.g. "Nathaniel and Jeanette Brooks").
     * Put two whole names where there are exactly two people with different names
     * (e.g. "Doug Philbrook and Karen Andrews")
     * When there are more than two people in the family I don't have any way to know
     * which people are children, so I would have to just use the family name (e.g. "Grossman Family").
     *
     * @return string
     */
    public function getSaluation()
    {
        $childRoleId = SystemConfig::getValue("sDirRoleChild");
        $people = $this->getPeople();
        $notChildren = null;
        foreach ($people as $person) {
            if ($person->getFmrId() != $childRoleId) {
                $notChildren[] = $person;
            }
        }

        $notChildrenCount = count($notChildren);
        if ($notChildrenCount === 1) {
            return $notChildren[0]->getFullName();
        }

        if ($notChildrenCount === 2) {
            if ($notChildren[0]->getLastName() == $notChildren[1]->getLastName()) {
                return $notChildren[0]->getFirstName() .' & '. $notChildren[1]->getFirstName() .' '. $notChildren[0]->getLastName();
            }
            return $notChildren[0]->getFullName() .' & '. $notChildren[1]->getFullName();
        }

        return $this->getName() . ' Family';
    }

    private function getPhoto()
    {
      $photo = new Photo("Family",  $this->getId());
      return $photo;
    }

    public function deletePhoto()
    {
      if ($_SESSION['bAddRecords'] || $bOkToEdit ) {
        if ( $this->getPhoto()->delete() )
        {
          $note = new Note();
          $note->setText(gettext("Profile Image Deleted"));
          $note->setType("photo");
          $note->setEntered($_SESSION['iUserID']);
          $note->setPerId($this->getId());
          $note->save();
          return true;
        }
      }
      return false;
    }

    public function getPhotoBytes() {
      return $this->getPhoto()->getPhotoBytes();
    }

    public function getPhotoURI() {
      return $this->getPhoto()->getPhotoURI();
    }

    public function getThumbnailBytes() {
      return $this->getPhoto()->getThumbnailBytes();
    }

    public function getThumbnailURI() {
       return $this->getPhoto()->getThumbnailURI();
    }

    public function setImageFromBase64($base64) {
      if ($_SESSION['bAddRecords'] || $bOkToEdit ) {
        $note = new Note();
        $note->setText(gettext("Profile Image uploaded"));
        $note->setType("photo");
        $note->setEntered($_SESSION['iUserID']);
        $this->getPhoto()->setImageFromBase64($base64);
        $note->setFamId($this->getId());
        $note->save();
        return true;
      }
      return false;
    }

    public function isPhotoLocal() {
      return $this->getPhoto()->isPhotoLocal();
    }

    public function isPhotoRemote() {
      return $this->getPhoto()->isPhotoRemote();
    }

    public function getPhotoContentType() {
      return $this->getPhoto()->getPhotoContentType();
    }

    public function verify()
    {
        $note = new Note();
        $note->setFamId($this->getId());
        $note->setText(gettext('Family Data Verified'));
        $note->setType('verify');
        $note->setEntered($_SESSION['user']->getId());
        $note->save();
    }

    public function getFamilyString()
    {
        return $this->getName(). " " . $this->getAddress();
    }

    public function hasLatitudeAndLongitude() {
        return !empty($this->getLatitude()) && !empty($this->getLongitude());
    }

    /**
     * if the latitude or longitude is empty find the lat/lng from the address and update the lat lng for the family.
     * @return array of Lat/Lng
     */
    public function updateLanLng() {
        if (!empty($this->getAddress()) && (!$this->hasLatitudeAndLongitude())) {
            $latLng = GeoUtils::getLatLong($this->getAddress());
            if(!empty( $latLng['Latitude']) && !empty($latLng['Longitude'])) {
                $this->setLatitude($latLng['Latitude']);
                $this->setLongitude($latLng['Longitude']);
                $this->save();
            }
        }
    }
}
