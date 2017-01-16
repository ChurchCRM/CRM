<?php

namespace ChurchCRM;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Base\Family as BaseFamily;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Skeleton subclass for representing a row from the 'family_fam' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Family extends BaseFamily
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
            $note->setDateEntered($this->getDateEntered());
        } else {
            $note->setText('Updated');
            $note->setType('edit');
            $note->setEnteredBy($this->getEditedBy());
            $note->setDateLastEdited($this->getDateLastEdited());
        }

        $note->save();
    }
}
