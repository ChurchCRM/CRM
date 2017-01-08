<?php

namespace ChurchCRM;

use ChurchCRM\Base\Person as BasePerson;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\util\PhotoUtils;
use ChurchCRM\dto\Photo;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Skeleton subclass for representing a row from the 'person_per' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Person extends BasePerson implements iPhoto
{
    public function getFullName()
    {
        return $this->getFirstName().' '.$this->getLastName();
    }

    public function isMale()
    {
        return $this->getGender() == 1;
    }

    public function isFemale()
    {
        return $this->getGender() == 2;
    }

    public function hideAge()
    {
        return $this->getFlags() == 1 || $this->getBirthYear() == '' || $this->getBirthYear() == '0';
    }

    public function getBirthDate()
    {
        if (!is_null($this->getBirthDay()) && $this->getBirthDay() != '' &&
      !is_null($this->getBirthMonth()) && $this->getBirthMonth() != ''
    ) {
            $birthYear = $this->getBirthYear();
            if ($this->hideAge()) {
                $birthYear = 1900;
            }

            return date_create($birthYear.'-'.$this->getBirthMonth().'-'.$this->getBirthDay());
        }

        return date_create();
    }

    public function getViewURI()
    {
        return SystemURLs::getRootPath().'/PersonView.php?PersonID='.$this->getId();
    }

    public function getFamilyRole()
    {
        $roleId = $this->getFmrId();
        if (isset($roleId) && $roleId !== 0) {
            $familyRole = ListOptionQuery::create()->filterById(2)->filterByOptionId($roleId)->findOne();

            return $familyRole;
        }
    }

    public function getFamilyRoleName()
    {
        $roleName = '';
        $role = $this->getFamilyRole();
        if (!is_null($role)) {
            $roleName = $this->getFamilyRole()->getOptionName();
        }

        return $roleName;
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

    public function isUser()
    {
        $user = UserQuery::create()->findPk($this->getId());

        return !is_null($user);
    }

    public function getOtherFamilyMembers()
    {
        $familyMembers = $this->getFamily()->getPeople();
        $otherFamilyMembers = [];
        foreach ($familyMembers as $member) {
            if ($member->getId() != $this->getId()) {
                array_push($otherFamilyMembers, $member);
            }
        }

        return $otherFamilyMembers;
    }
    
    public function deletePhoto()
    {
      if ($_SESSION['bAddRecords'] || $bOkToEdit ) {
        if ( $this->getPhoto()->delete() )
        {
          $note = new Note();
          $note->setText("Profile Image Deleted");
          $note->setType("photo");
          $note->setEntered($_SESSION['iUserID']);

          $note->setPerId($this->getId());
          $note->save();
          return true;
        }
      }
      return false;
    }
    
    private function getPhoto()
    {

      $photo = new Photo("Person",  $this->getId());
       if (!$photo->isPhotoLocal() && SystemConfig::getValue('sEnableGravatarPhotos') && $this->getEmail() != '') {
         $photo->loadFromGravatar($this->getEmail());
       }
       return $photo;
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
        $note->setText("Profile Image uploaded");
        $note->setType("photo");
        $note->setEntered($_SESSION['iUserID']);
        $this->getPhoto()->setImageFromBase64($base64);
        $note->setPerId($this->getId());
        $note->save();
        return true;
      }
      return false;
      
    }

    public function isPhotoLocal() {
      return $this->getPhoto()->isPhotoLocal();
    }

}
