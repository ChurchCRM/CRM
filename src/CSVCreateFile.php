<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\dto\Cart;
use ChurchCRM\dto\Classification;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Base\PersonQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\MiscUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Utils\CsvExporter;

// Initialize data collection arrays
$headers = [];
$rows = [];

// Get Source and Format from the request object and assign them locally
$sSource = strtolower($_POST['Source']);
$sFormat = strtolower($_POST['Format']);
$bSkipIncompleteAddr = isset($_POST['SkipIncompleteAddr']);
$bSkipNoEnvelope = isset($_POST['SkipNoEnvelope']);

// Get the custom fields
if ($sFormat === 'default') {
    $sSQL = 'SELECT * FROM person_custom_master ORDER BY custom_Order';
    $rsCustomFields = RunQuery($sSQL);

    $sSQL = 'SELECT * FROM family_custom_master ORDER BY fam_custom_Order';
    $rsFamCustomFields = RunQuery($sSQL);
}
if ($sFormat === 'rollup') {
    $sSQL = 'SELECT * FROM family_custom_master ORDER BY fam_custom_Order';
    $rsFamCustomFields = RunQuery($sSQL);
}

//Get membership classes
$memberClass = [0];
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

// Prepare the MySQL query
$sJoinFamTable = ' LEFT JOIN family_fam ON per_fam_ID = fam_ID ';
$sPerTable = 'person_per';

// If our source is the cart contents, we don't need to build a WHERE filter string
if ($sSource === 'cart') {
    $sWhereExt = 'AND per_ID IN (' . convertCartToString($_SESSION['aPeopleCart']) . ')';
} else {
    // If we're filtering by groups, include the p2g2r table
    if (!empty($_POST['GroupID'])) {
        $sPerTable = '(person_per, person2group2role_p2g2r)';
    }

    // Prepare any extensions to the WHERE clauses
    $sWhereExt = '';
    if (!empty($_POST['Classification'])) {
        $count = 0;
        foreach ($_POST['Classification'] as $Cls) {
            $Class[$count++] = InputUtils::legacyFilterInput($Cls, 'int');
        }
        if ($count === 1) {
            if ($Class[0]) {
                $sWhereExt .= 'AND per_cls_ID = ' . $Class[0] . ' ';
            }
        } else {
            $sWhereExt .= 'AND (per_cls_ID = ' . $Class[0];
            for ($i = 1; $i < $count; $i++) {
                $sWhereExt .= ' OR per_cls_ID = ' . $Class[$i];
            }
            $sWhereExt .= ') ';
            // this is silly: should be something like..  $sWhereExt .= "AND per_cls_ID IN
        }
    }

    if (!empty($_POST['FamilyRole'])) {
        $count = 0;
        foreach ($_POST['FamilyRole'] as $Fmr) {
            $Class[$count++] = InputUtils::legacyFilterInput($Fmr, 'int');
        }
        if ($count === 1) {
            if ($Class[0]) {
                $sWhereExt .= 'AND per_fmr_ID = ' . $Class[0] . ' ';
            }
        } else {
            $sWhereExt .= 'AND (per_fmr_ID = ' . $Class[0];
            for ($i = 1; $i < $count; $i++) {
                $sWhereExt .= ' OR per_fmr_ID = ' . $Class[$i];
            }
            $sWhereExt .= ') ';
        }
    }

    if (!empty($_POST['Gender'])) {
        $sWhereExt .= 'AND per_Gender = ' . InputUtils::legacyFilterInput($_POST['Gender'], 'int') . ' ';
    }

    if (!empty($_POST['GroupID'])) {
        $count = 0;
        foreach ($_POST['GroupID'] as $Grp) {
            $Class[$count++] = InputUtils::legacyFilterInput($Grp, 'int');
        }
        if ($count === 1) {
            if ($Class[0]) {
                $sWhereExt .= 'AND per_ID = p2g2r_per_ID AND p2g2r_grp_ID = ' . $Class[0] . ' ';
            }
        } else {
            $sWhereExt .= 'AND per_ID = p2g2r_per_ID AND (p2g2r_grp_ID = ' . $Class[0];
            for ($i = 1; $i < $count; $i++) {
                $sWhereExt .= ' OR p2g2r_grp_ID = ' . $Class[$i];
            }
            $sWhereExt .= ') ';
        }

        // This is used for individual mode to remove duplicate rows from people assigned multiple groups.
        $sGroupBy = ' GROUP BY per_ID';
    } else {
        $sGroupBy = '';
    }

    if (!empty($_POST['MembershipDate1'])) {
        $sWhereExt .= "AND per_MembershipDate >= '" . InputUtils::legacyFilterInput($_POST['MembershipDate1'], 'char', 10) . "' ";
    }
    if ($_POST['MembershipDate2'] != date('Y-m-d')) {
        $sWhereExt .= "AND per_MembershipDate <= '" . InputUtils::legacyFilterInput($_POST['MembershipDate2'], 'char', 10) . "' ";
    }

    $refDate = getdate(time());

    if (!empty($_POST['BirthDate1'])) {
        $sWhereExt .= "AND DATE_FORMAT(CONCAT(per_BirthYear,'-',per_BirthMonth,'-',per_BirthDay),'%Y-%m-%d') >= '" . InputUtils::legacyFilterInput($_POST['BirthDate1'], 'char', 10) . "' ";
    }

    if ($_POST['BirthDate2'] != date('Y-m-d')) {
        $sWhereExt .= "AND DATE_FORMAT(CONCAT(per_BirthYear,'-',per_BirthMonth,'-',per_BirthDay),'%Y-%m-%d') <= '" . InputUtils::legacyFilterInput($_POST['BirthDate2'], 'char', 10) . "' ";
    }

    if (!empty($_POST['AnniversaryDate1'])) {
        $annivStart = getdate(strtotime(InputUtils::legacyFilterInput($_POST['AnniversaryDate1'])));

        // Add year to query if not in future
        if ($annivStart['year'] < date('Y') || ($annivStart['year'] == date('Y') && $annivStart['mon'] <= date('m') && $annivStart['mday'] <= date('d'))) {
            $sWhereExt .= "AND fam_WeddingDate >= '" . InputUtils::legacyFilterInput($_POST['AnniversaryDate1'], 'char', 10) . "' ";
        } else {
            $sWhereExt .= "AND DAYOFYEAR(fam_WeddingDate) >= DAYOFYEAR('" . InputUtils::legacyFilterInput($_POST['AnniversaryDate1'], 'char', 10) . "') ";
        }
    }

    if ($_POST['AnniversaryDate2'] != date('Y-m-d')) {
        $annivEnd = getdate(strtotime(InputUtils::legacyFilterInput($_POST['AnniversaryDate2'], 'char', 10)));

        // Add year to query if not in future
        if ($annivEnd['year'] < date('Y') || ($annivEnd['year'] == date('Y') && $annivEnd['mon'] <= date('m') && $annivEnd['mday'] <= date('d'))) {
            $sWhereExt .= "AND  fam_WeddingDate <= '" . InputUtils::legacyFilterInput($_POST['AnniversaryDate2'], 'char', 10) . "' ";
        } else {
            $refDate = getdate(strtotime($_POST['AnniversaryDate2']));
            $sWhereExt .= "AND  DAYOFYEAR(fam_WeddingDate) <= DAYOFYEAR('" . InputUtils::legacyFilterInput($_POST['AnniversaryDate2'], 'char', 10) . "') ";
        }
    }

    if (!empty($_POST['EnterDate1'])) {
        $sWhereExt .= "AND per_DateEntered >= '" . InputUtils::legacyFilterInput($_POST['EnterDate1'], 'char', 10) . "' ";
    }
    if ($_POST['EnterDate2'] != date('Y-m-d')) {
        $sWhereExt .= "AND per_DateEntered <= '" . InputUtils::legacyFilterInput($_POST['EnterDate2'], 'char', 10) . "' ";
    }
}

if ($sFormat === 'addtocart') {
    // Get individual records to add to the cart

    $sSQL = "SELECT per_ID FROM $sPerTable $sJoinFamTable WHERE 1 = 1 $sWhereExt $sGroupBy";
    $sSQL .= ' ORDER BY per_LastName';
    $rsLabelsToWrite = RunQuery($sSQL);
    while ($aRow = mysqli_fetch_array($rsLabelsToWrite)) {
        extract($aRow);
        Cart::addPerson($per_ID);
    }
    RedirectUtils::redirect('v2/cart');
} else {
    // Build the complete SQL statement

    if ($sFormat === 'rollup') {
        $sSQL = "(SELECT *, 0 AS memberCount, per_LastName AS SortMe FROM $sPerTable $sJoinFamTable WHERE per_fam_ID = 0 $sWhereExt)
        UNION (SELECT *, COUNT(*) AS memberCount, fam_Name AS SortMe FROM $sPerTable $sJoinFamTable WHERE per_fam_ID > 0 $sWhereExt GROUP BY per_fam_ID HAVING memberCount = 1)
        UNION (SELECT *, COUNT(*) AS memberCount, fam_Name AS SortMe FROM $sPerTable $sJoinFamTable WHERE per_fam_ID > 0 $sWhereExt GROUP BY per_fam_ID HAVING memberCount > 1) ORDER BY SortMe";
    } else {
        $sSQL = "SELECT * FROM $sPerTable $sJoinFamTable WHERE 1 = 1 $sWhereExt $sGroupBy ORDER BY per_LastName";
    }

    //Execute whatever SQL was entered
    $rsLabelsToWrite = RunQuery($sSQL);

    // Build headers array
    // Note: CsvExporter will handle gettext localization, charset translation, and formula injection escaping
    $headers[] = 'Family ID';
    
    if ($sFormat === 'rollup') {
        $headers[] = 'Name';
        $headers[] = 'Salutation';
    } else {
        $headers[] = 'Person Id';
        $headers[] = 'Last Name';
        if (!empty($_POST['Title'])) {
            $headers[] = 'Title';
        }
        if (!empty($_POST['FirstName'])) {
            $headers[] = 'First Name';
        }
        if (!empty($_POST['Suffix'])) {
            $headers[] = 'Suffix';
        }
        if (!empty($_POST['MiddleName'])) {
            $headers[] = 'Middle Name';
        }
    }

    if (!empty($_POST['Address1'])) {
        $headers[] = 'Address 1';
    }
    if (!empty($_POST['Address2'])) {
        $headers[] = 'Address 2';
    }
    if (!empty($_POST['City'])) {
        $headers[] = 'City';
    }
    if (!empty($_POST['State'])) {
        $headers[] = 'State';
    }
    if (!empty($_POST['Zip'])) {
        $headers[] = 'Zip';
    }
    if (!empty($_POST['Country'])) {
        $headers[] = 'Country';
    }
    if (!empty($_POST['HomePhone'])) {
        $headers[] = 'Home Phone';
    }
    if (!empty($_POST['WorkPhone'])) {
        $headers[] = 'Work Phone';
    }
    if (!empty($_POST['CellPhone'])) {
        $headers[] = 'Cell Phone';
    }
    if (!empty($_POST['Email'])) {
        $headers[] = 'Email';
    }
    if (!empty($_POST['WorkEmail'])) {
        $headers[] = 'Work Email';
    }
    if (!empty($_POST['Envelope'])) {
        $headers[] = 'Envelope Number';
    }
    if (!empty($_POST['MembershipDate'])) {
        $headers[] = 'MembershipDate';
    }

    if ($sFormat === 'default') {
        if (!empty($_POST['BirthdayDate'])) {
            $headers[] = 'Birth Date';
        }
        if (!empty($_POST['Age'])) {
            $headers[] = 'Age';
        }
        if (!empty($_POST['PrintMembershipStatus'])) {
            $headers[] = 'Classification';
        }
        if (!empty($_POST['PrintFamilyRole'])) {
            $headers[] = 'Family Role';
        }
        if (!empty($_POST['PrintGender'])) {
            $headers[] = 'Gender';
        }
    } else {
        if (!empty($_POST['Birthday Date'])) {
            $headers[] = 'AnnivDate';
        }
        if (!empty($_POST['Age'])) {
            $headers[] = 'Anniv';
        }
    }

    // Add any custom field names to the header, unless using family roll-up mode
    $bUsedCustomFields = false;
    if ($sFormat === 'default') {
        while ($aRow = mysqli_fetch_array($rsCustomFields)) {
            extract($aRow);
            if (isset($_POST["$custom_Field"])) {
                $bUsedCustomFields = true;
                $headers[] = $custom_Name;
            }
        }
        while ($aFamRow = mysqli_fetch_array($rsFamCustomFields)) {
            extract($aFamRow);
            if (($aSecurityType[$fam_custom_FieldSec] == 'bAll') || $_SESSION[$aSecurityType[$fam_custom_FieldSec]]) {
                if (isset($_POST["$fam_custom_Field"])) {
                    $bUsedCustomFields = true;
                    $headers[] = $fam_custom_Name;
                }
            }
        }
    }
    // Add any family custom fields names to the header
    if ($sFormat === 'rollup') {
        while ($aFamRow = mysqli_fetch_array($rsFamCustomFields)) {
            extract($aFamRow);
            if (($aSecurityType[$fam_custom_FieldSec] == 'bAll') || $_SESSION[$aSecurityType[$fam_custom_FieldSec]]) {
                if (isset($_POST["$fam_custom_Field"])) {
                    $bUsedCustomFields = true;
                    $headers[] = $fam_custom_Name;
                }
            }
        }
    }


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
        $person = PersonQuery::create()->findOneById($per_ID);

        // If we are doing a family roll-up, we want to favor available family address / phone numbers over the individual data returned
        if ($sFormat === 'rollup') {
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
        } else {
            // Otherwise, the individual data gets precedence over the family data
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
        if (!($bSkipIncompleteAddr && (strlen($sCity) === 0 || strlen($sState) === 0 || strlen($sZip) === 0 || (strlen($sAddress1) === 0 && strlen($sAddress2) === 0)))) {
            // Check if we're filtering out people with no envelope number assigned
            // ** should move this to the WHERE clause
            if (!($bSkipNoEnvelope && (strlen($fam_Envelope) === 0))) {
                // Build row as array
                $row = [];
                
                // Family ID
                $row[] = $fam_ID ? (string) $fam_ID : '';
                
                if ($sFormat === 'default') {
                    $row[] = (string) $per_ID;
                    $row[] = $per_LastName;
                    if (isset($_POST['Title'])) {
                        $row[] = $per_Title;
                    }
                    if (isset($_POST['FirstName'])) {
                        $row[] = $per_FirstName;
                    }
                    if (isset($_POST['Suffix'])) {
                        $row[] = $per_Suffix;
                    }
                    if (isset($_POST['MiddleName'])) {
                        $row[] = $per_MiddleName;
                    }
                } elseif ($sFormat === 'rollup') {
                    $family = FamilyQuery::create()->findPk($fam_ID);
                    if ($memberCount > 1) {
                        $row[] = $family->getSalutation();
                        $row[] = $family->getFirstNameSalutation();
                    } else {
                        $row[] = $per_FirstName . ' ' . $per_LastName;
                        $row[] = $per_FirstName;
                    }
                }

                if (isset($_POST['Address1'])) {
                    $row[] = $sAddress1;
                }
                if (isset($_POST['Address2'])) {
                    $row[] = $sAddress2;
                }
                if (isset($_POST['City'])) {
                    $row[] = $sCity;
                }
                if (isset($_POST['State'])) {
                    $row[] = $sState;
                }
                if (isset($_POST['Zip'])) {
                    $row[] = $sZip;
                }
                if (isset($_POST['Country'])) {
                    $row[] = $sCountry;
                }
                if (isset($_POST['HomePhone'])) {
                    $row[] = $sHomePhone;
                }
                if (isset($_POST['WorkPhone'])) {
                    $row[] = $sWorkPhone;
                }
                if (isset($_POST['CellPhone'])) {
                    $row[] = $sCellPhone;
                }
                if (isset($_POST['Email'])) {
                    $row[] = $sEmail;
                }
                if (isset($_POST['WorkEmail'])) {
                    $row[] = $per_WorkEmail;
                }
                if (isset($_POST['Envelope'])) {
                    $row[] = $fam_Envelope;
                }
                if (isset($_POST['MembershipDate'])) {
                    $row[] = $per_MembershipDate;
                }

                if ($sFormat === 'default') {
                    if (isset($_POST['BirthdayDate'])) {
                        $birthDate = '';
                        if ($per_BirthYear != '') {
                            $birthDate = $per_BirthYear . '-';
                        }
                        $birthDate .= $per_BirthMonth . '-' . $per_BirthDay;
                        $row[] = $birthDate;
                    }

                    if (isset($_POST['Age'])) {
                        if (isset($per_BirthYear)) {
                            $age = MiscUtils::formatAge($per_BirthMonth, $per_BirthDay, $per_BirthYear);
                        } else {
                            $age = '';
                        }
                        $row[] = $age;
                    }

                    if (isset($_POST['PrintMembershipStatus'])) {
                        $row[] = $memberClass[$per_cls_ID];
                    }
                    if (isset($_POST['PrintFamilyRole'])) {
                        $row[] = $familyRoles[$per_fmr_ID];
                    }
                    if (isset($_POST['PrintGender'])) {
                        $row[] = $person->getGenderName();
                    }
                } else {
                    if (isset($_POST['BirthdayDate'])) {
                        $row[] = $fam_WeddingDate;
                    }

                    if (isset($_POST['Age'])) {
                        if (isset($fam_WeddingDate)) {
                            $annivDate = getdate(strtotime($fam_WeddingDate));
                            $age = $refDate['year'] - $annivDate['year'] - ($annivDate['mon'] > $refDate['mon'] || ($annivDate['mon'] == $refDate['mon'] && $annivDate['mday'] > $refDate['mday']));
                        } else {
                            $age = '';
                        }
                        $row[] = (string) $age;
                    }
                }

                if ($bUsedCustomFields && ($sFormat == 'default')) {
                    $sSQLcustom = 'SELECT * FROM person_custom WHERE per_ID = ' . $per_ID;
                    $rsCustomData = RunQuery($sSQLcustom);
                    $aCustomData = mysqli_fetch_array($rsCustomData);

                    if (mysqli_num_rows($rsCustomData) > 0) {
                        // Add custom field data
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
                                    $row[] = displayCustomField($type_ID, trim($aCustomData[$custom_Field]), $custom_Special);
                                }
                            }
                        }
                    }

                    $sSQLFamCustom = 'SELECT * FROM family_custom WHERE fam_ID = ' . $per_fam_ID;
                    $rsFamCustomData = RunQuery($sSQLFamCustom);
                    $aFamCustomData = mysqli_fetch_array($rsFamCustomData);

                    if (@mysqli_num_rows($rsFamCustomData) > 0) {
                        // Add custom field data
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
                                $row[] = displayCustomField($type_ID, trim($aFamCustomData[$fam_custom_Field]), $fam_custom_Special);
                            }
                        }
                    }
                }

                if ($bUsedCustomFields && ($sFormat == 'rollup')) {
                    $sSQLFamCustom = 'SELECT * FROM family_custom WHERE fam_ID = ' . $per_fam_ID;
                    $rsFamCustomData = RunQuery($sSQLFamCustom);
                    $aFamCustomData = mysqli_fetch_array($rsFamCustomData);

                    if (@mysqli_num_rows($rsFamCustomData) > 0) {
                        // Add custom field data
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
                                $row[] = displayCustomField($type_ID, trim($aFamCustomData[$fam_custom_Field]), $fam_custom_Special);
                            }
                        }
                    }
                }

                // Add row to collection
                $rows[] = $row;
            }
        }
    }
}

// Use CsvExporter to output CSV with League\CSV
// basename: 'churchcrm-export', includeDateInFilename: true adds today's date, .csv is added automatically
CsvExporter::create($headers, $rows, 'churchcrm-export', 'UTF-8', true);
