<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\notifications\NewPersonOrFamilyEmail;
use ChurchCRM\model\ChurchCRM\Base\Person as BasePerson;
use ChurchCRM\PhotoInterface;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
use ChurchCRM\Service\GroupService;
use ChurchCRM\Utils\GeoUtils;
use ChurchCRM\Utils\LoggerUtils;
use DateTime;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Map\TableMap;

/**
 * Skeleton subclass for representing a row from the 'person_per' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Person extends BasePerson implements PhotoInterface
{
    public const SELF_REGISTER = -1;
    public const SELF_VERIFY = -2;
    private ?Photo $photo = null;
    private bool $skipPostUpdateNote = false;

    public function getFullName(): string
    {
        return $this->getFormattedName(SystemConfig::getIntValue('iPersonNameStyle'));
    }

    public function isMale(): bool
    {
        return $this->getGender() == 1;
    }

    public function isFemale(): bool
    {
        return $this->getGender() == 2;
    }

    public function getGenderName(): string
    {
        switch (strtolower($this->getGender())) {
            case 1:
                return gettext('Male');
            case 2:
                return gettext('Female');
            default:
                return gettext('Unassigned');
        }
    }

    public function hideAge(): bool
    {
        return $this->getFlags() === 1 || empty($this->getBirthYear());
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        if (
            !empty($this->getBirthDay()) &
            !empty($this->getBirthMonth())
        ) {
            $birthYear = $this->getBirthYear();
            if (empty($birthYear)) {
                $birthYear = 0;
            }

            return \DateTimeImmutable::createFromFormat('Y-m-d', $birthYear . '-' . $this->getBirthMonth() . '-' . $this->getBirthDay());
        }

        return null;
    }

    public function getFormattedBirthDate()
    {
        $birthDate = $this->getBirthDate();
        if (!$birthDate) {
            return false;
        }
        if ($this->hideAge()) {
            return $birthDate->format(SystemConfig::getValue('sDateFormatNoYear'));
        } else {
            return $birthDate->format(SystemConfig::getValue('sDateFormatLong'));
        }
    }

    public function getViewURI(): string
    {
        return SystemURLs::getRootPath() . '/PersonView.php?PersonID=' . $this->getId();
    }

    public function getFamilyRole()
    {
        $familyRole = null;
        $roleId = $this->getFmrId();
        if ($roleId !== 0) {
            $familyRole = ListOptionQuery::create()->filterById(2)->filterByOptionId($roleId)->findOne();
        }

        return $familyRole;
    }

    public function getFamilyRoleName(): string
    {
        $roleName = 'Unassigned';
        $role = $this->getFamilyRole();
        if ($role !== null) {
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
        $classificationName = 'Unassigned';
        $classification = $this->getClassification();
        if ($classification !== null) {
            $classificationName = $classification->getOptionName();
        }

        return $classificationName;
    }

    public function postInsert(?ConnectionInterface $con = null): void
    {
        $this->createTimeLineNote('create');

        // Unaffiliated persons only — Family::postInsert() sends one email per family.
        if (empty($this->getFamId())) {
            NewPersonOrFamilyEmail::sendIfConfigured($this);
        }

        HookManager::doAction(Hooks::PERSON_CREATED, $this);
    }

    public function postUpdate(?ConnectionInterface $con = null): void
    {
        if (!empty($this->getDateLastEdited()) && !$this->skipPostUpdateNote) {
            $this->createTimeLineNote('edit');
        }

        HookManager::doAction(Hooks::PERSON_UPDATED, $this);
    }

    private function createTimeLineNote(string $type): void
    {
        $note = new Note();
        $note->setPerId($this->getId());
        $note->setType($type);
        $note->setDateEntered(new DateTime());

        switch ($type) {
            case 'create':
                $note->setText(gettext('Created'));
                $note->setEnteredBy($this->getEnteredBy());
                $note->setDateEntered($this->getDateEntered());
                break;
            case 'edit':
                $note->setText(gettext('Updated'));
                $note->setEnteredBy($this->getEditedBy());
                $note->setDateEntered($this->getDateLastEdited());
                break;
        }

        $note->save();
    }

    public function isUser(): bool
    {
        $user = UserQuery::create()->findPk($this->getId());

        return $user !== null;
    }

    /**
     * Get full address string of a person.
     *
     * If the address is not defined on the person record, return family address if one exists.
     */
    public function getAddress(): string
    {
        if (!empty($this->getAddress1())) {
            $address = [];
            $tmp = $this->getAddress1();
            if (!empty($this->getAddress2())) {
                $tmp = $tmp . ' ' . $this->getAddress2();
            }

            $address[] = $tmp;
            if (!empty($this->getCity())) {
                $address[] = $this->getCity() . ',';
            }
            if (!empty($this->getState())) {
                $address[] = $this->getState();
            }
            if (!empty($this->getZip())) {
                $address[] = $this->getZip();
            }
            if (!empty($this->getCountry())) {
                $address[] = $this->getCountry();
            }

            return implode(' ', $address);
        } elseif ($this->getFamily()) {
            return $this->getFamily()->getAddress();
        }

        return '';
    }

    /**
     * Get name of a person family.
     */
    public function getFamilyName(): string
    {
        if ($this->getFamily()) {
            return $this->getFamily()
              ->getName();
        }
        //if it reaches here, no family name found. return empty family name
        return '';
    }

    /**
     * Get name of a person family.
     */
    public function getFamilyCountry(): string
    {
        if ($this->getFamily()) {
            return $this->getFamily()
              ->getCountry();
        }
        //if it reaches here, no country found. return empty country
        return '';
    }

    /**
     * Get Phone of a person family.
     * 0 = Home
     * 1 = Work
     * 2 = Cell.
     */
    public function getFamilyPhone(int $type): string
    {
        switch ($type) {
            case 0:
                if ($this->getFamily()) {
                    return $this->getFamily()
                    ->getHomePhone();
                }
                break;
            case 1:
                if ($this->getFamily()) {
                    return $this->getFamily()
                    ->getWorkPhone();
                }
                break;
            case 2:
                if ($this->getFamily()) {
                    return $this->getFamily()
                    ->getCellPhone();
                }
                break;
        }
        //if it reaches here, no phone found. return empty phone
        return '';
    }

    /**
     * Returns the latitude and longitude for this person.
     *
     * Priority:
     *  1. Person has their own address → geocode it.
     *  2. Geocoding fails or person has no address → use family's cached coords.
     *  3. Family has no cached coords → geocode the family address and cache it.
     *  4. Nothing available → returns ['Latitude' => 0, 'Longitude' => 0].
     */
    public function getLatLng(): array
    {
        $family = $this->getFamily();

        // Person has their own address — try geocoding it first.
        if (!empty($this->getAddress1())) {
            $latLng = GeoUtils::getLatLong($this->getAddress());
            if (!empty($latLng['Latitude']) && !empty($latLng['Longitude'])) {
                return ['Latitude' => $latLng['Latitude'], 'Longitude' => $latLng['Longitude']];
            }
        }

        // No personal address, or geocoding failed — fall back to family coords.
        if ($family) {
            if ($family->hasLatitudeAndLongitude()) {
                return ['Latitude' => $family->getLatitude(), 'Longitude' => $family->getLongitude()];
            }

            // Family has no cached coords yet — geocode family address and cache it.
            $family->updateLanLng();
            if ($family->hasLatitudeAndLongitude()) {
                return ['Latitude' => $family->getLatitude(), 'Longitude' => $family->getLongitude()];
            }
        }

        return ['Latitude' => 0, 'Longitude' => 0];
    }

    /**
     * Returns a Google Maps directions deep-link for this person's address.
     *
     * - Person has own address (Address1 set): use address string only — no family info.
     * - Person has no own address, family has stored lat/lng: use coordinates (accurate, no API call).
     * - Person has no own address, family has address only: use family address string.
     * - No address anywhere: return empty string.
     *
     * Note: per_Latitude/per_Longitude columns do not yet exist on person_per.
     * When added (issue #8045) this method should be updated to prefer them.
     */
    public function getDirectionsUrl(): string
    {
        if (!empty($this->getAddress1())) {
            return GeoUtils::buildDirectionsUrl($this->getAddress());
        }

        $family = $this->getFamily();
        if ($family === null) {
            return '';
        }

        if ($family->hasLatitudeAndLongitude()) {
            return GeoUtils::buildDirectionsUrl('', (float) $family->getLatitude(), (float) $family->getLongitude());
        }

        return GeoUtils::buildDirectionsUrl($family->getAddress());
    }

    /**
     * Apple Maps companion to {@see self::getDirectionsUrl()}. Mirrors the
     * same address/lat/lng precedence rules so the two links resolve to the
     * same destination.
     */
    public function getAppleMapsDirectionsUrl(): string
    {
        if (!empty($this->getAddress1())) {
            return GeoUtils::buildAppleMapsDirectionsUrl($this->getAddress());
        }

        $family = $this->getFamily();
        if ($family === null) {
            return '';
        }

        if ($family->hasLatitudeAndLongitude()) {
            return GeoUtils::buildAppleMapsDirectionsUrl('', (float) $family->getLatitude(), (float) $family->getLongitude());
        }

        return GeoUtils::buildAppleMapsDirectionsUrl($family->getAddress());
    }

    public function deletePhoto(): bool
    {
        if (AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled()) {
            if ($this->getPhoto()->delete()) {
                $note = new Note();
                $note->setText(gettext('Profile Image Deleted'));
                $note->setType('photo');
                $note->setEntered(AuthenticationManager::getCurrentUser()->getId());
                $note->setPerId($this->getId());
                $note->save();

                return true;
            }
        }

        return false;
    }

    public function getPhoto(): Photo
    {
        if (!$this->photo) {
            $this->photo = new Photo('Person', $this->getId());
        }

        return $this->photo;
    }

    public function setImageFromBase64($base64): void
    {
        $note = new Note();
        $note->setText(gettext('Profile Image uploaded'));
        $note->setType('photo');
        $note->setEntered(AuthenticationManager::getCurrentUser()->getId());
        $this->getPhoto()->setImageFromBase64($base64);
        $note->setPerId($this->getId());
        $note->save();

        $this->setDateLastEdited(new \DateTime());
        $this->setEditedBy(AuthenticationManager::getCurrentUser()->getId());
        $this->skipPostUpdateNote = true;
        try {
            $this->save();
        } finally {
            $this->skipPostUpdateNote = false;
        }
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
     * $Style = 8  :  "LastName, FirstName Middlename".
     */
    public function getFormattedName(int $Style): string
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
                if ($this->getFirstName()) {
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

    public function preDelete(?ConnectionInterface $con = null): bool
    {
        // Remove the uploaded image from disk. Call Photo::delete() directly
        // rather than $this->deletePhoto(), which gates on the current user's
        // delete-records permission and writes a Note that NoteQuery below
        // would immediately delete. See GH issue #1697.
        $this->getPhoto()->delete();

        $obj = Person2group2roleP2g2rQuery::create()->filterByPerson($this)->find($con);
        if ($obj->count() > 0) {
            $groupService = new GroupService();
            foreach ($obj as $group2roleP2g2r) {
                $groupService->removeUserFromGroup($group2roleP2g2r->getGroupId(), $group2roleP2g2r->getPersonId());
            }
        }

        $perCustom = PersonCustomQuery::create()->findPk($this->getId(), $con);
        if ($perCustom !== null) {
            $perCustom->delete($con);
        }

        $user = UserQuery::create()->findPk($this->getId(), $con);
        if ($user !== null) {
            $user->delete($con);
        }

        PersonVolunteerOpportunityQuery::create()->filterByPersonId($this->getId())->delete($con);

        PropertyQuery::create()
            ->filterByProClass('p')
            ->useRecordPropertyQuery()
            ->filterByRecordId($this->getId())
            ->delete($con);

        NoteQuery::create()->filterByPerson($this)->delete($con);

        return parent::preDelete($con);
    }

    public function getProperties()
    {
        return PropertyQuery::create()
          ->filterByProClass('p')
          ->useRecordPropertyQuery()
          ->filterByRecordId($this->getId())
          ->find();
    }

    //  return array of person properties
    // created for the person-list.php datatable
    /**
     * @return string[]
     */
    public function getPropertiesString(): array
    {
        $personProperties = PropertyQuery::create()
          ->filterByProClass('p')
          ->leftJoinRecordProperty()
          ->where('r2p_record_ID=' . $this->getId())
          ->find();

        $PropertiesList = [];
        foreach ($personProperties as $element) {
            $PropertiesList[] = $element->getProName();
        }

        return $PropertiesList;
    }

    // return array of person custom fields
    // created for the person-list.php datatable
    /**
     * @return string[]
     */
    public function getCustomFields($allPersonCustomFields, array $customMapping, array &$CustomList, $name_func): array
    {
        // add custom fields to person_custom table since they are not defined in the propel schema
        $rawQry = PersonCustomQuery::create();
        foreach ($allPersonCustomFields as $customfield) {
            if (AuthenticationManager::getCurrentUser()->isEnabledSecurity($customfield->getFieldSecurity())) {
                $rawQry->withColumn($customfield->getId());
            }
        }
        $thisPersonCustomFields = $rawQry->findOneByPerId($this->getId());

        // get custom column names and values
        $personCustom = [];
        if ($thisPersonCustomFields) {
            //Lets use the map created instead of querying the column name
            foreach ($thisPersonCustomFields->getVirtualColumns() as $column => $value) {
                if (!empty($value)) {
                    $temp = $customMapping[$column]['Name'];
                    $personCustom[] = $temp;
                    $CustomList[$temp] += 1;

                    if (array_key_exists($value, $customMapping[$column]['Elements'])) {
                        $temp = $name_func($customMapping[$column]['Name'], $customMapping[$column]['Elements'][$value]);
                        $personCustom[] = $temp;
                        $CustomList[$temp] += 1;
                    }
                }
            }
        }

        return $personCustom;
    }

    // return array of person groups
    // created for the person-list.php datatable
    /**
     * @return string[]
     */
    public function getGroups(): array
    {
        $GroupList = GroupQuery::create()
        ->leftJoinPerson2group2roleP2g2r()
        ->where('p2g2r_per_ID=' . $this->getId())
        ->find();

        $group = [];
        foreach ($GroupList as $element) {
            $group[] = $element->getName();
        }

        return $group;
    }

    public function getNumericCellPhone(): string
    {
        return preg_replace('/\D/', '', $this->getCellPhone());
    }

    /**
     * Returns the best available phone number for this person.
     * Prefers cell phone; falls back to this person's home phone.
     */
    public function getBestPhone(): string
    {
        return $this->getCellPhone() ?: $this->getHomePhone() ?? '';
    }

    public function postSave(?ConnectionInterface $con = null): void
    {
        $this->getPhoto()->refresh();

        parent::postSave($con);
    }

    public function getAge(?string $date = null): ?string
    {
        if ($this->getBirthYear() === null) {
            return null;
        }

        $birthDate = $this->getBirthDate();

        if (!$birthDate instanceof \DateTimeImmutable || $this->hideAge()) {
            return false;
        }
        $now = $date === null ? new \DateTimeImmutable('today') : \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        $age = date_diff($now, $birthDate);

        if ($age->y < 1) {
            return sprintf(ngettext('%d month old', '%d months old', $age->m), $age->m);
        }

        return sprintf(ngettext('%d year old', '%d years old', $age->y), $age->y);
    }

    public function getNumericAge(): int
    {
        $birthDate = $this->getBirthDate();
        if (!$birthDate instanceof \DateTimeImmutable || $this->hideAge()) {
            return false;
        }

        $now = date_create('today');
        $age = date_diff($now, $birthDate);
        if ($age->y < 1) {
            $ageValue = 0;
        } else {
            $ageValue = $age->y;
        }

        return $ageValue;
    }

    public function getFullNameWithAge(): string
    {
        return $this->getFullName() . ' ' . $this->getAge();
    }

    public function toArray(string $keyType = TableMap::TYPE_PHPNAME, bool $includeLazyLoadColumns = true, array $alreadyDumpedObjects = [], bool $includeForeignObjects = false): array
    {
        $array = parent::toArray($keyType, $includeLazyLoadColumns, $alreadyDumpedObjects, $includeForeignObjects);
        $array['Address'] = $this->getAddress();
        $array['FullName'] = $this->getFullName();
        $array['HasPhoto'] = $this->getPhoto()->hasUploadedPhoto();

        return $array;
    }

    public function getEmail(): ?string
    {
        if (parent::getEmail() === null) {
            $family = $this->getFamily();
            if ($family !== null) {
                return $family->getEmail();
            }
        }

        return parent::getEmail();
    }

    /**
     * Returns a string of a person's full name initial, formatted as specified by $Style
     * $Style = 0  :  "One character from FirstName and one character from LastName"
     * $Style = 1  :  "Two characters from FirstName"
     */
    public function getInitial(int $Style): string
    {
        $initialString = '';
        switch ($Style) {
            case 0:
                $initialString .= mb_strtoupper(mb_substr($this->getFirstName(), 0, 1));
                $initialString .= mb_strtoupper(mb_substr($this->getLastName(), 0, 1));
                break;
            case 1:
                $fullNameArr = $this->getFirstName();
                $initialString .= mb_strtoupper(mb_substr($fullNameArr, 0, 2));
        }

        return $initialString;
    }

    /**
     * Resolve address fields with family fallback (issue #7937).
     *
     * When a person doesn't have personal address/contact data, falls back to their family's data.
     * Ensures complete address coverage for single-member households and people without personal address entries.
     * Returns empty string if neither person nor family has the value.
     *
     * @return string Resolved address field with family fallback
     */
    public function getResolvedAddress1(): string
    {
        if (!empty(parent::getAddress1())) {
            return parent::getAddress1();
        }
        $family = $this->getFamily();
        return $family?->getAddress1() ?? '';
    }

    public function getResolvedAddress2(): string
    {
        if (!empty(parent::getAddress2())) {
            return parent::getAddress2();
        }
        $family = $this->getFamily();
        return $family?->getAddress2() ?? '';
    }

    public function getResolvedCity(): string
    {
        if (!empty(parent::getCity())) {
            return parent::getCity();
        }
        $family = $this->getFamily();
        return $family?->getCity() ?? '';
    }

    public function getResolvedState(): string
    {
        if (!empty(parent::getState())) {
            return parent::getState();
        }
        $family = $this->getFamily();
        return $family?->getState() ?? '';
    }

    public function getResolvedZip(): string
    {
        if (!empty(parent::getZip())) {
            return parent::getZip();
        }
        $family = $this->getFamily();
        return $family?->getZip() ?? '';
    }

    public function getResolvedCountry(): string
    {
        if (!empty(parent::getCountry())) {
            return parent::getCountry();
        }
        $family = $this->getFamily();
        return $family?->getCountry() ?? '';
    }

    public function getResolvedHomePhone(): string
    {
        if (!empty(parent::getHomePhone())) {
            return parent::getHomePhone();
        }
        $family = $this->getFamily();
        return $family?->getHomePhone() ?? '';
    }
}
