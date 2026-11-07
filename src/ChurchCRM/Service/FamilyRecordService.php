<?php

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\data\Countries;
use ChurchCRM\data\States;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Service\FamilyService;
use ChurchCRM\Service\GradeService;
use ChurchCRM\model\ChurchCRM\Map\FamilyTableMap;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonCustom;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\CustomFieldUtils;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\AddressDefaults;
use ChurchCRM\Service\CustomFieldService;
use Propel\Runtime\Map\TableMap;

class FamilyRecordService
{
    public function apply(?Family $family, array $data = [], bool $submitted = false): array
    {
        $iFamilyID = $family?->getId() ?? -1;
        $customFieldService = new CustomFieldService();
        $rsCustomFields = $customFieldService->getVisibleFamilyFields();
        $numCustomFields = count($rsCustomFields);

        $bErrorFlag = false;
        $sNameError = '';
        $sWeddingDateError = '';
        $sSecondAddressError = '';

        $sName = '';

        $UpdateBirthYear = 0;

        $aFirstNameError = [];
        $aBirthDateError = [];
        $aperFlags = [];

        if ($submitted) {
            $sName = InputUtils::sanitizeText($data['name'] ?? '');
            $sAddress1 = str_replace(',', '', InputUtils::sanitizeText($data['address1'] ?? ''));
            $sAddress2 = str_replace(',', '', InputUtils::sanitizeText($data['address2'] ?? ''));
            $sCity = InputUtils::sanitizeText($data['city'] ?? '');
            $sZip = InputUtils::sanitizeText($data['zip'] ?? '');

            if (SystemConfig::getBooleanValue('bForceUppercaseZip')) {
                $sZip = strtoupper($sZip);
            }

            $sCountry = InputUtils::sanitizeText($data['country'] ?? '');
            $iFamilyMemberRows = (int) InputUtils::filterInt($data['famCount'] ?? '');
            if ($iFamilyMemberRows < 0 || $iFamilyMemberRows > 500) {
                throw new \InvalidArgumentException(gettext('Invalid family member count'));
            }
            $members = [];
            if ($iFamilyID > 0) {
                foreach (PersonQuery::create()->filterByFamId($iFamilyID)->find() as $member) {
                    $members[(int) $member->getId()] = $member;
                }
                $seen = [];
                for ($row = 1; $row <= $iFamilyMemberRows; $row++) {
                    $memberId = (int) ($data['personId' . $row] ?? 0);
                    if (!isset($members[$memberId]) || isset($seen[$memberId])) {
                        throw new \InvalidArgumentException(gettext('Invalid family member'));
                    }
                    $seen[$memberId] = true;
                }
            }

            $sState = InputUtils::sanitizeText($data[$data['stateType'] === 'dropDown' ? 'state' : 'stateTextbox']);

            $sSecondAddress1 = str_replace(',', '', InputUtils::sanitizeText($data['secondAddress1'] ?? ''));
            $sSecondAddress2 = str_replace(',', '', InputUtils::sanitizeText($data['secondAddress2'] ?? ''));
            $sSecondCity = InputUtils::sanitizeText($data['secondCity'] ?? '');
            $sSecondZip = InputUtils::sanitizeText($data['secondZip'] ?? '');

            if (SystemConfig::getBooleanValue('bForceUppercaseZip')) {
                $sSecondZip = strtoupper($sSecondZip);
            }

            $sSecondCountry = InputUtils::sanitizeText($data['secondCountry'] ?? '');
            $sSecondState = InputUtils::sanitizeText(
                ($data['secondStateType'] ?? '') === 'dropDown'
                    ? ($data['secondState'] ?? '')
                    : ($data['secondStateTextbox'] ?? '')
            );
            $bSecondIsMailing = isset($data['secondIsMailing']);

            if (strlen(trim($sSecondAddress1)) < 1 && strlen(trim($sSecondCity)) < 1) {
                $sSecondAddress2 = '';
                $sSecondState = '';
                $sSecondZip = '';
                $sSecondCountry = '';
            }

            $sHomePhone = InputUtils::sanitizeText($data['homePhone'] ?? '');
            $bSendNewsLetter = isset($data['sendNewsLetter']);

            $nLatitude = 0.0;
            $nLongitude = 0.0;
            if (array_key_exists('latitude', $data)) {
                $nLatitude = InputUtils::filterFloat($data['latitude'] ?? '');
            }
            if (array_key_exists('longitude', $data)) {
                $nLongitude = InputUtils::filterFloat($data['longitude'] ?? '');
            }

            if (!is_numeric($nLatitude)) {
                $nLatitude = null;
            }

            if (!is_numeric($nLongitude)) {
                $nLongitude = null;
            }

            $nEnvelope = (int) ($family?->getEnvelope() ?? 0);
            if (array_key_exists('envelope', $data)) {
                $nEnvelope = InputUtils::filterInt($data['envelope'] ?? '');
            }

            $nEnvelope = (int) $nEnvelope;

            $dWeddingDate = InputUtils::sanitizeText($data['weddingDate'] ?? '');

            $bNoFormat_HomePhone = isset($data['noFormatHomePhone']);

            for ($iCount = 1; $iCount <= $iFamilyMemberRows; $iCount++) {
                $aFirstNames[$iCount] = InputUtils::sanitizeText($data["firstName$iCount"] ?? '');
                $aMiddleNames[$iCount] = InputUtils::sanitizeText($data["middleName$iCount"] ?? '');
                $aLastNames[$iCount] = InputUtils::sanitizeText($data["lastName$iCount"] ?? '');
                $aSuffix[$iCount] = InputUtils::sanitizeText($data["suffix$iCount"] ?? '');
                $aRoles[$iCount] = InputUtils::filterInt($data["role$iCount"] ?? '');
                $aGenders[$iCount] = InputUtils::filterInt($data["gender$iCount"] ?? '');
                $aBirthDays[$iCount] = InputUtils::filterInt($data["birthDay$iCount"] ?? '');
                $aBirthMonths[$iCount] = InputUtils::filterInt($data["birthMonth$iCount"] ?? '');
                $aBirthYears[$iCount] = InputUtils::filterInt($data["birthYear$iCount"] ?? '');
                $aClassification[$iCount] = InputUtils::filterInt($data["classification$iCount"] ?? '');
                $aGrades[$iCount] = GradeService::resolve($data["grade$iCount"] ?? null);
                $aPersonIDs[$iCount] = InputUtils::filterInt($data["personId$iCount"] ?? '');
                $aUpdateBirthYear[$iCount] = InputUtils::filterInt($data['updateBirthYear'] ?? '');

                if ($iFamilyID > 0) {
                    if (strlen($aFirstNames[$iCount]) === 0) {
                        $aFirstNameError[$iCount] = gettext('First name must be entered');
                        $bErrorFlag = true;
                    }
                }

                if ($aBirthMonths[$iCount] > 0 xor $aBirthDays[$iCount] > 0) {
                    $aBirthDateError[$iCount] = gettext('Invalid Birth Date: Missing birth month or day.');
                    $bErrorFlag = true;
                } elseif ($aBirthYears[$iCount] > 0 && $aBirthMonths[$iCount] === 0 && $aBirthDays[$iCount] === 0) {
                    $aBirthDateError[$iCount] = gettext('Invalid Birth Date: Missing birth month and day.');
                    $bErrorFlag = true;
                } elseif (strlen($aFirstNames[$iCount]) > 0 && $aBirthYears[$iCount] > 0) {
                    if ($aBirthYears[$iCount] < 0) {
                        $aBirthDateError[$iCount] = gettext('Invalid Year');
                        $bErrorFlag = true;
                    } elseif ($aBirthMonths[$iCount] > 0 && $aBirthDays[$iCount] > 0) {
                        if (!checkdate($aBirthMonths[$iCount], $aBirthDays[$iCount], $aBirthYears[$iCount])) {
                            $aBirthDateError[$iCount] = gettext('Invalid Birth Date.');
                            $bErrorFlag = true;
                        }
                    }
                }
            }

            if (strlen($sName) < 1) {
                $sNameError = gettext('You must enter a name');
                $bErrorFlag = true;
            }

            if ($bSecondIsMailing && strlen(trim($sSecondAddress1)) < 1 && strlen(trim($sSecondCity)) < 1) {
                $sSecondAddressError = gettext('Enter a second address before marking it as the mailing address');
                $bErrorFlag = true;
            }

            $dateString = DateTimeUtils::parseAndValidate($dWeddingDate, 'past');
            if (strlen($dWeddingDate) > 0 && $dateString === false) {
                $sWeddingDateError = gettext('Not a valid Wedding Date');
                $bErrorFlag = true;
            } else {
                $dWeddingDate = $dateString;
            }

            $aCustomData = [];
            foreach ($rsCustomFields as $field) {
                $fieldId = $field->getField();
                $currentFieldData = CustomFieldUtils::cleanInput($data[$fieldId] ?? '');
                $bErrorFlag |= !CustomFieldUtils::validate((int) $field->getTypeId(), $currentFieldData, $fieldId, $aCustomErrors);
                $aCustomData[$fieldId] = $currentFieldData;
            }

            if (!$bErrorFlag) {
                $bSendNewsLetterString = $bSendNewsLetter ? 'TRUE' : 'FALSE';

                $family = new Family();
                if ($iFamilyID >= 1) {
                    $family = FamilyQuery::create()->findPk($iFamilyID);
                    $family
                        ->setDateLastEdited(date('YmdHis'))
                        ->setEditedBy(AuthenticationManager::getCurrentUser()->getId());
                } else {
                    $family
                        ->setDateEntered(date('YmdHis'))
                        ->setEnteredBy(AuthenticationManager::getCurrentUser()->getId());
                }
                $family
                    ->setName($sName)
                    ->setAddress1($sAddress1)
                    ->setAddress2($sAddress2)
                    ->setCity($sCity)
                    ->setState(States::keepStoredIfSame($sCountry, $family->getState(), $sState))
                    ->setZip($sZip)
                    ->setCountry(Countries::keepStoredIfSame($family->getCountry(), $sCountry))
                    ->setSecondAddress1($sSecondAddress1)
                    ->setSecondAddress2($sSecondAddress2)
                    ->setSecondCity($sSecondCity)
                    ->setSecondState(States::keepStoredIfSame($sSecondCountry, $family->getSecondState(), $sSecondState))
                    ->setSecondZip($sSecondZip)
                    ->setSecondCountry(Countries::keepStoredIfSame($family->getSecondCountry(), $sSecondCountry))
                    ->setSecondIsMailing($bSecondIsMailing)
                    ->setHomePhone($sHomePhone)
                    ->setSendNewsletter($bSendNewsLetterString)
                    ->setEnvelope(AuthenticationManager::getCurrentUser()->isFinanceEnabled() ? $nEnvelope : ($family->getEnvelope() ?? 0))
                    ->setWeddingdate($dWeddingDate)
                    ->setLatitude($nLatitude)
                    ->setLongitude($nLongitude);

                $addressChanged = $family->isColumnModified(FamilyTableMap::COL_FAM_ADDRESS1)
                    || $family->isColumnModified(FamilyTableMap::COL_FAM_ADDRESS2)
                    || $family->isColumnModified(FamilyTableMap::COL_FAM_CITY)
                    || $family->isColumnModified(FamilyTableMap::COL_FAM_STATE)
                    || $family->isColumnModified(FamilyTableMap::COL_FAM_ZIP)
                    || $family->isColumnModified(FamilyTableMap::COL_FAM_COUNTRY);

                $family->save();
                $family->reload();

                if ($addressChanged && empty($family->getLatitude())) {
                    $familyService = new FamilyService();
                    $familyService->autoGeocodeFamily($family);
                }

                if ($iFamilyID < 1) {
                    $iFamilyID = $family->getId();
                    $customFieldService->ensureFamilyRow($iFamilyID);

                    for ($iCount = 1; $iCount <= $iFamilyMemberRows; $iCount++) {
                        if (strlen($aFirstNames[$iCount]) > 0) {
                            if (strlen((string) $aBirthYears[$iCount]) < 4) {
                                $aBirthYears[$iCount] = null;
                            }

                            $sLastNameToEnter = strlen($aLastNames[$iCount]) && $aLastNames[$iCount] != $sName
                                ? $aLastNames[$iCount]
                                : $sName;

                            $person = new Person();
                            $person
                                ->setFirstName($aFirstNames[$iCount])
                                ->setMiddleName($aMiddleNames[$iCount])
                                ->setLastName($sLastNameToEnter)
                                ->setSuffix($aSuffix[$iCount])
                                ->setFamId($iFamilyID)
                                ->setFmrId($aRoles[$iCount])
                                ->setDateEntered(date('YmdHis'))
                                ->setEnteredBy(AuthenticationManager::getCurrentUser()->getId())
                                ->setGender($aGenders[$iCount])
                                ->setBirthDay($aBirthDays[$iCount])
                                ->setBirthMonth($aBirthMonths[$iCount])
                                ->setBirthYear($aBirthYears[$iCount])
                                ->setClsId($aClassification[$iCount])
                                ->setGradeId($aGrades[$iCount]);
                            $person->save();
                            $person->reload();
                            $personCustom = new PersonCustom();
                            $personCustom->setPerId($person->getId());
                            $personCustom->save();
                        }
                    }
                    $family = FamilyQuery::create()->findPk($iFamilyID);
                    $family->updateLanLng();
                } else {
                    for ($iCount = 1; $iCount <= $iFamilyMemberRows; $iCount++) {
                        if (strlen($aFirstNames[$iCount]) > 0) {
                            if (strlen((string) $aBirthYears[$iCount]) < 4) {
                                $aBirthYears[$iCount] = null;
                            }

                            $sLastNameToEnter = strlen($aLastNames[$iCount]) && $aLastNames[$iCount] != $sName
                                ? $aLastNames[$iCount]
                                : $sName;
                            $person = $members[(int) $aPersonIDs[$iCount]];
                            $person
                                ->setFirstName($aFirstNames[$iCount])
                                ->setMiddleName($aMiddleNames[$iCount])
                                ->setLastName($aLastNames[$iCount])
                                ->setSuffix($aSuffix[$iCount])
                                ->setGender($aGenders[$iCount])
                                ->setFmrId($aRoles[$iCount])
                                ->setBirthMonth($aBirthMonths[$iCount])
                                ->setBirthDay($aBirthDays[$iCount])
                                ->setClsId($aClassification[$iCount])
                                ->setGradeId($aGrades[$iCount])
                                ->setDateLastEdited(date('YmdHis'))
                                ->setEditedBy(AuthenticationManager::getCurrentUser()->getId());
                            if ($aUpdateBirthYear[$iCount] & 1) {
                                $person->setBirthYear($aBirthYears[$iCount]);
                            }
                            $person->save();
                        }
                    }
                    $family = FamilyQuery::create()->findPk($iFamilyID);
                    $family->updateLanLng();
                }

                if ($numCustomFields > 0) {
                    $customFieldService->saveFamilyValues((int) $iFamilyID, $aCustomData);
                }

                return ['saved' => true, 'familyId' => (int) $iFamilyID];
            }
        } else {
            if ($family) {

                $iFamilyID = $family->getId();
                $sName = $family->getName();
                $sAddress1 = $family->getAddress1();
                $sAddress2 = $family->getAddress2();
                $sCity = $family->getCity();
                $sState = $family->getState();
                $sZip = $family->getZip();
                $sCountry = $family->getCountry();
                $sSecondAddress1 = $family->getSecondAddress1();
                $sSecondAddress2 = $family->getSecondAddress2();
                $sSecondCity = $family->getSecondCity();
                $sSecondState = $family->getSecondState();
                $sSecondZip = $family->getSecondZip();
                $sSecondCountry = $family->getSecondCountry();
                $bSecondIsMailing = (bool) $family->getSecondIsMailing();
                $sHomePhone = $family->getHomePhone();
                $bSendNewsLetter = $family->getSendNewsletter() === 'TRUE';
                $dWeddingDate = $family->getWeddingdate('Y-m-d');
                $nLatitude = $family->getLatitude();
                $nLongitude = $family->getLongitude();

                $bNoFormat_HomePhone = !empty($sHomePhone);

                $aCustomData = $customFieldService->getFamilyValues($iFamilyID);

                $aCustomErrors = [];

                if ($numCustomFields > 0) {
                    foreach ($rsCustomFields as $field) {
        $aCustomErrors[$field->getField()] = false;
                    }
                }

                $rsMembers = PersonQuery::create()->filterByFamId($iFamilyID)->orderByFmrId()->find();
                $iCount = 0;
                $iFamilyMemberRows = 0;
                foreach ($rsMembers as $member) {
                    extract($member->toArray(TableMap::TYPE_FIELDNAME));
                    $iCount++;
                    $iFamilyMemberRows++;
                    $aFirstNames[$iCount] = $per_FirstName;
                    $aMiddleNames[$iCount] = $per_MiddleName;
                    $aLastNames[$iCount] = $per_LastName;
                    $aSuffix[$iCount] = $per_Suffix;
                    $aGenders[$iCount] = (int)$per_Gender;
                    $aRoles[$iCount] = (int)$per_fmr_ID;
                    $aBirthMonths[$iCount] = (int)$per_BirthMonth;
                    $aBirthDays[$iCount] = (int)$per_BirthDay;
                    $aBirthYears[$iCount] = $per_BirthYear > 0 ? (int)$per_BirthYear : '';
                    $aClassification[$iCount] = (int)$per_cls_ID;
                    $aGrades[$iCount] = GradeService::resolve($per_grd_ID);
                    $aPersonIDs[$iCount] = (int)$per_ID;
                    $aperFlags[$iCount] = $per_Flags;
                }
            } else {
                $sCity = AddressDefaults::city();
                $sCountry = AddressDefaults::country();
                $sState = AddressDefaults::state();
                $sZip = AddressDefaults::zip();
                $iClassification = '0';
                $iFamilyMemberRows = 4;

                $iFamilyID = -1;
                $sName = '';
                $sAddress1 = '';
                $sAddress2 = '';
                $sSecondAddress1 = '';
                $sSecondAddress2 = '';
                $sSecondCity = '';
                $sSecondState = '';
                $sSecondZip = '';
                $sSecondCountry = '';
                $bSecondIsMailing = false;
                $sHomePhone = '';
                $bNoFormat_HomePhone = isset($data['noFormatHomePhone']);
                $sWorkPhone = '';
                $bNoFormat_WorkPhone = isset($data['noFormatWorkPhone']);
                $sCellPhone = '';
                $bNoFormat_CellPhone = isset($data['noFormatCellPhone']);
                $bSendNewsLetter = 'TRUE';
                $dWeddingDate = '';
                $nLatitude = 0.0;
                $nLongitude = 0.0;

                $iDefaultHeadRole = (int) SystemConfig::getValue('sDirRoleHead');
                for ($iCount = 1; $iCount <= $iFamilyMemberRows; $iCount++) {
                    $aFirstNames[$iCount] = '';
                    $aMiddleNames[$iCount] = '';
                    $aLastNames[$iCount] = '';
                    $aSuffix[$iCount] = '';
                    $aRoles[$iCount] = ($iCount === 1) ? $iDefaultHeadRole : 0;
                    $aGenders[$iCount] = '';
                    $aBirthDays[$iCount] = 0;
                    $aBirthMonths[$iCount] = 0;
                    $aBirthYears[$iCount] = '';
                    $aClassification[$iCount] = 0;
                    $aGrades[$iCount] = null;
                    $aPersonIDs[$iCount] = 0;
                    $aUpdateBirthYear[$iCount] = 0;
                }

                $aCustomData = [];
                $aCustomErrors = [];
                if ($numCustomFields > 0) {
                    foreach ($rsCustomFields as $field) {
                        $aCustomData[$field->getField()] = '';
                        $aCustomErrors[$field->getField()] = false;
                    }
                }
            }
        }
        $sCountry = Countries::toISO($sCountry);
        $sSecondCountry = Countries::toISO($sSecondCountry ?? '');

        $familyRoles = ListOptionQuery::create()->filterById(2)->orderByOptionSequence()->find();
        $classifications = ListOptionQuery::create()->filterById(1)->orderByOptionSequence()->find();
        $familyRolesJS = [];
        foreach ($familyRoles as $role) {
            $familyRolesJS[] = ['id' => $role->getOptionId(), 'name' => $role->getOptionName()];
        }
        $classificationsJS = [];
        foreach ($classifications as $classification) {
            $classificationsJS[] = ['id' => $classification->getOptionId(), 'name' => $classification->getOptionName()];
        }

        return [
            'saved' => false,
            'family' => $family,
            'iFamilyID' => $iFamilyID ?? null,
            'iFamilyMemberRows' => $iFamilyMemberRows ?? null,
            'bErrorFlag' => $bErrorFlag ?? null,
            'sName' => $sName ?? null,
            'sNameError' => $sNameError ?? null,
            'dWeddingDate' => $dWeddingDate ?? null,
            'sWeddingDateError' => $sWeddingDateError ?? null,
            'sAddress1' => $sAddress1 ?? null,
            'sAddress2' => $sAddress2 ?? null,
            'sCity' => $sCity ?? null,
            'sState' => $sState ?? null,
            'sZip' => $sZip ?? null,
            'sCountry' => $sCountry ?? null,
            'nLatitude' => $nLatitude ?? null,
            'nLongitude' => $nLongitude ?? null,
            'bSecondIsMailing' => $bSecondIsMailing ?? null,
            'sSecondAddress1' => $sSecondAddress1 ?? null,
            'sSecondAddress2' => $sSecondAddress2 ?? null,
            'sSecondCity' => $sSecondCity ?? null,
            'sSecondZip' => $sSecondZip ?? null,
            'sSecondState' => $sSecondState ?? '',
            'sSecondCountry' => $sSecondCountry ?? '',
            'sSecondAddressError' => $sSecondAddressError ?? null,
            'sHomePhone' => $sHomePhone ?? null,
            'bNoFormat_HomePhone' => $bNoFormat_HomePhone ?? null,
            'bSendNewsLetter' => $bSendNewsLetter ?? null,
            'numCustomFields' => $numCustomFields ?? null,
            'rsCustomFields' => $rsCustomFields ?? null,
            'aCustomData' => $aCustomData ?? null,
            'submitted' => $submitted ?? null,
            'aCustomErrors' => $aCustomErrors ?? null,
            'familyRoles' => $familyRoles ?? null,
            'aPersonIDs' => $aPersonIDs ?? null,
            'aFirstNames' => $aFirstNames ?? null,
            'aFirstNameError' => $aFirstNameError ?? null,
            'aMiddleNames' => $aMiddleNames ?? null,
            'aLastNames' => $aLastNames ?? null,
            'aSuffix' => $aSuffix ?? null,
            'aGenders' => $aGenders ?? null,
            'aRoles' => $aRoles ?? null,
            'aBirthMonths' => $aBirthMonths ?? null,
            'aBirthDays' => $aBirthDays ?? null,
            'aperFlags' => $aperFlags ?? null,
            'UpdateBirthYear' => $UpdateBirthYear ?? null,
            'aBirthYears' => $aBirthYears ?? null,
            'aBirthDateError' => $aBirthDateError ?? null,
            'aClassification' => $aClassification ?? null,
            'classifications' => $classifications ?? null,
            'aGrades' => $aGrades ?? null,
            'familyRolesJS' => $familyRolesJS ?? null,
            'classificationsJS' => $classificationsJS ?? null,
        ];
    }
}
