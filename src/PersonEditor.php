<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\notifications\NewPersonOrFamilyEmail;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonCustom;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;

$sPageTitle = gettext('Person Editor');

// Get the PersonID out of the querystring
$iPersonID = 0;
if (array_key_exists('PersonID', $_GET)) {
    $iPersonID = (int) InputUtils::legacyFilterInput($_GET['PersonID'], 'int');
}
$isNewPerson = $iPersonID === 0;

$sPreviousPage = '';
if (array_key_exists('previousPage', $_GET)) {
    $sPreviousPage = InputUtils::legacyFilterInput($_GET['previousPage']);
}

$queryParamFamilyId = null;
if (array_key_exists('FamilyID', $_GET)) {
    $queryParamFamilyId = InputUtils::legacyFilterInput($_GET['FamilyID'], 'int');
}

// Security: User must have Add or Edit Records permission to use this form in those manners
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
$person = null;
if (!$isNewPerson) {
    $person = PersonQuery::create()->findOneById($iPersonID);
    if ($person === null) {
        RedirectUtils::redirect('v2/dashboard');
    }

    $per_fam_ID = $person->getFamId();

    if (
        !(
            AuthenticationManager::getCurrentUser()->isEditRecordsEnabled() ||
            (AuthenticationManager::getCurrentUser()->isEditSelfEnabled() && $iPersonID === AuthenticationManager::getCurrentUser()->getId()) ||
            (AuthenticationManager::getCurrentUser()->isEditSelfEnabled() && $per_fam_ID > 0 && $per_fam_ID === AuthenticationManager::getCurrentUser()->getPerson()->getFamId())
        )
    ) {
        RedirectUtils::redirect('v2/dashboard');
    }
} elseif (!AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()) {
    RedirectUtils::redirect('v2/dashboard');
}

// Get Field Security List Matrix
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence';
$rsSecurityGrp = RunQuery($sSQL);

while ($aRow = mysqli_fetch_array($rsSecurityGrp)) {
    extract($aRow);
    $aSecurityType[$lst_OptionID] = $lst_OptionName;
}

// Get the list of custom person fields
$sSQL = 'SELECT person_custom_master.* FROM person_custom_master ORDER BY custom_Order';
$rsCustomFields = RunQuery($sSQL);
$numCustomFields = mysqli_num_rows($rsCustomFields);

//Initialize the error flag
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
$aCustomErrors = [];

$fam_Country = '';

$bNoFormat_HomePhone = false;
$bNoFormat_WorkPhone = false;
$bNoFormat_CellPhone = false;
$sFacebookError = false;
$sTwitterError = false;
$sLinkedInError = false;

//Set defaults as if we are adding a new Person
$sTitle = '';
$sFirstName = '';
$sMiddleName = '';
$sLastName = '';
$sSuffix = '';
$iGender = 0;
$sAddress1 = '';
$sAddress2 = '';
$sCity = SystemConfig::getValue('sDefaultCity');
$sState = SystemConfig::getValue('sDefaultState');
$sZip = SystemConfig::getValue('sDefaultZip');
$sCountry = SystemConfig::getValue('sDefaultCountry');
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
$iClassification = 0;
$iViewAgeFlag = 0;
$sPhoneCountry = '';

$sFacebook = '';
$sTwitter = '';
$sLinkedIn = '';

$sHomePhone = '';
$sWorkPhone = '';
$sCellPhone = '';

//The following values are True booleans if the family record has a value for the
//indicated field.  These are used to highlight field headers in red.
$bFamilyAddress1 = 0;
$bFamilyAddress2 = 0;
$bFamilyCity = 0;
$bFamilyState = 0;
$bFamilyZip = 0;
$bFamilyCountry = 0;
$bFamilyHomePhone = 0;
$bFamilyWorkPhone = 0;
$bFamilyCellPhone = 0;
$bFamilyEmail = 0;
$bHomeBound = false;
$aCustomData = [];

//Is this the second pass?
if (isset($_POST['PersonSubmit']) || isset($_POST['PersonSubmitAndAdd'])) {
    //Get all the variables from the request object and assign them locally
    $sTitle = InputUtils::legacyFilterInput($_POST['Title']);
    $sFirstName = InputUtils::legacyFilterInput($_POST['FirstName']);
    $sMiddleName = InputUtils::legacyFilterInput($_POST['MiddleName']);
    $sLastName = InputUtils::legacyFilterInput($_POST['LastName']);
    $sSuffix = InputUtils::legacyFilterInput($_POST['Suffix']);
    $iGender = InputUtils::legacyFilterInput($_POST['Gender'], 'int');

    // Person address stuff is normally suppressed in favor of family address info
    $sAddress1 = '';
    $sAddress2 = '';
    $sCity = '';
    $sZip = '';
    $sCountry = '';
    if (array_key_exists('Address1', $_POST)) {
        $sAddress1 = InputUtils::legacyFilterInput($_POST['Address1']);
    }
    if (array_key_exists('Address2', $_POST)) {
        $sAddress2 = InputUtils::legacyFilterInput($_POST['Address2']);
    }
    if (array_key_exists('City', $_POST)) {
        $sCity = InputUtils::legacyFilterInput($_POST['City']);
    }
    if (array_key_exists('Zip', $_POST)) {
        $sZip = InputUtils::legacyFilterInput($_POST['Zip']);
    }

    if (SystemConfig::getBooleanValue('bForceUppercaseZip')) {
        $sZip = strtoupper($sZip);
    }

    if (array_key_exists('Country', $_POST)) {
        $sCountry = InputUtils::legacyFilterInput($_POST['Country']);
    }

    $iFamily = InputUtils::legacyFilterInput($_POST['Family'], 'int');
    $iFamilyRole = InputUtils::legacyFilterInput($_POST['FamilyRole'], 'int');
    $family = null;

    // Person data is now authoritative - no family fallback
    // State handling: API determines which countries have states; JS toggles UI visibility
    $sState = '';
    if (array_key_exists('State', $_POST)) {
        $sState = InputUtils::legacyFilterInput($_POST['State']);
    } elseif (array_key_exists('StateTextbox', $_POST)) {
        $sState = InputUtils::legacyFilterInput($_POST['StateTextbox']);
    }

    $sHomePhone = InputUtils::legacyFilterInput($_POST['HomePhone']);
    $sWorkPhone = InputUtils::legacyFilterInput($_POST['WorkPhone']);
    $sCellPhone = InputUtils::legacyFilterInput($_POST['CellPhone']);
    $sEmail = InputUtils::legacyFilterInput($_POST['Email']);
    $sWorkEmail = InputUtils::legacyFilterInput($_POST['WorkEmail']);
    $iBirthMonth = InputUtils::legacyFilterInput($_POST['BirthMonth'], 'int');
    $iBirthDay = InputUtils::legacyFilterInput($_POST['BirthDay'], 'int');
    $iBirthYear = InputUtils::legacyFilterInput($_POST['BirthYear'], 'int');
    $bHideAge = isset($_POST['HideAge']);
    $dFriendDate = InputUtils::filterDate($_POST['FriendDate']);
    $dMembershipDate = InputUtils::filterDate($_POST['MembershipDate']);
    $iClassification = InputUtils::legacyFilterInput($_POST['Classification'], 'int');
    $iEnvelope = 0;
    if (array_key_exists('EnvID', $_POST)) {
        $iEnvelope = InputUtils::legacyFilterInput($_POST['EnvID'], 'int');
    }
    if (array_key_exists('updateBirthYear', $_POST)) {
        $iupdateBirthYear = InputUtils::legacyFilterInput($_POST['updateBirthYear'], 'int');
    }

    $sFacebook = InputUtils::sanitizeText($_POST['Facebook']);
    $sTwitter = InputUtils::sanitizeText($_POST['Twitter']);
    $sLinkedIn = InputUtils::sanitizeText($_POST['LinkedIn']);

    $bNoFormat_HomePhone = isset($_POST['NoFormat_HomePhone']);
    $bNoFormat_WorkPhone = isset($_POST['NoFormat_WorkPhone']);
    $bNoFormat_CellPhone = isset($_POST['NoFormat_CellPhone']);

    //Adjust variables as needed
    if ($iFamily === 0) {
        $iFamilyRole = 0;
    }

    //Validate the Last Name. Last name is always required.
    if (strlen($sLastName) < 1) {
        $sLastNameError = gettext('You must enter a Last Name.');
        $bErrorFlag = true;
    }

    // If they entered a full date, see if it's valid
    if ($iBirthMonth > 0 xor $iBirthDay > 0) {
        $sBirthDateError = gettext('Invalid Birth Date: Missing birth month or day.');
        $bErrorFlag = true;
    } elseif ($iBirthYear > 0 && $iBirthMonth === 0 && $iBirthDay === 0) {
        $sBirthDateError = gettext('Invalid Birth Date: Missing birth month and day.');
        $bErrorFlag = true;
    } elseif (strlen($iBirthYear) > 0) {
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

    // Validate Friend Date if one was entered
    if (strlen($dFriendDate) > 0) {
        $dateString = parseAndValidateDate($dFriendDate, 'US', 'past');
        if ($dateString === false) {
            $sFriendDateError = '<span class="text-danger">'
                . gettext('Not a valid Friend Date') . '</span>';
            $bErrorFlag = true;
        } else {
            $dFriendDate = $dateString;
        }
    }

    // Validate Membership Date if one was entered
    if (strlen($dMembershipDate) > 0) {
        $dMembershipDate = parseAndValidateDate($dMembershipDate, 'US', 'past');
        if ($dMembershipDate === false) {
            $sMembershipDateError = '<span class="text-danger">'
                . gettext('Not a valid Membership Date') . '</span>';
            $bErrorFlag = true;
        }
    }

    // Validate Email
    if (strlen($sEmail) > 0) {
        if (!checkEmail($sEmail)) {
            $sEmailError = '<span class="text-danger">'
                . gettext('Email is Not Valid') . '</span>';
            $bErrorFlag = true;
        }
    }

    // Validate Work Email
    if (strlen($sWorkEmail) > 0) {
        if (!checkEmail($sWorkEmail)) {
            $sWorkEmailError = '<span class="text-danger">'
                . gettext('Work Email is Not Valid') . '</span>';
            $bErrorFlag = true;
        }
    }

    // Validate all the custom fields
    $aCustomData = [];
    while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH)) {
        extract($rowCustomField);

        if (AuthenticationManager::getCurrentUser()->isEnabledSecurity($aSecurityType[$custom_FieldSec])) {
            $currentFieldData = InputUtils::legacyFilterInput($_POST[$custom_Field]);

            $bErrorFlag |= !validateCustomField($type_ID, $currentFieldData, $custom_Field, $aCustomErrors);

            // assign processed value locally to $aPersonProps so we can use it to generate the form later
            $aCustomData[$custom_Field] = $currentFieldData;
        }
    }

    //If no errors, then let's update...
    if (!$bErrorFlag) {
        $sPhoneCountry = $sCountry;

        if (!$bNoFormat_HomePhone) {
            $sHomePhone = CollapsePhoneNumber($sHomePhone, $sPhoneCountry);
        }
        if (!$bNoFormat_WorkPhone) {
            $sWorkPhone = CollapsePhoneNumber($sWorkPhone, $sPhoneCountry);
        }
        if (!$bNoFormat_CellPhone) {
            $sCellPhone = CollapsePhoneNumber($sCellPhone, $sPhoneCountry);
        }

        //If no birth year, set to NULL
        if (strlen($iBirthYear) !== 4) {
            $iBirthYear = null;
        }

        // New Family (add)
        // Family will be named by the Last Name.
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
                ->setWorkPhone($sWorkPhone)
                ->setCellPhone($sCellPhone)
                ->setEmail($sEmail)
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

        // If a person already exists, update the preexisting record.
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
            ->setState($sState)
            ->setZip($sZip)
            ->setCountry($sCountry)
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

        if (AuthenticationManager::getCurrentUser()->isFinanceEnabled()) {
            $person->setEnvelope($iEnvelope);
        }

        $person->save();
        $person->reload();

        $note = new Note();
        $note->setEntered(AuthenticationManager::getCurrentUser()->getId());
        // If this is a new person, get the key back and insert a blank row into the person_custom table
        if (!$personAlreadyExist) {
            $iPersonID = $person->getId();

            $personCustom = new PersonCustom();
            $personCustom->setPerId($iPersonID);
            $personCustom->save();

            $note->setPerId($iPersonID);
            $note->setText(gettext('Created'));
            $note->setType('create');

            if (!empty(SystemConfig::getValue("sNewPersonNotificationRecipientIDs"))) {
                $person = PersonQuery::create()->findOneByID($iPersonID);
                $NotificationEmail = new NewPersonOrFamilyEmail($person);
                if (!$NotificationEmail->send()) {
                    LoggerUtils::getAppLogger()->warning($NotificationEmail->getError());
                }
            }
        } else {
            $note->setPerId($iPersonID);
            $note->setText(gettext('Updated'));
            $note->setType('edit');
        }
        $note->save();

        $photo = new Photo('Person', $iPersonID);
        $photo->refresh();

        // Update the custom person fields.
        if ($numCustomFields > 0) {
            mysqli_data_seek($rsCustomFields, 0);
            $sSQL = '';
            while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH)) {
                extract($rowCustomField);
                if (AuthenticationManager::getCurrentUser()->isEnabledSecurity($aSecurityType[$custom_FieldSec])) {
                    $currentFieldData = trim($aCustomData[$custom_Field]);
                    sqlCustomField($sSQL, $type_ID, $currentFieldData, $custom_Field, $sPhoneCountry);
                }
            }

            // chop off the last 2 characters (comma and space) added in the last while loop iteration.
            if ($sSQL > '') {
                $sSQL = 'REPLACE INTO person_custom SET ' . $sSQL . ' per_ID = ' . $iPersonID;
                //Execute the SQL
                RunQuery($sSQL);
            }
        }

        // Check for redirection to another page after saving information: (ie. PersonEditor.php?previousPage=prev.php?a=1;b=2;c=3)
        if ($sPreviousPage !== '') {
            $sPreviousPage = str_replace(';', '&', $sPreviousPage);
            RedirectUtils::redirect($sPreviousPage . $iPersonID);
        } elseif (isset($_POST['PersonSubmit'])) {
            //Send to the view of this person
            RedirectUtils::redirect('PersonView.php?PersonID=' . $iPersonID);
        } else {
            //Reload to editor to add another record, passing the family ID to pre-select it
            $redirectUrl = 'PersonEditor.php';
            if ($iFamily > 0) {
                $redirectUrl .= '?FamilyID=' . $iFamily;
            }
            RedirectUtils::redirect($redirectUrl);
        }
    }

    // Set the envelope in case the form failed.
    $per_Envelope = $iEnvelope;
} else {
    //FirstPass
    //Are we editing or adding?

    if (!$isNewPerson) {
        //Editing....
        //Get all the data on this record

        $sSQL = 'SELECT * FROM person_per LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_ID = ' . $iPersonID;
        $rsPerson = RunQuery($sSQL);
        extract(mysqli_fetch_array($rsPerson));

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
        $bHideAge = ($per_Flags & 1) != 0;
        $iOriginalFamily = $per_fam_ID;
        $iFamily = (int) $per_fam_ID;
        $iFamilyRole = (int) $per_fmr_ID;
        $dMembershipDate = $per_MembershipDate;
        $dFriendDate = $per_FriendDate;
        $iClassification = (int) $per_cls_ID;
        $iViewAgeFlag = (int) $per_Flags;

        $sFacebook = $per_Facebook;
        $sTwitter = $per_Twitter;
        $sLinkedIn = $per_LinkedIn;

        $sPhoneCountry = $sCountry;

        $sHomePhone = ExpandPhoneNumber($per_HomePhone, $sPhoneCountry, $bNoFormat_HomePhone);
        $sWorkPhone = ExpandPhoneNumber($per_WorkPhone, $sPhoneCountry, $bNoFormat_WorkPhone);
        $sCellPhone = ExpandPhoneNumber($per_CellPhone, $sPhoneCountry, $bNoFormat_CellPhone);

        //The following values are True booleans if the family record has a value for the
        //indicated field.  These are used to highlight field headers in red.
        $bFamilyAddress1 = strlen($fam_Address1);
        $bFamilyAddress2 = strlen($fam_Address2);
        $bFamilyCity = strlen($fam_City);
        $bFamilyState = strlen($fam_State);
        $bFamilyZip = strlen($fam_Zip);
        $bFamilyCountry = strlen($fam_Country);
        $bFamilyHomePhone = strlen($fam_HomePhone);
        $bFamilyEmail = strlen($fam_Email);

        $bFacebook = strlen($per_Facebook);
        $bTwitter = strlen($per_Twitter);
        $bLinkedIn = strlen($per_LinkedIn);

        $sSQL = 'SELECT * FROM person_custom WHERE per_ID = ' . $iPersonID;
        $rsCustomData = RunQuery($sSQL);
        $aCustomData = [];
        if (mysqli_num_rows($rsCustomData) >= 1) {
            $aCustomData = mysqli_fetch_array($rsCustomData, MYSQLI_BOTH);
        }
    }
}

//Get Classifications for the drop-down
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence';
$rsClassifications = RunQuery($sSQL);

//Get Families for the drop-down
$sSQL = 'SELECT * FROM family_fam ORDER BY fam_Name';
$rsFamilies = RunQuery($sSQL);

//Get Family Roles for the drop-down
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 2 ORDER BY lst_OptionSequence';
$rsFamilyRoles = RunQuery($sSQL);

require_once __DIR__ . '/Include/Header.php';

?>
<form method="post" action="PersonEditor.php?PersonID=<?= $iPersonID ?>" name="PersonEditor">
    <?php if ($bErrorFlag) {
        ?>
        <div class="alert alert-danger alert-dismissable">
            <i class="fa-solid fa-ban"></i>
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <?= gettext('Invalid fields or selections. Changes not saved! Please correct and try again!') ?>
        </div>
        <?php
    } ?>
    <!-- Card 1: Name & Identity -->
    <div class="card card-info clearfix">
        <div class="card-header">
            <h3 class="card-title"><?= gettext('Name & Identity') ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="form-group col-md-2">
                    <label for="Title"><?= gettext('Title') ?>:</label>
                    <input type="text" name="Title" id="Title"
                           value="<?= InputUtils::escapeAttribute(stripslashes($sTitle)) ?>"
                           class="form-control" placeholder="<?= gettext('Mr., Mrs., Dr.') ?>">
                </div>
                <div class="form-group col-md-3">
                    <label for="FirstName"><?= gettext('First Name') ?>:</label>
                    <input type="text" name="FirstName" id="FirstName"
                           value="<?= InputUtils::escapeAttribute(stripslashes($sFirstName)) ?>"
                           class="form-control">
                    <?php if ($sFirstNameError) { ?>
                        <span class="text-danger small"><?= $sFirstNameError ?></span>
                    <?php } ?>
                </div>
                <div class="form-group col-md-2">
                    <label for="MiddleName"><?= gettext('Middle') ?>:</label>
                    <input type="text" name="MiddleName" id="MiddleName"
                           value="<?= InputUtils::escapeAttribute(stripslashes($sMiddleName)) ?>"
                           class="form-control">
                    <?php if ($sMiddleNameError) { ?>
                        <span class="text-danger small"><?= $sMiddleNameError ?></span>
                    <?php } ?>
                </div>
                <div class="form-group col-md-3">
                    <label for="LastName"><?= gettext('Last Name') ?>:</label>
                    <input type="text" name="LastName" id="LastName"
                           value="<?= InputUtils::escapeAttribute(stripslashes($sLastName)) ?>"
                           class="form-control">
                    <?php if ($sLastNameError) { ?>
                        <span class="text-danger small"><?= $sLastNameError ?></span>
                    <?php } ?>
                </div>
                <div class="form-group col-md-1">
                    <label for="Suffix"><?= gettext('Suffix') ?>:</label>
                    <input type="text" name="Suffix" id="Suffix"
                           value="<?= InputUtils::escapeAttribute(stripslashes($sSuffix)) ?>"
                           placeholder="<?= gettext('Jr., Sr.') ?>" class="form-control">
                </div>
                <div class="form-group col-md-1">
                    <label for="Gender"><?= gettext('Gender') ?>:</label>
                    <select id="Gender" name="Gender" class="form-control">
                        <option value="0">-</option>
                        <option value="1" <?= $iGender === 1 ? 'selected' : '' ?>><?= gettext('M') ?></option>
                        <option value="2" <?= $iGender === 2 ? 'selected' : '' ?>><?= gettext('F') ?></option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Birth & Family -->
    <div class="card card-info clearfix">
        <div class="card-header">
            <h3 class="card-title"><?= gettext('Birth & Family') ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="form-group col-md-2">
                    <label for="BirthMonth"><?= gettext('Birth Month') ?>:</label>
                    <select id="BirthMonth" name="BirthMonth" class="form-control">
                        <option value="0" <?= $iBirthMonth === 0 ? 'selected' : '' ?>>-</option>
                        <option value="01" <?= $iBirthMonth === 1 ? 'selected' : '' ?>><?= gettext('Jan') ?></option>
                        <option value="02" <?= $iBirthMonth === 2 ? 'selected' : '' ?>><?= gettext('Feb') ?></option>
                        <option value="03" <?= $iBirthMonth === 3 ? 'selected' : '' ?>><?= gettext('Mar') ?></option>
                        <option value="04" <?= $iBirthMonth === 4 ? 'selected' : '' ?>><?= gettext('Apr') ?></option>
                        <option value="05" <?= $iBirthMonth === 5 ? 'selected' : '' ?>><?= gettext('May') ?></option>
                        <option value="06" <?= $iBirthMonth === 6 ? 'selected' : '' ?>><?= gettext('Jun') ?></option>
                        <option value="07" <?= $iBirthMonth === 7 ? 'selected' : '' ?>><?= gettext('Jul') ?></option>
                        <option value="08" <?= $iBirthMonth === 8 ? 'selected' : '' ?>><?= gettext('Aug') ?></option>
                        <option value="09" <?= $iBirthMonth === 9 ? 'selected' : '' ?>><?= gettext('Sep') ?></option>
                        <option value="10" <?= $iBirthMonth === 10 ? 'selected' : '' ?>><?= gettext('Oct') ?></option>
                        <option value="11" <?= $iBirthMonth === 11 ? 'selected' : '' ?>><?= gettext('Nov') ?></option>
                        <option value="12" <?= $iBirthMonth === 12 ? 'selected' : '' ?>><?= gettext('Dec') ?></option>
                    </select>
                </div>
                <div class="form-group col-md-1">
                    <label for="BirthDay"><?= gettext('Day') ?>:</label>
                    <select id="BirthDay" name="BirthDay" class="form-control">
                        <option value="0">-</option>
                        <?php for ($x = 1; $x < 32; $x++) {
                            $sDay = $x < 10 ? '0' . $x : $x; ?>
                            <option value="<?= $sDay ?>" <?= $iBirthDay === $x ? 'selected' : '' ?>><?= $x ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group col-md-1">
                    <label for="BirthYear"><?= gettext('Year') ?>:</label>
                    <input type="text" id="BirthYear" name="BirthYear" value="<?= $iBirthYear ?>"
                           maxlength="4" placeholder="YYYY" class="form-control">
                    <?php if ($sBirthYearError) { ?>
                        <span class="text-danger small"><?= $sBirthYearError ?></span>
                    <?php } ?>
                    <?php if ($sBirthDateError) { ?>
                        <span class="text-danger small"><?= $sBirthDateError ?></span>
                    <?php } ?>
                </div>
                <div class="form-group col-md-1">
                    <label for="HideAge"><?= gettext('Hide Age') ?></label>
                    <div class="custom-control custom-checkbox mt-2">
                        <input type="checkbox" class="custom-control-input" id="HideAge" name="HideAge" value="1" <?= $bHideAge ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="HideAge">&nbsp;</label>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <label for="familyId"><?= gettext('Family') ?>:</label>
                    <select name="Family" id="familyId" class="form-control">
                        <option value="0" selected><?= gettext('Unassigned') ?></option>
                        <option value="-1"><?= gettext('Create a new family (using last name)') ?></option>
                        <option value="" disabled>-----------------------</option>
                        <?php while ($aRow = mysqli_fetch_array($rsFamilies)) {
                            extract($aRow);
                            $fam_ID = (int)$fam_ID;
                            echo '<option value="' . $fam_ID . '"';
                            if ($iFamily === $fam_ID || $queryParamFamilyId === $fam_ID) {
                                echo ' selected';
                            }
                            echo '>' . InputUtils::escapeHTML($fam_Name) . '&nbsp;' . InputUtils::escapeHTML(FormatAddressLine($fam_Address1, $fam_City, $fam_State));
                        } ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="FamilyRole"><?= gettext('Family Role') ?>:</label>
                    <select name="FamilyRole" id="FamilyRole" class="form-control">
                        <option value="0"><?= gettext('Unassigned') ?></option>
                        <option value="" disabled>-----------------------</option>
                        <?php while ($aRow = mysqli_fetch_array($rsFamilyRoles)) {
                            extract($aRow);
                            echo '<option value="' . $lst_OptionID . '"';
                            if ($iFamilyRole == $lst_OptionID) {
                                echo ' selected';
                            }
                            echo '>' . $lst_OptionName . '&nbsp;';
                        } ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Address -->
    <?php if (!SystemConfig::getValue('bHidePersonAddress') && $iFamily === 0) { /* Only show address for unaffiliated persons - General Settings */ ?>
    <div class="card card-info clearfix">
        <div class="card-header">
            <h3 class="card-title"><?= gettext('Address') ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="form-group col-md-6">
                    <label for="Address1">
                        <?= $bFamilyAddress1 ? '<span class="text-danger">' : '' ?>
                        <?= gettext('Address') ?> 1:
                        <?= $bFamilyAddress1 ? '</span>' : '' ?>
                    </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa-solid fa-location-dot"></i></span>
                        </div>
                        <input type="text" name="Address1" id="Address1"
                               value="<?= InputUtils::escapeAttribute(stripslashes($sAddress1)) ?>"
                               maxlength="250" class="form-control">
                    </div>
                </div>
                <div class="form-group col-md-6">
                    <label for="Address2">
                        <?= $bFamilyAddress2 ? '<span class="text-danger">' : '' ?>
                        <?= gettext('Address') ?> 2:
                        <?= $bFamilyAddress2 ? '</span>' : '' ?>
                    </label>
                    <input type="text" name="Address2" id="Address2"
                           value="<?= InputUtils::escapeAttribute(stripslashes($sAddress2)) ?>"
                           maxlength="250" class="form-control">
                </div>
            </div>
            <div class="row">
                <div class="form-group col-md-4">
                    <label for="City">
                        <?= $bFamilyCity ? '<span class="text-danger">' : '' ?>
                        <?= gettext('City') ?>:
                        <?= $bFamilyCity ? '</span>' : '' ?>
                    </label>
                    <input type="text" name="City" id="City"
                           value="<?= InputUtils::escapeAttribute(stripslashes($sCity)) ?>"
                           class="form-control">
                </div>
                <div id="stateOptionDiv" class="form-group col-md-3">
                    <label for="State">
                        <?= $bFamilyState ? '<span class="text-danger">' : '' ?>
                        <?= gettext('State') ?>:
                        <?= $bFamilyState ? '</span>' : '' ?>
                    </label>
                    <select id="State" name="State" class="form-control select2" data-user-selected="<?= InputUtils::escapeAttribute($sState) ?>" data-system-default="<?= SystemConfig::getValue('sDefaultState') ?>">
                    </select>
                </div>
                <div id="stateInputDiv" class="form-group col-md-3 d-none">
                    <label for="StateTextbox"><?= gettext('State (Other)') ?>:</label>
                    <input type="text" name="StateTextbox" id="StateTextbox"
                           value="<?= InputUtils::escapeAttribute(stripslashes($sState)) ?>"
                           maxlength="30" class="form-control">
                </div>
                <div class="form-group col-md-2">
                    <label for="Zip">
                        <?= $bFamilyZip ? '<span class="text-danger">' : '' ?>
                        <?= gettext('Zip / Postal Code') ?>:
                        <?= $bFamilyZip ? '</span>' : '' ?>
                    </label>
                    <input type="text" name="Zip" id="Zip" class="form-control"
                           <?= SystemConfig::getBooleanValue('bForceUppercaseZip') ? 'style="text-transform:uppercase"' : '' ?>
                           value="<?= InputUtils::escapeAttribute(stripslashes($sZip)) ?>"
                           maxlength="10">
                </div>
                <div class="form-group col-md-3">
                    <label for="Country">
                        <?= $bFamilyCountry ? '<span class="text-danger">' : '' ?>
                        <?= gettext('Country') ?>:
                        <?= $bFamilyCountry ? '</span>' : '' ?>
                    </label>
                    <select id="Country" name="Country" class="form-control select2" data-user-selected="<?= InputUtils::escapeAttribute($sCountry) ?>" data-system-default="<?= SystemConfig::getValue('sDefaultCountry') ?>">
                    </select>
                </div>
            </div>
        </div>
    </div>
    <?php } else { // Hidden fields when address is hidden ?>
        <input type="hidden" name="Address1" value="<?= InputUtils::escapeAttribute(stripslashes($sAddress1)) ?>">
        <input type="hidden" name="Address2" value="<?= InputUtils::escapeAttribute(stripslashes($sAddress2)) ?>">
        <input type="hidden" name="City" value="<?= InputUtils::escapeAttribute(stripslashes($sCity)) ?>">
        <input type="hidden" name="State" value="<?= InputUtils::escapeAttribute(stripslashes($sState)) ?>">
        <input type="hidden" name="StateTextbox" value="<?= InputUtils::escapeAttribute(stripslashes($sState)) ?>">
        <input type="hidden" name="Zip" value="<?= InputUtils::escapeAttribute(stripslashes($sZip)) ?>">
        <input type="hidden" name="Country" value="<?= InputUtils::escapeAttribute(stripslashes($sCountry)) ?>">
    <?php } ?>

    <!-- Card 3: Contact Information -->
    <div class="card card-info clearfix">
        <div class="card-header">
            <h3 class="card-title"><?= gettext('Contact Information') ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Phones Column -->
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="HomePhone">
                            <?php
                            if ($bFamilyHomePhone) {
                                echo '<span class="text-danger">' . gettext('Home Phone') . ':</span>';
                            } else {
                                echo gettext('Home Phone') . ':';
                            }
                            ?>
                        </label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa-solid fa-house"></i></span>
                            </div>
                            <input type="tel" name="HomePhone" id="HomePhone"
                                   value="<?= InputUtils::escapeAttribute(stripslashes($sHomePhone)) ?>"
                                   maxlength="30" class="form-control"
                                   data-inputmask='"mask": "<?= SystemConfig::getValue('sPhoneFormat') ?>"' data-mask>
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <div class="custom-control custom-checkbox mb-0">
                                        <input type="checkbox" class="custom-control-input" id="NoFormat_HomePhone" name="NoFormat_HomePhone" value="1" <?= $bNoFormat_HomePhone ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="NoFormat_HomePhone"><?= gettext('No format') ?></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="CellPhone">
                            <?php
                            if ($bFamilyCellPhone) {
                                echo '<span class="text-danger">' . gettext('Mobile Phone') . ':</span>';
                            } else {
                                echo gettext('Mobile Phone') . ':';
                            }
                            ?>
                        </label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa-solid fa-mobile-screen"></i></span>
                            </div>
                            <input type="tel" name="CellPhone" id="CellPhone"
                                   value="<?= InputUtils::escapeAttribute(stripslashes($sCellPhone)) ?>"
                                   maxlength="30" class="form-control"
                                   data-inputmask='"mask": "<?= SystemConfig::getValue('sPhoneFormatCell') ?>"' data-mask>
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <div class="custom-control custom-checkbox mb-0">
                                        <input type="checkbox" class="custom-control-input" id="NoFormat_CellPhone" name="NoFormat_CellPhone" value="1" <?= $bNoFormat_CellPhone ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="NoFormat_CellPhone"><?= gettext('No format') ?></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="WorkPhone">
                            <?php
                            if ($bFamilyWorkPhone) {
                                echo '<span class="text-danger">' . gettext('Work Phone') . ':</span>';
                            } else {
                                echo gettext('Work Phone') . ':';
                            }
                            ?>
                        </label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa-solid fa-building"></i></span>
                            </div>
                            <input type="tel" name="WorkPhone" id="WorkPhone"
                                   value="<?= InputUtils::escapeAttribute(stripslashes($sWorkPhone)) ?>"
                                   maxlength="30" class="form-control"
                                   data-inputmask='"mask": "<?= SystemConfig::getValue('sPhoneFormatWithExt') ?>"' data-mask>
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <div class="custom-control custom-checkbox mb-0">
                                        <input type="checkbox" class="custom-control-input" id="NoFormat_WorkPhone" name="NoFormat_WorkPhone" value="1" <?= $bNoFormat_WorkPhone ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="NoFormat_WorkPhone"><?= gettext('No format') ?></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Emails Column -->
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="Email">
                            <?php
                            if ($bFamilyEmail) {
                                echo '<span class="text-danger">' . gettext('Email') . ':</span>';
                            } else {
                                echo gettext('Email') . ':';
                            }
                            ?>
                        </label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa-solid fa-at"></i></span>
                            </div>
                            <input type="email" name="Email" id="Email"
                                   value="<?= InputUtils::escapeAttribute(stripslashes($sEmail)) ?>"
                                   maxlength="100" class="form-control">
                        </div>
                        <?php if ($sEmailError) { ?>
                            <span class="text-danger small"><?= $sEmailError ?></span>
                        <?php } ?>
                    </div>
                    <div class="form-group">
                        <label for="WorkEmail"><?= gettext('Work / Other Email') ?>:</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa-solid fa-briefcase"></i></span>
                            </div>
                            <input type="email" name="WorkEmail" id="WorkEmail"
                                   value="<?= InputUtils::escapeAttribute($sWorkEmail) ?>"
                                   maxlength="100" class="form-control">
                        </div>
                        <?php if ($sWorkEmailError) { ?>
                            <span class="text-danger small"><?= $sWorkEmailError ?></span>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 4: Social Media -->
    <div class="card card-info clearfix">
        <div class="card-header">
            <h3 class="card-title"><?= gettext('Social Media') ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="form-group col-md-4">
                    <label for="Facebook">Facebook:</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa-brands fa-facebook"></i></span>
                        </div>
                        <input type="text" name="Facebook" id="Facebook"
                               value="<?= InputUtils::escapeAttribute($sFacebook) ?>"
                               maxlength="50" class="form-control">
                    </div>
                    <?php if ($sFacebookError) { ?>
                        <span class="text-danger small"><?= $sFacebookError ?></span>
                    <?php } ?>
                </div>
                <div class="form-group col-md-4">
                    <label for="Twitter">X:</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa-brands fa-x-twitter"></i></span>
                        </div>
                        <input type="text" name="Twitter" id="Twitter"
                               value="<?= InputUtils::escapeAttribute($sTwitter) ?>"
                               maxlength="50" class="form-control">
                    </div>
                    <?php if ($sTwitterError) { ?>
                        <span class="text-danger small"><?= $sTwitterError ?></span>
                    <?php } ?>
                </div>
                <div class="form-group col-md-4">
                    <label for="LinkedIn">LinkedIn:</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa-brands fa-linkedin"></i></span>
                        </div>
                        <input type="text" name="LinkedIn" id="LinkedIn"
                               value="<?= InputUtils::escapeAttribute($sLinkedIn) ?>"
                               maxlength="50" class="form-control">
                    </div>
                    <?php if ($sLinkedInError) { ?>
                        <span class="text-danger small"><?= $sLinkedInError ?></span>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 5: Church Membership -->
    <div class="card card-info clearfix">
        <div class="card-header">
            <h3 class="card-title"><?= gettext('Church Membership') ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="form-group col-md-3">
                    <label for="Classification"><?= gettext('Classification') ?>:</label>
                    <select id="Classification" name="Classification" class="form-control">
                        <option value="0"><?= gettext('Unassigned') ?></option>
                        <option value="" disabled>-----------------------</option>
                        <?php while ($aRow = mysqli_fetch_array($rsClassifications)) {
                            extract($aRow);
                            echo '<option value="' . $lst_OptionID . '"';
                            if ($iClassification == $lst_OptionID) {
                                echo ' selected';
                            }
                            echo '>' . $lst_OptionName . '&nbsp;';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="MembershipDate"><?= gettext('Membership Date') ?>:</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa-solid fa-calendar"></i></span>
                        </div>
                        <input type="text" name="MembershipDate" id="MembershipDate" class="form-control date-picker"
                               value="<?= change_date_for_place_holder($dMembershipDate) ?>" maxlength="10"
                               placeholder="<?= SystemConfig::getValue("sDatePickerFormat") ?>">
                    </div>
                    <?php if ($sMembershipDateError) { ?>
                        <span class="text-danger small"><?= $sMembershipDateError ?></span>
                    <?php } ?>
                </div>
                <?php if (!SystemConfig::getBooleanValue('bHideFriendDate')) { ?>
                <div class="form-group col-md-3">
                    <label for="FriendDate"><?= gettext('Friend Date') ?>:</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa-solid fa-handshake"></i></span>
                        </div>
                        <input type="text" name="FriendDate" id="FriendDate" class="form-control date-picker"
                               value="<?= change_date_for_place_holder($dFriendDate) ?>" maxlength="10"
                               placeholder="<?= SystemConfig::getValue("sDatePickerFormat") ?>">
                    </div>
                    <?php if ($sFriendDateError) { ?>
                        <span class="text-danger small"><?= $sFriendDateError ?></span>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <?php if ($numCustomFields > 0) { ?>
        <div class="card card-info clearfix">
            <div class="card-header">
                <h3 class="card-title"><?= gettext('Custom Fields') ?></h3>
            </div><!-- /.box-header -->
            <div class="card-body">
                <?php
                    mysqli_data_seek($rsCustomFields, 0);
                    while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH)) {
                        extract($rowCustomField);
                        if (AuthenticationManager::getCurrentUser()->isEnabledSecurity($aSecurityType[$custom_FieldSec])) {
                            echo '<div class="row"><div class="form-group col-md-6"><label for="' . $custom_Field . '">' . $custom_Name . '</label>';

                            if (array_key_exists($custom_Field, $aCustomData)) {
                                $currentFieldData = trim($aCustomData[$custom_Field]);
                            } else {
                                $currentFieldData = '';
                            }

                            if ($type_ID == 11) {
                                $custom_Special = $sPhoneCountry;
                            }

                            formCustomField($type_ID, $custom_Field, $currentFieldData, $custom_Special, !isset($_POST['PersonSubmit']));
                            if (isset($aCustomErrors[$custom_Field]) && !empty($aCustomErrors[$custom_Field])) {
                                echo '<span class="text-danger small">' . $aCustomErrors[$custom_Field] . '</span>';
                            }
                            echo '</div></div>';
                        }
                    }
                ?>
            </div>
        </div>
    <?php } ?>
    <!-- Hidden submit buttons for form submission -->
    <div style="display: none;">
        <input type="submit" class="btn btn-primary" id="PersonSaveButton" value="<?= gettext('Save') ?>"
               name="PersonSubmit">
        <?php if (AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()) {
            echo '<input type="submit" class="btn btn-primary" id="PersonSaveAndAddButton" value="' . gettext('Save and Add') . '" name="PersonSubmitAndAdd">';
        } ?>
    </div>
</form>

<!-- FAB Container -->
<div id="fab-person-editor" class="fab-container fab-person-editor">
    <?php if ($iPersonID > 0) { ?>
    <a href="PersonView.php?PersonID=<?= $iPersonID ?>" class="fab-button fab-cancel" title="<?= gettext('Cancel') ?>">
        <span class="fab-label"><?= gettext('Cancel') ?></span>
        <div class="fab-icon">
            <i class="fa-solid fa-xmark"></i>
        </div>
    </a>
    <?php } else { ?>
    <a href="v2/people" class="fab-button fab-cancel" title="<?= gettext('Cancel') ?>">
        <span class="fab-label"><?= gettext('Cancel') ?></span>
        <div class="fab-icon">
            <i class="fa-solid fa-xmark"></i>
        </div>
    </a>
    <?php } ?>
    <?php if (AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()) { ?>
    <a href="#" class="fab-button fab-save-add" role="button" title="<?= gettext('Save and Add') ?>" onclick="document.getElementById('PersonSaveAndAddButton').click(); return false;">
        <span class="fab-label"><?= gettext('Save and Add') ?></span>
        <div class="fab-icon">
            <i class="fa-solid fa-user-plus"></i>
        </div>
    </a>
    <?php } ?>
    <a href="#" class="fab-button fab-save" role="button" title="<?= gettext('Save') ?>" onclick="document.getElementById('PersonSaveButton').click(); return false;">
        <span class="fab-label"><?= gettext('Save') ?></span>
        <div class="fab-icon">
            <i class="fa-solid fa-check"></i>
        </div>
    </a>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(function() {
        $("[data-mask]").inputmask();
        $("#familyId").select2();
    });
</script>

<script src="<?= SystemURLs::assetVersioned('/skin/js/DropdownManager.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/PersonEditor.js') ?>"></script>

<?php
require_once __DIR__ . '/Include/Footer.php';
