<?php
/*******************************************************************************
 *
 *  filename    : FamilyEditor.php
 *  last change : 2003-01-04
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002, 2003 Deane Barker, Chris Gebhardt
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';
require 'Include/CanvassUtilities.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Note;
use ChurchCRM\FamilyQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\NewPersonOrFamilyEmail;
use ChurchCRM\Utils\RedirectUtils;

//Set the page title
$sPageTitle = gettext('Family Editor');

$iFamilyID = -1;

//Get the FamilyID from the querystring
if (array_key_exists('FamilyID', $_GET)) {
    $iFamilyID = InputUtils::LegacyFilterInput($_GET['FamilyID'], 'int');
}

// Security: User must have Add or Edit Records permission to use this form in those manners
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
if ($iFamilyID > 0) {
    if (!($_SESSION['user']->isEditRecordsEnabled() || ($_SESSION['user']->isEditSelfEnabled() && ($iFamilyID == $_SESSION['user']->getPerson()->getFamId())))) {
        RedirectUtils::Redirect('Menu.php');
        exit;
    }

    $sSQL = 'SELECT fam_ID FROM family_fam WHERE fam_ID = '.$iFamilyID;
    if (mysqli_num_rows(RunQuery($sSQL)) == 0) {
        RedirectUtils::Redirect('Menu.php');
        exit;
    }
} elseif (!$_SESSION['user']->isAddRecordsEnabled()) {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

// Get the list of funds
$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun WHERE fun_Active = 'true'";
$rsFunds = RunQuery($sSQL);

// Get the lists of canvassers
$rsCanvassers = CanvassGetCanvassers(gettext('Canvassers'));
$rsBraveCanvassers = CanvassGetCanvassers(gettext('BraveCanvassers'));

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
    $sName = InputUtils::LegacyFilterInput($_POST['Name']);
    // Strip commas out of address fields because they are problematic when
    // exporting addresses to CSV file
    $sAddress1 = str_replace(',', '', InputUtils::LegacyFilterInput($_POST['Address1']));
    $sAddress2 = str_replace(',', '', InputUtils::LegacyFilterInput($_POST['Address2']));
    $sCity = InputUtils::LegacyFilterInput($_POST['City']);
    $sZip = InputUtils::LegacyFilterInput($_POST['Zip']);

    // bevand10 2012-04-26 Add support for uppercase ZIP - controlled by administrator via cfg param
    if (SystemConfig::getBooleanValue('bForceUppercaseZip')) {
        $sZip = strtoupper($sZip);
    }

    $sCountry = InputUtils::LegacyFilterInput($_POST['Country']);
    $iFamilyMemberRows = InputUtils::LegacyFilterInput($_POST['FamCount']);

    if ($sCountry == 'United States' || $sCountry == 'Canada' || $sCountry == '') {
        $sState = InputUtils::LegacyFilterInput($_POST['State']);
    } else {
        $sState = InputUtils::LegacyFilterInput($_POST['StateTextbox']);
    }

    $sHomePhone = InputUtils::LegacyFilterInput($_POST['HomePhone']);
    $sWorkPhone = InputUtils::LegacyFilterInput($_POST['WorkPhone']);
    $sCellPhone = InputUtils::LegacyFilterInput($_POST['CellPhone']);
    $sEmail = InputUtils::LegacyFilterInput($_POST['Email']);
    $bSendNewsLetter = isset($_POST['SendNewsLetter']);

    $nLatitude = 0.0;
    $nLongitude = 0.0;
    if (array_key_exists('Latitude', $_POST)) {
        $nLatitude = InputUtils::LegacyFilterInput($_POST['Latitude'], 'float');
    }
    if (array_key_exists('Longitude', $_POST)) {
        $nLongitude = InputUtils::LegacyFilterInput($_POST['Longitude'], 'float');
    }


    if (is_numeric($nLatitude)) {
        $nLatitude = "'".$nLatitude."'";
    } else {
        $nLatitude = 'NULL';
    }

    if (is_numeric($nLongitude)) {
        $nLongitude = "'".$nLongitude."'";
    } else {
        $nLongitude = 'NULL';
    }

    $nEnvelope = 0;
    if (array_key_exists('Envelope', $_POST)) {
        $nEnvelope = InputUtils::LegacyFilterInput($_POST['Envelope'], 'int');
    }

    if (is_numeric($nEnvelope)) { // Only integers are allowed as Envelope Numbers
        if (intval($nEnvelope) == floatval($nEnvelope)) {
            $nEnvelope = "'".intval($nEnvelope)."'";
        } else {
            $nEnvelope = "'0'";
        }
    } else {
        $nEnvelope = "'0'";
    }

    $iCanvasser = 0;
    if ($_SESSION['user']->isCanvasserEnabled()) { // Only take modifications to this field if the current user is a canvasser
        $bOkToCanvass = isset($_POST['OkToCanvass']);
        if (array_key_exists('Canvasser', $_POST)) {
            $iCanvasser = InputUtils::LegacyFilterInput($_POST['Canvasser']);
        }
        if ((!$iCanvasser) && array_key_exists('BraveCanvasser', $_POST)) {
            $iCanvasser = InputUtils::LegacyFilterInput($_POST['BraveCanvasser']);
        }
        if (empty($iCanvasser)) {
            $iCanvasser = 0;
        }
    }

    $iPropertyID = 0;
    if (array_key_exists('PropertyID', $_POST)) {
        $iPropertyID = InputUtils::LegacyFilterInput($_POST['PropertyID'], 'int');
    }
    $dWeddingDate = InputUtils::LegacyFilterInput($_POST['WeddingDate']);

    $bNoFormat_HomePhone = isset($_POST['NoFormat_HomePhone']);
    $bNoFormat_WorkPhone = isset($_POST['NoFormat_WorkPhone']);
    $bNoFormat_CellPhone = isset($_POST['NoFormat_CellPhone']);

    //Loop through the Family Member 'quick entry' form fields
    for ($iCount = 1; $iCount <= $iFamilyMemberRows; $iCount++) {
        // Assign everything to arrays
        $aFirstNames[$iCount] = InputUtils::LegacyFilterInput($_POST['FirstName'.$iCount]);
        $aMiddleNames[$iCount] = InputUtils::LegacyFilterInput($_POST['MiddleName'.$iCount]);
        $aLastNames[$iCount] = InputUtils::LegacyFilterInput($_POST['LastName'.$iCount]);
        $aSuffix[$iCount] = InputUtils::LegacyFilterInput($_POST['Suffix'.$iCount]);
        $aRoles[$iCount] = InputUtils::LegacyFilterInput($_POST['Role'.$iCount], 'int');
        $aGenders[$iCount] = InputUtils::LegacyFilterInput($_POST['Gender'.$iCount], 'int');
        $aBirthDays[$iCount] = InputUtils::LegacyFilterInput($_POST['BirthDay'.$iCount], 'int');
        $aBirthMonths[$iCount] = InputUtils::LegacyFilterInput($_POST['BirthMonth'.$iCount], 'int');
        $aBirthYears[$iCount] = InputUtils::LegacyFilterInput($_POST['BirthYear'.$iCount], 'int');
        $aClassification[$iCount] = InputUtils::LegacyFilterInput($_POST['Classification'.$iCount], 'int');
        $aPersonIDs[$iCount] = InputUtils::LegacyFilterInput($_POST['PersonID'.$iCount], 'int');
        $aUpdateBirthYear[$iCount] = InputUtils::LegacyFilterInput($_POST['UpdateBirthYear'], 'int');

        // Make sure first names were entered if editing existing family
        if ($iFamilyID > 0) {
            if (strlen($aFirstNames[$iCount]) == 0) {
                $aFirstNameError[$iCount] = gettext('First name must be entered');
                $bErrorFlag = true;
            }
        }

        // Validate any family member birthdays
        if ((strlen($aFirstNames[$iCount]) > 0) && (strlen($aBirthYears[$iCount]) > 0)) {
            if (($aBirthYears[$iCount] > 2155) || ($aBirthYears[$iCount] < 1901)) {
                $aBirthDateError[$iCount] = gettext('Invalid Year: allowable values are 1901 to 2155');
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
    if ((strlen($dWeddingDate) > 0) && ($dWeddingDate != '')) {
        $dateString = parseAndValidateDate($dWeddingDate, $locale = "<?= $localeInfo->getCountryCode() ?>", $pasfut = 'past');
        if ($dateString === false) {
            $sWeddingDateError = '<span style="color: red; ">'
                                .gettext('Not a valid Wedding Date').'</span>';
            $bErrorFlag = true;
        } else {
            $dWeddingDate = "'$dateString'";
        }
    } else {
        $dWeddingDate = 'NULL';
    }

    // Validate Email
    if (strlen($sEmail) > 0) {
        if (checkEmail($sEmail) == false) {
            $sEmailError = '<span style="color: red; ">'
                                .gettext('Email is Not Valid').'</span>';
            $bErrorFlag = true;
        } else {
            $sEmail = $sEmail;
        }
    }

    // Validate all the custom fields
    $aCustomData = [];
    while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH)) {
        extract($rowCustomField);

        $currentFieldData = InputUtils::LegacyFilterInput($_POST[$fam_custom_Field]);

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
        if ($bSendNewsLetter) {
            $bSendNewsLetterString = "'TRUE'";
        } else {
            $bSendNewsLetterString = "'FALSE'";
        }
        if ($bOkToCanvass) {
            $bOkToCanvassString = "'TRUE'";
        } else {
            $bOkToCanvassString = "'FALSE'";
        }
        if ($iFamilyID < 1) {
            $sSQL = "INSERT INTO family_fam (
						fam_Name, 
						fam_Address1, 
						fam_Address2, 
						fam_City, 
						fam_State, 
						fam_Zip, 
						fam_Country, 
						fam_HomePhone, 
						fam_WorkPhone, 
						fam_CellPhone, 
						fam_Email, 
						fam_WeddingDate, 
						fam_DateEntered, 
						fam_EnteredBy, 
						fam_SendNewsLetter,
						fam_OkToCanvass,
						fam_Canvasser,
						fam_Latitude,
						fam_Longitude,
						fam_Envelope)
					VALUES ('".
                        $sName."','".
                        $sAddress1."','".
                        $sAddress2."','".
                        $sCity."','".
                        $sState."','".
                        $sZip."','".
                        $sCountry."','".
                        $sHomePhone."','".
                        $sWorkPhone."','".
                        $sCellPhone."','".
                        $sEmail."',".
                        $dWeddingDate.",'".
                        date('YmdHis')."',".
                        $_SESSION['user']->getId().','.
                        $bSendNewsLetterString.','.
                        $bOkToCanvassString.",'".
                        $iCanvasser."',".
                        $nLatitude.','.
                        $nLongitude.','.
                        $nEnvelope.')';
            $bGetKeyBack = true;
        } else {
            $sSQL = "UPDATE family_fam SET fam_Name='".$sName."',".
                        "fam_Address1='".$sAddress1."',".
                        "fam_Address2='".$sAddress2."',".
                        "fam_City='".$sCity."',".
                        "fam_State='".$sState."',".
                        "fam_Zip='".$sZip."',".
                        'fam_Latitude='.$nLatitude.','.
                        'fam_Longitude='.$nLongitude.','.
                        "fam_Country='".$sCountry."',".
                        "fam_HomePhone='".$sHomePhone."',".
                        "fam_WorkPhone='".$sWorkPhone."',".
                        "fam_CellPhone='".$sCellPhone."',".
                        "fam_Email='".$sEmail."',".
                        'fam_WeddingDate='.$dWeddingDate.','.
                        'fam_Envelope='.$nEnvelope.','.
                        "fam_DateLastEdited='".date('YmdHis')."',".
                        'fam_EditedBy = '.$_SESSION['user']->getId().','.
                        'fam_SendNewsLetter = '.$bSendNewsLetterString;
            if ($_SESSION['user']->isCanvasserEnabled()) {
                $sSQL .= ', fam_OkToCanvass = '.$bOkToCanvassString.
                                    ", fam_Canvasser = '".$iCanvasser."'";
            }
            $sSQL .= ' WHERE fam_ID = '.$iFamilyID;
            $bGetKeyBack = false;
        }

        //Execute the SQL
        RunQuery($sSQL);

        //If the user added a new record, we need to key back to the route to the FamilyView page
        if ($bGetKeyBack) {
            //Get the key back
            $sSQL = 'SELECT MAX(fam_ID) AS iFamilyID FROM family_fam';
            $rsLastEntry = RunQuery($sSQL);
            extract(mysqli_fetch_array($rsLastEntry));

            $sSQL = "INSERT INTO `family_custom` (`fam_ID`) VALUES ('".$iFamilyID."')";
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

                    RunQuery('LOCK TABLES person_per WRITE, person_custom WRITE');
                    $sSQL = "INSERT INTO person_per (
								per_FirstName,
								per_MiddleName,
								per_LastName,
                        per_Suffix,
								per_fam_ID,
								per_fmr_ID,
								per_DateEntered,
								per_EnteredBy,
								per_Gender,
								per_BirthDay,
								per_BirthMonth,
								per_BirthYear,
								per_cls_ID)
							VALUES (
								'$aFirstNames[$iCount]',
								'$aMiddleNames[$iCount]',
								'$sLastNameToEnter',
								'$aSuffix[$iCount]',
								$iFamilyID,
								$aRoles[$iCount],
								'".date('YmdHis')."',
								".$_SESSION['user']->getId().",
								$aGenders[$iCount],
								$aBirthDays[$iCount],
								$aBirthMonths[$iCount],
								$aBirthYears[$iCount],
								$aClassification[$iCount])";
                    RunQuery($sSQL);
                    $dbPersonId = mysqli_insert_id($cnInfoCentral);
                    $note = new Note();
                    $note->setPerId($dbPersonId);
                    $note->setText(gettext('Created via Family'));
                    $note->setType('create');
                    $note->setEntered($_SESSION['user']->getId());
                    $note->save();
                    $sSQL = 'INSERT INTO person_custom (per_ID) VALUES ('
                                .$dbPersonId.')';
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
                    $logger->warn($NotificationEmail->getError());
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
                    $sBirthYearScript = ($aUpdateBirthYear[$iCount] & 1) ? 'per_BirthYear='.$aBirthYears[$iCount].', ' : '';
                    //RunQuery("LOCK TABLES person_per WRITE, person_custom WRITE");
                    $sSQL = "UPDATE person_per SET per_FirstName='".$aFirstNames[$iCount]."', per_MiddleName='".$aMiddleNames[$iCount]."',per_LastName='".$aLastNames[$iCount]."',per_Suffix='".$aSuffix[$iCount]."',per_Gender='".$aGenders[$iCount]."',per_fmr_ID='".$aRoles[$iCount]."',per_BirthMonth='".$aBirthMonths[$iCount]."',per_BirthDay='".$aBirthDays[$iCount]."', ".$sBirthYearScript."per_cls_ID='".$aClassification[$iCount]."' WHERE per_ID=".$aPersonIDs[$iCount];
                    RunQuery($sSQL);
                    //RunQuery("UNLOCK TABLES");

                    $note = new Note();
                    $note->setPerId($aPersonIDs[$iCount]);
                    $note->setText(gettext('Updated via Family'));
                    $note->setType('edit');
                    $note->setEntered($_SESSION['user']->getId());
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
                if (($aSecurityType[$fam_custom_FieldSec] == 'bAll') || ($_SESSION[$aSecurityType[$fam_custom_FieldSec]])) {
                    $currentFieldData = trim($aCustomData[$fam_custom_Field]);

                    sqlCustomField($sSQL, $type_ID, $currentFieldData, $fam_custom_Field, $sCountry);
                }
            }

            // chop off the last 2 characters (comma and space) added in the last while loop iteration.
            $sSQL = mb_substr($sSQL, 0, -2);

            $sSQL .= ', fam_ID = '.$iFamilyID;

            //Execute the SQL
            RunQuery($sSQL);
        }

        //Which submit button did they press?
        if (isset($_POST['FamilySubmit'])) {
            //Send to the view of this person
            RedirectUtils::Redirect('FamilyView.php?FamilyID='.$iFamilyID);
        } else {
            //Reload to editor to add another record
            RedirectUtils::Redirect('FamilyEditor.php');
        }
    }
} else {
    //FirstPass
    //Are we editing or adding?
    if ($iFamilyID > 0) {
        //Editing....
        //Get the information on this family
        $sSQL = 'SELECT * FROM family_fam WHERE fam_ID = '.$iFamilyID;
        $rsFamily = RunQuery($sSQL);
        extract(mysqli_fetch_array($rsFamily));

        $iFamilyID = $fam_ID;
        $sName = $fam_Name;
        $sAddress1 = $fam_Address1;
        $sAddress2 = $fam_Address2;
        $sCity = $fam_City;
        $sState = $fam_State;
        $sZip = $fam_Zip;
        $sCountry = $fam_Country;
        $sHomePhone = $fam_HomePhone;
        $sWorkPhone = $fam_WorkPhone;
        $sCellPhone = $fam_CellPhone;
        $sEmail = $fam_Email;
        $bSendNewsLetter = ($fam_SendNewsLetter == 'TRUE');
        $bOkToCanvass = ($fam_OkToCanvass == 'TRUE');
        $iCanvasser = $fam_Canvasser;
        $dWeddingDate = $fam_WeddingDate;
        $nLatitude = $fam_Latitude;
        $nLongitude = $fam_Longitude;

        // Expand the phone number
        $sHomePhone = ExpandPhoneNumber($sHomePhone, $sCountry, $bNoFormat_HomePhone);
        $sWorkPhone = ExpandPhoneNumber($sWorkPhone, $sCountry, $bNoFormat_WorkPhone);
        $sCellPhone = ExpandPhoneNumber($sCellPhone, $sCountry, $bNoFormat_CellPhone);

        $sSQL = 'SELECT * FROM family_custom WHERE fam_ID = '.$iFamilyID;
        $rsCustomData = RunQuery($sSQL);
        $aCustomData = mysqli_fetch_array($rsCustomData, MYSQLI_BOTH);

        $aCustomErrors = [];

        if ($numCustomFields > 0) {
            mysqli_data_seek($rsCustomFields, 0);
            while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH)) {
                $aCustomErrors[$rowCustomField['fam_custom_Field']] = false;
            }
        }

        $sSQL = 'SELECT * FROM person_per LEFT JOIN family_fam ON per_fam_ID = fam_ID WHERE per_fam_ID ='.$iFamilyID.' ORDER BY per_fmr_ID';
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
        $iClassification = '0';
        $iFamilyMemberRows = 6;
        $bOkToCanvass = 1;

        $iFamilyID = -1;
        $sName = '';
        $sAddress1 = '';
        $sAddress2 = '';
        $sZip = '';
        $sHomePhone = '';
        $bNoFormat_HomePhone = isset($_POST['NoFormat_HomePhone']);
        $sWorkPhone = '';
        $bNoFormat_WorkPhone = isset($_POST['NoFormat_WorkPhone']);
        $sCellPhone = '';
        $bNoFormat_CellPhone = isset($_POST['NoFormat_CellPhone']);
        $sEmail = '';
        $bSendNewsLetter = 'TRUE';
        $iCanvasser = -1;
        $dWeddingDate = '';
        $nLatitude = 0.0;
        $nLongitude = 0.0;

        //Loop through the Family Member 'quick entry' form fields
        for ($iCount = 1; $iCount <= $iFamilyMemberRows; $iCount++) {
            // Assign everything to arrays
            $aFirstNames[$iCount] = '';
            $aMiddleNames[$iCount] = '';
            $aLastNames[$iCount] = '';
            $aSuffix[$iCount] = '';
            $aRoles[$iCount] = 0;
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

require 'Include/Header.php';

?>

<form method="post" action="FamilyEditor.php?FamilyID=<?php echo $iFamilyID ?>">
	<input type="hidden" Name="iFamilyID" value="<?= $iFamilyID ?>">
	<input type="hidden" name="FamCount" value="<?= $iFamilyMemberRows ?>">
	<div class="box box-info clearfix">
		<div class="box-header">
			<h3 class="box-title"><?= gettext('Family Info') ?></h3>
			<div class="pull-right"><br/>
				<input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="FamilySubmit">
			</div>
		</div><!-- /.box-header -->
		<div class="box-body">
			<div class="form-group">
				<div class="row">
					<div class="col-md-6">
						<label><?= gettext('Family Name') ?>:</label>
						<input type="text" Name="Name" id="FamilyName" value="<?= htmlentities(stripslashes($sName), ENT_NOQUOTES, 'UTF-8') ?>" maxlength="48"  class="form-control">
						<?php if ($sNameError) {
    ?><font color="red"><?= $sNameError ?></font><?php
} ?>
					</div>
				</div>
				<p/>
				<div class="row">
					<div class="col-md-6">
						<label><?= gettext('Address') ?> 1:</label>
							<input type="text" Name="Address1" value="<?= htmlentities(stripslashes($sAddress1), ENT_NOQUOTES, 'UTF-8') ?>" size="50" maxlength="250"  class="form-control">
					</div>
					<div class="col-md-6">
						<label><?= gettext('Address') ?> 2:</label>
						<input type="text" Name="Address2" value="<?= htmlentities(stripslashes($sAddress2), ENT_NOQUOTES, 'UTF-8') ?>" size="50" maxlength="250"  class="form-control">
					</div>
					<div class="col-md-6">
						<label><?= gettext('City') ?>:</label>
						<input type="text" Name="City" value="<?= htmlentities(stripslashes($sCity), ENT_NOQUOTES, 'UTF-8') ?>" maxlength="50"  class="form-control">
					</div>
				</div>
				<p/>
				<div class="row">
					<div class="form-group col-md-3">
						<label for="StatleTextBox"><?= gettext('State')?>: </label>
						<?php require 'Include/StateDropDown.php'; ?>
					</div>
					<div class="form-group col-md-3">
						<label><?= gettext('None US/CND State') ?>:</label>
						<input type="text"  class="form-control" name="StateTextbox" value="<?php if ($sCountry != 'United States' && $sCountry != 'Canada') {
        echo htmlentities(stripslashes($sState), ENT_NOQUOTES, 'UTF-8');
    } ?>" size="20" maxlength="30">
					</div>
					<div class="form-group col-md-3">
						<label><?= gettext('Zip')?>:</label>
						<input type="text" Name="Zip"  class="form-control" <?php
                            // bevand10 2012-04-26 Add support for uppercase ZIP - controlled by administrator via cfg param
                            if (SystemConfig::getBooleanValue('bForceUppercaseZip')) {
                                echo 'style="text-transform:uppercase" ';
                            }
                            echo 'value="'.htmlentities(stripslashes($sZip), ENT_NOQUOTES, 'UTF-8').'" '; ?>
							maxlength="10" size="8">
					</div>
					<div class="form-group col-md-3">
						<label> <?= gettext('Country') ?>:</label>
						<?php require 'Include/CountryDropDown.php' ?>
					</div>
				</div>
				<?php if (!SystemConfig::getValue('bHideLatLon')) { /* Lat/Lon can be hidden - General Settings */
                                if (!$bHaveXML) { // No point entering if values will just be overwritten?>
				<div class="row">
					<div class="form-group col-md-3">
						<label><?= gettext('Latitude') ?>:</label>
						<input type="text" class="form-control" Name="Latitude" value="<?= $nLatitude ?>" size="30" maxlength="50">
					</div>
					<div class="form-group col-md-3">
						<label><?= gettext('Longitude') ?>:</label>
						<input type="text" class="form-control" Name="Longitude" value="<?= $nLongitude ?>" size="30" maxlength="50">
					</div>
				</div>
				<?php
                                }
                            } /* Lat/Lon can be hidden - General Settings */ ?>
			</div>
		</div>
	</div>
    <script nonce="<?= SystemURLs::getCSPNonce() ?>" >
        $(document).ready(function() {
            $("#country-input").select2();
            $("#state-input").select2();
        });
    </script>
	<div class="box box-info clearfix">
		<div class="box-header">
			<h3 class="box-title"><?= gettext('Contact Info') ?></h3>
			<div class="pull-right"><br/>
				<input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="FamilySubmit" >
			</div>
		</div><!-- /.box-header -->
		<div class="box-body">
			<div class="row">
				<div class="form-group col-md-6">
					<label><?= gettext('Home Phone') ?>:</label>
					<div class="input-group">
						<div class="input-group-addon">
							<i class="fa fa-phone"></i>
						</div>
						<input type="text" Name="HomePhone" value="<?= htmlentities(stripslashes($sHomePhone)) ?>" size="30" maxlength="30" class="form-control" data-inputmask='"mask": "<?= SystemConfig::getValue('sPhoneFormat')?>"' data-mask>
						<input type="checkbox" name="NoFormat_HomePhone" value="1" <?php if ($bNoFormat_HomePhone) {
                                echo ' checked';
                            } ?>><?= gettext('Do not auto-format') ?>
					</div>
				</div>
				<div class="form-group col-md-6">
					<label><?= gettext('Work Phone') ?>:</label>
					<div class="input-group">
						<div class="input-group-addon">
							<i class="fa fa-phone"></i>
						</div>
						<input type="text" name="WorkPhone" value="<?= htmlentities(stripslashes($sWorkPhone)) ?>" size="30" maxlength="30" class="form-control" data-inputmask='"mask": "<?= SystemConfig::getValue('sPhoneFormatWithExt')?>"' data-mask/>
						<input type="checkbox" name="NoFormat_WorkPhone" value="1" <?= $bNoFormat_WorkPhone ? ' checked' : ''?>><?= gettext('Do not auto-format') ?>
					</div>
				</div>
				<div class="form-group col-md-6">
					<label><?= gettext('Mobile Phone') ?>:</label>
					<div class="input-group">
						<div class="input-group-addon">
							<i class="fa fa-phone"></i>
						</div>
						<input type="text" name="CellPhone" value="<?= htmlentities(stripslashes($sCellPhone)) ?>" size="30" maxlength="30" class="form-control" data-inputmask='"mask": "<?= SystemConfig::getValue('sPhoneFormatCell')?>"' data-mask>
						<input type="checkbox" name="NoFormat_CellPhone" value="1" <?= $bNoFormat_CellPhone ? ' checked' : '' ?>><?= gettext('Do not auto-format') ?>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="form-group col-md-6">
					<label><?= gettext('Email') ?>:</label>
					<div class="input-group">
						<div class="input-group-addon">
							<i class="fa fa-envelope"></i>
						</div>
						<input type="text" Name="Email" class="form-control" value="<?= htmlentities(stripslashes($sEmail)) ?>" size="30" maxlength="100"><font color="red"><?php echo '<BR>'.$sEmailError ?></font>
					</div>
				</div>
				<?php if (!SystemConfig::getValue('bHideFamilyNewsletter')) { /* Newsletter can be hidden - General Settings */ ?>
				<div class="form-group col-md-4">
					<label><?= gettext('Send Newsletter') ?>:</label><br/>
					<input type="checkbox" Name="SendNewsLetter" value="1" <?php if ($bSendNewsLetter) {
                                echo ' checked';
                            } ?>>
				</div>
				<?php
                            } ?>
			</div>
		</div>
	</div>
	<div class="box box-info clearfix">
		<div class="box-header">
			<h3 class="box-title"><?= gettext('Other Info') ?>:</h3>
			<div class="pull-right"><br/>
				<input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="FamilySubmit">
			</div>
		</div><!-- /.box-header -->
		<div class="box-body">
			<?php if (!SystemConfig::getValue('bHideWeddingDate')) { /* Wedding Date can be hidden - General Settings */
                                if ($dWeddingDate == 'NULL') {
                                    $dWeddingDate = '';
                                } ?>
				<div class="row">
					<div class="form-group col-md-4">
                        <label><?= gettext('Wedding Date') ?>:</label>
						<input type="text" class="form-control date-picker" Name="WeddingDate" value="<?= change_date_for_place_holder($dWeddingDate) ?>" maxlength="12" id="WeddingDate" size="15" placeholder="<?= SystemConfig::getValue("sDatePickerPlaceHolder") ?>">
						<?php if ($sWeddingDateError) {
                                    ?> <span style="color: red"><br/><?php $sWeddingDateError ?></span> <?php
                                } ?>
					</div>
				</div>
			<?php
                            } /* Wedding date can be hidden - General Settings */ ?>
			<div class="row">
				<?php if ($_SESSION['user']->isCanvasserEnabled()) { // Only show this field if the current user is a canvasser?>
					<div class="form-group col-md-4">
						<label><?= gettext('Ok To Canvass') ?>: </label><br/>
						<input type="checkbox" Name="OkToCanvass" value="1" <?php if ($bOkToCanvass) {
                                echo ' checked ';
                            } ?> >
					</div>
				<?php
                            }

                if ($rsCanvassers != 0 && mysqli_num_rows($rsCanvassers) > 0) {
                    ?>
				<div class="form-group col-md-4">
					<label><?= gettext('Assign a Canvasser') ?>:</label>
					<?php // Display all canvassers
                    echo "<select name='Canvasser' class=\"form-control\"><option value=\"0\">None selected</option>";
                    while ($aCanvasser = mysqli_fetch_array($rsCanvassers)) {
                        echo '<option value="'.$aCanvasser['per_ID'].'"';
                        if ($aCanvasser['per_ID'] == $iCanvasser) {
                            echo ' selected';
                        }
                        echo '>';
                        echo $aCanvasser['per_FirstName'].' '.$aCanvasser['per_LastName'];
                        echo '</option>';
                    }
                    echo '</select></div>';
                }

                if ($rsBraveCanvassers != 0 && mysqli_num_rows($rsBraveCanvassers) > 0) {
                    ?>
					<div class="form-group col-md-4">
						<label><?= gettext('Assign a Brave Canvasser') ?>: </label>

						<?php // Display all canvassers
                        echo "<select name='BraveCanvasser' class=\"form-control\"><option value=\"0\">None selected</option>";
                    while ($aBraveCanvasser = mysqli_fetch_array($rsBraveCanvassers)) {
                        echo '<option value="'.$aBraveCanvasser['per_ID'].'"';
                        if ($aBraveCanvasser['per_ID'] == $iCanvasser) {
                            echo ' selected';
                        }
                        echo '>';
                        echo $aBraveCanvasser['per_FirstName'].' '.$aBraveCanvasser['per_LastName'];
                        echo '</option>';
                    }
                    echo '</select></div>';
                } ?>
			</div>
		</div>
	</div>
	<?php if (SystemConfig::getValue('bUseDonationEnvelopes')) { /* Donation envelopes can be hidden - General Settings */ ?>
	<div class="box box-info clearfix">
		<div class="box-header">
			<h3><?= gettext('Envelope Info') ?></h3>
			<div class="pull-right"><br/>
				<input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="FamilySubmit">
			</div>
		</div><!-- /.box-header -->
		<div class="box-body">
			<div class="row">
				<div class="form-group col-md-4">
					<label><?= gettext('Envelope Number') ?>:</label>
					<input type="text" Name="Envelope" <?php if ($fam_Envelope) {
                    echo ' value="'.$fam_Envelope;
                } ?>" size="30" maxlength="50">
				</div>
			</div>
		</div>
	</div>
	<?php
                }
    if ($numCustomFields > 0) {
        ?>
	<div class="box box-info clearfix">
		<div class="box-header">
			<h3 class="box-title"><?= gettext('Custom Fields') ?></h3>
			<div class="pull-right"><br/>
				<input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="FamilySubmit">
			</div>
		</div><!-- /.box-header -->
		<div class="box-body">
		<?php mysqli_data_seek($rsCustomFields, 0);
        while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_BOTH)) {
            extract($rowCustomField);
            if (($aSecurityType[$fam_custom_FieldSec] == 'bAll') || ($_SESSION[$aSecurityType[$fam_custom_FieldSec]])) {
                ?>
			<div class="row">
				<div class="form-group col-md-4">
				<label><?= $fam_custom_Name  ?> </label>
				<?php $currentFieldData = trim($aCustomData[$fam_custom_Field]);

                if ($type_ID == 11) {
                    $fam_custom_Special = $sCountry;
                }

                formCustomField($type_ID, $fam_custom_Field, $currentFieldData, $fam_custom_Special, !isset($_POST['FamilySubmit']));
                echo '<span style="color: red; ">'.$aCustomErrors[$fam_custom_Field].'</span>';
                echo '</div></div>';
            }
        } ?>
		</div>
	</div>
	<?php
    } ?>
	<div class="box box-info clearfix">
		<div class="box-header">
			<h3 class="box-title"><?= gettext('Family Members') ?></h3>
			<div class="pull-right"><br/>
				<input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="FamilySubmit">
			</div>
		</div><!-- /.box-header -->
		<div class="box-body">

	<?php if ($iFamilyMemberRows > 0) {
        ?>

	<tr>
		<td colspan="2">
		<div class="MediumText">
			<center><?= $iFamilyID < 0 ? gettext('You may create family members now or add them later.  All entries will become <i>new</i> person records.') : '' ?></center>
		</div><br><br>
            <div class="table-responsive">
		<table cellpadding="3" cellspacing="0" width="100%">
		<thead>
		<tr class="TableHeader" align="center">
			<th><?= gettext('First') ?></th>
			<th><?= gettext('Middle') ?></th>
			<th><?= gettext('Last') ?></th>
			<th><?= gettext('Suffix') ?></th>
			<th><?= gettext('Gender') ?></th>
			<th><?= gettext('Role') ?></th>
			<th><?= gettext('Month') ?></th>
			<th><?= gettext('Day') ?></th>
			<th><?= gettext('Year') ?></th>
			<th><?= gettext('Classification') ?></th>
		</tr>
		</thead>
		<?php

        //Get family roles
        $sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 2 ORDER BY lst_OptionSequence';
        $rsFamilyRoles = RunQuery($sSQL);
        $numFamilyRoles = mysqli_num_rows($rsFamilyRoles);
        for ($c = 1; $c <= $numFamilyRoles; $c++) {
            $aRow = mysqli_fetch_array($rsFamilyRoles);
            extract($aRow);
            $aFamilyRoleNames[$c] = $lst_OptionName;
            $aFamilyRoleIDs[$c] = $lst_OptionID;
        }

        for ($iCount = 1; $iCount <= $iFamilyMemberRows; $iCount++) {
            ?>
		<input type="hidden" name="PersonID<?= $iCount ?>" value="<?= $aPersonIDs[$iCount] ?>">
		<tr>
			<td class="TextColumn">
				<input name="FirstName<?= $iCount ?>" type="text" value="<?= $aFirstNames[$iCount] ?>" size="10">
				<div><font color="red"><?php if (array_key_exists($iCount, $aFirstNameError)) {
                echo $aFirstNameError[$iCount];
            } ?></font></div>
			</td>
			<td class="TextColumn">
				<input name="MiddleName<?= $iCount ?>" type="text" value="<?= $aMiddleNames[$iCount] ?>" size="10">
			</td>
			<td class="TextColumn">
				<input name="LastName<?= $iCount ?>" type="text" value="<?= $aLastNames[$iCount] ?>" size="10">
			</td>
			<td class="TextColumn">
				<input name="Suffix<?= $iCount ?>" type="text" value="<?= $aSuffix[$iCount] ?>" size="10">
			</td>
			<td class="TextColumn">
				<select name="Gender<?php echo $iCount ?>">
					<option value="0" <?php if ($aGenders[$iCount] == 0) {
                echo 'selected';
            } ?> ><?= gettext('Select Gender') ?></option>
					<option value="1" <?php if ($aGenders[$iCount] == 1) {
                echo 'selected';
            } ?> ><?= gettext('Male') ?></option>
					<option value="2" <?php if ($aGenders[$iCount] == 2) {
                echo 'selected';
            } ?> ><?= gettext('Female') ?></option>
				</select>
			</td>

			<td class="TextColumn">
				<select name="Role<?php echo $iCount ?>">
					<option value="0" <?php if ($aRoles[$iCount] == 0) {
                echo 'selected';
            } ?> ><?= gettext('Select Role') ?></option>
				<?php
                //Build the role select box
                for ($c = 1; $c <= $numFamilyRoles; $c++) {
                    echo '<option value="'.$aFamilyRoleIDs[$c].'"';
                    if ($aRoles[$iCount] == $aFamilyRoleIDs[$c]) {
                        echo ' selected';
                    }
                    echo '>'.$aFamilyRoleNames[$c].'</option>';
                } ?>
				</select>
			</td>
			<td class="TextColumn">
				<select name="BirthMonth<?php echo $iCount ?>">
					<option value="0" <?php if ($aBirthMonths[$iCount] == 0) {
                    echo 'selected';
                } ?>><?= gettext('Unknown') ?></option>
					<option value="01" <?php if ($aBirthMonths[$iCount] == 1) {
                    echo 'selected';
                } ?>><?= gettext('January') ?></option>
					<option value="02" <?php if ($aBirthMonths[$iCount] == 2) {
                    echo 'selected';
                } ?>><?= gettext('February') ?></option>
					<option value="03" <?php if ($aBirthMonths[$iCount] == 3) {
                    echo 'selected';
                } ?>><?= gettext('March') ?></option>
					<option value="04" <?php if ($aBirthMonths[$iCount] == 4) {
                    echo 'selected';
                } ?>><?= gettext('April') ?></option>
					<option value="05" <?php if ($aBirthMonths[$iCount] == 5) {
                    echo 'selected';
                } ?>><?= gettext('May') ?></option>
					<option value="06" <?php if ($aBirthMonths[$iCount] == 6) {
                    echo 'selected';
                } ?>><?= gettext('June') ?></option>
					<option value="07" <?php if ($aBirthMonths[$iCount] == 7) {
                    echo 'selected';
                } ?>><?= gettext('July') ?></option>
					<option value="08" <?php if ($aBirthMonths[$iCount] == 8) {
                    echo 'selected';
                } ?>><?= gettext('August') ?></option>
					<option value="09" <?php if ($aBirthMonths[$iCount] == 9) {
                    echo 'selected';
                } ?>><?= gettext('September') ?></option>
					<option value="10" <?php if ($aBirthMonths[$iCount] == 10) {
                    echo 'selected';
                } ?>><?= gettext('October') ?></option>
					<option value="11" <?php if ($aBirthMonths[$iCount] == 11) {
                    echo 'selected';
                } ?>><?= gettext('November') ?></option>
					<option value="12" <?php if ($aBirthMonths[$iCount] == 12) {
                    echo 'selected';
                } ?>><?= gettext('December') ?></option>
				</select>
			</td>
			<td class="TextColumn">
				<select name="BirthDay<?= $iCount ?>">
					<option value="0"><?= gettext('Unk')?></option>
					<?php for ($x = 1; $x < 32; $x++) {
                    if ($x < 10) {
                        $sDay = '0'.$x;
                    } else {
                        $sDay = $x;
                    } ?>
					<option value="<?= $sDay ?>" <?php if ($aBirthDays[$iCount] == $x) {
                        echo 'selected';
                    } ?>><?= $x ?></option>
				<?php
                } ?>
				</select>
			</td>
			<td class="TextColumn">
			<?php	if (!array_key_exists($iCount, $aperFlags) || !$aperFlags[$iCount]) {
                    $UpdateBirthYear = 1; ?>
				<input name="BirthYear<?= $iCount ?>" type="text" value="<?= $aBirthYears[$iCount] ?>" size="4" maxlength="4">
				<div><font color="red"><?php if (array_key_exists($iCount, $aBirthDateError)) {
                        echo $aBirthDateError[$iCount];
                    } ?></font></div>
			<?php
                } else {
                    $UpdateBirthYear = 0;
                } ?>
				&nbsp;
			</td>
			<td>
				<select name="Classification<?php echo $iCount ?>">
					<option value="0" <?php if ($aClassification[$iCount] == 0) {
                    echo 'selected';
                } ?>><?= gettext('Unassigned') ?></option>
					<option value="0" disabled>-----------------------</option>
					<?php
                    //Get Classifications for the drop-down
                    $sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence';
            $rsClassifications = RunQuery($sSQL);

            //Display Classifications
            while ($aRow = mysqli_fetch_array($rsClassifications)) {
                extract($aRow);
                echo '<option value="'.$lst_OptionID.'"';
                if ($aClassification[$iCount] == $lst_OptionID) {
                    echo ' selected';
                }
                echo '>'.$lst_OptionName.'&nbsp;';
            }
            echo '</select></td></tr>';
        }
        echo '</table></div>';

        echo '</div></div>';
    }

    echo '<td colspan="2" align="center">';
    echo '<input type="hidden" Name="UpdateBirthYear" value="'.$UpdateBirthYear.'">';

    echo '<input type="submit" class="btn btn-primary" value="'.gettext('Save').'" Name="FamilySubmit"> ';
    if ($_SESSION['user']->isAddRecordsEnabled()) {
        echo ' <input type="submit" class="btn btn-info" value="'.gettext('Save and Add').'" name="FamilySubmitAndAdd"> ';
    }
    echo ' <input type="button" class="btn" value="'.gettext('Cancel').'" Name="FamilyCancel"';
    if ($iFamilyID > 0) {
        echo " onclick=\"javascript:document.location='FamilyView.php?FamilyID=$iFamilyID';\">";
    } else {
        echo " onclick=\"javascript:document.location='".SystemURLs::getRootPath()."/v2/family';\">";
    }
    echo '</td></tr></form></table>';
?>

	<script nonce="<?= SystemURLs::getCSPNonce() ?>" >
		$(function() {
			$("[data-mask]").inputmask();
		});

	</script>
<?php require 'Include/Footer.php' ?>
