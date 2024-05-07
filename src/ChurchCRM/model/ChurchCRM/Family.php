<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\verify\FamilyVerificationEmail;
use ChurchCRM\Emails\notifications\NewPersonOrFamilyEmail;
use ChurchCRM\model\ChurchCRM\Base\Family as BaseFamily;
use ChurchCRM\PhotoInterface;
use ChurchCRM\Utils\GeoUtils;
use ChurchCRM\Utils\LoggerUtils;
use DateTime;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\TableMap;

/**
 * Skeleton subclass for representing a row from the 'family_fam' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Family extends BaseFamily implements PhotoInterface
{
    private ?Photo $photo = null;

    public function getAddress(): string
    {
        $address = [];
        if (!empty($this->getAddress1())) {
            $tmp = $this->getAddress1();
            if (!empty($this->getAddress2())) {
                $tmp = $tmp . ' ' . $this->getAddress2();
            }
            $address[] = $tmp;
        }

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
    }

    public function getViewURI(): string
    {
        return SystemURLs::getRootPath() . '/v2/family/' . $this->getId();
    }

    public function getWeddingDay()
    {
        if ($this->getWeddingdate() !== null && $this->getWeddingdate() !== '') {
            $day = $this->getWeddingdate()->format('d');

            return $day;
        }

        return '';
    }

    public function getWeddingMonth()
    {
        if ($this->getWeddingdate() !== null && $this->getWeddingdate() !== '') {
            $month = $this->getWeddingdate()->format('m');

            return $month;
        }

        return '';
    }

    public function postInsert(ConnectionInterface $con = null): void
    {
        $this->createTimeLineNote('create');
        if (!empty(SystemConfig::getValue('sNewPersonNotificationRecipientIDs'))) {
            $NotificationEmail = new NewPersonOrFamilyEmail($this);
            if (!$NotificationEmail->send()) {
                LoggerUtils::getAppLogger()->warning(gettext('New Family Notification Email Error') . ' :' . $NotificationEmail->getError());
            }
        }
    }

    public function postUpdate(ConnectionInterface $con = null): void
    {
        if (!empty($this->getDateLastEdited())) {
            $this->createTimeLineNote('edit');
        }
    }

    public function getPeopleSorted(): array
    {
        $familyMembersParents = array_merge($this->getHeadPeople(), $this->getSpousePeople());
        $familyMembersChildren = $this->getChildPeople();
        $familyMembersOther = $this->getOtherPeople();

        return array_merge($familyMembersParents, $familyMembersChildren, $familyMembersOther);
    }

    public function getHeadPeople(): array
    {
        return $this->getPeopleByRole('sDirRoleHead');
    }

    public function getSpousePeople(): array
    {
        return $this->getPeopleByRole('sDirRoleSpouse');
    }

    public function getAdults(): array
    {
        return array_merge($this->getHeadPeople(), $this->getSpousePeople());
    }

    public function getChildPeople(): array
    {
        return $this->getPeopleByRole('sDirRoleChild');
    }

    /**
     * @return Person[]
     */
    public function getOtherPeople(): array
    {
        $roleIds = array_merge(
            explode(',', SystemConfig::getValue('sDirRoleHead')),
            explode(
                ',',
                SystemConfig::getValue('sDirRoleSpouse')
            ),
            explode(',', SystemConfig::getValue('sDirRoleChild'))
        );
        $foundPeople = [];
        foreach ($this->getPeople() as $person) {
            if (!in_array($person->getFmrId(), $roleIds)) {
                $foundPeople[] = $person;
            }
        }

        return $foundPeople;
    }

    /**
     * @return Person[]
     */
    private function getPeopleByRole(string $roleConfigName): array
    {
        $roleIds = explode(',', SystemConfig::getValue($roleConfigName));
        $foundPeople = [];
        foreach ($this->getPeople() as $person) {
            if (in_array($person->getFmrId(), $roleIds)) {
                $foundPeople[] = $person;
            }
        }

        return $foundPeople;
    }

    /**
     * @throws PropelException
     *
     * @return array
     */
    public function getEmails(): array
    {
        $emails = [];
        if (!(empty($this->getEmail()))) {
            $emails[] = $this->getEmail();
        }
        foreach ($this->getPeopleSorted() as $person) {
            $email = $person->getEmail();
            if ($email != null) {
                $emails[] = $email;
            }
            $email = $person->getWorkEmail();
            if ($email != null) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    public function createTimeLineNote($type): void
    {
        $note = new Note();
        $note->setFamId($this->getId());
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
            case 'verify':
                $note->setText(gettext('Family Data Verified'));
                $note->setEnteredBy(AuthenticationManager::getCurrentUser()->getId());
                break;
            case 'verify-link':
                $note->setText(gettext('Verification email sent'));
                $note->setEnteredBy(AuthenticationManager::getCurrentUser()->getId());
                break;
            case 'verify-URL':
                $note->setText(gettext('Verification URL created'));
                $note->setEnteredBy(AuthenticationManager::getCurrentUser()->getId());
                break;
        }

        $note->save();
    }

    public function getPhoto(): ?Photo
    {
        if (!$this->photo) {
            $this->photo = new Photo('Family', $this->getId());
        }

        return $this->photo;
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

    public function setImageFromBase64($base64): bool
    {
        if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
            $note = new Note();
            $note->setText(gettext('Profile Image uploaded'));
            $note->setType('photo');
            $note->setEntered(AuthenticationManager::getCurrentUser()->getId());
            $this->getPhoto()->setImageFromBase64($base64);
            $note->setFamId($this->getId());
            $note->save();

            return true;
        }

        return false;
    }

    public function verify(): void
    {
        $this->createTimeLineNote('verify');
    }

    public function getFamilyString($booleanIncludeHOH = true)
    {
        $HoH = [];
        if ($booleanIncludeHOH) {
            $HoH = $this->getHeadPeople();
        }
        if (count($HoH) == 1) {
            return $this->getName() . ': ' . $HoH[0]->getFirstName() . ' - ' . $this->getAddress();
        } elseif (count($HoH) > 1) {
            $HoHs = [];
            foreach ($HoH as $person) {
                $HoHs[] = $person->getFirstName();
            }

            return $this->getName() . ': ' . join(',', $HoHs) . ' - ' . $this->getAddress();
        } else {
            return $this->getName() . ' ' . $this->getAddress();
        }
    }

    public function hasLatitudeAndLongitude(): bool
    {
        return !empty($this->getLatitude()) && !empty($this->getLongitude());
    }

    /**
     * if the latitude or longitude is empty find the lat/lng from the address and update the lat lng for the family.
     */
    public function updateLanLng(): void
    {
        if (!empty($this->getAddress()) && (!$this->hasLatitudeAndLongitude())) {
            $latLng = GeoUtils::getLatLong($this->getAddress());
            if (!empty($latLng['Latitude']) && !empty($latLng['Longitude'])) {
                $this->setLatitude($latLng['Latitude']);
                $this->setLongitude($latLng['Longitude']);
                $this->save();
            }
        }
    }

    public function toArray($keyType = TableMap::TYPE_PHPNAME, $includeLazyLoadColumns = true, $alreadyDumpedObjects = [], $includeForeignObjects = false)
    {
        $array = parent::toArray();
        $array['Address'] = $this->getAddress();
        $array['FamilyString'] = $this->getFamilyString();

        return $array;
    }

    public function toSearchArray(): array
    {
        $searchArray = [
            'Id'          => $this->getId(),
            'displayName' => $this->getFamilyString(SystemConfig::getBooleanValue('bSearchIncludeFamilyHOH')),
            'uri'         => SystemURLs::getRootPath() . '/v2/family/' . $this->getId(),
        ];

        return $searchArray;
    }

    public function isActive(): bool
    {
        return empty($this->getDateDeactivated());
    }

    public function getProperties()
    {
        return PropertyQuery::create()
            ->filterByProClass('f')
            ->useRecordPropertyQuery()
            ->filterByRecordId($this->getId())
            ->find();
    }

    public function sendVerifyEmail(): bool
    {
        $familyEmails = $this->getEmails();

        if (empty($familyEmails)) {
            throw new \Exception(gettext('Family has no emails to use'));
        }

        // delete old tokens
        TokenQuery::create()->filterByType('verifyFamily')->filterByReferenceId($this->getId())->delete();

        // create a new token an send to all emails
        $token = new Token();
        $token->build('verifyFamily', $this->getId());
        $token->save();
        $email = new FamilyVerificationEmail($familyEmails, $this->getName(), $token);
        if (!$email->send()) {
            LoggerUtils::getAppLogger()->error($email->getError());

            throw new \Exception($email->getError());
        }
        $this->createTimeLineNote('verify-link');

        return true;
    }

    public function isSendNewsletter(): bool
    {
        return $this->getSendNewsletter() == 'TRUE';
    }

    public function getSalutation()
    {
        $adults = $this->getAdults();
        $adultsCount = count($adults);

        if ($adultsCount == 1) {
            return $adults[0]->getFullName();
        } elseif ($adultsCount == 2) {
            $firstLastName = $adults[0]->getLastName();
            $secondLastName = $adults[1]->getLastName();
            if ($firstLastName == $secondLastName) {
                return $adults[0]->getFirstName() . ' & ' . $adults[1]->getFirstName() . ' ' . $firstLastName;
            } else {
                return $adults[0]->getFullName() . ' & ' . $adults[1]->getFullName();
            }
        } else {
            return $this->getName() . ' Family';
        }
    }

    public function getFirstNameSalutation(): string
    {
        $names = [];
        foreach ($this->getPeopleSorted() as $person) {
            $names[] = $person->getFirstName();
        }

        return implode(', ', $names);
    }
}
