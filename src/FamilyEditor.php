<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\notifications\NewPersonOrFamilyEmail;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Map\FamilyTableMap;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$sPageTitle = gettext('Family Editor');

$iFamilyID = -1;
$family = null;

// Get the FamilyID from the querystring
if (array_key_exists('FamilyID', $_GET)) {
    $iFamilyID = InputUtils::legacyFilterInput($_GET['FamilyID'], 'int');
}

// Security: User must have Add or Edit Records permission to use this form in those manners
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
if ($iFamilyID > 0) {
    if (!(AuthenticationManager::getCurrentUser()->isEditRecordsEnabled() || (AuthenticationManager::getCurrentUser()->isEditSelfEnabled() && $iFamilyID == AuthenticationManager::getCurrentUser()->getPerson()->getFamId()))) {
        RedirectUtils::securityRedirect('EditRecords');
    }

    $family = FamilyQuery::create()->findOneById($iFamilyID);
    if ($family === null) {
        RedirectUtils::redirect('v2/dashboard');
    }
} elseif (!AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()) {
    RedirectUtils::securityRedirect('AddRecords');
}

// Get the list of custom person fields
$sSQL = 'SELECT family_custom_master.* FROM family_custom_master ORDER BY fam_custom_Order';
$rsCustomFields = RunQuery($sSQL);
$numCustomFields = mysqli_num_rows($rsCustomFields);

// Get Field Security List Matrix
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence';
$rsSecurityGrp = RunQuery($sSQL);

while ($aRow = mysqli_fetch_array($rsSecurityGrp)) {
    extract($aRow);
    $aSecurityType[$lst_OptionID] = $lst_OptionName;
}

$bErrorFlag = false;
$sNameError = '';
$sEmailError = '';
$sWeddingDateError = '';

$sName = '';

$UpdateBirthYear = 0;

$aFirstNameError = [];
$aBirthDateError = [];
$aperFlags = [];

//Is this the second pass?
if (isset($_POST['FamilySubmit']) || isset($_POST['FamilySubmitAndAdd'])) {
    //Assign everything locally
    $sName = InputUtils::legacyFilterInput($_POST['Name']);
    // Strip commas out of address fields because they are problematic when
    // exporting addresses to CSV file
    $sAddress1 = str_replace(',', '', InputUtils::legacyFilterInput($_POST['Address1']));
    $sAddress2 = str_replace(',', '', InputUtils::legacyFilterInput($_POST['Address2']));
    $sCity = InputUtils::legacyFilterInput($_POST['City']);
    $sZip = InputUtils::legacyFilterInput($_POST['Zip']);

    if (SystemConfig::getBooleanValue('bForceUppercaseZip')) {
        $sZip = strtoupper($sZip);
    }

    $sCountry = InputUtils::legacyFilterInput($_POST['Country']);
    $iFamilyMemberRows = InputUtils::legacyFilterInput($_POST['FamCount']);

    if ($_POST['stateType'] == "dropDown") {
        $sState = InputUtils::legacyFilterInput($_POST['State']);
    } else {
        $sState = InputUtils::legacyFilterInput($_POST['StateTextbox']);
    }

    $sHomePhone = InputUtils::legacyFilterInput($_POST['HomePhone']);
    $sWorkPhone = InputUtils::legacyFilterInput($_POST['WorkPhone']);
    $sCellPhone = InputUtils::legacyFilterInput($_POST['CellPhone']);
    $sEmail = InputUtils::legacyFilterInput($_POST['Email']);
    $bSendNewsLetter = isset($_POST['SendNewsLetter']);

    $nLatitude = 0.0;
    $nLongitude = 0.0;
    if (array_key_exists('Latitude', $_POST)) {
        $nLatitude = InputUtils::legacyFilterInput($_POST['Latitude'], 'float');
    }
    if (array_key_exists('Longitude', $_POST)) {
        $nLongitude = InputUtils::legacyFilterInput($_POST['Longitude'], 'float');
    }

    if (!is_numeric($nLatitude)) {
        $nLatitude = null;
    }

    if (!is_numeric($nLongitude)) {
        $nLongitude = null;
    }

    $nEnvelope = 0;
    if (array_key_exists('Envelope', $_POST)) {
        $nEnvelope = InputUtils::legacyFilterInput($_POST['Envelope'], 'int');
    }

    if (is_numeric($nEnvelope)) { // Only integers are allowed as Envelope Numbers
        if (intval($nEnvelope) == floatval($nEnvelope)) {
            $nEnvelope = "'" . intval($nEnvelope) . "'";
        } else {
            $nEnvelope = "'0'";
        }
    } else {
        $nEnvelope = "'0'";
    }

    $iPropertyID = 0;
    if (array_key_exists('PropertyID', $_POST)) {
        $iPropertyID = InputUtils::legacyFilterInput($_POST['PropertyID'], 'int');
    }
    $dWeddingDate = InputUtils::legacyFilterInput($_POST['WeddingDate'] ?? '');

    $bNoFormat_HomePhone = isset($_POST['NoFormat_HomePhone']);
    $bNoFormat_WorkPhone = isset($_POST['NoFormat_WorkPhone']);
    $bNoFormat_CellPhone = isset($_POST['NoFormat_CellPhone']);

    //Loop through the Family Member 'quick entry' form fields
    for ($iCount = 1; $iCount <= $iFamilyMemberRows; $iCount++) {
        // Assign everything to arrays
        $aFirstNames[$iCount] = InputUtils::legacyFilterInput($_POST['FirstName' . $iCount]);
        $aMiddleNames[$iCount] = InputUtils::legacyFilterInput($_POST['MiddleName' . $iCount]);
        $aLastNames[$iCount] = InputUtils::legacyFilterInput($_POST['LastName' . $iCount]);
        $aSuffix[$iCount] = InputUtils::legacyFilterInput($_POST['Suffix' . $iCount]);
        $aRoles[$iCount] = InputUtils::legacyFilterInput($_POST['Role' . $iCount], 'int');
        $aGenders[$iCount] = InputUtils::legacyFilterInput($_POST['Gender' . $iCount], 'int');
        $aBirthDays[$iCount] = InputUtils::legacyFilterInput($_POST['BirthDay' . $iCount], 'int');
        $aBirthMonths[$iCount] = InputUtils::legacyFilterInput($_POST['BirthMonth' . $iCount], 'int');
        $aBirthYears[$iCount] = InputUtils::legacyFilterInput($_POST['BirthYear' . $iCount], 'int');
        $aClassification[$iCount] = InputUtils::legacyFilterInput($_POST['Classification' . $iCount], 'int');
        $aPersonIDs[$iCount] = InputUtils::legacyFilterInput($_POST['PersonID' . $iCount], 'int');
        $aUpdateBirthYear[$iCount] = InputUtils::legacyFilterInput($_POST['UpdateBirthYear'], 'int');

        // Make sure first names were entered if editing existing family
        if ($iFamilyID > 0) {
            if (strlen($aFirstNames[$iCount]) === 0) {
                $aFirstNameError[$iCount] = gettext('First name must be entered');
                $bErrorFlag = true;
            }
        }

        // Validate any family member birthdays
        if ($aBirthMonths[$iCount] > 0 xor $aBirthDays[$iCount] > 0) {
            $aBirthDateError[$iCount] = gettext('Invalid Birth Date: Missing birth month or day.');
            $bErrorFlag = true;
        } elseif (strlen($aBirthYears[$iCount]) > 0 && $aBirthMonths[$iCount] == 0 && $aBirthDays[$iCount] == 0) {
            $aBirthDateError[$iCount] = gettext('Invalid Birth Date: Missing birth month and day.');
            $bErrorFlag = true;
        } elseif ((strlen($aFirstNames[$iCount]) > 0) && (strlen($aBirthYears[$iCount]) > 0)) {
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

    //Did they enter a name?
    if (strlen($sName) < 1) {
        $sNameError = gettext('You must enter a name');
        $bErrorFlag = true;
    }

    // Validate Wedding Date if one was entered
    $dateString = parseAndValidateDate($dWeddingDate, Bootstrapper::getCurrentLocale()->getCountryCode(), $pasfut = 'past');
    if ((strlen($dWeddingDate) > 0) && $dateString === false) {
        $sWeddingDateError = '<span class="text-danger">'
            . gettext('Not a valid Wedding Date') . '</span>';
        $bErrorFlag = true;
    } else {
        $dWeddingDate = $dateString;
    }

    // Validate Email
    if (strlen($sEmail) > 0) {
        if (checkEmail($sEmail) == false) {
            $sEmailError = '<span class="text-danger">'
                . gettext('Email is Not Valid') . '</span>';
            $bErrorFlag = true;
            $sEmail = null;
        }
    }

    // Validate all the custom fields
    $aCustomData = [];
    while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH)) {
        extract($rowCustomField);

        $currentFieldData = InputUtils::legacyFilterInput($_POST[$fam_custom_Field]);

        $bErrorFlag |= !validateCustomField($type_ID, $currentFieldData, $fam_custom_Field, $aCustomErrors);

        // assign processed value locally to $aPersonProps so we can use it to generate the form later
        $aCustomData[$fam_custom_Field] = $currentFieldData;
    }

    //If no errors, then let's update...
    if (!$bErrorFlag) {
        // Format the phone numbers before we store them
        if (!$bNoFormat_HomePhone) {
            $sHomePhone = CollapsePhoneNumber($sHomePhone, $sCountry);
        }
        if (!$bNoFormat_WorkPhone) {
            $sWorkPhone = CollapsePhoneNumber($sWorkPhone, $sCountry);
        }
        if (!$bNoFormat_CellPhone) {
            $sCellPhone = CollapsePhoneNumber($sCellPhone, $sCountry);
        }

        //Write the base SQL depending on the Action
        $bSendNewsLetterString = $bSendNewsLetter ? 'TRUE' : 'FALSE';

        $family = new \ChurchCRM\model\ChurchCRM\Family();
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
            ->setState($sState)
            ->setZip($sZip)
            ->setCountry($sCountry)
            ->setHomePhone($sHomePhone)
            ->setWorkPhone($sWorkPhone)
            ->setCellPhone($sCellPhone)
            ->setSendNewsletter($bSendNewsLetterString)
            ->setEnvelope($nEnvelope)
            ->setWeddingdate($dWeddingDate)
            ->setEmail($sEmail)
            ->setLatitude($nLatitude)
            ->setLongitude($nLongitude);

        // Update Lat/Long if address changes
        if (
            ($family->isColumnModified(FamilyTableMap::COL_FAM_ADDRESS1)
                || $family->isColumnModified(FamilyTableMap::COL_FAM_ADDRESS2)
                || $family->isColumnModified(FamilyTableMap::COL_FAM_CITY)
                || $family->isColumnModified(FamilyTableMap::COL_FAM_STATE)
                || $family->isColumnModified(FamilyTableMap::COL_FAM_ZIP)
                || $family->isColumnModified(FamilyTableMap::COL_FAM_COUNTRY))
            && (!$family->isColumnModified(FamilyTableMap::COL_FAM_LATITUDE)
                && !$family->isColumnModified(FamilyTableMap::COL_FAM_LONGITUDE))
        ) {
            $family->setLatitude(null);
            $family->setLongitude(null);
        }

        $family->save();
        $family->reload();

        //If the user added a new record, we need to key back to the route to the FamilyView page
        if ($iFamilyID < 1) {
            $iFamilyID = $family->getId();
            $sSQL = "INSERT INTO `family_custom` (`fam_ID`) VALUES ('" . $iFamilyID . "')";
            RunQuery($sSQL);

            // Add property if assigned
            if ($iPropertyID) {
                $sSQL = "INSERT INTO record2property_r2p (r2p_pro_ID, r2p_record_ID) VALUES ($iPropertyID, $iFamilyID)";
                RunQuery($sSQL);
            }

            //Run through the family member arrays...
            for ($iCount = 1; $iCount <= $iFamilyMemberRows; $iCount++) {
                if (strlen($aFirstNames[$iCount]) > 0) {
                    if (strlen($aBirthYears[$iCount]) < 4) {
                        $aBirthYears[$iCount] = 'NULL';
                    }

                    //If no last name is entered for a member, use the family name.
                    if (strlen($aLastNames[$iCount]) && $aLastNames[$iCount] != $sName) {
                        $sLastNameToEnter = $aLastNames[$iCount];
                    } else {
                        $sLastNameToEnter = $sName;
                    }

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
                        ->setClsId($aClassification[$iCount]);
                    $person->save();
                    $person->reload();
                    $dbPersonId = $person->getId();
                    $note = new Note();
                    $note->setPerId($dbPersonId);
                    $note->setText(gettext('Created via Family'));
                    $note->setType('create');
                    $note->setEntered(AuthenticationManager::getCurrentUser()->getId());
                    $note->save();
                    RunQuery('LOCK TABLES person_custom WRITE');
                    $sSQL = 'INSERT INTO person_custom (per_ID) VALUES ('
                        . $dbPersonId . ')';
                    RunQuery($sSQL);
                    RunQuery('UNLOCK TABLES');
                }
            }
            $family = FamilyQuery::create()->findPk($iFamilyID);
            $family->createTimeLineNote('create');
            $family->updateLanLng();

            if (!empty(SystemConfig::getValue("sNewPersonNotificationRecipientIDs"))) {
                $NotificationEmail = new NewPersonOrFamilyEmail($family);
                if (!$NotificationEmail->send()) {
                    $logger->warning($NotificationEmail->getError());
                }
            }
        } else {
            for ($iCount = 1; $iCount <= $iFamilyMemberRows; $iCount++) {
                if (strlen($aFirstNames[$iCount]) > 0) {
                    if (strlen($aBirthYears[$iCount]) < 4) {
                        $aBirthYears[$iCount] = 'NULL';
                    }

                    //If no last name is entered for a member, use the family name.
                    if (strlen($aLastNames[$iCount]) && $aLastNames[$iCount] != $sName) {
                        $sLastNameToEnter = $aLastNames[$iCount];
                    } else {
                        $sLastNameToEnter = $sName;
                    }
                    //RunQuery("LOCK TABLES person_per WRITE, person_custom WRITE");
                    $person = PersonQuery::create()->findOneById($aPersonIDs[$iCount]);
                    $person
                        ->setFirstName($aFirstNames[$iCount])
                        ->setMiddleName($aMiddleNames[$iCount])
                        ->setLastName($aLastNames[$iCount])
                        ->setSuffix($aSuffix[$iCount])
                        ->setGender($aGenders[$iCount])
                        ->setFmrId($aRoles[$iCount])
                        ->setBirthMonth($aBirthMonths[$iCount])
                        ->setBirthDay($aBirthDays[$iCount])
                        ->setClsId($aClassification[$iCount]);
                    if ($aUpdateBirthYear[$iCount] & 1) {
                        $person->setBirthYear($aBirthYears[$iCount]);
                    }
                    $person->save();
                    //RunQuery("UNLOCK TABLES");

                    $note = new Note();
                    $note->setPerId($aPersonIDs[$iCount]);
                    $note->setText(gettext('Updated via Family'));
                    $note->setType('edit');
                    $note->setEntered(AuthenticationManager::getCurrentUser()->getId());
                    $note->save();
                }
            }
            $family = FamilyQuery::create()->findPk($iFamilyID);
            $family->updateLanLng();
            $family->createTimeLineNote('edit');
        }

        // Update the custom person fields.
        if ($numCustomFields > 0) {
            $sSQL = 'REPLACE INTO family_custom SET ';
            mysqli_data_seek($rsCustomFields, 0);

            while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH)) {
                extract($rowCustomField);
                if (AuthenticationManager::getCurrentUser()->isEnabledSecurity($aSecurityType[$fam_custom_FieldSec])) {
                    $currentFieldData = trim($aCustomData[$fam_custom_Field]);

                    sqlCustomField($sSQL, $type_ID, $currentFieldData, $fam_custom_Field, $sCountry);
                }
            }

            // chop off the last 2 characters (comma and space) added in the last while loop iteration.
            $sSQL = mb_substr($sSQL, 0, -2);

            $sSQL .= ', fam_ID = ' . $iFamilyID;

            //Execute the SQL
            RunQuery($sSQL);
        }

        //Which submit button did they press?
        if (isset($_POST['FamilySubmit'])) {
            //Send to the view of this person
            RedirectUtils::redirect('v2/family/' . $iFamilyID);
        } else {
            //Reload to editor to add another record
            RedirectUtils::redirect('FamilyEditor.php');
        }
    }
} else {
    //FirstPass
    //Are we editing or adding?
    if ($family) {
        //Editing....
        //Get the information on this family
        $sSQL = 'SELECT * FROM family_fam WHERE fam_ID = ' . $iFamilyID;
        $rsFamily = RunQuery($sSQL);
        extract(mysqli_fetch_array($rsFamily));

        $iFamilyID = $family->getId();
        $sName = $family->getName();
        $sAddress1 = $family->getAddress1();
        $sAddress2 = $family->getAddress2();
        $sCity = $family->getCity();
        $sState = $family->getState();
        $sZip = $family->getZip();
        $sCountry = $family->getCountry();
        $sHomePhone = $family->getHomePhone();
        $sWorkPhone = $family->getWorkPhone();
        $sCellPhone = $family->getCellPhone();
        $sEmail = $family->getEmail();
        $bSendNewsLetter = $family->getSendNewsletter() === 'TRUE';
        $dWeddingDate = $family->getWeddingdate(SystemConfig::getValue("sDatePickerFormat"));
        $nLatitude = $family->getLatitude();
        $nLongitude = $family->getLongitude();

        // Expand the phone number
        $sHomePhone = ExpandPhoneNumber($sHomePhone, $sCountry, $bNoFormat_HomePhone);
        $sWorkPhone = ExpandPhoneNumber($sWorkPhone, $sCountry, $bNoFormat_WorkPhone);
        $sCellPhone = ExpandPhoneNumber($sCellPhone, $sCountry, $bNoFormat_CellPhone);

        $sSQL = 'SELECT * FROM family_custom WHERE fam_ID = ' . $iFamilyID;
        $rsCustomData = RunQuery($sSQL);
        $aCustomData = mysqli_fetch_array($rsCustomData, MYSQLI_BOTH);

        $aCustomErrors = [];

        if ($numCustomFields > 0) {
            mysqli_data_seek($rsCustomFields, 0);
            while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH)) {
                $aCustomErrors[$rowCustomField['fam_custom_Field']] = false;
            }
        }

        $sSQL = 'SELECT * FROM person_per LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_fam_ID =' . $iFamilyID . ' ORDER BY per_fmr_ID';
        $rsMembers = RunQuery($sSQL);
        $iCount = 0;
        $iFamilyMemberRows = 0;
        while ($aRow = mysqli_fetch_array($rsMembers)) {
            extract($aRow);
            $iCount++;
            $iFamilyMemberRows++;
            $aFirstNames[$iCount] = $per_FirstName;
            $aMiddleNames[$iCount] = $per_MiddleName;
            $aLastNames[$iCount] = $per_LastName;
            $aSuffix[$iCount] = $per_Suffix;
            $aGenders[$iCount] = $per_Gender;
            $aRoles[$iCount] = $per_fmr_ID;
            $aBirthMonths[$iCount] = $per_BirthMonth;
            $aBirthDays[$iCount] = $per_BirthDay;
            if ($per_BirthYear > 0) {
                $aBirthYears[$iCount] = $per_BirthYear;
            } else {
                $aBirthYears[$iCount] = '';
            }
            $aClassification[$iCount] = $per_cls_ID;
            $aPersonIDs[$iCount] = $per_ID;
            $aPerFlag[$iCount] = $per_Flags;
        }
    } else {
        //Adding....
        //Set defaults
        $sCity = SystemConfig::getValue('sDefaultCity');
        $sCountry = SystemConfig::getValue('sDefaultCountry');
        $sState = SystemConfig::getValue('sDefaultState');
        $sZip = SystemConfig::getValue('sDefaultZip');
        $iClassification = '0';
        $iFamilyMemberRows = 4;

        $iFamilyID = -1;
        $sName = '';
        $sAddress1 = '';
        $sAddress2 = '';
        $sHomePhone = '';
        $bNoFormat_HomePhone = isset($_POST['NoFormat_HomePhone']);
        $sWorkPhone = '';
        $bNoFormat_WorkPhone = isset($_POST['NoFormat_WorkPhone']);
        $sCellPhone = '';
        $bNoFormat_CellPhone = isset($_POST['NoFormat_CellPhone']);
        $sEmail = '';
        $bSendNewsLetter = 'TRUE';
        $dWeddingDate = '';
        $nLatitude = 0.0;
        $nLongitude = 0.0;

        //Loop through the Family Member 'quick entry' form fields
        $iDefaultHeadRole = (int) SystemConfig::getValue('sDirRoleHead');
        for ($iCount = 1; $iCount <= $iFamilyMemberRows; $iCount++) {
            // Assign everything to arrays
            $aFirstNames[$iCount] = '';
            $aMiddleNames[$iCount] = '';
            $aLastNames[$iCount] = '';
            $aSuffix[$iCount] = '';
            // First member defaults to Head of Household
            $aRoles[$iCount] = ($iCount === 1) ? $iDefaultHeadRole : 0;
            $aGenders[$iCount] = '';
            $aBirthDays[$iCount] = 0;
            $aBirthMonths[$iCount] = 0;
            $aBirthYears[$iCount] = '';
            $aClassification[$iCount] = 0;
            $aPersonIDs[$iCount] = 0;
            $aUpdateBirthYear[$iCount] = 0;
        }

        $aCustomData = [];
        $aCustomErrors = [];
        if ($numCustomFields > 0) {
            mysqli_data_seek($rsCustomFields, 0);
            while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH)) {
                extract($rowCustomField);
                $aCustomData[$fam_custom_Field] = '';
                $aCustomErrors[$fam_custom_Field] = false;
            }
        }
    }
}

require_once 'Include/Header.php';
?>
<form method="post" action="FamilyEditor.php?FamilyID=<?php echo $iFamilyID ?>" id="familyEditor">
    <input type="hidden" name="iFamilyID" value="<?= $iFamilyID ?>">
    <input type="hidden" name="FamCount" value="<?= $iFamilyMemberRows ?>">
    <input type="hidden" id="stateType" name="stateType" value="">

    <!-- Card 1: Family Info -->
    <div class="card card-info clearfix">
        <div class="card-header">
            <h3 class="card-title"><?= gettext('Family Info') ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="form-group col-md-6">
                    <label for="FamilyName"><?= gettext('Family Name') ?>:</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa-solid fa-people-roof"></i></span>
                        </div>
                        <input type="text" name="Name" id="FamilyName" value="<?= InputUtils::escapeAttribute($sName) ?>" maxlength="48" class="form-control">
                    </div>
                    <?php if ($sNameError) { ?>
                        <span class="text-danger small"><?= $sNameError ?></span>
                    <?php } ?>
                </div>
                <?php if (!SystemConfig::getValue('bHideWeddingDate')) { /* Wedding Date can be hidden - General Settings */
                    if (empty($dWeddingDate)) {
                        $dWeddingDate = '';
                    } ?>
                <div class="form-group col-md-4">
                    <label for="WeddingDate"><?= gettext('Wedding Date') ?>:</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa-solid fa-heart"></i></span>
                        </div>
                        <input type="text" class="form-control date-picker" name="WeddingDate" id="WeddingDate" value="<?= change_date_for_place_holder($dWeddingDate) ?>" maxlength="12" placeholder="<?= SystemConfig::getValue("sDatePickerPlaceHolder") ?>">
                    </div>
                    <?php if ($sWeddingDateError) { ?>
                    <span class="text-danger small"><?= $sWeddingDateError ?></span>
                    <?php } ?>
                </div>
                <?php } /* Wedding date can be hidden - General Settings */ ?>
            </div>
        </div>
    </div>

    <!-- Card 2: Address -->
    <div class="card card-info clearfix">
        <div class="card-header">
            <h3 class="card-title"><?= gettext('Address') ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="form-group col-md-6">
                    <label for="Address1"><?= gettext('Address') ?> 1:</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa-solid fa-location-dot"></i></span>
                        </div>
                        <input type="text" id="Address1" name="Address1" value="<?= InputUtils::escapeAttribute($sAddress1) ?>" maxlength="250" class="form-control">
                    </div>
                </div>
                <div class="form-group col-md-6">
                    <label for="Address2"><?= gettext('Address') ?> 2:</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa-solid fa-location-dot"></i></span>
                        </div>
                        <input type="text" id="Address2" name="Address2" value="<?= InputUtils::escapeAttribute($sAddress2) ?>" maxlength="250" class="form-control">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="form-group col-md-4">
                    <label for="City"><?= gettext('City') ?>:</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa-solid fa-city"></i></span>
                        </div>
                        <input type="text" id="City" name="City" value="<?= InputUtils::escapeAttribute($sCity) ?>" maxlength="50" class="form-control">
                    </div>
                </div>
                <div id="stateOptionDiv" class="form-group col-md-3">
                    <label for="State"><?= gettext('State') ?>:</label>
                    <select id="State" name="State" class="form-control select2" data-user-selected="<?= $sState ?>" data-system-default="<?= SystemConfig::getValue('sDefaultState') ?>">
                    </select>
                </div>
                <div id="stateInputDiv" class="form-group col-md-3 d-none">
                    <label for="StateTextbox"><?= gettext('State') ?>:</label>
                    <input id="StateTextbox" type="text" class="form-control" name="StateTextbox" value="<?= InputUtils::escapeAttribute($sState) ?>" maxlength="30">
                </div>
                <div class="form-group col-md-2">
                    <label for="Zip"><?= gettext('Zip') ?>:</label>
                    <input type="text" id="Zip" name="Zip" class="form-control" <?php
                    if (SystemConfig::getBooleanValue('bForceUppercaseZip')) {
                        echo 'style="text-transform:uppercase" ';
                    }
                    echo 'value="' . InputUtils::escapeAttribute($sZip) . '" '; ?> maxlength="10">
                </div>
                <div class="form-group col-md-3">
                    <label for="Country"><?= gettext('Country') ?>:</label>
                    <select id="Country" name="Country" class="form-control select2" data-user-selected="<?= $sCountry ?>" data-system-default="<?= SystemConfig::getValue('sDefaultCountry') ?>">
                    </select>
                </div>
            </div>
            <?php if (!SystemConfig::getValue('bHideLatLon')) { /* Lat/Lon can be hidden - General Settings */
                if (!$bHaveXML) { // No point entering if values will just be overwritten
                    ?>
                    <div class="row">
                        <div class="form-group col-md-3">
                            <label for="Latitude"><?= gettext('Latitude') ?>:</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa-solid fa-globe"></i></span>
                                </div>
                                <input type="text" class="form-control" id="Latitude" name="Latitude" value="<?= $nLatitude ?>" maxlength="50">
                            </div>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="Longitude"><?= gettext('Longitude') ?>:</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa-solid fa-globe"></i></span>
                                </div>
                                <input type="text" class="form-control" id="Longitude" name="Longitude" value="<?= $nLongitude ?>" maxlength="50">
                            </div>
                        </div>
                    </div>
                    <?php
                }
            } /* Lat/Lon can be hidden - General Settings */ ?>
        </div>
    </div>

    <!-- Card 3: Contact Information -->
    <div class="card card-info clearfix">
        <div class="card-header">
            <h3 class="card-title"><?= gettext('Contact Information') ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="form-group col-md-6">
                    <label for="Email"><?= gettext('Email') ?>:</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa-solid fa-at"></i></span>
                        </div>
                        <input type="email" id="Email" name="Email" class="form-control" value="<?= InputUtils::escapeAttribute($sEmail) ?>" maxlength="100">
                    </div>
                    <?php if ($sEmailError) { ?>
                    <span class="text-danger small"><?= $sEmailError ?></span>
                    <?php } ?>
                </div>
                <div class="form-group col-md-6">
                    <label for="CellPhone"><?= gettext('Mobile Phone') ?>:</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa-solid fa-mobile-screen"></i></span>
                        </div>
                        <input type="text" id="CellPhone" name="CellPhone" value="<?= InputUtils::escapeAttribute($sCellPhone) ?>" maxlength="30" class="form-control" data-inputmask='"mask": "<?= SystemConfig::getValue('sPhoneFormatCell') ?>"' data-mask>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="NoFormat_CellPhone" value="1" <?= $bNoFormat_CellPhone ? 'checked' : '' ?>>
                                <label class="mb-0 ml-1 small"><?= gettext('No format') ?></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="form-group col-md-6">
                    <label for="HomePhone"><?= gettext('Home Phone') ?>:</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa-solid fa-house"></i></span>
                        </div>
                        <input type="text" id="HomePhone" name="HomePhone" value="<?= InputUtils::escapeAttribute($sHomePhone) ?>" maxlength="30" class="form-control" data-inputmask='"mask": "<?= SystemConfig::getValue('sPhoneFormat') ?>"' data-mask>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="NoFormat_HomePhone" value="1" <?= $bNoFormat_HomePhone ? 'checked' : '' ?>>
                                <label class="mb-0 ml-1 small"><?= gettext('No format') ?></label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group col-md-6">
                    <label for="WorkPhone"><?= gettext('Work Phone') ?>:</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa-solid fa-briefcase"></i></span>
                        </div>
                        <input type="text" id="WorkPhone" name="WorkPhone" value="<?= InputUtils::escapeAttribute($sWorkPhone) ?>" maxlength="30" class="form-control" data-inputmask='"mask": "<?= SystemConfig::getValue('sPhoneFormatWithExt') ?>"' data-mask>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="NoFormat_WorkPhone" value="1" <?= $bNoFormat_WorkPhone ? 'checked' : '' ?>>
                                <label class="mb-0 ml-1 small"><?= gettext('No format') ?></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (!SystemConfig::getValue('bHideFamilyNewsletter')) { /* Newsletter can be hidden - General Settings */ ?>
            <div class="row">
                <div class="form-group col-md-6">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="SendNewsLetter" name="SendNewsLetter" value="1" <?= $bSendNewsLetter ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="SendNewsLetter"><?= gettext('Send Newsletter') ?></label>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
    <?php if (SystemConfig::getValue('bUseDonationEnvelopes')) { /* Donation envelopes can be hidden - General Settings */ ?>
        <div class="card card-info clearfix">
            <div class="card-header">
                <h3 class="card-title"><?= gettext('Envelope Info') ?></h3>
            </div><!-- /.box-header -->
            <div class="card-body">
                <div class="row">
                    <div class="form-group col-md-4">
                        <label for="Envelope"><?= gettext('Envelope Number') ?>:</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa-solid fa-envelope-open-text"></i></span>
                            </div>
                            <input type="text" id="Envelope" name="Envelope" class="form-control" value="<?= $fam_Envelope ?? '' ?>" maxlength="50">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    if ($numCustomFields > 0) {
        ?>
        <div class="card card-info clearfix">
            <div class="card-header">
                <h3 class="card-title"><?= gettext('Custom Fields') ?></h3>
            </div><!-- /.box-header -->
            <div class="card-body">
                <?php mysqli_data_seek($rsCustomFields, 0);
                while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH)) {
                    extract($rowCustomField);
                    if (AuthenticationManager::getCurrentUser()->isEnabledSecurity($aSecurityType[$fam_custom_FieldSec])) {
                        ?>
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="<?= $fam_custom_Field ?>"><?= $fam_custom_Name ?></label>
                        <?php $currentFieldData = trim($aCustomData[$fam_custom_Field]);

                        if ($type_ID == 11) {
                            $fam_custom_Special = $sCountry;
                        }

                        formCustomField($type_ID, $fam_custom_Field, $currentFieldData, $fam_custom_Special, !isset($_POST['FamilySubmit']));
                        if (!empty($aCustomErrors[$fam_custom_Field])) {
                            echo '<span class="text-danger small">' . $aCustomErrors[$fam_custom_Field] . '</span>';
                        }
                        echo '</div></div>';
                    }
                } ?>
            </div>
        </div>
    <?php
    } ?>
    <div class="card card-info clearfix">
        <div class="card-header">
            <h3 class="card-title"><?= gettext('Family Members') ?></h3>
        </div><!-- /.box-header -->
        <div class="card-body">

            <?php if ($iFamilyMemberRows > 0) {
                ?>

                                <?php if ($iFamilyID < 0) { ?>
                                <div class="alert alert-info mb-3">
                                    <i class="fa-solid fa-info-circle"></i> <?= gettext('You may create family members now or add them later. All entries will become new person records.') ?>
                                </div>
                                <?php } ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="thead-light">
                                            <tr class="text-center">
                                                <th><?= gettext('First') ?></th>
                                                <th><?= gettext('Middle') ?></th>
                                                <th><?= gettext('Last') ?></th>
                                                <th><?= gettext('Suffix') ?></th>
                                                <th><?= gettext('Gender') ?></th>
                                                <th><?= gettext('Role') ?></th>
                                                <th><?= gettext('Birth Month') ?></th>
                                                <th><?= gettext('Birth Day') ?></th>
                                                <th><?= gettext('Birth Year') ?></th>
                                                <th><?= gettext('Classification') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="familyMembersTbody">
                                        <?php

                                        //Get family roles using Propel ORM
                                        $familyRoles = ListOptionQuery::create()
                                            ->filterById(2)
                                            ->orderByOptionSequence()
                                            ->find();
                                        $numFamilyRoles = $familyRoles->count();
                                        $c = 1;
                                        foreach ($familyRoles as $role) {
                                            $aFamilyRoleNames[$c] = $role->getOptionName();
                                            $aFamilyRoleIDs[$c] = $role->getOptionId();
                                            $c++;
                                        }

                                        for ($iCount = 1; $iCount <= $iFamilyMemberRows; $iCount++) {
                                            ?>
                                            <tr>
                                                <td>
                                                    <input type="hidden" name="PersonID<?= $iCount ?>" value="<?= $aPersonIDs[$iCount] ?>">
                                                    <input name="FirstName<?= $iCount ?>" type="text" value="<?= $aFirstNames[$iCount] ?>" class="form-control form-control-sm">
                                                    <?php if (array_key_exists($iCount, $aFirstNameError)) { ?>
                                                    <span class="text-danger small"><?= $aFirstNameError[$iCount] ?></span>
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <input name="MiddleName<?= $iCount ?>" type="text" value="<?= $aMiddleNames[$iCount] ?>" class="form-control form-control-sm">
                                                </td>
                                                <td>
                                                    <input name="LastName<?= $iCount ?>" type="text" value="<?= $aLastNames[$iCount] ?>" class="form-control form-control-sm">
                                                </td>
                                                <td>
                                                    <input name="Suffix<?= $iCount ?>" type="text" value="<?= $aSuffix[$iCount] ?>" class="form-control form-control-sm" style="width: 60px;">
                                                </td>
                                                <td>
                                                    <select name="Gender<?= $iCount ?>" class="form-control form-control-sm">
                                                        <option value="0" <?= $aGenders[$iCount] == 0 ? 'selected' : '' ?>><?= gettext('Select Gender') ?></option>
                                                        <option value="1" <?= $aGenders[$iCount] == 1 ? 'selected' : '' ?>><?= gettext('Male') ?></option>
                                                        <option value="2" <?= $aGenders[$iCount] == 2 ? 'selected' : '' ?>><?= gettext('Female') ?></option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="Role<?= $iCount ?>" class="form-control form-control-sm">
                                                        <option value="0" <?= $aRoles[$iCount] == 0 ? 'selected' : '' ?>><?= gettext('Select Role') ?></option>
                                                        <?php
                                                        //Build the role select box
                                                        for ($c = 1; $c <= $numFamilyRoles; $c++) {
                                                            echo '<option value="' . $aFamilyRoleIDs[$c] . '"';
                                                            if ($aRoles[$iCount] == $aFamilyRoleIDs[$c]) {
                                                                echo ' selected';
                                                            }
                                                            echo '>' . $aFamilyRoleNames[$c] . '</option>';
                                                        } ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="BirthMonth<?= $iCount ?>" class="form-control form-control-sm">
                                                        <option value="0" <?= $aBirthMonths[$iCount] == 0 ? 'selected' : '' ?>><?= gettext('Unknown') ?></option>
                                                        <option value="01" <?= $aBirthMonths[$iCount] == 1 ? 'selected' : '' ?>><?= gettext('January') ?></option>
                                                        <option value="02" <?= $aBirthMonths[$iCount] == 2 ? 'selected' : '' ?>><?= gettext('February') ?></option>
                                                        <option value="03" <?= $aBirthMonths[$iCount] == 3 ? 'selected' : '' ?>><?= gettext('March') ?></option>
                                                        <option value="04" <?= $aBirthMonths[$iCount] == 4 ? 'selected' : '' ?>><?= gettext('April') ?></option>
                                                        <option value="05" <?= $aBirthMonths[$iCount] == 5 ? 'selected' : '' ?>><?= gettext('May') ?></option>
                                                        <option value="06" <?= $aBirthMonths[$iCount] == 6 ? 'selected' : '' ?>><?= gettext('June') ?></option>
                                                        <option value="07" <?= $aBirthMonths[$iCount] == 7 ? 'selected' : '' ?>><?= gettext('July') ?></option>
                                                        <option value="08" <?= $aBirthMonths[$iCount] == 8 ? 'selected' : '' ?>><?= gettext('August') ?></option>
                                                        <option value="09" <?= $aBirthMonths[$iCount] == 9 ? 'selected' : '' ?>><?= gettext('September') ?></option>
                                                        <option value="10" <?= $aBirthMonths[$iCount] == 10 ? 'selected' : '' ?>><?= gettext('October') ?></option>
                                                        <option value="11" <?= $aBirthMonths[$iCount] == 11 ? 'selected' : '' ?>><?= gettext('November') ?></option>
                                                        <option value="12" <?= $aBirthMonths[$iCount] == 12 ? 'selected' : '' ?>><?= gettext('December') ?></option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="BirthDay<?= $iCount ?>" class="form-control form-control-sm">
                                                        <option value="0"><?= gettext('Unk') ?></option>
                                                        <?php for ($x = 1; $x < 32; $x++) {
                                                            $sDay = $x < 10 ? '0' . $x : $x;
                                                            ?>
                                                            <option value="<?= $sDay ?>" <?= $aBirthDays[$iCount] == $x ? 'selected' : '' ?>><?= $x ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <?php if (!array_key_exists($iCount, $aperFlags) || !$aperFlags[$iCount]) {
                                                        $UpdateBirthYear = 1; ?>
                                                        <input name="BirthYear<?= $iCount ?>" type="text" value="<?= $aBirthYears[$iCount] ?>" class="form-control form-control-sm" style="width: 70px;" maxlength="4">
                                                        <?php if (array_key_exists($iCount, $aBirthDateError)) { ?>
                                                        <span class="text-danger small"><?= $aBirthDateError[$iCount] ?></span>
                                                        <?php }
                                                    } else {
                                                        $UpdateBirthYear = 0;
                                                    } ?>
                                                </td>
                                                <td>
                                                    <select name="Classification<?= $iCount ?>" class="form-control form-control-sm">
                                                        <option value="0" <?= $aClassification[$iCount] == 0 ? 'selected' : '' ?>><?= gettext('Unassigned') ?></option>
                                                        <option value="" disabled>-----------------------</option>
                                                        <?php
                                                        //Get Classifications for the drop-down
                                                        $sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence';
                                                        $rsClassifications = RunQuery($sSQL);

                                                        //Display Classifications
                                                        while ($aRow = mysqli_fetch_array($rsClassifications)) {
                                                            extract($aRow);
                                                            echo '<option value="' . $lst_OptionID . '"';
                                                            if ($aClassification[$iCount] == $lst_OptionID) {
                                                                echo ' selected';
                                                            }
                                                            echo '>' . $lst_OptionName . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if ($iFamilyID < 0) { ?>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="addFamilyMemberRow">
                                        <i class="fa-solid fa-plus"></i> <?= gettext('Add Another Family Member') ?>
                                    </button>
                                </div>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </div>

                    <input type="hidden" Name="UpdateBirthYear" value="<?= $UpdateBirthYear ?>">

                    <!-- Hidden submit buttons for FAB -->
                    <div class="d-none">
                        <input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" Name="FamilySubmit" id="FamilySubmitBottom">
                        <?php if (AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()) { ?>
                        <input type="submit" class="btn btn-info" value="<?= gettext('Save and Add New Family') ?>" name="FamilySubmitAndAdd" id="FamilySubmitAndAddButton">
                        <?php } ?>
                    </div>
                </form>

<!-- FAB Container -->
<div id="fab-family-editor" class="fab-container fab-family-editor">
    <?php if ($iFamilyID > 0) { ?>
    <a href="<?= SystemURLs::getRootPath() ?>/v2/family/<?= $iFamilyID ?>" class="fab-button fab-cancel" aria-label="<?= gettext('Cancel') ?>">
        <span class="fab-label"><?= gettext('Cancel') ?></span>
        <div class="fab-icon">
            <i class="fa-solid fa-xmark"></i>
        </div>
    </a>
    <?php } else { ?>
    <a href="<?= SystemURLs::getRootPath() ?>/v2/family" class="fab-button fab-cancel" aria-label="<?= gettext('Cancel') ?>">
        <span class="fab-label"><?= gettext('Cancel') ?></span>
        <div class="fab-icon">
            <i class="fa-solid fa-xmark"></i>
        </div>
    </a>
    <?php } ?>
    <?php if (AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()) { ?>
    <a href="#" class="fab-button fab-save-add" aria-label="<?= gettext('Save and Add New Family') ?>" onclick="document.getElementById('FamilySubmitAndAddButton').click(); return false;">
        <span class="fab-label"><?= gettext('Save and Add New Family') ?></span>
        <div class="fab-icon">
            <i class="fa-solid fa-users-rectangle"></i>
        </div>
    </a>
    <?php } ?>
    <a href="#" class="fab-button fab-save" aria-label="<?= gettext('Save') ?>" onclick="document.getElementById('FamilySubmitBottom').click(); return false;">
        <span class="fab-label"><?= gettext('Save') ?></span>
        <div class="fab-icon">
            <i class="fa-solid fa-check"></i>
        </div>
    </a>
</div>

<?php if ($iFamilyID < 0) {
    // Get family roles for JavaScript using Propel ORM
    $familyRolesForJS = ListOptionQuery::create()
        ->filterById(2)
        ->orderByOptionSequence()
        ->find();
    $familyRolesJS = [];
    foreach ($familyRolesForJS as $role) {
        $familyRolesJS[] = ['id' => $role->getOptionId(), 'name' => $role->getOptionName()];
    }

    // Get classifications for JavaScript using Propel ORM
    $classificationsForJS = ListOptionQuery::create()
        ->filterById(1)
        ->orderByOptionSequence()
        ->find();
    $classificationsJS = [];
    foreach ($classificationsForJS as $classification) {
        $classificationsJS[] = ['id' => $classification->getOptionId(), 'name' => $classification->getOptionName()];
    }
?>
<script>
    window.CRM.familyRoles = <?= json_encode($familyRolesJS) ?>;
    window.CRM.classifications = <?= json_encode($classificationsJS) ?>;
    window.CRM.initialFamilyMemberCount = <?= $iFamilyMemberRows ?>;
    window.CRM.i18n = {
        selectGender: <?= json_encode(gettext('Select Gender')) ?>,
        male: <?= json_encode(gettext('Male')) ?>,
        female: <?= json_encode(gettext('Female')) ?>,
        selectRole: <?= json_encode(gettext('Select Role')) ?>,
        unknown: <?= json_encode(gettext('Unknown')) ?>,
        unassigned: <?= json_encode(gettext('Unassigned')) ?>,
        months: [
            <?= json_encode(gettext('January')) ?>,
            <?= json_encode(gettext('February')) ?>,
            <?= json_encode(gettext('March')) ?>,
            <?= json_encode(gettext('April')) ?>,
            <?= json_encode(gettext('May')) ?>,
            <?= json_encode(gettext('June')) ?>,
            <?= json_encode(gettext('July')) ?>,
            <?= json_encode(gettext('August')) ?>,
            <?= json_encode(gettext('September')) ?>,
            <?= json_encode(gettext('October')) ?>,
            <?= json_encode(gettext('November')) ?>,
            <?= json_encode(gettext('December')) ?>
        ]
    };
</script>
<?php } ?>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/FamilyEditor.js"></script>
<?php
require_once 'Include/Footer.php';
