<?php

/*******************************************************************************
 *
 *  filename    : GroupPropsFormEditor.php
 *  last change : 2003-02-09
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2003 Chris Gebhardt (http://www.openserve.org)
 *                Copyright 2013 Michael Wilt
 *
 *  function    : Editor for group-specific properties form
 *




******************************************************************************/

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOption;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: user must be allowed to edit records to use this page.
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isManageGroupsEnabled());

// Get the Group from the querystring
$iGroupID = InputUtils::legacyFilterInput($_GET['GroupID'], 'int');

// Get the group information
$sSQL = 'SELECT * FROM group_grp WHERE grp_ID = ' . $iGroupID;
$rsGroupInfo = RunQuery($sSQL);
extract(mysqli_fetch_array($rsGroupInfo));

// Abort if user tries to load with group having no special properties.
if ($grp_hasSpecialProps == false) {
    RedirectUtils::redirect('GroupView.php?GroupID=' . $iGroupID);
}

$sPageTitle = gettext('Group-Specific Properties Form Editor:') . '  ' . $grp_Name;

require 'Include/Header.php'; ?>

<div class="card card-body">

<?php
$bErrorFlag = false;
$aNameErrors = [];
$bNewNameError = false;
$bDuplicateNameError = false;

// Does the user want to save changes to text fields?
if (isset($_POST['SaveChanges'])) {
    // Fill in the other needed property data arrays not gathered from the form submit
    $sSQL = 'SELECT prop_ID, prop_Field, type_ID, prop_Special, prop_PersonDisplay FROM groupprop_master WHERE grp_ID = ' . $iGroupID . ' ORDER BY prop_ID';
    $rsPropList = RunQuery($sSQL);
    $numRows = mysqli_num_rows($rsPropList);

    for ($row = 1; $row <= $numRows; $row++) {
        $aRow = mysqli_fetch_array($rsPropList, MYSQLI_BOTH);
        extract($aRow);

        $aFieldFields[$row] = $prop_Field;
        $aTypeFields[$row] = $type_ID;
        $aSpecialFields[$row] = $prop_Special;
        if (isset($prop_Special)) {
            $aSpecialFields[$row] = $prop_Special;
        } else {
            $aSpecialFields[$row] = 'NULL';
        }
    }

    for ($iPropID = 1; $iPropID <= $numRows; $iPropID++) {
        $aNameFields[$iPropID] = InputUtils::legacyFilterInput($_POST[$iPropID . 'name']);

        if (strlen($aNameFields[$iPropID]) == 0) {
            $aNameErrors[$iPropID] = true;
            $bErrorFlag = true;
        } else {
            $aNameErrors[$iPropID] = false;
        }

        $aDescFields[$iPropID] = InputUtils::legacyFilterInput($_POST[$iPropID . 'desc']);

        if (isset($_POST[$iPropID . 'special'])) {
            $aSpecialFields[$iPropID] = InputUtils::legacyFilterInput($_POST[$iPropID . 'special'], 'int');

            if ($aSpecialFields[$iPropID] == 0) {
                $aSpecialErrors[$iPropID] = true;
                $bErrorFlag = true;
            } else {
                $aSpecialErrors[$iPropID] = false;
            }
        }

        if (isset($_POST[$iPropID . 'show'])) {
            $aPersonDisplayFields[$iPropID] = true;
        } else {
            $aPersonDisplayFields[$iPropID] = false;
        }
    }

    // If no errors, then update.
    if (!$bErrorFlag) {
        for ($iPropID = 1; $iPropID <= $numRows; $iPropID++) {
            if ($aPersonDisplayFields[$iPropID]) {
                $temp = 'true';
            } else {
                $temp = 'false';
            }

            $sSQL = "UPDATE groupprop_master
					SET `prop_Name` = '" . $aNameFields[$iPropID] . "',
						`prop_Description` = '" . $aDescFields[$iPropID] . "',
						`prop_Special` = " . $aSpecialFields[$iPropID] . ",
						`prop_PersonDisplay` = '" . $temp . "'
					WHERE `grp_ID` = '" . $iGroupID . "' AND `prop_ID` = '" . $iPropID . "';";

            RunQuery($sSQL);
        }
    }
} else {
    // Check if we're adding a field
    if (isset($_POST['AddField'])) {
        $newFieldType = InputUtils::legacyFilterInput($_POST['newFieldType'], 'int');
        $newFieldName = InputUtils::legacyFilterInput($_POST['newFieldName']);
        $newFieldDesc = InputUtils::legacyFilterInput($_POST['newFieldDesc']);

        if (strlen($newFieldName) == 0) {
            $bNewNameError = true;
        } else {
            $sSQL = 'SELECT prop_Name FROM groupprop_master WHERE grp_ID = ' . $iGroupID;
            $rsPropNames = RunQuery($sSQL);
            while ($aRow = mysqli_fetch_array($rsPropNames)) {
                if ($aRow[0] == $newFieldName) {
                    $bDuplicateNameError = true;
                    break;
                }
            }

            if (!$bDuplicateNameError) {
                // Get the new prop_ID (highest existing plus one)
                $sSQL = 'SELECT prop_ID	FROM groupprop_master WHERE grp_ID = ' . $iGroupID;
                $rsPropList = RunQuery($sSQL);
                $newRowNum = mysqli_num_rows($rsPropList) + 1;

                // Find the highest existing field number in the group's table to determine the next free one.
                // This is essentially an auto-incrementing system where deleted numbers are not re-used.
                $tableName = 'groupprop_' . $iGroupID;

                $fields = mysqli_query($cnInfoCentral, 'SELECT * FROM ' . $tableName);
                $newFieldNum = mysqli_num_fields($fields);

                // If we're inserting a new custom-list type field, create a new list and get its ID
                if ($newFieldType == 12) {
                    // Get the first available lst_ID for insertion.  lst_ID 0-9 are reserved for permanent lists.
                    $sSQL = 'SELECT MAX(lst_ID) FROM list_lst';
                    $aTemp = mysqli_fetch_array(RunQuery($sSQL));
                    if ($aTemp[0] > 9) {
                        $newListID = $aTemp[0] + 1;
                    } else {
                        $newListID = 10;
                    }

                    // Insert into the lists table with an example option.
                    $listOption = new ListOption();
                    $listOption
                        ->setId($newListID)
                        ->setOptionId(1)
                        ->setOptionSequence(1)
                        ->setOptionName(gettext('Default Option'));
                    $listOption->save();

                    $newSpecial = "'$newListID'";
                } else {
                    $newSpecial = 'NULL';
                }

                // Insert into the master table
                $sSQL = "INSERT INTO `groupprop_master`
							( `grp_ID` , `prop_ID` , `prop_Field` , `prop_Name` , `prop_Description` , `type_ID` , `prop_Special` )
							VALUES ('" . $iGroupID . "', '" . $newRowNum . "', 'c" . $newFieldNum . "', '" . $newFieldName . "', '" . $newFieldDesc . "', '" . $newFieldType . "', $newSpecial);";
                RunQuery($sSQL);

                // Insert into the group-specific properties table
                $sSQL = 'ALTER TABLE `groupprop_' . $iGroupID . '` ADD `c' . $newFieldNum . '` ';

                switch ($newFieldType) {
                    case 1:
                        $sSQL .= "ENUM('false', 'true')";
                        break;
                    case 2:
                        $sSQL .= 'DATE';
                        break;
                    case 3:
                        $sSQL .= 'VARCHAR(50)';
                        break;
                    case 4:
                        $sSQL .= 'VARCHAR(100)';
                        break;
                    case 5:
                        $sSQL .= 'TEXT';
                        break;
                    case 6:
                        $sSQL .= 'YEAR';
                        break;
                    case 7:
                        $sSQL .= "ENUM('winter', 'spring', 'summer', 'fall')";
                        break;
                    case 8:
                        $sSQL .= 'INT';
                        break;
                    case 9:
                        $sSQL .= 'MEDIUMINT(9)';
                        break;
                    case 10:
                        $sSQL .= 'DECIMAL(10,2)';
                        break;
                    case 11:
                        $sSQL .= 'VARCHAR(30)';
                        break;
                    case 12:
                        $sSQL .= 'TINYINT(4)';
                }

                $sSQL .= ' DEFAULT NULL ;';
                RunQuery($sSQL);

                $bNewNameError = false;
            }
        }
    }

    // Get data for the form as it now exists..
    $sSQL = 'SELECT * FROM groupprop_master WHERE grp_ID = ' . $iGroupID . ' ORDER BY prop_ID';

    $rsPropList = RunQuery($sSQL);
    $numRows = mysqli_num_rows($rsPropList);

    // Create arrays of the properties.
    for ($row = 1; $row <= $numRows; $row++) {
        $aRow = mysqli_fetch_array($rsPropList, MYSQLI_BOTH);
        extract($aRow);

        // This is probably more clear than using a multi-dimensional array
        $aNameFields[$row] = $prop_Name;
        $aDescFields[$row] = $prop_Description;
        $aSpecialFields[$row] = $prop_Special;
        $aFieldFields[$row] = $prop_Field;
        $aTypeFields[$row] = $type_ID;
        $aPersonDisplayFields[$row] = ($prop_PersonDisplay == 'true');
    }
}

// Construct the form
?>

<form method="post" action="GroupPropsFormEditor.php?GroupID=<?= $iGroupID ?>" name="GroupPropFormEditor">

    <div class="table-responsive">
<table class="table">

<?php
if ($numRows == 0) {
    ?>
    <center><h2><?= gettext('No properties have been added yet') ?></h2>
    </center>
    <?php
} else {
    ?>
    <tr><td colspan="7">
    <center><b><?= gettext("Warning: Field changes will be lost if you do not 'Save Changes' before using an up, down, delete, or 'add new' button!") ?></b></center>
    </td></tr>

    <tr><td colspan="7" align="center">
    <?php
    if ($bErrorFlag) {
        echo '<span class="LargeText" style="color: red;">' . gettext('Invalid fields or selections. Changes not saved! Please correct and try again!') . '</span>';
    } ?>
    </td></tr>

        <tr>
            <th></th>
            <th></th>
            <th><?= gettext('Type') ?></th>
            <th><?= gettext('Name') ?></th>
            <th><?= gettext('Description') ?></th>
            <th><?= gettext('Special option') ?></th>
            <th><?= gettext('Show in') ?><br><?= gettext('Person View') ?></th>
        </tr>

    <?php

    for ($row = 1; $row <= $numRows; $row++) {
        ?>
        <tr>
            <td class="LabelColumn"><h2><b><?= $row ?></b></h2></td>
            <td class="TextColumn" width="5%" nowrap>
                <?php
                if ($row != 1) {
                    echo "<a href=\"GroupPropsFormRowOps.php?GroupID=$iGroupID&PropID=$row&Field=" . $aFieldFields[$row] . '&Action=up"><i class="fa fa-arrow-up"></i></a>';
                }
                if ($row < $numRows) {
                    echo "<a href=\"GroupPropsFormRowOps.php?GroupID=$iGroupID&PropID=$row&Field=" . $aFieldFields[$row] . '&Action=down"><i class="fa fa-arrow-down"></i></a>';
                } ?>

                <?= "<a href=\"GroupPropsFormRowOps.php?GroupID=$iGroupID&PropID=$row&Field=$aFieldFields[$row]&Action=delete\"><i class='fa fa-times' ></i></a>"; ?>
            </td>
            <td class="TextColumn" style="font-size:70%;">
            <?= $aPropTypes[$aTypeFields[$row]]; ?>
            </td>

            <td class="TextColumn"><input type="text" name="<?= $row ?>name" value="<?= htmlentities(stripslashes($aNameFields[$row]), ENT_NOQUOTES, 'UTF-8') ?>" size="25" maxlength="40">
                <?php
                if (array_key_exists($row, $aNameErrors) && $aNameErrors[$row]) {
                    echo '<span style="color: red;"><BR>' . gettext('You must enter a name') . ' </span>';
                } ?>
            </td>

            <td class="TextColumn"><textarea name="<?= $row ?>desc" cols="30" rows="1" onKeyPress="LimitTextSize(this,60)"><?= htmlentities(stripslashes($aDescFields[$row]), ENT_NOQUOTES, 'UTF-8') ?></textarea></td>

            <td class="TextColumn">
            <?php

            if ($aTypeFields[$row] == 9) {
                echo '<select name="' . $row . 'special">';
                echo '<option value="0" selected>' . gettext('Select a group') . '</option>';

                $sSQL = 'SELECT grp_ID,grp_Name FROM group_grp ORDER BY grp_Name';

                $rsGroupList = RunQuery($sSQL);

                while ($aRow = mysqli_fetch_array($rsGroupList)) {
                    extract($aRow);

                    echo '<option value="' . $grp_ID . '"';
                    if ($aSpecialFields[$row] == $grp_ID) {
                        echo ' selected';
                    }
                    echo '>' . $grp_Name;
                }

                echo '</select>';

                if ($aSpecialErrors[$row]) {
                    echo '<span style="color: red;"><BR>' . gettext('You must select a group.') . '</span>';
                }
            } elseif ($aTypeFields[$row] == 12) {
                echo "<a href=\"javascript:void(0)\" onClick=\"Newwin=window.open('OptionManager.php?mode=groupcustom&ListID=$aSpecialFields[$row]','Newwin','toolbar=no,status=no,width=400,height=500')\">Edit List Options</a>";
            } else {
                echo '&nbsp;';
            } ?></td>

            <td class="TextColumn">
                <input type="checkbox" name="<?= $row ?>show" value="1" <?php if ($aPersonDisplayFields[$row]) {
                    echo ' checked';
                                             } ?>>
            </td>
        </tr>
        <?php
    } ?>

        <tr>
            <td colspan="7">
            <table width="100%">
                <tr>
                    <td width="30%"></td>
                    <td width="40%" align="center" valign="bottom">
                        <input type="submit" class="btn btn-default" value="<?= gettext('Save Changes') ?>" Name="SaveChanges">
                    </td>
                    <td width="30%"></td>
                </tr>
            </table>
            </td>
            <td>
        </tr>
    <?php
} ?>
        <tr><td colspan="7"><hr></td></tr>
        <tr>
            <td colspan="7">
            <table width="100%">
                <tr>
                    <td width="15%"></td>
                    <td valign="top">
                    <div><?= gettext('Type') ?>:</div>
                    <?php
                        echo '<select name="newFieldType">';
                    for ($iOptionID = 1; $iOptionID <= count($aPropTypes); $iOptionID++) {
                        echo '<option value="' . $iOptionID . '"';
                        echo '>' . $aPropTypes[$iOptionID];
                    }
                        echo '</select>';
                    ?><BR>
                    <a href="<?= SystemURLs::getSupportURL() ?>"><?= gettext('Help on types..') ?></a>
                    </td>
                    <td valign="top">
                        <div><?= gettext('Name') ?>:</div>
                        <input type="text" name="newFieldName" size="25" maxlength="40">
                        <?php
                        if ($bNewNameError) {
                            echo '<div><span style="color: red;"><BR>' . gettext('You must enter a name') . '</span></div>';
                        }
                        if ($bDuplicateNameError) {
                            echo '<div><span style="color: red;"><BR>' . gettext('That field name already exists.') . '</span></div>';
                        }
                        ?>
                        &nbsp;
                    </td>
                    <td valign="top">
                        <div><?= gettext('Description') ?>:</div>
                        <input type="text" name="newFieldDesc" size="30" maxlength="60">
                        &nbsp;
                    </td>
                    <td>
                        <input type="submit" class="btn btn-default" value="<?= gettext('Add New Field') ?>" Name="AddField">
                    </td>
                    <td width="15%"></td>
                </tr>
            </table>
            </td>
        </tr>

    </table>
    </div>
    </form>

</div>

<?php require 'Include/Footer.php' ?>
