<?php
/*******************************************************************************
*
*  filename    : SelectList.php
*  website     : http://www.churchcrm.io
*  copyright   : Copyright 2001-2003 Deane Barker and Chris Gebhardt
*
*  Additional Contributors:
*  2006 Ed Davis
*  2011 Michael Wilt

*  Design notes: this file would benefit from some thoughtful cleanup.  The filter
*  settings are using badly overloaded values, with positive, negative, and not-set
*  all significant.  Originally it relied on the old php behavior of not-set quietly
*  acting as nothing or zero, but the newer version of php does not permit this.
*  Fixing it for the new version of php involved adding a whole lot of calls to
*  isset().
*
******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\UserQuery;
use ChurchCRM\Utils\InputUtils;

$iTenThousand = 10000;  // Constant used to offset negative choices in drop down lists

// Create array with Classification Information (lst_ID = 1)
$sClassSQL = 'SELECT * FROM list_lst WHERE lst_ID=1 ORDER BY lst_OptionSequence';
$rsClassification = RunQuery($sClassSQL);
unset($aClassificationName);
$aClassificationName[0] = gettext('Unassigned');
while ($aRow = mysqli_fetch_array($rsClassification)) {
    extract($aRow);
    $aClassificationName[intval($lst_OptionID)] = $lst_OptionName;
}

// Create array with Family Role Information (lst_ID = 2)
$sFamRoleSQL = 'SELECT * FROM list_lst WHERE lst_ID=2 ORDER BY lst_OptionSequence';
$rsFamilyRole = RunQuery($sFamRoleSQL);
unset($aFamilyRoleName);
$aFamilyRoleName[0] = gettext('Unassigned');
while ($aRow = mysqli_fetch_array($rsFamilyRole)) {
    extract($aRow);
    $aFamilyRoleName[intval($lst_OptionID)] = $lst_OptionName;
}

// Create array with Person Property

 // Get the total number of Person Properties (p) in table Property_pro
$sSQL = 'SELECT * FROM property_pro WHERE pro_Class="p"';
$rsPro = RunQuery($sSQL);
$ProRows = mysqli_num_rows($rsPro);

$sPersonPropertySQL = 'SELECT * FROM property_pro WHERE pro_Class="p" ORDER BY pro_Name';
$rsPersonProperty = RunQuery($sPersonPropertySQL);
unset($aPersonPropertyName);
$aPersonPropertyName[0] = gettext('Unassigned');
$i = 1;
while ($i <= $ProRows) {
    $aRow = mysqli_fetch_array($rsPersonProperty);
    extract($aRow);
    $aPersonPropertyName[intval($pro_ID)] = $pro_Name;
    $i++;
}

// Create array with Group Type Information (lst_ID = 3)
$sGroupTypeSQL = 'SELECT * FROM list_lst WHERE lst_ID=3 ORDER BY lst_OptionSequence';
$rsGroupTypes = RunQuery($sGroupTypeSQL);
unset($aGroupTypes);
while ($aRow = mysqli_fetch_array($rsGroupTypes)) {
    extract($aRow);
    $aGroupTypes[intval($lst_OptionID)] = $lst_OptionName;
}

// Filter received user input as needed
if (isset($_GET['Sort'])) {
    $sSort = InputUtils::LegacyFilterInput($_GET['Sort']);
} else {
    $sSort = 'name';
}

$sBlankLine = '';
$sFilter = '';
$sLetter = '';
$sPrevLetter = '';
if (array_key_exists('Filter', $_GET)) {
    $sFilter = InputUtils::LegacyFilterInput($_GET['Filter']);
}
if (array_key_exists('Letter', $_GET)) {
    $sLetter = mb_strtoupper(InputUtils::LegacyFilterInput($_GET['Letter']));
}

if (array_key_exists('mode', $_GET)) {
    $sMode = InputUtils::LegacyFilterInput($_GET['mode']);
} elseif (array_key_exists('SelectListMode', $_SESSION)) {
    $sMode = $_SESSION['SelectListMode'];
}

switch ($sMode) {
    case 'groupassign':
        $_SESSION['SelectListMode'] = $sMode;
        break;
    case 'family':
        $_SESSION['SelectListMode'] = $sMode;
        break;
    default:
        $_SESSION['SelectListMode'] = 'person';
        break;
}

// Save default search mode
$_SESSION['bSearchFamily'] = ($sMode != 'person');

if (array_key_exists('Number', $_GET)) {
    $_SESSION['SearchLimit'] = InputUtils::LegacyFilterInput($_GET['Number'], 'int');
    $tmpUser = UserQuery::create()->findPk($_SESSION['iUserID']);
    $tmpUser->setSearchLimit($_SESSION['SearchLimit']);
    $tmpUser->save();
}

if (array_key_exists('PersonColumn3', $_GET)) {
    $_SESSION['sPersonColumn3'] = InputUtils::LegacyFilterInput($_GET['PersonColumn3']);
}

if (array_key_exists('PersonColumn5', $_GET)) {
    $_SESSION['sPersonColumn5'] = InputUtils::LegacyFilterInput($_GET['PersonColumn5']);
}

$iGroupTypeMissing = 0;

$iGroupID = -1;
$iRoleID = -1;
$iClassification = -1;
$iFamilyRole = -1;
$iGender = -1;
$iGroupType = -1;

if ($sMode == 'person') {
    // Set the page title
    $sPageTitle = gettext('Person Listing');
    $iMode = 1;

    if (array_key_exists('Classification', $_GET) && $_GET['Classification'] != '') {
        $iClassification = InputUtils::LegacyFilterInput($_GET['Classification'], 'int');
    }
    if (array_key_exists('FamilyRole', $_GET) && $_GET['FamilyRole'] != '') {
        $iFamilyRole = InputUtils::LegacyFilterInput($_GET['FamilyRole'], 'int');
    }
    if (array_key_exists('Gender', $_GET) && $_GET['Gender'] != '') {
        $iGender = InputUtils::LegacyFilterInput($_GET['Gender'], 'int');
    }
    if (array_key_exists('PersonProperties', $_GET) && $_GET['PersonProperties'] != '') {
        $iPersonProperty = InputUtils::LegacyFilterInput($_GET['PersonProperties'], 'int');
    }
    if (array_key_exists('grouptype', $_GET) && $_GET['grouptype'] != '') {
        $iGroupType = InputUtils::LegacyFilterInput($_GET['grouptype'], 'int');
        if (array_key_exists('groupid', $_GET)) {
            $iGroupID = InputUtils::LegacyFilterInput($_GET['groupid'], 'int');
            if ($iGroupID == 0) {
                $iGroupID = -1;
            }
        }
        if (array_key_exists('grouproleid', $_GET) && $_GET['grouproleid'] != '') {
            $iRoleID = InputUtils::LegacyFilterInput($_GET['grouproleid'], 'int');
            if ($iRoleID == 0) {
                $iRoleID = -1;
            }
        }
    }
} elseif ($sMode == 'groupassign') {
    $sPageTitle = gettext('Group Assignment Helper');
    $iMode = 2;

    if (array_key_exists('Classification', $_GET) && $_GET['Classification'] != '') {
        $iClassification = InputUtils::LegacyFilterInput($_GET['Classification'], 'int');
    }
    if (array_key_exists('FamilyRole', $_GET) && $_GET['FamilyRole'] != '') {
        $iFamilyRole = InputUtils::LegacyFilterInput($_GET['FamilyRole'], 'int');
    }
    if (array_key_exists('Gender', $_GET) && $_GET['Gender'] != '') {
        $iGender = InputUtils::LegacyFilterInput($_GET['Gender'], 'int');
    }
    if (array_key_exists('type', $_GET)) {
        $iGroupTypeMissing = InputUtils::LegacyFilterInput($_GET['type'], 'int');
    } else {
        $iGroupTypeMissing = 1;
    }
}

$iPerPage = $_SESSION['SearchLimit'];

$sLimit5 = '';
$sLimit10 = '';
$sLimit20 = '';
$sLimit25 = '';
$sLimit50 = '';
$sLimit100 = '';
$sLimit200 = '';
$sLimit500 = '';

// SQL for group-assignment helper
if ($iMode == 2) {
    $sBaseSQL = 'SELECT *, IF(LENGTH(per_Zip) > 0,per_Zip,fam_Zip) AS zip '.
                'FROM person_per LEFT JOIN family_fam '.
                'ON person_per.per_fam_ID = family_fam.fam_ID ';

    // Find people who are part of a group of the specified type.
    // MySQL doesn't have subqueries until version 4.1.. for now, do it the hard way
    $sSQLsub = 'SELECT per_ID FROM person_per LEFT JOIN person2group2role_p2g2r '.
                'ON p2g2r_per_ID = per_ID LEFT JOIN group_grp '.
                'ON grp_ID = p2g2r_grp_ID '.
                "WHERE grp_Type = $iGroupTypeMissing GROUP BY per_ID";
    $rsSub = RunQuery($sSQLsub);

    if (mysqli_num_rows($rsSub) > 0) {
        $sExcludedIDs = '';
        while ($aTemp = mysqli_fetch_row($rsSub)) {
            $sExcludedIDs .= $aTemp[0].',';
        }
        $sExcludedIDs = mb_substr($sExcludedIDs, 0, -1);
        $sGroupWhereExt = ' AND per_ID NOT IN ('.$sExcludedIDs.')';
    }
}

// SQL for standard Person List
if ($iMode == 1) {
    // Set the base SQL
    $sBaseSQL = 'SELECT *, IF(LENGTH(per_Zip) > 0,per_Zip,fam_Zip) AS zip '.
                'FROM person_per LEFT JOIN family_fam '.
                'ON per_fam_ID = family_fam.fam_ID ';

    $sGroupWhereExt = ''; // Group Filtering Logic
    $sJoinExt = '';
    if (isset($iGroupType)) {
        if ($iGroupType >= 0) {
            $sJoinExt = ' LEFT JOIN person2group2role_p2g2r ON per_ID = p2g2r_per_ID '.
                        ' LEFT JOIN group_grp ON grp_ID = p2g2r_grp_ID ';
            $sGroupWhereExt = ' AND grp_type = '.$iGroupType.' ';

            if ($iGroupID >= 0) {
                if ($iRoleID >= 0) {
                    $sJoinExt = ' LEFT JOIN person2group2role_p2g2r '.
                                ' ON per_ID = p2g2r_per_ID '.
                                ' LEFT JOIN list_lst '.
                                ' ON p2g2r_grp_ID = lst_ID ';
                    $sGroupWhereExt = ' AND p2g2r_grp_ID='.$iGroupID.' '.
                                        ' AND p2g2r_per_ID=per_ID '.
                                        ' AND p2g2r_rle_ID='.$iRoleID.' ';
                } else {
                    $sJoinExt = ' LEFT JOIN person2group2role_p2g2r '.
                                ' ON per_ID = p2g2r_per_ID ';
                    $sGroupWhereExt = ' AND p2g2r_grp_ID='.$iGroupID.' '.
                                        ' AND p2g2r_per_ID = per_ID ';
                }
            } else {
                $sJoinExt = ' LEFT JOIN person2group2role_p2g2r '.
                            ' ON per_ID = p2g2r_per_ID '.
                            ' LEFT JOIN group_grp ON grp_ID = p2g2r_grp_ID ';
                $sGroupWhereExt = ' AND grp_type='.$iGroupType.' '.
                                    ' AND per_ID NOT IN '.
                                    ' (SELECT p2g2r_per_ID FROM person2group2role_p2g2r '.
                                    '  WHERE p2g2r_grp_ID='.($iGroupID + $iTenThousand).') ';
            }
        } else {
            $sJoinExt = ' ';
            $sGroupWhereExt = ' AND per_ID NOT IN (SELECT p2g2r_per_ID '.
                                ' FROM person2group2role_p2g2r '.
                                ' LEFT JOIN group_grp ON grp_ID = p2g2r_grp_ID '.
                                ' WHERE grp_type = '.($iGroupType + $iTenThousand).')';
        }
    }
}

$sPersonPropertyWhereExt = ''; // Person Property Filtering Logic
$sJoinExt2 = '';
if (isset($iPersonProperty)) {
    if ($iPersonProperty >= 0) {
        $sJoinExt2 = ' LEFT JOIN record2property_r2p ON per_ID = r2p_record_ID '; // per_ID should match the r2p_record_ID
        $sPersonPropertyWhereExt = ' AND r2p_pro_ID = '.$iPersonProperty.' ';
    } else { // >>>> THE SQL CODE BELOW IS NOT TESTED PROPERLY <<<<<
        $sJoinExt2 = ' ';
        $sPersonPropertyWhereExt = ' AND per_ID NOT IN (SELECT r2p_record_ID '.
                            ' FROM record2property_r2p '.
                            ' WHERE r2p_pro_ID = '.($iPersonProperty + $iTenThousand).')';
    }
    $sJoinExt .= $sJoinExt2; // We add our new SQL statement to the JoinExt variable from the group type.
}

$sFilterWhereExt = '';
if (isset($sFilter)) {
    // Check if there's a space
    if (strstr($sFilter, ' ')) {
        // break on the space...
        $aFilter = explode(' ', $sFilter);

        // use the results to check the first and last names
        $sFilterWhereExt = " AND per_FirstName LIKE '%".$aFilter[0]."%' ".
                            " AND per_LastName LIKE '%".$aFilter[1]."%' ";
    } else {
        $sFilterWhereExt = " AND (per_FirstName LIKE '%".$sFilter."%' ".
                            " OR per_LastName LIKE '%".$sFilter."%') ";
    }
}

$sClassificationWhereExt = '';
if ($iClassification >= 0) {
    $sClassificationWhereExt = ' AND per_cls_ID='.$iClassification.' ';
} else {
    $sClassificationWhereExt = ' AND per_cls_ID!='.
                                    ($iClassification + $iTenThousand).' ';
}

$sFamilyRoleWhereExt = '';
if ($iFamilyRole >= 0) {
    $sFamilyRoleWhereExt = ' AND per_fmr_ID='.$iFamilyRole.' ';
} else {
    $sFamilyRoleWhereExt = ' AND per_fmr_ID!='.($iFamilyRole + $iTenThousand).' ';
}

if ($iGender >= 0) {
    $sGenderWhereExt = ' AND per_Gender = '.$iGender;
} else {
    $sGenderWhereExt = '';
}
if (isset($sLetter)) {
    $sLetterWhereExt = " AND per_LastName LIKE '".$sLetter."%'";
} else {
    $sLetterWhereExt = '';
}

$sGroupBySQL = ' GROUP BY per_ID';

$activeFamiliesWhereExt = ' AND fam_DateDeactivated is null';
$sWhereExt = $sGroupWhereExt . $sFilterWhereExt . $sClassificationWhereExt .
    $sFamilyRoleWhereExt . $sGenderWhereExt . $sLetterWhereExt . $sPersonPropertyWhereExt . $activeFamiliesWhereExt;

$sSQL = $sBaseSQL.$sJoinExt.' WHERE 1'.$sWhereExt.$sGroupBySQL;

// URL to redirect back to this same page
$sRedirect = 'SelectList.php?';
if (array_key_exists('mode', $_GET)) {
    $sRedirect .= 'mode='.$_GET['mode'].'&amp;';
}
if (array_key_exists('type', $_GET)) {
    $sRedirect .= 'type='.$_GET['type'].'&amp;';
}
if (array_key_exists('Filter', $_GET)) {
    $sRedirect .= 'Filter='.$_GET['Filter'].'&amp;';
}
if (array_key_exists('Sort', $_GET)) {
    $sRedirect .= 'Sort='.$_GET['Sort'].'&amp;';
}
if (array_key_exists('Letter', $_GET)) {
    $sRedirect .= 'Letter='.$_GET['Letter'].'&amp;';
}
if (array_key_exists('Classification', $_GET)) {
    $sRedirect .= 'Classification='.$_GET['Classification'].'&amp;';
}
if (array_key_exists('FamilyRole', $_GET)) {
    $sRedirect .= 'FamilyRole='.$_GET['FamilyRole'].'&amp;';
}
if (array_key_exists('Gender', $_GET)) {
    $sRedirect .= 'Gender='.$_GET['Gender'].'&amp;';
}
if (array_key_exists('grouptype', $_GET)) {
    $sRedirect .= 'grouptype='.$_GET['grouptype'].'&amp;';
}
if (array_key_exists('groupid', $_GET)) {
    $sRedirect .= 'groupid='.$_GET['groupid'].'&amp;';
}
if (array_key_exists('grouproleid', $_GET)) {
    $sRedirect .= 'grouproleid='.$_GET['grouproleid'].'&amp;';
}
if (array_key_exists('Number', $_GET)) {
    $sRedirect .= 'Number='.$_GET['Number'].'&amp;';
}
if (array_key_exists('Result_Set', $_GET)) {
    $sRedirect .= 'Result_Set='.$_GET['Result_Set'].'&amp;';
}
if (array_key_exists('PersonProperties', $_GET)) {
    $sRedirect .= 'PersonProperties='.$_GET['PersonProperties'].'&amp;';
}

$sRedirect = mb_substr($sRedirect, 0, -5); // Chop off last &amp;


$peopleIDArray = array();
$rsPersons = RunQuery($sSQL);
while ($aRow = mysqli_fetch_row($rsPersons)) {
    array_push($peopleIDArray, intval($aRow[0]));
}


// Get the total number of persons
$rsPer = RunQuery($sSQL);
$Total = mysqli_num_rows($rsPer);

// Select the proper sort SQL
switch ($sSort) {
    case 'family':
            $sOrderSQL = ' ORDER BY fam_Name';
            break;
    case 'zip':
            $sOrderSQL = ' ORDER BY zip, per_LastName, per_FirstName';
            break;
    case 'entered':
            $sOrderSQL = ' ORDER BY per_DateEntered DESC';
            break;
    case 'edited':
            $sOrderSQL = ' ORDER BY per_DateLastEdited DESC';
            break;
    default:
            $sOrderSQL = ' ORDER BY per_LastName, per_FirstName';
            break;
}

if ($iClassification >= 0) {
    $iClassificationStr = $iClassification;
} else {
    $iClassificationStr = '';
}

if ($iFamilyRole >= 0) {
    $iFamilyRoleStr = $iFamilyRole;
} else {
    $iFamilyRoleStr = '';
}

if ($iGender >= 0) {
    $iGenderStr = $iGender;
} else {
    $iGenderStr = '';
}

if ($iGroupType >= 0) {
    $iGroupTypeStr = $iGroupType;
} else {
    $iGroupTypeStr = '';
}

if (isset($iGroupID) && $iGroupID != '') {
    $iGroupIDStr = $iGroupID;
} else {
    $iGroupIDStr = '';
}

if (isset($iRoleID) && $iRoleID != '') {
    $iRoleIDStr = $iRoleID;
} else {
    $iRoleIDStr = '';
}

if (isset($iPersonProperty) && $iPersonProperty != '') {
    $iPersonPropertyStr = $iPersonProperty;
} else {
    $iPersonPropertyStr = '';
}

// Regular PersonList display
$sLimitSQL = '';

// Append a LIMIT clause to the SQL statement
if (empty($_GET['Result_Set'])) {
    $Result_Set = 0;
} else {
    $Result_Set = InputUtils::LegacyFilterInput($_GET['Result_Set'], 'int');
}

$sLimitSQL .= " LIMIT $Result_Set, $iPerPage";

// Run the query with order and limit to get the final result for this list page
$finalSQL = $sSQL.$sOrderSQL.$sLimitSQL;
$rsPersons = RunQuery($finalSQL);

// Run query to get first letters of last name.
$sSQL = 'SELECT DISTINCT LEFT(per_LastName,1) AS letter FROM person_per LEFT JOIN family_fam ON per_fam_ID = family_fam.fam_ID '.
        $sJoinExt." WHERE 1 $sWhereExt ORDER BY letter";
$rsLetters = RunQuery($sSQL);

require 'Include/Header.php';
?>
<script nonce="<?= SystemURLs::getCSPNonce()?>">
  var listPeople=<?= json_encode($peopleIDArray)?>;
</script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/SelectList.js"></script>

<div class="box box-primary">
    <div class="box-header">
        <?= gettext('Filter and Cart') ?>
    </div>
    <div class="box-body">
<form method="get" action="SelectList.php" name="PersonList">
<?php
if ($iMode == 1) {
    echo '<p align="center">';
} else {
    $sSQLtemp = 'SELECT * FROM list_lst WHERE lst_ID = 3';
    $rsGroupTypes = RunQuery($sSQLtemp);
    echo '<p align="center" class="MediumText">'.gettext('Show people <b>not</b> in this group type:');
    echo '<select name="type" onchange="this.form.submit()">';
    while ($aRow = mysqli_fetch_array($rsGroupTypes)) {
        extract($aRow);
        echo '<option value="'.$lst_OptionID.'"';
        if ($iGroupTypeMissing == $lst_OptionID) {
            echo ' selected';
        }
        echo '>'.$lst_OptionName.'&nbsp;';
    }
    echo '</select></p>';
}

?>

<table align="center"><tr><td align="center">
<?= gettext('Sort order') ?>:
<select name="Sort" onchange="this.form.submit()">
		<option value="name" <?php if ($sSort == 'name' || empty($sSort)) {
    echo 'selected';
} ?>><?= gettext('By Name') ?></option>
		<option value="family" <?php if ($sSort == 'family') {
    echo 'selected';
} ?>><?= gettext('By Family') ?></option>
		<option value="zip" <?php if ($sSort == 'zip') {
    echo 'selected';
} ?>><?= gettext('By Zip Code') ?></option>
		<option value="entered" <?php if ($sSort == 'entered') {
    echo 'selected';
} ?>><?= gettext('By Newest Entries') ?></option>
		<option value="edited" <?php if ($sSort == 'edited') {
    echo 'selected';
} ?>><?= gettext('By Recently Edited') ?></option>

</select>&nbsp;
<input type="text" name="Filter" value="<?= $sFilter ?>">
<input type="hidden" name="mode" value="<?= $sMode ?>">
<input type="hidden" name="Letter" value="<?= $sLetter ?>">
<input type="submit" class="btn" value="<?= gettext('Apply Filter') ?>">

</td></tr>
<?php

echo '	<tr><td align="center">
		<select name="Gender" onchange="this.form.submit()">
		<option value="" ';
if (!isset($iGender)) {
    echo ' selected ';
}
echo '> '.gettext('Male & Female').'</option>';

echo '<option value="1"';
if (isset($iGender) && $iGender == 1) {
    echo ' selected ';
}
echo '> '.gettext('Male').'</option>';

echo '<option value="2"';
if (isset($iGender) && $iGender == 2) {
    echo ' selected ';
}
echo '> '.gettext('Female').'</option></select>';

// **********
// Classification drop down list
echo '	<select name="Classification" onchange="this.form.submit()">
		<option value="" ';
if ($iClassification >= 0) {
    echo ' selected ';
}
echo '>'.gettext('All Classifications').'</option>';

foreach ($aClassificationName as $key => $value) {
    echo '<option value="'.$key.'"';
    if ($iClassification >= 0 && $iClassification == $key) {
        echo ' selected ';
    }
    echo '>'.$value.'</option>';
}

foreach ($aClassificationName as $key => $value) {
    echo '<option value="'.($key - $iTenThousand).'"';
    if ($iClassification >= 0 && $iClassification == ($key - $iTenThousand)) {
        echo ' selected ';
    }
    echo '>! '.$value.'</option>';
}
echo '</select>';

// **********
// Family Role Drop Down Box
echo '<select name="FamilyRole" onchange="this.form.submit()">';
echo '<option value="" ';
if ($iFamilyRole < 0) {
    echo ' selected ';
}
echo '>'.gettext('All Family Roles').'</option>';

foreach ($aFamilyRoleName as $key => $value) {
    echo '<option value="'.$key.'"';
    if ($iFamilyRole >= 0 && $iFamilyRole == $key) {
        echo ' selected ';
    }
    echo '>'.$value.'</option>';
}

foreach ($aFamilyRoleName as $key => $value) {
    echo '<option value="'.($key - $iTenThousand).'"';
    if ($iFamilyRole >= 0 && $iFamilyRole == ($key - $iTenThousand)) {
        echo ' selected ';
    }
    echo '>! '.$value.'</option>';
}

echo '</select>';

// Person Property Drop Down Box
echo '<select name="PersonProperties" onchange="this.form.submit()">';
echo '<option value="" ';
if (!isset($iPersonProperty)) {
    echo ' selected ';
}
echo '>'.gettext('All Contact Properties').'</option>';

foreach ($aPersonPropertyName as $key => $value) {
    echo '<option value="'.$key.'"';
    if (isset($iPersonProperty)) {
        if ($iPersonProperty == $key) {
            echo ' selected ';
        }
    }
    echo '>'.$value.'</option>';
}

foreach ($aPersonPropertyName as $key => $value) {
    echo '<option value="'.($key - $iTenThousand).'"';
    if (isset($iPersonProperty)) {
        if ($iPersonProperty == ($key - $iTenThousand)) {
            echo ' selected ';
        }
    }
    echo '>! '.$value.'</option>';
}

echo '</select>';

//grouptype drop down box
if ($iMode == 1) {
    echo '<select name="grouptype" onchange="this.form.submit()">';

    echo '<option value="" ';
    if (!isset($iGroupType)) {
        echo ' selected ';
    }
    echo '>'.gettext('All Group Types').'</option>';

    foreach ($aGroupTypes as $key => $value) {
        echo '<option value="'.$key.'"';
        if (isset($iGroupType)) {
            if ($iGroupType == $key) {
                echo ' selected ';
            }
        }
        echo '>'.$value.'</option>';
    }

    foreach ($aGroupTypes as $key => $value) {
        echo '<option value="'.($key - $iTenThousand).'"';
        if (isset($iGroupType)) {
            if ($iGroupType == ($key - $iTenThousand)) {
                echo ' selected ';
            }
        }
        echo '>! '.$value.'</option>';
    }
    echo '</select>';

    if (isset($iGroupType) && ($iGroupType > -1)) {
        // Create array with Group Information
        $sGroupsSQL = "SELECT * FROM group_grp WHERE grp_Type = $iGroupType ".
                        'ORDER BY grp_Name ';

        $rsGroups = RunQuery($sGroupsSQL);
        $aGroupNames = [];
        while ($aRow = mysqli_fetch_array($rsGroups)) {
            extract($aRow);
            $aGroupNames[intval($grp_ID)] = $grp_Name;
        }

        echo '	<select name="groupid" onchange="this.form.submit()">
				<option value="" ';
        if (!isset($iGroupType)) {
            echo ' selected ';
        }
        echo '>'.gettext('All Groups').'</option>';

        foreach ($aGroupNames as $key => $value) {
            echo '<option value="'.$key.'"';
            if (isset($iGroupType)) {
                if ($iGroupID == $key) {
                    echo ' selected ';
                }
            }
            echo '>'.$value.'</option>';
        }

        foreach ($aGroupNames as $key => $value) {
            echo '<option value="'.($key - $iTenThousand).'"';
            if (isset($iGroupType)) {
                if ($iGroupID == ($key - $iTenThousand)) {
                    echo ' selected ';
                }
            }
            echo '>! '.$value.'</option>';
        }
        echo '</select>';
    }

    // *********
    // Create Group Role drop down box
    if (isset($iGroupID) && ($iGroupID > -1)) {

        // Get the group's role list ID
        $sSQL = 'SELECT grp_RoleListID '.
                'FROM group_grp WHERE grp_ID ='.$iGroupID;
        $aTemp = mysqli_fetch_array(RunQuery($sSQL));
        $iRoleListID = $aTemp[0];

        // Get the roles
        $sSQL = 'SELECT * FROM list_lst WHERE lst_ID = '.$iRoleListID.
                ' ORDER BY lst_OptionSequence';
        $rsRoles = RunQuery($sSQL);
        unset($aGroupRoles);
        while ($aRow = mysqli_fetch_array($rsRoles)) {
            extract($aRow);
            $aGroupRoles[intval($lst_OptionID)] = $lst_OptionName;
        }

        echo '	<select name="grouproleid" onchange="this.form.submit()" >
				<option value="" ';
        if ($iRoleID < 0) {
            echo ' selected ';
        }
        echo '>'.gettext('All Roles').'</option>';

        foreach ($aGroupRoles as $key => $value) {
            echo '<option value="'.$key.'"';
            if ($iRoleID >= 0) {
                if ($iRoleID == $key) {
                    echo ' selected ';
                }
            }
            echo '>'.$value.'</option>';
        }
        echo '</select>';
    }
} ?>

<input type="button" class="btn" value="<?= gettext('Clear Filters') ?>" onclick="javascript:document.location='SelectList.php?mode=<?= $sMode ?>&amp;Sort=<?= $sSort ?>&amp;type=<?= $iGroupTypeMissing ?>'"><BR><BR>
<a id="AddAllToCart" class="btn btn-primary" ><?= gettext('Add All to Cart') ?></a>
<input name="IntersectCart" type="submit" class="btn btn-warning" value="<?= gettext('Intersect with Cart') ?>">&nbsp;
<a id="RemoveAllFromCart" class="btn btn-danger" ><?= gettext('Remove All from Cart') ?></a>
</td></tr>
</table></form>
	</div>
</div>
<div class="box box-warning">
	<div class="box-header">
        <?= gettext('Listing')?>
    </div>
	<div class="box-body">
<?php
// Display record count
if ($Total == 1) {
    echo '<p align = "center">' . $Total . gettext(" record returned") . '</p>';
} else {
    echo '<p align = "center">' . $Total . gettext(" records returned") . '</p>';
}
// Create Sort Links
echo '<div align="center">';
echo "<a href=\"SelectList.php?mode=$sMode&amp;type=$iGroupTypeMissing&amp;Filter=$sFilter&amp;Classification=$iClassificationStr&amp;FamilyRole=$iFamilyRoleStr&amp;Gender=$iGenderStr&amp;grouptype=$iGroupTypeStr&amp;groupid=$iGroupIDStr&amp;grouproleid=$iRoleIDStr&amp;PersonProperties=$iPersonPropertyStr";
if ($sSort) {
    echo "&amp;Sort=$sSort";
}
    echo '">'.gettext('View All').'</a>';
while ($aLetter = mysqli_fetch_row($rsLetters)) {
    $aLetter[0] = mb_strtoupper($aLetter[0]);
    if ($aLetter[0] == $sLetter) {
        echo ' &nbsp;|&nbsp; '.$aLetter[0];
    } else {
        echo " &nbsp;|&nbsp; <a href=\"SelectList.php?mode=$sMode&amp;type=$iGroupTypeMissing&amp;Filter=$sFilter&amp;Classification=$iClassificationStr&amp;FamilyRole=$iFamilyRoleStr&amp;Gender=$iGenderStr&amp;grouptype=$iGroupTypeStr&amp;groupid=$iGroupIDStr&amp;grouproleid=$iRoleIDStr";
        if ($sSort) {
            echo "&amp;Sort=$sSort";
        }
        echo '&amp;Letter='.$aLetter[0].'">'.$aLetter[0].'</a>';
    }
}
echo '</div><BR>';

// Create Next / Prev Links and $Result_Set Value
if ($Total > 0) {
    echo '<div align="center">';
    echo '<form method="get" action="SelectList.php" name="ListNumber">';

    // Show previous-page link unless we're at the first page
    if ($Result_Set < $Total && $Result_Set > 0) {
        $thisLinkResult = $Result_Set - $iPerPage;
        echo "<a href=\"SelectList.php?Result_Set=$thisLinkResult&amp;mode=$sMode&amp;type=$iGroupTypeMissing&amp;Filter=$sFilter&amp;Sort=$sSort&amp;Letter=$sLetter&amp;Classification=$iClassification&amp;FamilyRole=$iFamilyRole&amp;Gender=$iGender&amp;grouptype=$iGroupType&amp;groupid=$iGroupID&amp;grouproleid=$iRoleID\">".gettext('Previous Page').'</A>&nbsp;&nbsp;';
    }

    // Calculate starting and ending Page-Number Links
    $Pages = ceil($Total / $iPerPage);
    $startpage = (ceil($Result_Set / $iPerPage)) - 6;
    if ($startpage <= 2) {
        $startpage = 1;
    }
    $endpage = (ceil($Result_Set / $iPerPage)) + 9;
    if ($endpage >= ($Pages - 1)) {
        $endpage = $Pages;
    }

    // Show Link "1 ..." if startpage does not start at 1
    if ($startpage != 1) {
        echo "<a href=\"SelectList.php?Result_Set=0&amp;mode=$sMode&amp;type=$iGroupTypeMissing&amp;Filter=$sFilter&amp;Sort=$sSort&amp;Letter=$sLetter&amp;Classification=$iClassification&amp;FamilyRole=$iFamilyRole&amp;Gender=$iGender&amp;grouptype=$iGroupType&amp;groupid=$iGroupID&amp;grouproleid=$iRoleID\">1</a> ... \n";
    }

    // Display page links
    if ($Pages > 1) {
        for ($c = $startpage; $c <= $endpage; $c++) {
            $b = $c - 1;
            $thisLinkResult = $iPerPage * $b;
            if ($thisLinkResult != $Result_Set) {
                echo "&nbsp;&nbsp;<a href=\"SelectList.php?Result_Set=$thisLinkResult&amp;mode=$sMode&amp;type=$iGroupTypeMissing&amp;Filter=$sFilter&amp;Sort=$sSort&amp;Letter=$sLetter&amp;Classification=$iClassificationStr&amp;FamilyRole=$iFamilyRoleStr&amp;Gender=$iGenderStr&amp;grouptype=$iGroupTypeStr&amp;groupid=$iGroupIDStr&amp;grouproleid=$iRoleIDStr\">$c</a>&nbsp;\n";
            } else {
                echo '&nbsp;&nbsp;[ '.$c.' ]&nbsp;&nbsp;';
            }
        }
    }

    // Show Link "... xx" if endpage is not the maximum number of pages
    if ($endpage != $Pages) {
        $thisLinkResult = ($Pages - 1) * $iPerPage;
        echo " ... <a href=\"SelectList.php?Result_Set=$thisLinkResult&amp;mode=$sMode&amp;type=$iGroupTypeMissing&amp;Filter=$sFilter&amp;Sort=$sSort&amp;Letter=$sLetter&amp;Classification=$iClassification&amp;FamilyRole=$iFamilyRole&amp;Gender=$iGender&amp;grouptype=$iGroupType&amp;groupid=$iGroupID&amp;grouproleid=$iRoleID\">$Pages</a>\n";
    }
    // Show next-page link unless we're at the last page
    if ($Result_Set >= 0 && $Result_Set < $Total) {
        $thisLinkResult = $Result_Set + $iPerPage;
        if ($thisLinkResult < $Total) {
            echo "&nbsp;&nbsp;<a href=\"SelectList.php?Result_Set=$thisLinkResult&amp;mode=$sMode&amp;type=$iGroupTypeMissing&amp;Filter=$sFilter&amp;Sort=$sSort&amp;Letter=$sLetter&amp;Classification=$iClassificationStr&amp;FamilyRole=$iFamilyRoleStr&amp;Gender=$iGenderStr&amp;grouptype=$iGroupTypeStr&amp;groupid=$iGroupIDStr&amp;grouproleid=$iRoleIDStr\">".gettext('Next Page').'</a>';
        }
    }

    echo '<input type="hidden" name="mode" value="';
    echo $sMode.'">';
    if ($iGroupTypeMissing > 0) {
        echo '<input type="hidden" name="type" value="';
        echo $iGroupTypeMissing.'">';
    }
    if (isset($sFilter)) {
        echo '<input type="hidden" name="Filter" value="';
        echo $sFilter.'">';
    }
    if (isset($sSort)) {
        echo '<input type="hidden" name="Sort" value="';
        echo $sSort.'">';
    }
    if (isset($sLetter)) {
        echo '<input type="hidden" name="Letter" value="';
        echo $sLetter.'">';
    }
    if ($iClassification >= 0) {
        echo '<input type="hidden" name="Classification" value="';
        echo $iClassification.'">';
    }
    if ($iFamilyRole >= 0) {
        echo '<input type="hidden" name="FamilyRole" value="';
        echo $iFamilyRole.'">';
    }
    if ($iGender >= 0) {
        echo '<input type="hidden" name="Gender" value="';
        echo $iGender.'">';
    }
    if ($iGroupType >= 0) {
        echo '<input type="hidden" name="grouptype" value="';
        echo $iGroupType.'">';
    }
    if (isset($iPersonProperty)) {
        echo '<input type="hidden" name="PersonProperties" value="';
        echo $iPersonProperty.'">';
    }
    if (isset($iGroupID)) {
        echo '<input type="hidden" name="groupid" value="';
        echo $iGroupID.'">';
    }

    // Display record limit per page
    if ($_SESSION['SearchLimit'] == '5') {
        $sLimit5 = 'selected';
    }
    if ($_SESSION['SearchLimit'] == '10') {
        $sLimit10 = 'selected';
    }
    if ($_SESSION['SearchLimit'] == '20') {
        $sLimit20 = 'selected';
    }
    if ($_SESSION['SearchLimit'] == '25') {
        $sLimit25 = 'selected';
    }
    if ($_SESSION['SearchLimit'] == '50') {
        $sLimit50 = 'selected';
    }
    if ($_SESSION['SearchLimit'] == '100') {
        $sLimit100 = 'selected';
    }
    if ($_SESSION['SearchLimit'] == '200') {
        $sLimit200 = 'selected';
    }
    if ($_SESSION['SearchLimit'] == '500') {
        $sLimit500 = 'selected';
    }

    echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'.gettext('Display:').'&nbsp;
	<select class="SmallText" name="Number" onchange="this.form.submit()">
					<option value="5" '.$sLimit5.'>5</option>
					<option value="10" '.$sLimit10.'>10</option>
					<option value="20" '.$sLimit20.'>20</option>
					<option value="25" '.$sLimit25.'>25</option>
					<option value="50" '.$sLimit50.'>50</option>
					<option value="100" '.$sLimit100.'>100</option>
					<option value="200" '.$sLimit200.'>200</option>
					<option value="500" '.$sLimit500.'>500</option>
			</select>&nbsp;
			</form>
			</div>
			<BR>';
} ?>

<?php

// At this point we have finished the forms at the top of SelectList.
// Now begin the table displaying results.

// Read if sort by person is selected columns 3 and 5 are user selectable.  If the
// user has not selected a value then read from session variable.
if (!isset($sPersonColumn3)) {
    if (array_key_exists('sPersonColumn3', $_SESSION)) {
        switch ($_SESSION['sPersonColumn3']) {
        case 'Family Role':
            $sPersonColumn3 = 'Family Role';
            break;
        case 'Gender':
            $sPersonColumn3 = 'Gender';
            break;
        default:
            $sPersonColumn3 = 'Classification';
        break;
        }
    } else {
        $sPersonColumn3 = 'Classification';
    }
}

if (!isset($sPersonColumn5)) {
    if (array_key_exists('sPersonColumn5', $_SESSION)) {
        switch ($_SESSION['sPersonColumn5']) {
        case gettext('Home Phone'):
            $sPersonColumn5 = gettext('Home Phone');
            break;
        case gettext('Work Phone'):
            $sPersonColumn5 = gettext('Work Phone');
            break;
        case gettext('Mobile Phone'):
            $sPersonColumn5 = gettext('Mobile Phone');
        break;
        default:
            $sPersonColumn5 = gettext('Zip Code');
        break;
        }
    } else {
        $sPersonColumn5 = gettext('Zip Code');
    }
}

// Header Row for results table
echo '<form method="get" action="SelectList.php" name="ColumnOptions">';
echo '<div class="table-responsive">';
echo '<table cellpadding="4" align="center" cellspacing="0" width="100%">';
echo '<tr><th></th><th><a href="SelectList.php?mode='.$sMode.'&amp;type='.$iGroupTypeMissing;
echo '&amp;Sort=name&amp;Filter='.$sFilter.'">'.gettext('Name').'</a></th>';

echo '<th><input type="hidden" name="mode" value="'.$sMode.'">';
if ($iGroupTypeMissing > 0) {
    echo '<input type="hidden" name="type" value="'.$iGroupTypeMissing.'">';
}
if (isset($sFilter)) {
    echo '<input type="hidden" name="Filter" value="'.$sFilter.'">';
}
if (isset($sSort)) {
    echo '<input type="hidden" name="Sort" value="'.$sSort.'">';
}
if (isset($sLetter)) {
    echo '<input type="hidden" name="Letter" value="'.$sLetter.'">';
}
if ($iClassification >= 0) {
    echo '<input type="hidden" name="Classification" value="'.$iClassification.'">';
}
if ($iFamilyRole >= 0) {
    echo '<input type="hidden" name="FamilyRole" value="'.$iFamilyRole.'">';
}
if ($iGender >= 0) {
    echo '<input type="hidden" name="Gender" value="'.$iGender.'">';
}
if (isset($iPersonProperty)) {
    echo '<input type="hidden" name="PersonProperties" value="';
    echo $iPersonProperty.'">';
}
if ($iGroupType >= 0) {
    echo '<input type="hidden" name="grouptype" value="'.$iGroupType.'">';
}
if (isset($iGroupID)) {
    echo '<input type="hidden" name="groupid" value="'.$iGroupID.'">';
}

echo '<select class="SmallText" name="PersonColumn3" onchange="this.form.submit()">';

$aPersonCol3 = ['Classification', 'Family Role', 'Gender'];
foreach ($aPersonCol3 as $s) {
    $sel = '';
    if ($sPersonColumn3 == $s) {
        $sel = ' selected';
    }
    echo '<option value="'.$s.'"'.$sel.'>'.gettext($s).'</option>';
}

echo '</select></th>';

echo '<th><a href="SelectList.php?mode='.$sMode.'&amp;type='.$iGroupTypeMissing;
echo '&amp;Sort=family&amp;Filter='.$sFilter.'">'.gettext('Family').'</a></th>';

echo '<th>';
echo '<select class="SmallText" name="PersonColumn5" onchange="this.form.submit()">';
$aPersonCol5 = [gettext('Home Phone'), gettext('Work Phone'), gettext('Mobile Phone'), gettext('Zip Code')];
foreach ($aPersonCol5 as $s) {
    $sel = '';
    if ($sPersonColumn5 == $s) {
        $sel = ' selected';
    }
    echo '<option value="'.$s.'"'.$sel.'>'.gettext($s).'</option>';
}
echo '</select></th><th>';

if ($_SESSION['bEditRecords']) {
    echo gettext('Edit');
}

echo '</th><th>'.gettext('Cart').'</th>';

if ($iMode == 1) {
    echo '<th>'.gettext('Print').'</th>';
} else {
    echo '<th>'.gettext('Assign').'</th>';
}

// Table for results begins here
echo '</tr><tr><td>&nbsp;</td></tr>';

$sRowClass = 'RowColorA';

$iPrevFamily = -1;

//Loop through the person recordset
while ($aRow = mysqli_fetch_array($rsPersons)) {
    $per_Title = '';
    $per_FirstName = '';
    $per_MiddleName = '';
    $per_LastName = '';
    $per_Suffix = '';
    $per_Gender = '';

    $fam_Name = '';
    $fam_Address1 = '';
    $fam_Address2 = '';
    $fam_City = '';
    $fam_State = '';

    extract($aRow);

    // Add alphabetical headers based on sort
    $sBlankLine = '<tr><td>&nbsp;</td></tr>';
    switch ($sSort) {
    case 'family':
        if ($fam_ID != $iPrevFamily || $iPrevFamily == -1) {
            echo $sBlankLine;
            echo '<tr><td></td><td class="ControlBreak">';

            if (isset($fam_Name)) {
                echo $fam_Name;
            } else {
                echo gettext('Unassigned');
            }

            echo '</td></tr>';
            $sRowClass = 'RowColorA';
        }
        break;

    case 'name':
        if (mb_strtoupper(mb_substr($per_LastName, 0, 1, 'UTF-8')) != $sPrevLetter) {
            echo $sBlankLine;
            echo '<tr><td></td>';
            echo '<td class="ControlBreak">'.mb_strtoupper(mb_substr($per_LastName, 0, 1, 'UTF-8'));
            echo '</td></tr>';
            $sRowClass = 'RowColorA';
        }
        break;

    default:
        break;
    } // end switch

    //Alternate the row color
    $sRowClass = AlternateRowStyle($sRowClass);

    //Display the row
    echo '<tr class="'.$sRowClass.'">'; ?>
	</td>
    <td style="padding-bottom:5px"><img src="<?= SystemURLs::getRootPath(); ?>/api/persons/<?= $per_ID ?>/thumbnail" class="initials-image direct-chat-img " style="width: <?= SystemConfig::getValue('iProfilePictureListSize') ?>px; height: <?= SystemConfig::getValue('iProfilePictureListSize') ?>px" /> </td>
	<td>
	    <a href="PersonView.php?PersonID=<?= $per_ID ?>" >
	    <?= FormatFullName($per_Title, $per_FirstName, $per_MiddleName, $per_LastName, $per_Suffix, 3) ?>
	    </a>
    </td>
    <td>
    <?php
    if ($sPersonColumn3 == 'Classification') {
        echo $aClassificationName[$per_cls_ID];
    } elseif ($sPersonColumn3 == 'Family Role') {
        echo $aFamilyRoleName[$per_fmr_ID];
    } else {    // Display Gender
        switch ($per_Gender) {
            case 1: echo gettext('Male'); break;
            case 2: echo gettext('Female'); break;
            default: echo '';
        }
    }
    echo '&nbsp;</td>';

    echo '<td>';
    if ($fam_Name != '') {
        echo '<a href="FamilyView.php?FamilyID='.$fam_ID.'">'.$fam_Name;
        echo FormatAddressLine($fam_Address1, $fam_City, $fam_State).'</a>';
    }
    echo '&nbsp;</td>';

    echo '<td>';
    // Phone number or zip code
    if ($sPersonColumn5 == 'Home Phone') {
        echo SelectWhichInfo(ExpandPhoneNumber($fam_HomePhone, $fam_Country, $dummy),
                ExpandPhoneNumber($per_HomePhone, $fam_Country, $dummy), true);
    } elseif ($sPersonColumn5 == 'Work Phone') {
        echo SelectWhichInfo(ExpandPhoneNumber($per_WorkPhone, $fam_Country, $dummy),
                ExpandPhoneNumber($fam_WorkPhone, $fam_Country, $dummy), true);
    } elseif ($sPersonColumn5 == 'Mobile Phone') {
        echo SelectWhichInfo(ExpandPhoneNumber($per_CellPhone, $fam_Country, $dummy),
                ExpandPhoneNumber($fam_CellPhone, $fam_Country, $dummy), true);
    } else {
        if (isset($zip)) {
            echo $zip;
        } else {
            echo gettext('Unassigned');
        }
    } ?>
	</td>
    <td>
	<?php if ($_SESSION['bEditRecords']) {
        ?>
		<a href="PersonEditor.php?PersonID=<?= $per_ID ?>">
		    <span class="fa-stack">
                <i class="fa fa-square fa-stack-2x"></i>
                <i class="fa fa-pencil fa-stack-1x fa-inverse"></i>
            </span>
        </a>
	<?php
    } ?>
    </td>
	<td>
	<?php if (!isset($_SESSION['aPeopleCart']) || !in_array($per_ID, $_SESSION['aPeopleCart'], false)) {
        ?>
      <a class="AddToPeopleCart" data-cartpersonid="<?= $per_ID ?>">
		<span class="fa-stack">
                <i class="fa fa-square fa-stack-2x"></i>
                <i class="fa fa-cart-plus fa-stack-1x fa-inverse"></i>
            </span>
        </a>
    </td>
	<?php
    } else {
        ?>
    <a class="RemoveFromPeopleCart" data-cartpersonid="<?= $per_ID ?>">
		<span class="fa-stack">
                <i class="fa fa-square fa-stack-2x"></i>
                <i class="fa fa-remove fa-stack-1x fa-inverse"></i>
            </span>
        </a>
	<?php
    }

    if ($iMode == 1) {
        echo '<td><a href="PrintView.php?PersonID='.$per_ID.'">'; ?>
		<span class="fa-stack">
                <i class="fa fa-square fa-stack-2x"></i>
                <i class="fa fa-print fa-stack-1x fa-inverse"></i>
            </span>
        </a>
	<?php
    } else {
        echo '<td><a href="PersonToGroup.php?PersonID='.$per_ID;
        echo '&amp;prevquery='.rawurlencode($_SERVER['QUERY_STRING']).'">';
        echo gettext('Add to Group').'</a></td>';
    }

    echo '</td></tr>';

    //Store the family to enable the control break
    $iPrevFamily = $fam_ID;

    //If there was no family, set it to 0
    if (!isset($iPrevFamily)) {
        $iPrevFamily = 0;
    }

    //Store the first letter of this record to enable the control break
    $sPrevLetter = mb_strtoupper(mb_substr($per_LastName, 0, 1, 'UTF-8'));
} // end of while loop
?>

		</table>
        </div>
		</form>
	</div>
</div>

<?php require 'Include/Footer.php' ?>
