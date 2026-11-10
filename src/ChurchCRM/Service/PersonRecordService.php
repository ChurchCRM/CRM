<?php

namespace ChurchCRM\Service;

use Propel\Runtime\Map\TableMap;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\data\Countries;
use ChurchCRM\data\States;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Map\PersonTableMap;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonCustom;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PersonSigninQuery;
use ChurchCRM\Portal\Auth\EmailChangeChannel;
use ChurchCRM\Portal\Auth\SignInEmailPolicy;
use ChurchCRM\Service\FamilyService;
use ChurchCRM\Service\GradeService;
use ChurchCRM\Service\UserService;
use ChurchCRM\Utils\CustomFieldUtils;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\EmailAddress;
use Propel\Runtime\Propel;
use ChurchCRM\Utils\AddressDefaults;
use ChurchCRM\Service\CustomFieldService;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use DateTimeImmutable;
use Throwable;

class PersonRecordService
{
    public function apply(?Person $person, array $data = [], bool $submitted = false, ?int $familyId = null): array
    {
        $iPersonID = $person?->getId() ?? 0;
        $isNewPerson = $person === null;
        $queryParamFamilyId = $familyId;
        $customFieldService = new CustomFieldService();

        $rsCustomFields = $customFieldService->getVisiblePersonFields();
        $numCustomFields = count($rsCustomFields);

        $bErrorFlag = false;
        $sFirstNameError = '';
        $sMiddleNameError = '';
        $sLastNameError = '';
        $sEmailError = '';
        $sWorkEmailError = '';
        $sBirthDateError = '';
        $sBirthYearError = '';
        $sFriendDateError = '';
        $sMembershipDateError = '';
        $sDateDeceasedError = '';
        $aCustomErrors = [];

        $fam_Country = '';

        $bNoFormat_HomePhone = false;
        $bNoFormat_WorkPhone = false;
        $bNoFormat_CellPhone = false;
        $sFacebookError = false;
        $sTwitterError = false;
        $sLinkedInError = false;

        $sTitle = '';
        $sFirstName = '';
        $sMiddleName = '';
        $sLastName = '';
        $sSuffix = '';
        $iGender = 0;
        $sAddress1 = '';
        $sAddress2 = '';
        $sCity = AddressDefaults::city();
        $sState = AddressDefaults::state();
        $sZip = AddressDefaults::zip();
        $sCountry = AddressDefaults::country();
        $sHomePhone = '';
        $sWorkPhone = '';
        $sCellPhone = '';
        $sEmail = '';
        $sWorkEmail = '';
        $iBirthMonth = 0;
        $iBirthDay = 0;
        $iBirthYear = 0;
        $bHideAge = 0;
        $iOriginalFamily = 0;
        $iFamily = 0;
        $iFamilyRole = 0;
        $dMembershipDate = '';
        $dFriendDate = date('Y-m-d');
        $bIsDeceased = false;
        $dDateDeceased = '';
        $iClassification = 0;
        $iGrade = null;
        $iViewAgeFlag = 0;
        $sPhoneCountry = '';

        $sFacebook = '';
        $sTwitter = '';
        $sLinkedIn = '';

        $sHomePhone = '';
        $sWorkPhone = '';
        $sCellPhone = '';

        $bFamilyAddress1 = 0;
        $bFamilyAddress2 = 0;
        $bFamilyCity = 0;
        $bFamilyState = 0;
        $bFamilyZip = 0;
        $bFamilyCountry = 0;
        $bFamilyHomePhone = 0;
        $bFamilyWorkPhone = 0;
        $bFamilyCellPhone = 0;
        $bHomeBound = false;
        $aCustomData = [];

        if ($submitted) {
            $sTitle = InputUtils::sanitizeText($data['title'] ?? '');
            $sFirstName = InputUtils::sanitizeText($data['firstName'] ?? '');
            $sMiddleName = InputUtils::sanitizeText($data['middleName'] ?? '');
            $sLastName = InputUtils::sanitizeText($data['lastName'] ?? '');
            $sSuffix = InputUtils::sanitizeText($data['suffix'] ?? '');
            $iGender = InputUtils::filterInt($data['gender'] ?? '');

            $sAddress1 = '';
            $sAddress2 = '';
            $sCity = '';
            $sZip = '';
            $sCountry = '';
            if (array_key_exists('address1', $data)) {
                $sAddress1 = InputUtils::sanitizeText($data['address1'] ?? '');
            }
            if (array_key_exists('address2', $data)) {
                $sAddress2 = InputUtils::sanitizeText($data['address2'] ?? '');
            }
            if (array_key_exists('city', $data)) {
                $sCity = InputUtils::sanitizeText($data['city'] ?? '');
            }
            if (array_key_exists('zip', $data)) {
                $sZip = InputUtils::sanitizeText($data['zip'] ?? '');
            }

            if (SystemConfig::getBooleanValue('bForceUppercaseZip')) {
                $sZip = strtoupper($sZip);
            }

            if (array_key_exists('country', $data)) {
                $sCountry = InputUtils::sanitizeText($data['country'] ?? '');
            }

            $iFamily = (int) InputUtils::filterInt($data['family'] ?? '');
            if ($iFamily === -1 && !AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()) {
                throw new \InvalidArgumentException(gettext('Adding a family requires Add Records permission'));
            }
            if ($iFamily < -1 || ($iFamily > 0 && FamilyQuery::create()->findPk($iFamily) === null)) {
                throw new \InvalidArgumentException(gettext('Invalid family'));
            }
            $iFamilyRole = InputUtils::filterInt($data['familyRole'] ?? '');
            $family = null;

            $sState = '';
            if (array_key_exists('state', $data)) {
                $sState = InputUtils::sanitizeText($data['state'] ?? '');
            } elseif (array_key_exists('stateTextbox', $data)) {
                $sState = InputUtils::sanitizeText($data['stateTextbox'] ?? '');
            }

            $sHomePhone = InputUtils::sanitizeText($data['homePhone'] ?? '');
            $sWorkPhone = InputUtils::sanitizeText($data['workPhone'] ?? '');
            $sCellPhone = InputUtils::sanitizeText($data['cellPhone'] ?? '');
            $sEmail = InputUtils::sanitizeText($data['email'] ?? '');
            $sWorkEmail = InputUtils::sanitizeText($data['workEmail'] ?? '');
            $iBirthMonth = InputUtils::filterInt($data['birthMonth'] ?? '');
            $iBirthDay = InputUtils::filterInt($data['birthDay'] ?? '');
            $iBirthYear = InputUtils::filterInt($data['birthYear'] ?? '');
            $bHideAge = isset($data['hideAge']);
            $dFriendDate = trim((string) ($data['friendDate'] ?? ''));
            $dMembershipDate = trim((string) ($data['membershipDate'] ?? ''));
            $bIsDeceased = isset($data['isDeceased']);
            $dDateDeceased = trim((string) ($data['dateDeceased'] ?? ''));
            $iClassification = InputUtils::filterInt($data['classification'] ?? '');
            $iGrade = GradeService::resolve($data['grade'] ?? null);
            $iEnvelope = (int) ($person?->getEnvelope() ?? 0);
            if (array_key_exists('envId', $data)) {
                $iEnvelope = InputUtils::filterInt($data['envId'] ?? '');
            }
            if (array_key_exists('updateBirthYear', $data)) {
                $iupdateBirthYear = InputUtils::filterInt($data['updateBirthYear'] ?? '');
            }

            $sFacebook = InputUtils::sanitizeText($data['facebook'] ?? '');
            $sTwitter = InputUtils::sanitizeText($data['twitter'] ?? '');
            $sLinkedIn = InputUtils::sanitizeText($data['linkedIn'] ?? '');

            $bNoFormat_HomePhone = isset($data['noFormatHomePhone']);
            $bNoFormat_WorkPhone = isset($data['noFormatWorkPhone']);
            $bNoFormat_CellPhone = isset($data['noFormatCellPhone']);

            if ($iFamily === 0) {
                $iFamilyRole = 0;
            }

            if (strlen($sLastName) < 1) {
                $sLastNameError = gettext('You must enter a Last Name.');
                $bErrorFlag = true;
            }

            if ($iBirthMonth > 0 xor $iBirthDay > 0) {
                $sBirthDateError = gettext('Invalid Birth Date: Missing birth month or day.');
                $bErrorFlag = true;
            } elseif ($iBirthYear > 0 && $iBirthMonth === 0 && $iBirthDay === 0) {
                $sBirthDateError = gettext('Invalid Birth Date: Missing birth month and day.');
                $bErrorFlag = true;
            } elseif (strlen((string) $iBirthYear) > 0) {
                if ($iBirthYear === 0) { // If zero set to NULL
                    $iBirthYear = null;
                } elseif ($iBirthYear < 0) {
                    $sBirthYearError = gettext('Invalid Year');
                    $bErrorFlag = true;
                } elseif ($iBirthMonth > 0 && $iBirthDay > 0) {
                    if (!checkdate($iBirthMonth, $iBirthDay, $iBirthYear)) {
                        $sBirthDateError = gettext('Invalid Birth Date.');
                        $bErrorFlag = true;
                    }
                }
            }

            if (strlen($dFriendDate) > 0) {
                $dateString = DateTimeUtils::parseAndValidate($dFriendDate, 'past');
                if ($dateString === false) {
                    $sFriendDateError = gettext('Not a valid Friend Date');
                    $bErrorFlag = true;
                } else {
                    $dFriendDate = $dateString;
                }
            }

            if (strlen($dMembershipDate) > 0) {
                $dMembershipDate = DateTimeUtils::parseAndValidate($dMembershipDate, 'past');
                if ($dMembershipDate === false) {
                    $sMembershipDateError = gettext('Not a valid Membership Date');
                    $bErrorFlag = true;
                }
            }

            if ($bIsDeceased && strlen($dDateDeceased) > 0) {
                $dDateDeceased = DateTimeUtils::parseAndValidate($dDateDeceased, 'past');
                if ($dDateDeceased === false) {
                    $sDateDeceasedError = gettext('Not a valid date of death');
                    $bErrorFlag = true;
                } elseif ($dDateDeceased > date('Y-m-d')) {
                    $sDateDeceasedError = gettext('Not a valid date of death');
                    $bErrorFlag = true;
                    $dDateDeceased = false;
                }
            }

            if ($bIsDeceased && $iPersonID > 0 && (new UserService())->isLastSignInCapableAdmin($iPersonID)) {
                $sDateDeceasedError = gettext("Can't mark the only administrator who can sign in as deceased");
                $bErrorFlag = true;
            }

            if (strlen($sEmail) > 0) {
                if (!EmailAddress::isValid($sEmail)) {
                    $sEmailError = EmailAddress::validationMessage();
                    $bErrorFlag = true;
                }
            }

            $emailPolicy = new SignInEmailPolicy();
            if ($sEmailError === '' && $person instanceof Person) {
                $emailRefusal = $emailPolicy->changeRefusal($person, $sEmail, AuthenticationManager::getCurrentUser());
                if ($emailRefusal !== null) {
                    $sEmailError = $emailRefusal;
                    $bErrorFlag = true;
                }
            }

            if (strlen($sWorkEmail) > 0) {
                if (!EmailAddress::isValid($sWorkEmail)) {
                    $sWorkEmailError = EmailAddress::validationMessage();
                    $bErrorFlag = true;
                }
            }

            $aCustomData = [];
            foreach ($rsCustomFields as $field) {
                $fieldId = $field->getId();
                $currentFieldData = CustomFieldUtils::cleanInput($data[$fieldId] ?? '');
                $bErrorFlag |= !CustomFieldUtils::validate((int) $field->getTypeId(), $currentFieldData, $fieldId, $aCustomErrors);
                $aCustomData[$fieldId] = $currentFieldData;
            }

            if (!$bErrorFlag) {
                if (strlen((string) $iBirthYear) !== 4) {
                    $iBirthYear = null;
                }

                if ($iFamily === -1) {
                    $family = new Family();
                    $family
                        ->setName($sLastName)
                        ->setAddress1($sAddress1)
                        ->setAddress2($sAddress2)
                        ->setCity($sCity)
                        ->setState($sState)
                        ->setZip($sZip)
                        ->setCountry($sCountry)
                        ->setHomePhone($sHomePhone)
                        ->setDateEntered(new DateTimeImmutable())
                        ->setEnteredBy(AuthenticationManager::getCurrentUser()->getId());
                    $family->save();
                    $family->reload();
                    $iFamily = $family->getId();
                }

                if ($bHideAge) {
                    $per_Flags = 1;
                } else {
                    $per_Flags = 0;
                }

                $person = new Person();

                $personAlreadyExist = $iPersonID > 0;
                if ($personAlreadyExist) {
                    $person = PersonQuery::create()->findOneById($iPersonID);
                    $person
                        ->setDateLastEdited(date('YmdHis'))
                        ->setEditedBy(AuthenticationManager::getCurrentUser()->getId());
                } else {
                    $person
                        ->setDateEntered(date('YmdHis'))
                        ->setEnteredBy(AuthenticationManager::getCurrentUser()->getId());
                }

                $person
                    ->setTitle($sTitle)
                    ->setFirstName($sFirstName)
                    ->setMiddleName($sMiddleName)
                    ->setLastName($sLastName)
                    ->setSuffix($sSuffix)
                    ->setGender($iGender)
                    ->setAddress1($sAddress1)
                    ->setAddress2($sAddress2)
                    ->setCity($sCity)
                    ->setState(States::keepStoredIfSame($sCountry, $person->getState(), $sState))
                    ->setZip($sZip)
                    ->setCountry(Countries::keepStoredIfSame($person->getCountry(), $sCountry))
                    ->setHomePhone($sHomePhone)
                    ->setWorkPhone($sWorkPhone)
                    ->setCellPhone($sCellPhone)
                    ->setEmail($sEmail)
                    ->setWorkEmail($sWorkEmail)
                    ->setBirthYear($iBirthYear)
                    ->setBirthMonth($iBirthMonth)
                    ->setBirthDay($iBirthDay)
                    ->setFamId($iFamily)
                    ->setFmrId($iFamilyRole)
                    ->setClsId($iClassification)
                    ->setGradeId($iGrade)
                    ->setFlags($per_Flags)
                    ->setFacebook($sFacebook)
                    ->setTwitter($sTwitter)
                    ->setLinkedIn($sLinkedIn);

                if (strlen($dMembershipDate) > 0) {
                    $person->setMembershipDate($dMembershipDate);
                } else {
                    $person->setMembershipDate(null);
                }

                if (strlen($dFriendDate) > 0) {
                    $person->setFriendDate($dFriendDate);
                } else {
                    $person->setFriendDate(null);
                }

                if ($bIsDeceased) {
                    $person->setDateDeceased(strlen($dDateDeceased) > 0 ? $dDateDeceased : date('Y-m-d'));
                } else {
                    $person->setDateDeceased(null);
                }

                if (AuthenticationManager::getCurrentUser()->isFinanceEnabled()) {
                    $person->setEnvelope($iEnvelope);
                }

                $connection = Propel::getWriteConnection(PersonTableMap::DATABASE_NAME);
                $connection->beginTransaction();
                try {
                    $sOldEmail = $personAlreadyExist
                        ? (string) PersonQuery::create()->filterById($iPersonID)->lockForUpdate()->select(['Email'])->findOne($connection)
                        : null;
                    $person->save($connection);
                    $person->reload(false, $connection);
                    $emailPolicy->recordChange($person, $sOldEmail, EmailChangeChannel::STAFF, AuthenticationManager::getCurrentUser());
                    $connection->commit();
                } catch (Throwable $e) {
                    $connection->rollBack();

                    throw $e;
                }

                if ($person->hasOwnAddress() && $person->getGeocodeStatus() === Person::GEOCODE_UNTRIED) {
                    (new FamilyService())->autoGeocodePerson($person);
                }

                if (!$personAlreadyExist) {
                    $iPersonID = $person->getId();

                    $personCustom = new PersonCustom();
                    $personCustom->setPerId($iPersonID);
                    $personCustom->save();
                }

                $photo = new Photo('Person', $iPersonID);
                $photo->refresh();

                if ($numCustomFields > 0) {
                    $customFieldService->savePersonValues((int) $iPersonID, $aCustomData);
                }

                return ['saved' => true, 'personId' => (int) $iPersonID, 'familyId' => (int) $iFamily];
            }

            $per_Envelope = $iEnvelope;
        } else {

            if (!$isNewPerson) {

                extract($person->toArray(TableMap::TYPE_FIELDNAME));
                $family = $person->getFamily();

                $sTitle = $per_Title;
                $sFirstName = $per_FirstName;
                $sMiddleName = $per_MiddleName;
                $sLastName = $per_LastName;
                $sSuffix = $per_Suffix;
                $iGender = (int) $per_Gender;
                $sAddress1 = $per_Address1;
                $sAddress2 = $per_Address2;
                $sCity = $per_City;
                $sState = $per_State;
                $sZip = $per_Zip;
                $sCountry = $per_Country;
                $sHomePhone = $per_HomePhone;
                $sWorkPhone = $per_WorkPhone;
                $sCellPhone = $per_CellPhone;
                $sEmail = $per_Email;
                $sWorkEmail = $per_WorkEmail;
                $iBirthMonth = is_numeric($per_BirthMonth) ? (int) $per_BirthMonth : null;
                $iBirthDay = is_numeric($per_BirthDay) ? (int) $per_BirthDay : null;
                $iBirthYear = is_numeric($per_BirthYear) ? (int) $per_BirthYear : null;
                $bHideAge = ($per_Flags & 1) !== 0;
                $iOriginalFamily = $per_fam_ID;
                $iFamily = (int) $per_fam_ID;
                $iFamilyRole = (int) $per_fmr_ID;
                $dMembershipDate = $person->getMembershipDate('Y-m-d') ?? '';
                $dFriendDate = $person->getFriendDate('Y-m-d') ?? '';
                $bIsDeceased = !empty($per_DateDeceased);
                $dDateDeceased = $person->getDateDeceased('Y-m-d') ?? '';
                $iClassification = (int) $per_cls_ID;
                $iGrade = GradeService::resolve($per_grd_ID);
                $iViewAgeFlag = (int) $per_Flags;

                $sFacebook = $per_Facebook;
                $sTwitter = $per_Twitter;
                $sLinkedIn = $per_LinkedIn;

                $sPhoneCountry = $sCountry;

                $sHomePhone = $per_HomePhone ?? '';
                $sWorkPhone = $per_WorkPhone ?? '';
                $sCellPhone = $per_CellPhone ?? '';

                $bNoFormat_HomePhone = !empty($sHomePhone);
                $bNoFormat_CellPhone = !empty($sCellPhone);
                $bNoFormat_WorkPhone = !empty($sWorkPhone);

                $bFamilyAddress1 = strlen((string) $family?->getAddress1());
                $bFamilyAddress2 = strlen((string) $family?->getAddress2());
                $bFamilyCity = strlen((string) $family?->getCity());
                $bFamilyState = strlen((string) $family?->getState());
                $bFamilyZip = strlen((string) $family?->getZip());
                $bFamilyCountry = strlen((string) $family?->getCountry());
                $bFamilyHomePhone = strlen((string) $family?->getHomePhone());

                $bFacebook = strlen($per_Facebook);
                $bTwitter = strlen($per_Twitter);
                $bLinkedIn = strlen($per_LinkedIn);

                $aCustomData = $customFieldService->getPersonValues($iPersonID);
            }
        }

        $rsClassifications = ListOptionQuery::create()->filterById(1)->orderByOptionSequence()->find();

        $rsFamilies = FamilyQuery::create()->orderByName()->find();

        $rsFamilyRoles = ListOptionQuery::create()->filterById(2)->orderByOptionSequence()->find();

        $sCountry = Countries::toISO($sCountry);

        $bEmailLocked = false;
        $sEmailHint = '';
        if ($person instanceof Person && !$person->isNew()) {
            $emailPolicy ??= new SignInEmailPolicy();
            if (!AuthenticationManager::getCurrentUser()->isAdmin() && $emailPolicy->needsAdministrator($person)) {
                $bEmailLocked = true;
                $sEmailHint = gettext('Only an administrator can change the email of a person who has a staff account or portal permissions.');
            } elseif ($emailPolicy->hasSignedIn(PersonSigninQuery::create()->findPk((int) $person->getId()))) {
                $sEmailHint = gettext('This person has signed in to the Member Portal. Changing this email signs them out everywhere, and the new address cannot be used to sign in until it is confirmed.');
            }
        }

        return [
            'saved' => false,
            'bErrorFlag' => $bErrorFlag ?? null,
            'sTitle' => $sTitle ?? null,
            'sFirstName' => $sFirstName ?? null,
            'sFirstNameError' => $sFirstNameError ?? null,
            'sMiddleName' => $sMiddleName ?? null,
            'sMiddleNameError' => $sMiddleNameError ?? null,
            'sLastName' => $sLastName ?? null,
            'sLastNameError' => $sLastNameError ?? null,
            'sSuffix' => $sSuffix ?? null,
            'iGender' => $iGender ?? null,
            'iBirthMonth' => $iBirthMonth ?? null,
            'iBirthDay' => $iBirthDay ?? null,
            'iBirthYear' => $iBirthYear ?? null,
            'sBirthYearError' => $sBirthYearError ?? null,
            'sBirthDateError' => $sBirthDateError ?? null,
            'bHideAge' => $bHideAge ?? null,
            'rsFamilies' => $rsFamilies ?? null,
            'iFamily' => $iFamily ?? null,
            'queryParamFamilyId' => $queryParamFamilyId ?? null,
            'rsFamilyRoles' => $rsFamilyRoles ?? null,
            'iFamilyRole' => $iFamilyRole ?? null,
            'iGrade' => $iGrade ?? null,
            'bFamilyAddress1' => $bFamilyAddress1 ?? null,
            'sAddress1' => $sAddress1 ?? null,
            'bFamilyAddress2' => $bFamilyAddress2 ?? null,
            'sAddress2' => $sAddress2 ?? null,
            'bFamilyCity' => $bFamilyCity ?? null,
            'sCity' => $sCity ?? null,
            'bFamilyState' => $bFamilyState ?? null,
            'sState' => $sState ?? null,
            'bFamilyZip' => $bFamilyZip ?? null,
            'sZip' => $sZip ?? null,
            'bFamilyCountry' => $bFamilyCountry ?? null,
            'sCountry' => $sCountry ?? null,
            'isNewPerson' => $isNewPerson ?? null,
            'bFamilyHomePhone' => $bFamilyHomePhone ?? null,
            'sHomePhone' => $sHomePhone ?? null,
            'bNoFormat_HomePhone' => $bNoFormat_HomePhone ?? null,
            'bFamilyCellPhone' => $bFamilyCellPhone ?? null,
            'sCellPhone' => $sCellPhone ?? null,
            'bNoFormat_CellPhone' => $bNoFormat_CellPhone ?? null,
            'bFamilyWorkPhone' => $bFamilyWorkPhone ?? null,
            'sWorkPhone' => $sWorkPhone ?? null,
            'bNoFormat_WorkPhone' => $bNoFormat_WorkPhone ?? null,
            'sEmail' => $sEmail ?? null,
            'bEmailLocked' => $bEmailLocked ?? null,
            'sEmailHint' => $sEmailHint ?? null,
            'sEmailError' => $sEmailError ?? null,
            'sWorkEmail' => $sWorkEmail ?? null,
            'sWorkEmailError' => $sWorkEmailError ?? null,
            'sFacebook' => $sFacebook ?? null,
            'sFacebookError' => $sFacebookError ?? null,
            'sTwitter' => $sTwitter ?? null,
            'sTwitterError' => $sTwitterError ?? null,
            'sLinkedIn' => $sLinkedIn ?? null,
            'sLinkedInError' => $sLinkedInError ?? null,
            'rsClassifications' => $rsClassifications ?? null,
            'iClassification' => $iClassification ?? null,
            'dMembershipDate' => $dMembershipDate ?? null,
            'sMembershipDateError' => $sMembershipDateError ?? null,
            'dFriendDate' => $dFriendDate ?? null,
            'sFriendDateError' => $sFriendDateError ?? null,
            'bIsDeceased' => $bIsDeceased ?? null,
            'dDateDeceased' => $dDateDeceased ?? null,
            'sDateDeceasedError' => $sDateDeceasedError ?? null,
            'numCustomFields' => $numCustomFields ?? null,
            'rsCustomFields' => $rsCustomFields ?? null,
            'aCustomData' => $aCustomData ?? null,
            'submitted' => $submitted ?? null,
            'aCustomErrors' => $aCustomErrors ?? null,
            'iPersonID' => $iPersonID ?? null,
        ];
    }
}
