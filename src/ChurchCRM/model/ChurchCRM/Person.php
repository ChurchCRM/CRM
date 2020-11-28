<?php

namespace ChurchCRM;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Base\Person as BasePerson;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\NewPersonOrFamilyEmail;
use ChurchCRM\Service\GroupService;
use ChurchCRM\Utils\GeoUtils;
use ChurchCRM\Utils\LoggerUtils;
use DateTime;
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

    const SELF_REGISTER = -1;
    const SELF_VERIFY = -2;
    private $photo;

    public function getFullName()
    {
        return $this->getFormattedName(SystemConfig::getValue('iPersonNameStyle'));
    }

    public function isMale()
    {
        return $this->getGender() == 1;
    }

    public function isFemale()
    {
        return $this->getGender() == 2;
    }

    public function getGenderName()
    {
      switch (strtolower($this->getGender())) {
        case 1:
          return gettext('Male');
        case 2:
          return gettext('Female');
      }
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
            if ($birthYear == '')
            {
              $birthYear = 1900;
            }
            return date_create($birthYear . '-' . $this->getBirthMonth() . '-' . $this->getBirthDay());
        }
        return false;

    }

    public function getFormattedBirthDate()
    {
      $birthDate = $this->getBirthDate();
      if (!$birthDate) {
        return false;
      }
      if ($this->hideAge())
      {
        return $birthDate->format(SystemConfig::getValue("sDateFormatNoYear"));
      }
      else
      {
        return $birthDate->format(SystemConfig::getValue("sDateFormatLong"));
      }

    }

    public function getViewURI()
    {
        return SystemURLs::getRootPath() . '/PersonView.php?PersonID=' . $this->getId();
    }

    public function getFamilyRole()
    {
        $familyRole = null;
        $roleId = $this->getFmrId();
        if (isset($roleId) && $roleId !== 0) {
            $familyRole = ListOptionQuery::create()->filterById(2)->filterByOptionId($roleId)->findOne();
        }

        return $familyRole;
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

    public function getClassification()
    {
      $classification = null;
      $clsId = $this->getClsId();
      if (!empty($clsId)) {
        $classification = ListOptionQuery::create()->filterById(1)->filterByOptionId($clsId)->findOne();
      }
      return $classification;
    }

    public function getClassificationName()
    {
      $classificationName = '';
      $classification = $this->getClassification();
      if (!is_null($classification)) {
        $classificationName = $classification->getOptionName();
      }
      return $classificationName;
    }

    public function postInsert(ConnectionInterface $con = null)
    {
      $this->createTimeLineNote('create');
      if (!empty(SystemConfig::getValue("sNewPersonNotificationRecipientIDs")))
      {
        $NotificationEmail = new NewPersonOrFamilyEmail($this);
        if (!$NotificationEmail->send()) {
            LoggerUtils::getAppLogger()->warning(gettext("New Person Notification Email Error"). " :". $NotificationEmail->getError());
        }
      }
    }

    public function postUpdate(ConnectionInterface $con = null)
    {
      if (!empty($this->getDateLastEdited())) {
        $this->createTimeLineNote('edit');
      }
    }

    private function createTimeLineNote($type)
    {
        $note = new Note();
        $note->setPerId($this->getId());
        $note->setType($type);
        $note->setDateEntered(new DateTime());

         switch ($type) {
            case "create":
              $note->setText(gettext('Created'));
              $note->setEnteredBy($this->getEnteredBy());
              $note->setDateEntered($this->getDateEntered());
              break;
            case "edit":
              $note->setText(gettext('Updated'));
              $note->setEnteredBy($this->getEditedBy());
              $note->setDateEntered($this->getDateLastEdited());
              break;
        }

        $note->save();
    }

    public function isUser()
    {
        $user = UserQuery::create()->findPk($this->getId());

        return !is_null($user);
    }

    /**
     * Get address of  a person. If empty, return family address.
     * @return string
     */
    public function getAddress()
    {
        if (!empty($this->getAddress1())) {
            $address = [];
            $tmp = $this->getAddress1();
            if (!empty($this->getAddress2())) {
                $tmp = $tmp . ' ' . $this->getAddress2();
            }
            array_push($address, $tmp);
            if (!empty($this->getCity())) {
                array_push($address, $this->getCity() . ',');
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
        } else {
            if ($this->getFamily()) {
                return $this->getFamily()
                    ->getAddress();
            }
        }
        //if it reaches here, no address found. return empty $address
        return "";
    }

    /**
     * Get name of a person family.
     * @return string
     */
    public function getFamilyName()
    {
      if ($this->getFamily()) {
          return $this->getFamily()
              ->getName();
      }
      //if it reaches here, no family name found. return empty family name
      return "";
    }

    /**
     * Get name of a person family.
     * @return string
     */
    public function getFamilyCountry()
    {
      if ($this->getFamily()) {
          return $this->getFamily()
              ->getCountry();
      }
      //if it reaches here, no country found. return empty country
      return "";
    }

     /**
     * Get Phone of a person family.
     * 0 = Home
     * 1 = Work
     * 2 = Cell
     * @return string
     */
    public function getFamilyPhone($type)
    {
      switch ($type) {
        case 0:
          if($this->getFamily()) {
          return $this->getFamily()
              ->getHomePhone();
          }
          break;
        case 1:
        if($this->getFamily()) {
          return $this->getFamily()
              ->getWorkPhone();
          }
          break;
        case 2:
        if($this->getFamily()) {
          return $this->getFamily()
              ->getCellPhone();
          }
          break;
      }
      //if it reaches here, no phone found. return empty phone
      return "";
    }
    /**
     * * If person address found, return latitude and Longitude of person address
     * else return family latitude and Longitude
     * @return array
     */
    public function getLatLng()
    {
        $address = $this->getAddress(); //if person address empty, this will get Family address
        $lat = 0;
        $lng = 0;
        if (!empty($this->getAddress1())) {
            $latLng = GeoUtils::getLatLong($this->getAddress());
            if (!empty($latLng['Latitude']) && !empty($latLng['Longitude'])) {
                $lat = $latLng['Latitude'];
                $lng = $latLng['Longitude'];
            }
        } else {
         // Philippe Logel : this is usefull when a person don't have a family : ie not an address
         if (!empty($this->getFamily()))
         {
    if (!$this->getFamily()->hasLatitudeAndLongitude()) {
     $this->getFamily()->updateLanLng();
    }
    $lat = $this->getFamily()->getLatitude();
    $lng = $this->getFamily()->getLongitude();
   }
        }
        return array(
            'Latitude' => $lat,
            'Longitude' => $lng
        );
    }

    public function deletePhoto()
    {
        if (AuthenticationManager::GetCurrentUser()->isDeleteRecordsEnabled()) {
            if ($this->getPhoto()->delete()) {
                $note = new Note();
                $note->setText(gettext("Profile Image Deleted"));
                $note->setType("photo");
                $note->setEntered(AuthenticationManager::GetCurrentUser()->getId());
                $note->setPerId($this->getId());
                $note->save();
                return true;
            }
        }
        return false;
    }

    public function getPhoto()
    {
      if (!$this->photo)
      {
        $this->photo = new Photo("Person",  $this->getId());
      }
      return $this->photo;
    }

    public function setImageFromBase64($base64)
    {
        if (AuthenticationManager::GetCurrentUser()->isEditRecordsEnabled()) {
            $note = new Note();
            $note->setText(gettext("Profile Image uploaded"));
            $note->setType("photo");
            $note->setEntered(AuthenticationManager::GetCurrentUser()->getId());
            $this->getPhoto()->setImageFromBase64($base64);
            $note->setPerId($this->getId());
            $note->save();
            return true;
        }
        return false;

    }

    /**
     * Returns a string of a person's full name, formatted as specified by $Style
     * $Style = 0  :  "Title FirstName MiddleName LastName, Suffix"
     * $Style = 1  :  "Title FirstName MiddleInitial. LastName, Suffix"
     * $Style = 2  :  "LastName, Title FirstName MiddleName, Suffix"
     * $Style = 3  :  "LastName, Title FirstName MiddleInitial., Suffix"
     * $Style = 4  :  "FirstName MiddleName LastName"
     * $Style = 5  :  "Title FirstName LastName"
     * $Style = 6  :  "LastName, Title FirstName"
     * $Style = 7  :  "LastName FirstName"
     * $Style = 8  :  "LastName, FirstName Middlename"
     *
     * @param $Style
     * @return string
     */
    public function getFormattedName($Style)
    {
        $nameString = '';
        switch ($Style) {
            case 0:
                if ($this->getTitle()) {
                    $nameString .= $this->getTitle() . ' ';
                }
                $nameString .= $this->getFirstName();
                if ($this->getMiddleName()) {
                    $nameString .= ' ' . $this->getMiddleName();
                }
                if ($this->getLastName()) {
                    $nameString .= ' ' . $this->getLastName();
                }
                if ($this->getSuffix()) {
                    $nameString .= ', ' . $this->getSuffix();
                }
                break;

            case 1:
                if ($this->getTitle()) {
                    $nameString .= $this->getTitle() . ' ';
                }
                $nameString .= $this->getFirstName();
                if ($this->getMiddleName()) {
                    $nameString .= ' ' . strtoupper(mb_substr($this->getMiddleName(), 0, 1, 'UTF-8')) . '.';
                }
                if ($this->getLastName()) {
                    $nameString .= ' ' . $this->getLastName();
                }
                if ($this->getSuffix()) {
                    $nameString .= ', ' . $this->getSuffix();
                }
                break;

            case 2:
                if ($this->getLastName()) {
                    $nameString .= $this->getLastName() . ', ';
                }
                if ($this->getTitle()) {
                    $nameString .= $this->getTitle() . ' ';
                }
                $nameString .= $this->getFirstName();
                if ($this->getMiddleName()) {
                    $nameString .= ' ' . $this->getMiddleName();
                }
                if ($this->getSuffix()) {
                    $nameString .= ', ' . $this->getSuffix();
                }
                break;

            case 3:
                if ($this->getLastName()) {
                    $nameString .= $this->getLastName() . ', ';
                }
                if ($this->getTitle()) {
                    $nameString .= $this->getTitle() . ' ';
                }
                $nameString .= $this->getFirstName();
                if ($this->getMiddleName()) {
                    $nameString .= ' ' . strtoupper(mb_substr($this->getMiddleName(), 0, 1, 'UTF-8')) . '.';
                }
                if ($this->getSuffix()) {
                    $nameString .= ', ' . $this->getSuffix();
                }
                break;

            case 4:
                $nameString .= $this->getFirstName();
                if ($this->getMiddleName()) {
                    $nameString .= ' ' . $this->getMiddleName();
                }
                if ($this->getLastName()) {
                    $nameString .= ' ' . $this->getLastName();
                }
                break;

            case 5:

                if ($this->getTitle()) {
                    $nameString .= $this->getTitle() . ' ';
                }
                $nameString .= $this->getFirstName();
                if ($this->getLastName()) {
                    $nameString .= ' ' . $this->getLastName();
                }
                break;

            case 6:
                if ($this->getLastName()) {
                    $nameString .= $this->getLastName() . ', ';
                }
                if ($this->getTitle()) {
                    $nameString .= $this->getTitle() . ' ';
                }
                $nameString .= $this->getFirstName();
                break;

            case 7:
                if ($this->getLastName()) {
                    $nameString .= $this->getLastName() . ' ';
                }
                if ($this->getFirstName() ){
                    $nameString .= $this->getFirstName();
                } else {
                    $nameString = trim($nameString);
                }
                break;

            case 8:
                if ($this->getLastName()) {
                    $nameString .= $this->getLastName();
                }
                if ($this->getFirstName()) {
                  if (!$nameString) { // no first name
                    $nameString = $this->getFirstName();
                  } else {
                    $nameString .= ', ' . $this->getFirstName();
                  }

                }
                if ($this->getMiddleName()) {
                    $nameString .= ' ' . $this->getMiddleName();
                }
                break;
            default:
                $nameString = trim($this->getFullName());

        }
        return $nameString;
    }

    public function preDelete(ConnectionInterface $con = null)
    {
        $this->deletePhoto();

        $obj = Person2group2roleP2g2rQuery::create()->filterByPerson($this)->find($con);
        if (!empty($obj)) {
            $groupService = new GroupService();
            foreach ($obj as $group2roleP2g2r) {
                $groupService->removeUserFromGroup($group2roleP2g2r->getGroupId(), $group2roleP2g2r->getPersonId());
            }
        }

        $perCustom = PersonCustomQuery::create()->findPk($this->getId(), $con);
        if (!is_null($perCustom)) {
            $perCustom->delete($con);
        }

        $user = UserQuery::create()->findPk($this->getId(), $con);
        if (!is_null($user)) {
            $user->delete($con);
        }

        PersonVolunteerOpportunityQuery::create()->filterByPersonId($this->getId())->delete($con);

        PropertyQuery::create()
            ->filterByProClass("p")
            ->useRecordPropertyQuery()
            ->filterByRecordId($this->getId())
            ->delete($con);

        NoteQuery::create()->filterByPerson($this)->delete($con);

        return parent::preDelete($con);
    }

    public function getProperties() {
      $personProperties = PropertyQuery::create()
          ->filterByProClass("p")
          ->useRecordPropertyQuery()
          ->filterByRecordId($this->getId())
          ->find();
      return $personProperties;
    }

    //  return array of person properties
    // created for the person-list.php datatable
    public function getPropertiesString() {
      $personProperties = PropertyQuery::create()
          ->filterByProClass("p")
          ->leftJoinRecordProperty()
          ->where('r2p_record_ID='.$this->getId())
          ->find();

      $PropertiesList = [];
      foreach($personProperties as $element) {
          $PropertiesList[] = $element->getProName();
      }
      return $PropertiesList;
    }

    // return array of person custom fields
    // created for the person-list.php datatable
    public function getCustomFields() {
      // get list of custom field column names
      $allPersonCustomFields = PersonCustomMasterQuery::create()->find();

      // add custom fields to person_custom table since they are not defined in the propel schema
      $rawQry =  PersonCustomQuery::create();
      foreach ($allPersonCustomFields as $customfield ) {
          if (AuthenticationManager::GetCurrentUser()->isEnabledSecurity($customfield->getFieldSecurity())) {
            $rawQry->withColumn($customfield->getId());
          }
      }
      $thisPersonCustomFields = $rawQry->findOneByPerId($this->getId());

      // get custom column names and values
      $personCustom = [];
      if ($rawQry->count() > 0) {
        foreach ($allPersonCustomFields as $customfield ) {
            if (AuthenticationManager::GetCurrentUser()->isEnabledSecurity($customfield->getFieldSecurity())) {
                $value = $thisPersonCustomFields->getVirtualColumn($customfield->getId());
                if (!empty($value)) {
                    $personCustom[] = $customfield->getName();
                }
            }
        }
      }
      return $personCustom;
    }
    // return array of person groups
    // created for the person-list.php datatable
    public function getGroups() {
      $GroupList = GroupQuery::create()
      ->leftJoinPerson2group2roleP2g2r()
      ->where('p2g2r_per_ID='.$this->getId())
      ->find();

      $group = [];
      foreach($GroupList as $element) {
        $group[] = $element->getName();
      }
      return $group;
    }

    public function getNumericCellPhone()
    {
      return "1".preg_replace('/[^\.0-9]/',"",$this->getCellPhone());
    }

    public function postSave(ConnectionInterface $con = null) {
      $this->getPhoto()->refresh();
      return parent::postSave($con);
    }

    public function getAge($now = null)
    {
      $birthDate = $this->getBirthDate();

      if ($this->hideAge())
      {
        return false;
      }
      if (empty($now)) {
        $now = date_create('today');
      }
      $age = date_diff($now,$birthDate);

      if ($age->y < 1)
        return sprintf(ngettext('%d month old', '%d months old', $age->m), $age->m);

      return sprintf(ngettext('%d year old', '%d years old', $age->y), $age->y);
    }

    public function getNumericAge() {
      $birthDate = $this->getBirthDate();
      if ($this->hideAge())
      {
        return false;
      }
      if (empty($now)) {
        $now = date_create('today');
      }
      $age = date_diff($now,$birthDate);
       if ($age->y < 1) {
        $ageValue = 0;
      } else {
        $ageValue = $age->y;
      }
      return $ageValue;
    }

    /* Philippe Logel 2017 */
    public function getFullNameWithAge()
    {
       return $this->getFullName()." ".$this->getAge();
    }

    public function toArray($keyType = TableMap::TYPE_PHPNAME, $includeLazyLoadColumns = true, $alreadyDumpedObjects = array(), $includeForeignObjects = false)
    {
        $array = parent::toArray();
        $array['Address']=$this->getAddress();
        return $array;
    }

    public function getThumbnailURL() {
      return SystemURLs::getRootPath() . '/api/person/' . $this->getId() . '/thumbnail';
    }

    public function getEmail() {
      if (parent::getEmail() == null)
      {
        $family = $this->getFamily();
        if ($family != null)
        {
          return $family->getEmail();
        }
      }
      return parent::getEmail();
    }

}
