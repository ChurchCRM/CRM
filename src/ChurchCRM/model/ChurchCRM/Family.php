<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\data\Countries;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\verify\FamilyVerificationEmail;
use ChurchCRM\Emails\notifications\NewPersonOrFamilyEmail;
use ChurchCRM\model\ChurchCRM\Base\Family as BaseFamily;
use ChurchCRM\PhotoInterface;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
use ChurchCRM\Utils\GeoUtils;
use ChurchCRM\Utils\InputUtils;
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
    private bool $skipPostUpdateNote = false;

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

    /**
     * The family's primary (physical) address, part by part. Both this and
     * {@see self::getSecondaryAddressParts()} use the same six keys — Address1,
     * Address2, City, State, Zip, Country — so callers can treat them alike.
     */
    public function getPrimaryAddressParts(): array
    {
        return [
            'Address1' => trim($this->getAddress1() ?? ''),
            'Address2' => trim($this->getAddress2() ?? ''),
            'City'     => trim($this->getCity() ?? ''),
            'State'    => trim($this->getState() ?? ''),
            'Zip'      => trim($this->getZip() ?? ''),
            'Country'  => trim($this->getCountry() ?? ''),
        ];
    }

    /**
     * The family's optional second address, part by part.
     */
    public function getSecondaryAddressParts(): array
    {
        return [
            'Address1' => trim($this->getSecondAddress1() ?? ''),
            'Address2' => trim($this->getSecondAddress2() ?? ''),
            'City'     => trim($this->getSecondCity() ?? ''),
            'State'    => trim($this->getSecondState() ?? ''),
            'Zip'      => trim($this->getSecondZip() ?? ''),
            'Country'  => trim($this->getSecondCountry() ?? ''),
        ];
    }

    /**
     * True when the family has entered a second address. A street line or a city
     * is enough — the same tolerance the primary address gets.
     */
    public function hasSecondAddress(): bool
    {
        $parts = $this->getSecondaryAddressParts();

        return $parts['Address1'] !== '' || $parts['City'] !== '';
    }

    /**
     * True when the second address exists and is flagged as where mail goes.
     * The flag alone is never enough — an empty second address can't receive mail.
     */
    public function isSecondAddressMailing(): bool
    {
        return $this->hasSecondAddress() && (bool) $this->getSecondIsMailing();
    }

    /**
     * The address mail should be sent to: the flagged second address when there is
     * one, the primary address otherwise. Labels, letters and the directory call
     * this rather than reading the columns themselves.
     */
    public function getMailingAddressParts(): array
    {
        return $this->isSecondAddressMailing()
            ? $this->getSecondaryAddressParts()
            : $this->getPrimaryAddressParts();
    }

    /**
     * True only when a flagged mailing address actually differs from the primary
     * one, so callers can avoid printing the same address twice.
     */
    public function hasDistinctMailingAddress(): bool
    {
        if (!$this->isSecondAddressMailing()) {
            return false;
        }

        $primary = array_map('mb_strtolower', $this->getPrimaryAddressParts());
        $second = array_map('mb_strtolower', $this->getSecondaryAddressParts());

        return $primary !== $second;
    }

    /**
     * The one postal-block formatter. Reproduces the layout the label reports have
     * always produced: street lines, then "City, State  Zip", then the country only
     * when it is foreign to this installation.
     */
    public static function formatAddressBlock(array $parts, string $separator = "\n"): string
    {
        $lines = [];

        foreach (['Address1', 'Address2'] as $key) {
            $line = trim((string) ($parts[$key] ?? ''));
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        $city = trim((string) ($parts['City'] ?? ''));
        $state = trim((string) ($parts['State'] ?? ''));
        $zip = trim((string) ($parts['Zip'] ?? ''));
        $cityStateZip = trim($city . ($city !== '' && ($state !== '' || $zip !== '') ? ',' : '') . ' ' . trim($state . '  ' . $zip));
        if ($cityStateZip !== '') {
            $lines[] = $cityStateZip;
        }

        $country = trim((string) ($parts['Country'] ?? ''));
        if ($country !== '' && Countries::isForeign($country)) {
            $lines[] = $country;
        }

        return implode($separator, $lines);
    }

    /**
     * The resolved mailing address as a formatted postal block.
     */
    public function getMailingAddressLines(string $separator = "\n"): string
    {
        return self::formatAddressBlock($this->getMailingAddressParts(), $separator);
    }

    /**
     * The second address as a single line, mirroring {@see self::getAddress()}.
     */
    public function getSecondaryAddress(): string
    {
        $parts = $this->getSecondaryAddressParts();
        $address = [];

        if ($parts['Address1'] !== '') {
            $address[] = $parts['Address2'] !== ''
                ? $parts['Address1'] . ' ' . $parts['Address2']
                : $parts['Address1'];
        }
        if ($parts['City'] !== '') {
            $address[] = $parts['City'] . ',';
        }
        if ($parts['State'] !== '') {
            $address[] = $parts['State'];
        }
        if ($parts['Zip'] !== '') {
            $address[] = $parts['Zip'];
        }
        if ($parts['Country'] !== '') {
            $address[] = $parts['Country'];
        }

        return implode(' ', $address);
    }

    /**
     * Row-based counterpart of {@see self::getSecondaryAddressParts()} for the
     * report paths that read `SELECT * ... LEFT JOIN family_fam` rows.
     */
    public static function secondaryAddressPartsFromRow(array $row): array
    {
        return [
            'Address1' => trim((string) ($row['fam_SecondAddress1'] ?? '')),
            'Address2' => trim((string) ($row['fam_SecondAddress2'] ?? '')),
            'City'     => trim((string) ($row['fam_SecondCity'] ?? '')),
            'State'    => trim((string) ($row['fam_SecondState'] ?? '')),
            'Zip'      => trim((string) ($row['fam_SecondZip'] ?? '')),
            'Country'  => trim((string) ($row['fam_SecondCountry'] ?? '')),
        ];
    }

    /**
     * Row-based counterpart of {@see self::getPrimaryAddressParts()}.
     */
    public static function primaryAddressPartsFromRow(array $row): array
    {
        return [
            'Address1' => trim((string) ($row['fam_Address1'] ?? '')),
            'Address2' => trim((string) ($row['fam_Address2'] ?? '')),
            'City'     => trim((string) ($row['fam_City'] ?? '')),
            'State'    => trim((string) ($row['fam_State'] ?? '')),
            'Zip'      => trim((string) ($row['fam_Zip'] ?? '')),
            'Country'  => trim((string) ($row['fam_Country'] ?? '')),
        ];
    }

    /**
     * Row-based counterpart of {@see self::getMailingAddressParts()}.
     */
    public static function mailingAddressPartsFromRow(array $row): array
    {
        $second = self::secondaryAddressPartsFromRow($row);
        $hasSecond = $second['Address1'] !== '' || $second['City'] !== '';

        return $hasSecond && (int) ($row['fam_SecondIsMailing'] ?? 0) === 1
            ? $second
            : self::primaryAddressPartsFromRow($row);
    }

    /**
     * Row-based counterpart of {@see self::hasDistinctMailingAddress()}.
     */
    public static function rowHasDistinctMailingAddress(array $row): bool
    {
        $second = self::secondaryAddressPartsFromRow($row);
        $hasSecond = $second['Address1'] !== '' || $second['City'] !== '';
        if (!$hasSecond || (int) ($row['fam_SecondIsMailing'] ?? 0) !== 1) {
            return false;
        }

        return array_map('mb_strtolower', self::primaryAddressPartsFromRow($row)) !== array_map('mb_strtolower', $second);
    }

    public static function getFamilyViewURIForId(int $id): string
    {
        return SystemURLs::getRootPath() . '/people/family/' . $id;
    }

    public function getViewURI(): string
    {
        return self::getFamilyViewURIForId($this->getId());
    }

    public function getWeddingDay()
    {
        if ($this->getWeddingdate() !== null && $this->getWeddingdate() !== '') {
            return $this->getWeddingdate()->format('d');
        }

        return '';
    }

    public function getWeddingMonth()
    {
        if ($this->getWeddingdate() !== null && $this->getWeddingdate() !== '') {
            return $this->getWeddingdate()->format('m');
        }

        return '';
    }

    /**
     * Enforce the one invariant the second address carries: the "this is the
     * mailing address" flag is meaningless without an address to send mail to,
     * so it is cleared whenever the second address is empty. Applied here rather
     * than only in the editor so every write path (editor, CSV import, API
     * consumers) stores a consistent row.
     */
    public function preSave(?ConnectionInterface $con = null): bool
    {
        if ($this->getSecondIsMailing() && !$this->hasSecondAddress()) {
            $this->setSecondIsMailing(false);
        }

        return parent::preSave($con);
    }

    public function postInsert(ConnectionInterface $con = null): void
    {
        $this->createTimeLineNote('create');
        NewPersonOrFamilyEmail::sendIfConfigured($this);

        HookManager::doAction(Hooks::FAMILY_CREATED, $this);
    }

    public function postUpdate(ConnectionInterface $con = null): void
    {
        if (!empty($this->getDateLastEdited()) && !$this->skipPostUpdateNote) {
            $this->createTimeLineNote('edit');
        }

        HookManager::doAction(Hooks::FAMILY_UPDATED, $this);
    }

    /**
     * Ensure the family's uploaded photo is removed from disk whenever the
     * record is deleted — on any code path, not just the API route.
     * See GH issue #1697.
     */
    public function preDelete(?ConnectionInterface $con = null): bool
    {
        $this->getPhoto()->delete();

        return parent::preDelete($con);
    }

    public function getPeopleSorted(): array
    {
        $familyMembersParents = array_merge($this->getHeadPeople(), $this->getSpousePeople());
        $familyMembersChildren = $this->getChildPeople();
        $familyMembersOther = $this->getOtherPeople();

        return array_merge($familyMembersParents, $familyMembersChildren, $familyMembersOther);
    }

    /**
     * Returns only living (non-deceased) family members.
     *
     * @return Person[]
     */
    public function getLivingPeople(): array
    {
        $living = [];
        foreach ($this->getPeople() as $person) {
            if (!$person->isDeceased()) {
                $living[] = $person;
            }
        }
        return $living;
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
     */
    public function getEmails(): array
    {
        $emails = [];
        if (!(empty($this->getEmail()))) {
            $emails[] = $this->getEmail();
        }
        foreach ($this->getPeopleSorted() as $person) {
            $email = $person->getEmail();
            if ($email !== null) {
                $emails[] = $email;
            }
            $email = $person->getWorkEmail();
            if ($email !== null) {
                $emails[] = $email;
            }
        }

        return array_unique($emails);
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
                $note->setFamId($this->getId());
                $note->save();

                return true;
            }
        }

        return false;
    }

    public function setImageFromBase64($base64): void
    {
        $note = new Note();
        $note->setText(gettext('Profile Image uploaded'));
        $note->setType('photo');
        $note->setEntered(AuthenticationManager::getCurrentUser()->getId());
        $this->getPhoto()->setImageFromBase64($base64);
        $note->setFamId($this->getId());
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

    public function verify(): void
    {
        $this->createTimeLineNote('verify');
    }

    public function getFamilyString(bool $booleanIncludeHOH = true): string
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

    /**
     * Return the family's status as text ('Active' or 'Inactive').
     * Presentation (HTML badges) should be handled by the view or client.
     */
    public function getStatusText(): string
    {
        return $this->isActive() ? gettext('Active') : gettext('Inactive');
    }

    /**
     * Return an HTML link for the family name, optionally including the photo button.
     */
    public function getLinkHtml(bool $includePhoto = true, bool $strong = true): string
    {
        $name = $strong ? '<strong>' . InputUtils::escapeHTML($this->getName()) . '</strong>' : InputUtils::escapeHTML($this->getName());
        $html = '<a href="' . $this->getViewURI() . '">' . $name . '</a>';

        if ($includePhoto && $this->getPhoto() && $this->getPhoto()->hasUploadedPhoto()) {
            $html .= ' <button class="btn btn-sm btn-outline-secondary view-family-photo ms-1" data-family-id="' . $this->getId() . '" title="' . gettext('View Photo') . '"><i class="fa-solid fa-camera"></i></button>';
        }

        return $html;
    }

    /**
     * Return a compact City/State string, truncated to a maximum length.
     */
    public function getCityStateShort(int $maxLen = 30): string
    {
        $city = $this->getCity();
        $state = $this->getState();
        $s = trim($city . ($city && $state ? ', ' : '') . $state);
        if (mb_strlen($s) > $maxLen) {
            $s = mb_substr($s, 0, $maxLen) . '...';
        }
        return InputUtils::escapeHTML($s);
    }

    public function hasLatitudeAndLongitude(): bool
    {
        return !empty($this->getLatitude()) && !empty($this->getLongitude());
    }

    /**
     * Returns true when the family has a street address entered (Address1 is non-empty).
     * Used to distinguish "no address" from "unverified address" (has address but no geocode).
     */
    public function hasAddress(): bool
    {
        return !empty(trim($this->getAddress1() ?? ''));
    }

    /**
     * Returns a Google Maps directions deep-link for this family's address.
     * Uses stored lat/lng when available (more accurate); falls back to the address string.
     * Returns an empty string when no address is set.
     */
    public function getDirectionsUrl(): string
    {
        if ($this->hasLatitudeAndLongitude()) {
            return GeoUtils::buildDirectionsUrl('', (float) $this->getLatitude(), (float) $this->getLongitude());
        }
        return GeoUtils::buildDirectionsUrl($this->getAddress());
    }

    /**
     * Apple Maps companion to {@see self::getDirectionsUrl()}. Used
     * alongside the Google Maps link so users on iOS/macOS can open
     * directions natively in the Maps app.
     */
    public function getAppleMapsDirectionsUrl(): string
    {
        if ($this->hasLatitudeAndLongitude()) {
            return GeoUtils::buildAppleMapsDirectionsUrl('', (float) $this->getLatitude(), (float) $this->getLongitude());
        }
        return GeoUtils::buildAppleMapsDirectionsUrl($this->getAddress());
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

    public function toArray(string $keyType = TableMap::TYPE_PHPNAME, bool $includeLazyLoadColumns = true, array $alreadyDumpedObjects = [], bool $includeForeignObjects = false): array
    {
        $array = parent::toArray($keyType, $includeLazyLoadColumns, $alreadyDumpedObjects, $includeForeignObjects);
        $array['Address'] = $this->getAddress();
        $array['FamilyString'] = $this->getFamilyString();
        // Additive, read-only mailing-address resolution (#9743). `Address` stays the
        // primary/physical address so maps, search and the cart are unaffected.
        $array['HasSecondAddress'] = $this->hasSecondAddress();
        $array['SecondAddressIsMailing'] = $this->isSecondAddressMailing();
        $array['SecondAddress'] = $this->getSecondaryAddress();
        $array['MailingAddress'] = $this->getMailingAddressParts();
        $array['MailingAddressLines'] = $this->getMailingAddressLines(', ');

        return $array;
    }

    public function toSearchArray(): array
    {
        return [
            'Id'          => $this->getId(),
            'displayName' => $this->getFamilyString(SystemConfig::getBooleanValue('bSearchIncludeFamilyHOH')),
            'uri'         => SystemURLs::getRootPath() . '/people/family/' . $this->getId(),
        ];
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

    public function checkAgainstCart(): bool
    {
        if (!isset($_SESSION['aPeopleCart']) || empty($_SESSION['aPeopleCart'])) {
            return false;
        }

        $familyMembers = $this->getPeople();
        if (empty($familyMembers)) {
            return false;
        }

        // Check if ALL family members are in the cart
        foreach ($familyMembers as $person) {
            if (!in_array($person->getId(), $_SESSION['aPeopleCart'], false)) {
                return false; // At least one member is not in cart
            }
        }

        return true; // All members are in cart
    }
}
