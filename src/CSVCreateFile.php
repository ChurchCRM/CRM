<?php

/*******************************************************************************
 *
 *  filename    : CSVCreateFile.php
 *  last change : 2003-06-11
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001-2003 Deane Barker, Chris Gebhardt, Michael Wilt
  *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';
require 'Include/ReportFunctions.php';

use ChurchCRM\dto\Cart;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\Utils\MiscUtils;
use ChurchCRM\dto\Classification;
use ChurchCRM\Utils\RedirectUtils;

$delimiter = SystemConfig::getValue("sCSVExportDelemiter");

// Turn ON output buffering
ob_start();

// Get Source and Format from the request object and assign them locally
$sSource = strtolower($_POST['Source']);
$sFormat = strtolower($_POST['Format']);
$bSkipIncompleteAddr = isset($_POST['SkipIncompleteAddr']);
$bSkipNoEnvelope = isset($_POST['SkipNoEnvelope']);

// Get the custom fields
if ($sFormat == 'default') {
    $sSQL = 'SELECT * FROM person_custom_master ORDER BY custom_Order';
    $rsCustomFields = RunQuery($sSQL);

    $sSQL = 'SELECT * FROM family_custom_master ORDER BY fam_custom_Order';
    $rsFamCustomFields = RunQuery($sSQL);
}
if ($sFormat == 'rollup') {
    $sSQL = 'SELECT * FROM family_custom_master ORDER BY fam_custom_Order';
    $rsFamCustomFields = RunQuery($sSQL);
}

//Get membership classes
$memberClass = array(0);
foreach (Classification::getAll() as $Member) {
    $memberClass[] = $Member->getOptionName();
}

//Get family roles
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 2 ORDER BY lst_OptionSequence';
$rsFamilyRoles = RunQuery($sSQL);
while ($aRow = mysqli_fetch_array($rsFamilyRoles)) {
    extract($aRow);
    $familyRoles[$lst_OptionID] = $lst_OptionName;
    $roleSequence[$lst_OptionSequence] = $lst_OptionID;
}

// Get Field Security List Matrix
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence';
$rsSecurityGrp = RunQuery($sSQL);

while ($aRow = mysqli_fetch_array($rsSecurityGrp)) {
    extract($aRow);
    $aSecurityType[$lst_OptionID] = $lst_OptionName;
}

//
// Prepare the MySQL query
//

$sJoinFamTable = ' LEFT JOIN family_fam ON per_fam_ID = fam_ID ';
$sPerTable = 'person_per';

// If our source is the cart contents, we don't need to build a WHERE filter string
if ($sSource == 'cart') {
    $sWhereExt = 'AND per_ID IN ('.ConvertCartToString($_SESSION['aPeopleCart']).')';
} else {
    // If we're filtering by groups, include the p2g2r table
    if (!empty($_POST['GroupID'])) {
        $sPerTable = '(person_per, person2group2role_p2g2r)';
    }

    // Prepare any extentions to the WHERE clauses
    $sWhereExt = '';
    if (!empty($_POST['Classification'])) {
        $count = 0;
        foreach ($_POST['Classification'] as $Cls) {
            $Class[$count++] = InputUtils::LegacyFilterInput($Cls, 'int');
        }
        if ($count == 1) {
            if ($Class[0]) {
                $sWhereExt .= 'AND per_cls_ID = '.$Class[0].' ';
            }
        } else {
            $sWhereExt .= 'AND (per_cls_ID = '.$Class[0];
            for ($i = 1; $i < $count; $i++) {
                $sWhereExt .= ' OR per_cls_ID = '.$Class[$i];
            }
            $sWhereExt .= ') ';
            // this is silly: should be something like..  $sWhereExt .= "AND per_cls_ID IN
        }
    }

    if (!empty($_POST['FamilyRole'])) {
        $count = 0;
        foreach ($_POST['FamilyRole'] as $Fmr) {
            $Class[$count++] = InputUtils::LegacyFilterInput($Fmr, 'int');
        }
        if ($count == 1) {
            if ($Class[0]) {
                $sWhereExt .= 'AND per_fmr_ID = '.$Class[0].' ';
            }
        } else {
            $sWhereExt .= 'AND (per_fmr_ID = '.$Class[0];
            for ($i = 1; $i < $count; $i++) {
                $sWhereExt .= ' OR per_fmr_ID = '.$Class[$i];
            }
            $sWhereExt .= ') ';
        }
    }

    if (!empty($_POST['Gender'])) {
        $sWhereExt .= 'AND per_Gender = '.InputUtils::LegacyFilterInput($_POST['Gender'], 'int').' ';
    }

    if (!empty($_POST['GroupID'])) {
        $count = 0;
        foreach ($_POST['GroupID'] as $Grp) {
            $Class[$count++] = InputUtils::LegacyFilterInput($Grp, 'int');
        }
        if ($count == 1) {
            if ($Class[0]) {
                $sWhereExt .= 'AND per_ID = p2g2r_per_ID AND p2g2r_grp_ID = '.$Class[0].' ';
            }
        } else {
            $sWhereExt .= 'AND per_ID = p2g2r_per_ID AND (p2g2r_grp_ID = '.$Class[0];
            for ($i = 1; $i < $count; $i++) {
                $sWhereExt .= ' OR p2g2r_grp_ID = '.$Class[$i];
            }
            $sWhereExt .= ') ';
        }

        // This is used for individual mode to remove duplicate rows from people assigned multiple groups.
        $sGroupBy = ' GROUP BY per_ID';
    } else {
        $sGroupBy = '';
    }

    if (!empty($_POST['MembershipDate1'])) {
        $sWhereExt .= "AND per_MembershipDate >= '".InputUtils::LegacyFilterInput($_POST['MembershipDate1'], 'char', 10)."' ";
    }
    if ($_POST['MembershipDate2'] != date('Y-m-d')) {
        $sWhereExt .= "AND per_MembershipDate <= '".InputUtils::LegacyFilterInput($_POST['MembershipDate2'], 'char', 10)."' ";
    }

    $refDate = getdate(time());

    if (!empty($_POST['BirthDate1'])) {
        $sWhereExt .= "AND DATE_FORMAT(CONCAT(per_BirthYear,'-',per_BirthMonth,'-',per_BirthDay),'%Y-%m-%d') >= '".InputUtils::LegacyFilterInput($_POST['BirthDate1'], 'char', 10)."' ";
    }

    if ($_POST['BirthDate2'] != date('Y-m-d')) {
        $sWhereExt .= "AND DATE_FORMAT(CONCAT(per_BirthYear,'-',per_BirthMonth,'-',per_BirthDay),'%Y-%m-%d') <= '".InputUtils::LegacyFilterInput($_POST['BirthDate2'], 'char', 10)."' ";
    }

    if (!empty($_POST['AnniversaryDate1'])) {
        $annivStart = getdate(strtotime(InputUtils::LegacyFilterInput($_POST['AnniversaryDate1'])));

        // Add year to query if not in future
        if ($annivStart['year'] < date('Y') || ($annivStart['year'] == date('Y') && $annivStart['mon'] <= date('m') && $annivStart['mday'] <= date('d'))) {
            $sWhereExt .= "AND fam_WeddingDate >= '".InputUtils::LegacyFilterInput($_POST['AnniversaryDate1'], 'char', 10)."' ";
        } else {
            $sWhereExt .= "AND DAYOFYEAR(fam_WeddingDate) >= DAYOFYEAR('".InputUtils::LegacyFilterInput($_POST['AnniversaryDate1'], 'char', 10)."') ";
        }
    }

    if ($_POST['AnniversaryDate2'] != date('Y-m-d')) {
        $annivEnd = getdate(strtotime(InputUtils::LegacyFilterInput($_POST['AnniversaryDate2'], 'char', 10)));

        // Add year to query if not in future
        if ($annivEnd['year'] < date('Y') || ($annivEnd['year'] == date('Y') && $annivEnd['mon'] <= date('m') && $annivEnd['mday'] <= date('d'))) {
            $sWhereExt .= "AND  fam_WeddingDate <= '".InputUtils::LegacyFilterInput($_POST['AnniversaryDate2'], 'char', 10)."' ";
        } else {
            $refDate = getdate(strtotime($_POST['AnniversaryDate2']));
            $sWhereExt .= "AND  DAYOFYEAR(fam_WeddingDate) <= DAYOFYEAR('".InputUtils::LegacyFilterInput($_POST['AnniversaryDate2'], 'char', 10)."') ";
        }
    }

    if (!empty($_POST['EnterDate1'])) {
        $sWhereExt .= "AND per_DateEntered >= '".InputUtils::LegacyFilterInput($_POST['EnterDate1'], 'char', 10)."' ";
    }
    if ($_POST['EnterDate2'] != date('Y-m-d')) {
        $sWhereExt .= "AND per_DateEntered <= '".InputUtils::LegacyFilterInput($_POST['EnterDate2'], 'char', 10)."' ";
    }
}

if ($sFormat == 'addtocart') {
    // Get individual records to add to the cart

    $sSQL = "SELECT per_ID FROM $sPerTable $sJoinFamTable WHERE 1 = 1 $sWhereExt $sGroupBy";
    $sSQL .= ' ORDER BY per_LastName';
    $rsLabelsToWrite = RunQuery($sSQL);
    while ($aRow = mysqli_fetch_array($rsLabelsToWrite)) {
        extract($aRow);
        Cart::AddPerson($per_ID);
    }
    //// TODO: do this in API
    RedirectUtils::Redirect("v2/cart");
} else {
    // Build the complete SQL statement

    if ($sFormat == 'rollup') {
        $sSQL = "(SELECT *, 0 AS memberCount, per_LastName AS SortMe FROM $sPerTable $sJoinFamTable WHERE per_fam_ID = 0 $sWhereExt)
		UNION (SELECT *, COUNT(*) AS memberCount, fam_Name AS SortMe FROM $sPerTable $sJoinFamTable WHERE per_fam_ID > 0 $sWhereExt GROUP BY per_fam_ID HAVING memberCount = 1)
		UNION (SELECT *, COUNT(*) AS memberCount, fam_Name AS SortMe FROM $sPerTable $sJoinFamTable WHERE per_fam_ID > 0 $sWhereExt GROUP BY per_fam_ID HAVING memberCount > 1) ORDER BY SortMe";
    } else {
        $sSQL = "SELECT * FROM $sPerTable $sJoinFamTable WHERE 1 = 1 $sWhereExt $sGroupBy ORDER BY per_LastName";
    }

    //Execute whatever SQL was entered
    $rsLabelsToWrite = RunQuery($sSQL);

    //Produce Header Based on Selected Fields
    if ($sFormat == 'rollup') {
        $headerString = '"'.InputUtils::translate_special_charset("Name").'"'.$delimiter;
    } else {
        $headerString = '"'.InputUtils::translate_special_charset("Last Name").'"'.$delimiter;
        if (!empty($_POST['Title'])) {
            $headerString .= '"'.InputUtils::translate_special_charset("Title").'"'.$delimiter;
        }
        if (!empty($_POST['FirstName'])) {
            $headerString .= '"'.InputUtils::translate_special_charset("First Name").'"'.$delimiter;
        }
        if (!empty($_POST['Suffix'])) {
            $headerString .= '"'.InputUtils::translate_special_charset("Suffix").'"'.$delimiter;
        }
        if (!empty($_POST['MiddleName'])) {
            $headerString .= '"'.InputUtils::translate_special_charset("Middle Name").'"'.$delimiter;
        }
    }

    if (!empty($_POST['Address1'])) {
        $headerString .= '"'.InputUtils::translate_special_charset("Address 1").'"'.$delimiter;
    }
    if (!empty($_POST['Address2'])) {
        $headerString .= '"'.InputUtils::translate_special_charset("Address 2").'"'.$delimiter;
    }
    if (!empty($_POST['City'])) {
        $headerString .= '"'.InputUtils::translate_special_charset("City").'"'.$delimiter;
    }
    if (!empty($_POST['State'])) {
        $headerString .= '"'.InputUtils::translate_special_charset("State").'"'.$delimiter;
    }
    if (!empty($_POST['Zip'])) {
        $headerString .= '"'.InputUtils::translate_special_charset("Zip").'"'.$delimiter;
    }
    if (!empty($_POST['Country'])) {
        $headerString .= '"'.InputUtils::translate_special_charset("Country").'"'.$delimiter;
    }
    if (!empty($_POST['HomePhone'])) {
        $headerString .= '"'.InputUtils::translate_special_charset("Home Phone").'"'.$delimiter;
    }
    if (!empty($_POST['WorkPhone'])) {
        $headerString .= '"'.InputUtils::translate_special_charset("Work Phone").'"'.$delimiter;
    }
    if (!empty($_POST['CellPhone'])) {
        $headerString .= '"'.InputUtils::translate_special_charset("Cell Phone").'"'.$delimiter;
    }
    if (!empty($_POST['Email'])) {
        $headerString .= '"'.InputUtils::translate_special_charset("Email").'"'.$delimiter;
    }
    if (!empty($_POST['WorkEmail'])) {
        $headerString .= '"'.InputUtils::translate_special_charset("Work Email").'"'.$delimiter;
    }
    if (!empty($_POST['Envelope'])) {
        $headerString .= '"'.InputUtils::translate_special_charset("Envelope Number").'"'.$delimiter;
    }
    if (!empty($_POST['MembershipDate'])) {
        $headerString .= '"'.InputUtils::translate_special_charset("MembershipDate").'"'.$delimiter;
    }

    if ($sFormat == 'default') {
        if (!empty($_POST['BirthdayDate'])) {
            $headerString .= '"'.InputUtils::translate_special_charset("Birth Date").'"'.$delimiter;
        }
        if (!empty($_POST['Age'])) {
            $headerString .= '"'.InputUtils::translate_special_charset("Age").'"'.$delimiter;
        }
        if (!empty($_POST['PrintMembershipStatus'])) {
            $headerString .= '"'.InputUtils::translate_special_charset("Classification").'"'.$delimiter;
        }
        if (!empty($_POST['PrintFamilyRole'])) {
            $headerString .= '"'.InputUtils::translate_special_charset("Family Role").'"'.$delimiter;
        }
        if (!empty($_POST['PrintGender'])) {
            $headerString .= '"'.InputUtils::translate_special_charset("Gender").'"'.$delimiter;
        }
    } else {
        if (!empty($_POST['Birthday Date'])) {
            $headerString .= '"'.InputUtils::translate_special_charset("AnnivDate").'"'.$delimiter;
        }
        if (!empty($_POST['Age'])) {
            $headerString .= '"'.InputUtils::translate_special_charset("Anniv").'"'.$delimiter;
        }
    }

    // Add any custom field names to the header, unless using family roll-up mode
    $bUsedCustomFields = false;
    if ($sFormat == 'default') {
        while ($aRow = mysqli_fetch_array($rsCustomFields)) {
            extract($aRow);
            if (isset($_POST["$custom_Field"])) {
                $bUsedCustomFields = true;
                $headerString .= "\"".InputUtils::translate_special_charset($custom_Name)."\"".$delimiter;
            }
        }
        while ($aFamRow = mysqli_fetch_array($rsFamCustomFields)) {
            extract($aFamRow);
            if (($aSecurityType[$fam_custom_FieldSec] == 'bAll') || ($_SESSION[$aSecurityType[$fam_custom_FieldSec]])) {
                if (isset($_POST["$fam_custom_Field"])) {
                    $bUsedCustomFields = true;
                    $headerString .= "\"".InputUtils::translate_special_charset($fam_custom_Name)."\"".$delimiter;
                }
            }
        }
    }
    // Add any family custom fields names to the header
    if ($sFormat == 'rollup') {
        while ($aFamRow = mysqli_fetch_array($rsFamCustomFields)) {
            extract($aFamRow);
            if (($aSecurityType[$fam_custom_FieldSec] == 'bAll') || ($_SESSION[$aSecurityType[$fam_custom_FieldSec]])) {
                if (isset($_POST["$fam_custom_Field"])) {
                    $bUsedCustomFields = true;
                    $headerString .= "\"".InputUtils::translate_special_charset($fam_custom_Name)."\"".$delimiter;
                }
            }
        }
    }

    $headerString = mb_substr($headerString, 0, -1);
    $headerString .= "\n";

    header('Content-type: text/x-csv;charset='.SystemConfig::getValue("sCSVExportCharset"));
    header('Content-Disposition: attachment; filename=churchcrm-export-'.date(SystemConfig::getValue("sDateFilenameFormat")).'.csv');

    //add BOM to fix UTF-8 in Excel 2016 but not under, so the problem is solved with the sCSVExportCharset variable
    if (SystemConfig::getValue("sCSVExportCharset") == "UTF-8") {
        echo "\xEF\xBB\xBF";
    }


    echo $headerString;

    while ($aRow = mysqli_fetch_array($rsLabelsToWrite)) {
        $per_Title = '';
        $per_FirstName = '';
        $per_MiddleName = '';
        $per_LastName = '';
        $per_Suffix = '';
        $per_Address1 = '';
        $per_Address2 = '';
        $per_City = '';
        $per_State = '';
        $per_Zip = '';
        $per_Country = '';
        $per_HomePhone = '';
        $per_WorkPhone = '';
        $per_CellPhone = '';
        $per_Email = '';
        $per_WorkEmail = '';
        $fam_Envelope = '';
        $per_MembershipDate = '';

        $per_BirthDay = '';
        $per_BirthMonth = '';
        $per_BirthYear = '';

        $fam_Address1 = '';
        $fam_Address2 = '';
        $fam_City = '';
        $fam_State = '';
        $fam_Zip = '';
        $fam_Country = '';
        $fam_HomePhone = '';
        $fam_WorkPhone = '';
        $fam_CellPhone = '';
        $fam_Email = '';
        $fam_WeddingDate = '';

        $sCountry = '';

        extract($aRow);
        $person = ChurchCRM\PersonQuery::create()->findOneById($per_ID);

        // If we are doing a family roll-up, we want to favor available family address / phone numbers over the individual data returned
        if ($sFormat == 'rollup') {
            $sPhoneCountry = SelectWhichInfo($fam_Country, $per_Country, false);
            $sHomePhone = SelectWhichInfo(ExpandPhoneNumber($fam_HomePhone, $fam_Country, $dummy), ExpandPhoneNumber($per_HomePhone, $sPhoneCountry, $dummy), false);
            $sWorkPhone = SelectWhichInfo(ExpandPhoneNumber($fam_WorkPhone, $fam_Country, $dummy), ExpandPhoneNumber($per_WorkPhone, $sPhoneCountry, $dummy), false);
            $sCellPhone = SelectWhichInfo(ExpandPhoneNumber($fam_CellPhone, $fam_Country, $dummy), ExpandPhoneNumber($per_CellPhone, $sPhoneCountry, $dummy), false);
            $sCountry = SelectWhichInfo($fam_Country, $per_Country, false);
            SelectWhichAddress($sAddress1, $sAddress2, $fam_Address1, $fam_Address2, $per_Address1, $per_Address2, false);
            $sCity = SelectWhichInfo($fam_City, $per_City, false);
            $sState = SelectWhichInfo($fam_State, $per_State, false);
            $sZip = SelectWhichInfo($fam_Zip, $per_Zip, false);
            $sEmail = SelectWhichInfo($fam_Email, $per_Email, false);
        }
        // Otherwise, the individual data gets precedence over the family data
        else {
            $sPhoneCountry = SelectWhichInfo($per_Country, $fam_Country, false);
            $sHomePhone = SelectWhichInfo(ExpandPhoneNumber($per_HomePhone, $sPhoneCountry, $dummy), ExpandPhoneNumber($fam_HomePhone, $fam_Country, $dummy), false);
            $sWorkPhone = SelectWhichInfo(ExpandPhoneNumber($per_WorkPhone, $sPhoneCountry, $dummy), ExpandPhoneNumber($fam_WorkPhone, $fam_Country, $dummy), false);
            $sCellPhone = SelectWhichInfo(ExpandPhoneNumber($per_CellPhone, $sPhoneCountry, $dummy), ExpandPhoneNumber($fam_CellPhone, $fam_Country, $dummy), false);
            $sCountry = SelectWhichInfo($per_Country, $fam_Country, false);
            SelectWhichAddress($sAddress1, $sAddress2, $per_Address1, $per_Address2, $fam_Address1, $fam_Address2, false);
            $sCity = SelectWhichInfo($per_City, $fam_City, false);
            $sState = SelectWhichInfo($per_State, $fam_State, false);
            $sZip = SelectWhichInfo($per_Zip, $fam_Zip, false);
            $sEmail = SelectWhichInfo($per_Email, $fam_Email, false);
        }

        // Check if we're filtering out people with incomplete addresses
        if (!($bSkipIncompleteAddr && (strlen($sCity) == 0 || strlen($sState) == 0 || strlen($sZip) == 0 || (strlen($sAddress1) == 0 && strlen($sAddress2) == 0)))) {
            // Check if we're filtering out people with no envelope number assigned
            // ** should move this to the WHERE clause
            if (!($bSkipNoEnvelope && (strlen($fam_Envelope) == 0))) {
                // If we are doing family roll-up, we use a single, formatted name field
                if ($sFormat == 'default') {
                    $sString = '"'.$per_LastName;
                    if (isset($_POST['Title'])) {
                        $sString .= '"'.$delimiter.'"'.InputUtils::translate_special_charset($per_Title);
                    }
                    if (isset($_POST['FirstName'])) {
                        $sString .= '"'.$delimiter.'"'.InputUtils::translate_special_charset($per_FirstName);
                    }
                    if (isset($_POST['Suffix'])) {
                        $sString .= '"'.$delimiter.'"'.InputUtils::translate_special_charset($per_Suffix);
                    }
                    if (isset($_POST['MiddleName'])) {
                        $sString .= '"'.$delimiter.'"'.InputUtils::translate_special_charset($per_MiddleName);
                    }
                } elseif ($sFormat == 'rollup') {
                    if ($memberCount > 1) {
                        $sString = '"'.MakeSalutationUtility($fam_ID);
                    } else {
                        $sString = '"'.$per_LastName.', '.$per_FirstName;
                    }
                }

                if (isset($_POST['Address1'])) {
                    $sString .= '"'.$delimiter.'"'.InputUtils::translate_special_charset($sAddress1);
                }
                if (isset($_POST['Address2'])) {
                    $sString .= '"'.$delimiter.'"'.InputUtils::translate_special_charset($sAddress2);
                }
                if (isset($_POST['City'])) {
                    $sString .= '"'.$delimiter.'"'.InputUtils::translate_special_charset($sCity);
                }
                if (isset($_POST['State'])) {
                    $sString .= '"'.$delimiter.'"'.InputUtils::translate_special_charset($sState);
                }
                if (isset($_POST['Zip'])) {
                    $sString .= '"'.$delimiter.'"'.$sZip;
                }
                if (isset($_POST['Country'])) {
                    $sString .= '"'.$delimiter.'"'.InputUtils::translate_special_charset($sCountry);
                }
                if (isset($_POST['HomePhone'])) {
                    $sString .= '"'.$delimiter.'"'.$sHomePhone;
                }
                if (isset($_POST['WorkPhone'])) {
                    $sString .= '"'.$delimiter.'"'.$sWorkPhone;
                }
                if (isset($_POST['CellPhone'])) {
                    $sString .= '"'.$delimiter.'"'.$sCellPhone;
                }
                if (isset($_POST['Email'])) {
                    $sString .= '"'.$delimiter.'"'.$sEmail;
                }
                if (isset($_POST['WorkEmail'])) {
                    $sString .= '"'.$delimiter.'"'.$per_WorkEmail;
                }
                if (isset($_POST['Envelope'])) {
                    $sString .= '"'.$delimiter.'"'.$fam_Envelope;
                }
                if (isset($_POST['MembershipDate'])) {
                    $sString .= '"'.$delimiter.'"'.$per_MembershipDate;
                }

                if ($sFormat == 'default') {
                    if (isset($_POST['BirthdayDate'])) {
                        $sString .= '"'.$delimiter.'"';
                        if ($per_BirthYear != '') {
                            $sString .= $per_BirthYear.'-';
                        } else {
                            $sString .= '0000-';
                        }
                        $sString .= $per_BirthMonth.'-'.$per_BirthDay;
                    }

                    if (isset($_POST['Age'])) {
                        if (isset($per_BirthYear)) {
                            $age = MiscUtils::FormatAge($per_BirthMonth, $per_BirthDay, $per_BirthYear, 0);
                        } else {
                            $age = '';
                        }

                        $sString .= '"'.$delimiter.'"'.$age;
                    }

                    if (isset($_POST['PrintMembershipStatus'])) {
                        $sString .= '"'.$delimiter.'"'.InputUtils::translate_special_charset($memberClass[$per_cls_ID]);
                    }
                    if (isset($_POST['PrintFamilyRole'])) {
                        $sString .= '"'.$delimiter.'"'.InputUtils::translate_special_charset($familyRoles[$per_fmr_ID]);
                    }
                    if (isset($_POST['PrintGender'])) {
                        $sString .= '"'.$delimiter.'"'.InputUtils::translate_special_charset($person->getGenderName());
                    }
                } else {
                    if (isset($_POST['BirthdayDate'])) {
                        $sString .= '"'.$delimiter.'"'.$fam_WeddingDate;
                    }

                    if (isset($_POST['Age'])) {
                        if (isset($fam_WeddingDate)) {
                            $annivDate = getdate(strtotime($fam_WeddingDate));
                            $age = $refDate['year'] - $annivDate['year'] - ($annivDate['mon'] > $refDate['mon'] || ($annivDate['mon'] == $refDate['mon'] && $annivDate['mday'] > $refDate['mday']));
                        } else {
                            $age = '';
                        }

                        $sString .= '"'.$delimiter.'"'.$age;
                    }
                }

                if ($bUsedCustomFields && ($sFormat == 'default')) {
                    $sSQLcustom = 'SELECT * FROM person_custom WHERE per_ID = '.$per_ID;
                    $rsCustomData = RunQuery($sSQLcustom);
                    $aCustomData = mysqli_fetch_array($rsCustomData);

                    if (mysqli_num_rows($rsCustomData) > 0) {
                        // Write custom field data
                        mysqli_data_seek($rsCustomFields, 0);
                        while ($aCustomField = mysqli_fetch_array($rsCustomFields)) {
                            $custom_Field = '';
                            $custom_Special = '';
                            $type_ID = '';

                            extract($aCustomField);
                            if ($aSecurityType[$custom_FieldSec] == 'bAll' || $_SESSION[$aSecurityType[$custom_FieldSec]]) {
                                if (isset($_POST["$custom_Field"])) {
                                    if ($type_ID == 11) {
                                        $custom_Special = $sCountry;
                                    }
                                    $sString .= '"'.$delimiter.'"'.InputUtils::translate_special_charset(displayCustomField($type_ID, trim($aCustomData[$custom_Field]), $custom_Special));
                                }
                            }
                        }
                    }

                    $sSQLFamCustom = 'SELECT * FROM family_custom WHERE fam_ID = '.$per_fam_ID;
                    $rsFamCustomData = RunQuery($sSQLFamCustom);
                    $aFamCustomData = mysqli_fetch_array($rsFamCustomData);

                    if (@mysqli_num_rows($rsFamCustomData) > 0) {
                        // Write custom field data
                        mysqli_data_seek($rsFamCustomFields, 0);
                        while ($aFamCustomField = mysqli_fetch_array($rsFamCustomFields)) {
                            $fam_custom_Field = '';
                            $fam_custom_Special = '';
                            $type_ID = '';

                            extract($aFamCustomField);
                            if (isset($_POST["$fam_custom_Field"])) {
                                if ($type_ID == 11) {
                                    $fam_custom_Special = $sCountry;
                                }
                                $sString .= '"'.$delimiter.'"'.InputUtils::translate_special_charset(displayCustomField($type_ID, trim($aFamCustomData[$fam_custom_Field]), $fam_custom_Special));
                            }
                        }
                    }
                }

                if ($bUsedCustomFields && ($sFormat == 'rollup')) {
                    $sSQLFamCustom = 'SELECT * FROM family_custom WHERE fam_ID = '.$per_fam_ID;
                    $rsFamCustomData = RunQuery($sSQLFamCustom);
                    $aFamCustomData = mysqli_fetch_array($rsFamCustomData);

                    if (@mysqli_num_rows($rsFamCustomData) > 0) {
                        // Write custom field data
                        mysqli_data_seek($rsFamCustomFields, 0);
                        while ($aFamCustomField = mysqli_fetch_array($rsFamCustomFields)) {
                            $fam_custom_Field = '';
                            $fam_custom_Special = '';
                            $type_ID = '';

                            extract($aFamCustomField);
                            if (isset($_POST["$fam_custom_Field"])) {
                                if ($type_ID == 11) {
                                    $fam_custom_Special = $sCountry;
                                }
                                $sString .= '"'.$delimiter.'"'.InputUtils::translate_special_charset(displayCustomField($type_ID, trim($aFamCustomData[$fam_custom_Field]), $fam_custom_Special));
                            }
                        }
                    }
                }

                $sString .= "\"\n";
                echo $sString;
            }
        }
    }
}

// Turn OFF output buffering
ob_end_flush();
