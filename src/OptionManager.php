<?php

/*******************************************************************************
 *
 *  filename    : OptionsManager.php
 *  last change : 2003-04-16
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2003 Chris Gebhardt
 *
 *  OptionName : Interface for editing simple selection options such as those
 *              : used for Family Roles, Classifications, and Group Types
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\ListOption;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;

$mode = trim($_GET['mode']);

// Check security for the mode selected.
switch ($mode) {
    case 'famroles':
    case 'classes':
        AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled());
        break;

    case 'grptypes':
    case 'grproles':
    case 'groupcustom':
        AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isManageGroupsEnabled());
        break;

    case 'custom':
    case 'famcustom':
    case 'securitygrp':
        AuthenticationManager::redirectHomeIfNotAdmin();
        break;

    default:
        RedirectUtils::redirect('v2/dashboard');
        break;
}

// Select the proper settings for the editor mode
switch ($mode) {
    case 'famroles':
        //It don't work for postuguese because in it adjective come after noum
        $noun = gettext('Role');
        //In the same way, the plural isn't only add s
        $adjplusname = gettext('Family Role');
        $adjplusnameplural = gettext('Family Roles');
        $sPageTitle = gettext('Family Roles Editor');
        $listID = 2;
        $embedded = false;
        break;
    case 'classes':
        $noun = gettext('Classification');
        $adjplusname = gettext('Person Classification');
        $adjplusnameplural = gettext('Person Classifications');
        $sPageTitle = gettext('Person Classifications Editor');
        $listID = 1;
        $embedded = false;
        break;
    case 'grptypes':
        $noun = gettext('Type');
        $adjplusname = gettext('Group Type');
        $adjplusnameplural = gettext('Group Types');
        $sPageTitle = gettext('Group Types Editor');
        $listID = 3;
        $embedded = false;
        break;
    case 'securitygrp':
        $noun = gettext('Group');
        $adjplusname = gettext('Security Group');
        $adjplusnameplural = gettext('Security Groups');
        $sPageTitle = gettext('Security Groups Editor');
        $listID = 5;
        $embedded = false;
        break;
    case 'grproles':
        $noun = gettext('Role');
        $adjplusname = gettext('Group Member Role');
        $adjplusnameplural = gettext('Group Member Roles');
        $sPageTitle = gettext('Group Member Roles Editor');
        $listID = InputUtils::legacyFilterInput($_GET['ListID'], 'int');
        $embedded = true;

        $sSQL = 'SELECT grp_DefaultRole FROM group_grp WHERE grp_RoleListID = ' . $listID;
        $rsTemp = RunQuery($sSQL);

        // Validate that this list ID is really for a group roles list. (for security)
        if (mysqli_num_rows($rsTemp) == 0) {
            RedirectUtils::redirect('v2/dashboard');
            break;
        }

        $aTemp = mysqli_fetch_array($rsTemp);
        $iDefaultRole = $aTemp[0];

        break;
    case 'custom':
        $noun = gettext('Option');
        $adjplusname = gettext('Person Custom List Option');
        $adjplusnameplural = gettext('Person Custom List Options');
        $sPageTitle = gettext('Person Custom List Options Editor');
        $listID = InputUtils::legacyFilterInput($_GET['ListID'], 'int');
        $embedded = true;

        $sSQL = "SELECT '' FROM person_custom_master WHERE type_ID = 12 AND custom_Special = " . $listID;
        $rsTemp = RunQuery($sSQL);

        // Validate that this is a valid person-custom field custom list
        if (mysqli_num_rows($rsTemp) == 0) {
            RedirectUtils::redirect('v2/dashboard');
            break;
        }

        break;
    case 'groupcustom':
        $noun = gettext('Option');
        $adjplusname = gettext('Custom List Option');
        $adjplusnameplural = gettext('Custom List Options');
        $sPageTitle = gettext('Custom List Options Editor');
        $listID = InputUtils::legacyFilterInput($_GET['ListID'], 'int');
        $embedded = true;

        $sSQL = "SELECT '' FROM groupprop_master WHERE type_ID = 12 AND prop_Special = " . $listID;
        $rsTemp = RunQuery($sSQL);

        // Validate that this is a valid group-specific-property field custom list
        if (mysqli_num_rows($rsTemp) == 0) {
            RedirectUtils::redirect('v2/dashboard');
            break;
        }

        break;
    case 'famcustom':
        $noun = gettext('Option');
        $adjplusname = gettext('Family Custom List Option');
        $adjplusnameplural = gettext('Family Custom List Options');
        $sPageTitle = gettext('Family Custom List Options Editor');
        $listID = InputUtils::legacyFilterInput($_GET['ListID'], 'int');
        $embedded = true;

        $sSQL = "SELECT '' FROM family_custom_master WHERE type_ID = 12 AND fam_custom_Special = " . $listID;
        $rsTemp = RunQuery($sSQL);

        // Validate that this is a valid family_custom field custom list
        if (mysqli_num_rows($rsTemp) == 0) {
            RedirectUtils::redirect('v2/dashboard');
            break;
        }

        break;
    default:
        RedirectUtils::redirect('v2/dashboard');
        break;
}

$iNewNameError = 0;

// Check if we're adding a field
if (isset($_POST['AddField'])) {
    $newFieldName = InputUtils::legacyFilterInput($_POST['newFieldName']);

    if (strlen($newFieldName) == 0) {
        $iNewNameError = 1;
    } else {
        // Check for a duplicate option name
        $sSQL = "SELECT '' FROM list_lst WHERE lst_ID = $listID AND lst_OptionName = '" . $newFieldName . "'";
        $rsCount = RunQuery($sSQL);
        if (mysqli_num_rows($rsCount) > 0) {
            $iNewNameError = 2;
        } else {
            // Get count of the options
            $sSQL = "SELECT '' FROM list_lst WHERE lst_ID = $listID";
            $rsTemp = RunQuery($sSQL);
            $numRows = mysqli_num_rows($rsTemp);
            $newOptionSequence = $numRows + 1;

            // Get the new OptionID
            $sSQL = "SELECT MAX(lst_OptionID) FROM list_lst WHERE lst_ID = $listID";
            $rsTemp = RunQuery($sSQL);
            $aTemp = mysqli_fetch_array($rsTemp);
            $newOptionID = $aTemp[0] + 1;

            // Insert into the appropriate options table
            $listOption = new ListOption();
            $listOption
                ->setId($listID)
                ->setOptionId($newOptionID)
                ->setOptionName($newFieldName)
                ->setOptionSequence($newOptionSequence);
            $listOption->save();

            $iNewNameError = 0;
        }
    }
}

$bErrorFlag = false;
$bDuplicateFound = false;

// Get the original list of options..
//ADDITION - get Sequence Also
$sSQL = "SELECT lst_OptionName, lst_OptionID, lst_OptionSequence FROM list_lst WHERE lst_ID=$listID ORDER BY lst_OptionSequence";
$rsList = RunQuery($sSQL);
$numRows = mysqli_num_rows($rsList);

$aNameErrors = [];
for ($row = 1; $row <= $numRows; $row++) {
    $aNameErrors[$row] = 0;
}

if (isset($_POST['SaveChanges'])) {
    for ($row = 1; $row <= $numRows; $row++) {
        $aRow = mysqli_fetch_array($rsList, MYSQLI_BOTH);
        $aOldNameFields[$row] = $aRow['lst_OptionName'];
        $aIDs[$row] = $aRow['lst_OptionID'];

        //addition save off sequence also
        $aSeqs[$row] = $aRow['lst_OptionSequence'];

        $aNameFields[$row] = InputUtils::legacyFilterInput($_POST[$row . 'name']);
    }

    for ($row = 1; $row <= $numRows; $row++) {
        if (strlen($aNameFields[$row]) == 0) {
            $aNameErrors[$row] = 1;
            $bErrorFlag = true;
        } elseif ($row < $numRows) {
            $aNameErrors[$row] = 0;
            for ($rowcmp = $row + 1; $rowcmp <= $numRows; $rowcmp++) {
                if ($aNameFields[$row] == $aNameFields[$rowcmp]) {
                    $bErrorFlag = true;
                    $bDuplicateFound = true;
                    $aNameErrors[$row] = 2;
                    break;
                }
            }
        } else {
            $aNameErrors[$row] = 0;
        }
    }

    // If no errors, then update.
    if (!$bErrorFlag) {
        for ($row = 1; $row <= $numRows; $row++) {
            // Update the type's name if it has changed from what was previously stored
            if ($aOldNameFields[$row] != $aNameFields[$row]) {
                $sSQL = "UPDATE list_lst SET `lst_OptionName` = '" . $aNameFields[$row] . "' WHERE `lst_ID` = '$listID' AND `lst_OptionSequence` = '" . $row . "'";
                RunQuery($sSQL);
            }
        }
    }
}

// Get data for the form as it now exists..

$sSQL = "SELECT lst_OptionName, lst_OptionID, lst_OptionSequence FROM list_lst WHERE lst_ID = $listID ORDER BY lst_OptionSequence";
$rsRows = RunQuery($sSQL);
$numRows = mysqli_num_rows($rsRows);

// Create arrays of the option names and IDs
for ($row = 1; $row <= $numRows; $row++) {
    $aRow = mysqli_fetch_array($rsRows, MYSQLI_BOTH);

    if (!$bErrorFlag) {
        $aNameFields[$row] = $aRow['lst_OptionName'];
    }

    $aIDs[$row] = $aRow['lst_OptionID'];
    //addition save off sequence also
    $aSeqs[$row] = $aRow['lst_OptionSequence'];
}

//Set the starting row color
$sRowClass = 'RowColorA';

// Use a minimal page header if this form is going to be used within a frame
if ($embedded) {
    include 'Include/Header-Minimal.php';
} else {    //It don't work for postuguese because in it adjective come after noum
    //$sPageTitle = $adj . ' ' . $noun . "s ".gettext("Editor");
    include 'Include/Header.php';
}

?>
<div class="card">
    <div class="card-body">
<form method="post" action="OptionManager.php?<?= "mode=$mode&ListID=$listID" ?>" name="OptionManager">

<div class="callout callout-warning"><?= gettext('Warning: Removing will reset all assignments for all persons with the assignment!') ?></div>

<?php

if ($bErrorFlag) {
    echo '<span class="MediumLargeText" style="color: red;">';
    if ($bDuplicateFound) {
        echo '<br>' . gettext('Error: Duplicate') . ' ' . $adjplusnameplural . ' ' . gettext('are not allowed.');
    }
    echo '<br>' . gettext('Invalid fields or selections. Changes not saved! Please correct and try again!') . '</span><br><br>';
}
?>

<br>
<table cellpadding="3" width="30%" align="center">

<?php
    $aInactiveClassificationIds = explode(',', SystemConfig::getValue('sInactiveClassification'));
    $aInactiveClasses = array_filter($aInactiveClassificationIds, fn ($k) => is_numeric($k));

    if (count($aInactiveClassificationIds) !== count($aInactiveClasses))  {
        LoggerUtils::getAppLogger()->warning('Encountered invalid configuration(s) for sInactiveClassification, please fix this');
    }

for ($row = 1; $row <= $numRows; $row++) {
    ?>
    <tr align="center">
        <td class="LabelColumn">
            <b>
            <?php
            if ($mode == 'grproles' && $aIDs[$row] == $iDefaultRole) {
                echo gettext('Default') . ' ';
            }
            echo $row; ?>
            </b>
        </td>

        <td class="TextColumn" nowrap>

            <?php
            if ($row != 1) {
                echo "<a href=\"OptionManagerRowOps.php?mode=$mode&Order=$aSeqs[$row]&ListID=$listID&ID=" . $aIDs[$row] . '&Action=up"><i class="fa fa-arrow-up"></i></a>';
            }
            if ($row < $numRows) {
                echo "<a href=\"OptionManagerRowOps.php?mode=$mode&Order=$aSeqs[$row]&ListID=$listID&ID=" . $aIDs[$row] . '&Action=down"><i class="fa fa-arrow-down"></i></a>';
            }
            if ($numRows > 0) {
                echo "<a href=\"OptionManagerRowOps.php?mode=$mode&Order=$aSeqs[$row]&ListID=$listID&ID=" . $aIDs[$row] . '&Action=delete"><i class="fa fa-times"></i></a>';
            } ?>

        </td>
        <td class="TextColumn">
            <span class="SmallText">
                <input class="input-small" type="text" name="<?= $row . 'name' ?>" value="<?= htmlentities(stripslashes($aNameFields[$row]), ENT_NOQUOTES, 'UTF-8') ?>" size="30" maxlength="40">
            </span>
            <?php

            if ($aNameErrors[$row] == 1) {
                echo '<span style="color: red;"><BR>' . gettext('You must enter a name') . ' </span>';
            } elseif ($aNameErrors[$row] == 2) {
                echo '<span style="color: red;"><BR>' . gettext('Duplicate name found.') . ' </span>';
            } ?>
        </td>
        <?php
        if ($mode == 'grproles') {
            echo '<td class="TextColumn"><input class="form-control input-small" type="button" class="btn btn-default" value="' . gettext('Make Default') . "\" Name=\"default\" onclick=\"javascript:document.location='OptionManagerRowOps.php?mode=" . $mode . '&ListID=' . $listID . '&ID=' . $aIDs[$row] . "&Action=makedefault';\" ></td>";
        }
        if ($mode === 'classes') {
            echo "<td>";
            $check = in_array($aIDs[$row], $aInactiveClasses) ? "checked" : "";
            echo "<input id='inactive$aIDs[$row]' type=\"checkbox\" onclick=\"$.get('OptionManagerRowOps.php?mode=$mode&Order=$aSeqs[$row]&ListID=$listID&ID=" . $aIDs[$row] . "&Action=Inactive')\" $check >";
            echo gettext("Inactive");
            echo "</td>";
        } ?>

    </tr>
    <?php
} ?>

</table>
  <br/>
    <input type="submit" class="btn btn-primary" value="<?= gettext('Save Changes') ?>" Name="SaveChanges">


    <?php if ($mode == 'groupcustom' || $mode == 'custom' || $mode == 'famcustom') {
        ?>
        <input type="button" class="btn btn-default" value="<?= gettext('Exit') ?>" Name="Exit" onclick="javascript:window.close();">
        <?php
    } elseif ($mode != 'grproles') {
        ?>
        <input type="button" class="btn btn-default" value="<?= gettext('Exit') ?>" Name="Exit" onclick="javascript:document.location='<?php
        echo 'v2/dashboard'; ?>';">
        <?php
    } ?>
    </div>
</div>

<div class="card card-primary">
    <div class="card-body">
<?=  gettext('Name for New') . ' ' . $noun ?>:&nbsp;
<span class="SmallText">
    <input class="form-control input-small" type="text" name="newFieldName" size="30" maxlength="40">
</span>
<p>  </p>
<input type="submit" class="btn btn-default" value="<?= gettext('Add New') . ' ' . $adjplusname ?>" Name="AddField">
<?php
if ($iNewNameError > 0) {
    echo '<div><span style="color: red;"><BR>';
    if ($iNewNameError == 1) {
        echo gettext('Error: You must enter a name');
    } else {
        echo gettext('Error: A ') . $noun . gettext(' by that name already exists.');
    }
    echo '</span></div>';
}
?>
</center>
</form>
    </div>
</div>
<?php
if ($embedded) {
    echo '</body></html>';
} else {
    include 'Include/Footer.php';
}
?>
