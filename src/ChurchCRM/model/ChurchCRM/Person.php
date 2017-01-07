<?php

namespace ChurchCRM;

use ChurchCRM\Base\Person as BasePerson;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
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
class Person extends BasePerson
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

    public function getUploadedPhoto()
    {
        $validextensions = ['jpeg', 'jpg', 'png'];
        $hasFile = false;
        while (list(, $ext) = each($validextensions)) {
            $photoFile = SystemURLs::getDocumentRoot().'/Images/Person/thumbnails/'.$this->getId().'.'.$ext;
            if (file_exists($photoFile)) {
                $hasFile = true;
                $photoFile = SystemURLs::getRootPath().'/Images/Person/thumbnails/'.$this->getId().'.'.$ext;
                break;
            }
        }

        if ($hasFile) {
            return $photoFile;
        } else {
            return '';
        }
    }

    public function getPhoto()
    {
        $photoFile = $this->getUploadedPhoto();
        if ($photoFile == '') {
            $photoFile = $this->getGravatar();
            if ($photoFile == '') {
                $photoFile = $this->getDefaultPhoto();
            }
        }

        return $photoFile;
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

    public function getDefaultPhoto()
    {
        $photoFile = SystemURLs::getRootPath().'/Images/Person/man-128.png';
        $isChild = 'Child' == $this->getFamilyRoleName();
        if ($this->isMale() && $isChild) {
            $photoFile = SystemURLs::getRootPath().'/Images/Person/kid_boy-128.png';
        } elseif ($this->isFemale() && $isChild) {
            $photoFile = SystemURLs::getRootPath().'/Images/Person/kid_girl-128.png';
        } elseif ($this->isFemale() && !$isChild) {
            $photoFile = SystemURLs::getRootPath().'/Images/Person/woman-128.png';
        }

        return $photoFile;
    }

    public function getGravatar($s = 60, $d = '404', $r = 'g', $img = false, $atts = [])
    {
        if (SystemConfig::getValue('sEnableGravatarPhotos') && $this->getEmail() != '') {
            $url = 'http://www.gravatar.com/avatar/';
            $url .= md5(strtolower(trim($this->getEmail())));
            $url .= "?s=$s&d=$d&r=$r";

            $headers = @get_headers($url);
            if (strpos($headers[0], '404') === false) {
                return $url;
            }
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

    private function createTimeLineNote($new)
    {
        $note = new Note();
        $note->setPerId($this->getId());

        if ($new) {
            $note->setText('Created');
            $note->setType('create');
            $note->setEnteredBy($this->getEnteredBy());
            $note->setDateEntered($this->getDateEntered());
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
}
