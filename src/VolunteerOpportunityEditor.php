<?php

/*******************************************************************************
 *
 *  filename    : VolunteerOpportunityEditor.php
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2005 Michael Wilt
 *
 ******************************************************************************/

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\VolunteerOpportunity;
use ChurchCRM\model\ChurchCRM\VolunteerOpportunityQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have proper permission
// For now ... require $bAdmin
// Future ... $bManageVol
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isAdmin());

// top down design....
// title line
// separator line
// warning line
// first input line: [ Save Changes ] [ Exit ]
// column titles
// first record: text box with order, up, down, delete ; Name, Desc, Active radio buttons
// and so on
// action is change of order number, up, down, delete, Name, Desc, or Active, or Add New

$iOpp = -1;
$sAction = '';
$iRowNum = -1;
$bErrorFlag = false;
$aNameErrors = [];
$bNewNameError = false;

if (array_key_exists('act', $_GET)) {
    $sAction = InputUtils::legacyFilterInput($_GET['act']);
}
if (array_key_exists('Opp', $_GET)) {
    $iOpp = InputUtils::legacyFilterInput($_GET['Opp'], 'int');
}
if (array_key_exists('row_num', $_GET)) {
    $iRowNum = InputUtils::legacyFilterInput($_GET['row_num'], 'int');
}

$sDeleteError = '';

if (($sAction == 'delete') && $iOpp > 0) {
    // Delete Confirmation Page

    // Security: User must have Delete records permission
    // Otherwise, redirect to the main menu
    AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled());

    $sSQL = "SELECT * FROM `volunteeropportunity_vol` WHERE `vol_ID` = '" . $iOpp . "'";
    $rsOpps = RunQuery($sSQL);
    $aRow = mysqli_fetch_array($rsOpps);
    extract($aRow);

    $sPageTitle = gettext('Volunteer Opportunity Delete Confirmation');
    require 'Include/Header.php'; ?>
    <div class="card card-body">
    <div class="callout callout-danger"><?= gettext('Please confirm deletion of') ?>:</div>
    <table class="table">
        <tr><th>&nbsp;</th>
        <th><?= gettext('Name') ?></th>
        <th><?= gettext('Description') ?></th>
    </tr>
    <tr>
        <td><b><?= $vol_Order ?></b></td>
        <td><?= $vol_Name ?></td>
        <td><?= $vol_Description ?></td>
    </tr>
    </table>

    <?php
    // Do some error checking before deleting this Opportunity.
    // Notify user if there are currently people assigned to this
    // Volunteer Opportunity.

    $sSQL = 'SELECT `per_FirstName`, `per_LastName` FROM `person_per` ';
    $sSQL .= 'LEFT JOIN `person2volunteeropp_p2vo` ';
    $sSQL .= 'ON `p2vo_per_ID`=`per_ID` ';
    $sSQL .= "WHERE `p2vo_vol_ID` = '" . $iOpp . "' ";
    $sSQL .= 'ORDER BY `per_LastName`, `per_FirstName` ';
    $rsPeople = RunQuery($sSQL);
    $numRows = mysqli_num_rows($rsPeople);
    if ($numRows > 0) {
        echo "\n<br><h3>" . gettext('Warning') . '!</h3>';
        echo "\n<h3>" . gettext('There are people assigned to this Volunteer Opportunity.') . '</h3>';
        echo "\n<br>" . gettext('Volunteer Opportunity will be unassigned for the following people.');
        echo "\n<br>";
        for ($i = 0; $i < $numRows; $i++) {
            $aRow = mysqli_fetch_array($rsPeople);
            extract($aRow);
            echo "\n<br><b> $per_FirstName $per_LastName</b>";
        }
    }
    echo "\n<br><a class='btn btn-danger' href=\"VolunteerOpportunityEditor.php?act=ConfDelete&amp;Opp=" . $iOpp . '"> ';
    echo gettext('Yes, delete this Volunteer Opportunity') . ' </a>';
    echo "\n<a href=\"VolunteerOpportunityEditor.php\" class='btn btn-default'> ";
    echo gettext('No, cancel this deletion') . ' </a>';
    echo '</div>';
    require 'Include/Footer.php';
    exit;
}

if (($sAction == 'ConfDelete') && $iOpp > 0) {
    // Security: User must have Delete records permission
    // Otherwise, redirect to the main menu
    AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled());

    // get the order value for the record being deleted
    $sSQL = "SELECT vol_Order from volunteeropportunity_vol WHERE vol_ID='$iOpp'";
    $rsOrder = RunQuery($sSQL);
    $aRow = mysqli_fetch_array($rsOrder);
    $orderVal = $aRow[0];
    $sSQL = "DELETE FROM `volunteeropportunity_vol` WHERE `vol_ID` = '" . $iOpp . "'";
    RunQuery($sSQL);
    $sSQL = "DELETE FROM `person2volunteeropp_p2vo` WHERE `p2vo_vol_ID` = '" . $iOpp . "'";
    RunQuery($sSQL);
    // pull back all the vol_Order fields that are higher than the one just deleted
    $sSQL = "UPDATE volunteeropportunity_vol SET vol_Order=vol_Order-1 WHERE vol_Order>=$orderVal";
    RunQuery($sSQL);
}

if ($iRowNum == 0) {
    // Skip data integrity check if we are only changing the ordering
    // by moving items up or down.
    // System response is too slow to do these checks every time the page
    // is viewed.

    // Data integrity checks performed when adding or deleting records.
    // Also on initial page view

    // Data integrity check #1
    // The default value of `vol_Order` is '0'.  But '0' is not assigned.
    // If we find a '0' add it to the end of the list by changing it to
    // MAX(vol_Order)+1.

    $sSQL = "SELECT `vol_ID` FROM `volunteeropportunity_vol` WHERE vol_Order = '0' ";
    $sSQL .= 'ORDER BY `vol_ID`';
    $rsOrder = RunQuery($sSQL);
    $numRows = mysqli_num_rows($rsOrder);
    if ($numRows) {
        $sSQL = 'SELECT MAX(`vol_Order`) AS `Max_vol_Order` FROM `volunteeropportunity_vol`';
        $rsMax = RunQuery($sSQL);
        $aRow = mysqli_fetch_array($rsMax);
        extract($aRow);
        for ($row = 1; $row <= $numRows; $row++) {
            $aRow = mysqli_fetch_array($rsOrder);
            extract($aRow);
            $num_vol_Order = $Max_vol_Order + $row;
            $volunteerOpp = VolunteerOpportunityQuery::create()->findOneById($vol_ID);
            $volunteerOpp->setOrder($num_vol_Order);
            $volunteerOpp->save();
        }
    }

    // Data integrity check #2
    // re-order the vol_Order field just in case there is a missing number(s)
    $sSQL = 'SELECT * FROM `volunteeropportunity_vol` ORDER by `vol_Order`';
    $rsOpps = RunQuery($sSQL);
    $numRows = mysqli_num_rows($rsOpps);

    $orderCounter = 1;
    for ($row = 1; $row <= $numRows; $row++) {
        $aRow = mysqli_fetch_array($rsOpps);
        extract($aRow);
        if ($orderCounter != $vol_Order) { // found hole, update all records to the end
            $volunteerOpp = VolunteerOpportunityQuery::create()->findOneById($vol_ID);
            $volunteerOpp->setOrder($orderCounter);
            $volunteerOpp->save();
        }
        ++$orderCounter;
    }
}

$sPageTitle = gettext('Volunteer Opportunity Editor');

require 'Include/Header.php';

// Does the user want to save changes to text fields?
if (isset($_POST['SaveChanges'])) {
    $sSQL = 'SELECT * FROM `volunteeropportunity_vol`';
    $rsOpps = RunQuery($sSQL);
    $numRows = mysqli_num_rows($rsOpps);

    for ($iFieldID = 1; $iFieldID <= $numRows; $iFieldID++) {
        $nameName = $iFieldID . 'name';
        $descName = $iFieldID . 'desc';
        if (array_key_exists($nameName, $_POST)) {
            $aNameFields[$iFieldID] = InputUtils::legacyFilterInput($_POST[$nameName]);

            if (strlen($aNameFields[$iFieldID]) == 0) {
                $aNameErrors[$iFieldID] = true;
                $bErrorFlag = true;
            } else {
                $aNameErrors[$iFieldID] = false;
            }

            $aDescFields[$iFieldID] = InputUtils::legacyFilterInput($_POST[$descName]);

            $aRow = mysqli_fetch_array($rsOpps);
            $aIDFields[$iFieldID] = $aRow[0];
        }
    }

    // If no errors, then update.
    if (!$bErrorFlag) {
        for ($iFieldID = 1; $iFieldID <= $numRows; $iFieldID++) {
            if (array_key_exists($iFieldID, $aNameFields)) {
                $volunteerOpp = VolunteerOpportunityQuery::create()->findOneById($aIDFields[$iFieldID]);
                $volunteerOpp
                    ->setName($aNameFields[$iFieldID])
                    ->setDescription($aDescFields[$iFieldID]);
                $volunteerOpp->save();
            }
        }
    }
} else {
    if (isset($_POST['AddField'])) { // Check if we're adding a VolOp
        $newFieldName = InputUtils::legacyFilterInput($_POST['newFieldName']);
        $newFieldDesc = InputUtils::legacyFilterInput($_POST['newFieldDesc']);
        if (strlen($newFieldName) == 0) {
            $bNewNameError = true;
        } else { // Insert into table
            //  there must be an easier way to get the number of rows in order to generate the last order number.
            $sSQL = 'SELECT * FROM `volunteeropportunity_vol`';
            $rsOpps = RunQuery($sSQL);
            $numRows = mysqli_num_rows($rsOpps);
            $newOrder = $numRows + 1;

            $volunteerOpp = new VolunteerOpportunity();
            $volunteerOpp
                ->setOrder($newOrder)
                ->setName($newFieldName)
                ->setDescription($newFieldDesc);
            $volunteerOpp->save();

            $bNewNameError = false;
        }
    }
    // Get data for the form as it now exists..
    $sSQL = 'SELECT * FROM `volunteeropportunity_vol`';

    $rsOpps = RunQuery($sSQL);
    $numRows = mysqli_num_rows($rsOpps);

    // Create arrays of Vol Opps.
    for ($row = 1; $row <= $numRows; $row++) {
        $aRow = mysqli_fetch_array($rsOpps, MYSQLI_BOTH);
        extract($aRow);
        $rowIndex = $vol_Order; // is this dangerous?  the vol_Order field had better be correct.
        $aIDFields[$rowIndex] = $vol_ID;
        $aNameFields[$rowIndex] = $vol_Name;
        $aDescFields[$rowIndex] = $vol_Description;
    }
}

// Construct the form

?>
<div class="card card-body">
<form method="post" action="VolunteerOpportunityEditor.php" name="OppsEditor">

<table class="table">

<?php
if ($numRows == 0) {
    ?>
    <div class="callout callout-warning"><?= gettext('No volunteer opportunities have been added yet') ?></div>
    <?php
} else { // if an 'action' (up/down arrow clicked, or order was input)
    if ($iRowNum && $sAction != '') {
        // cast as int and couple with switch for sql injection prevention for $row_num
        $swapRow = $iRowNum;
        if ($sAction == 'up') {
            $newRow = --$iRowNum;
        } elseif ($sAction == 'down') {
            $newRow = ++$iRowNum;
        } else {
            $newRow = $iRowNum;
        }

        if (array_key_exists($swapRow, $aIDFields)) {
            $volunteerOpp = VolunteerOpportunityQuery::create()->findOneById($aIDFields[$swapRow]);
            $volunteerOpp->setOrder($newRow);
            $volunteerOpp->save();
        }

        if (array_key_exists($newRow, $aIDFields)) {
            $volunteerOpp = VolunteerOpportunityQuery::create()->findOneById($aIDFields[$newRow]);
            $volunteerOpp->setOrder($swapRow);
            $volunteerOpp->save();
        }

        // now update internal data to match
        if (array_key_exists($swapRow, $aIDFields)) {
            $saveID = $aIDFields[$swapRow];
            $saveName = $aNameFields[$swapRow];
            $saveDesc = $aDescFields[$swapRow];
            $aIDFields[$newRow] = $saveID;
            $aNameFields[$newRow] = $saveName;
            $aDescFields[$newRow] = $saveDesc;
        }

        if (array_key_exists($newRow, $aIDFields)) {
            $aIDFields[$swapRow] = $aIDFields[$newRow];
            $aNameFields[$swapRow] = $aNameFields[$newRow];
            $aDescFields[$swapRow] = $aDescFields[$newRow];
        }
    }
} // end if GET

?>
<tr>
    <td colspan="5">
        <div class="callout callout-info"><?= gettext("NOTE: ADD, Delete, and Ordering changes are immediate.  Changes to Name or Desc fields must be saved by pressing 'Save Changes'") ?></div>
    </td>
</tr>
<tr>
    <td colspan="5">
        <?php
        if ($bErrorFlag) {
            echo '<div class="callout callout-danger">';
            echo gettext('Invalid fields or selections. Changes not saved! Please correct and try again!');
            echo '</div>';
        }
        if (strlen($sDeleteError) > 0) {
            echo ' <div class="callout callout-danger">';
            echo $sDeleteError;
            echo '</div>';
        }
        ?>
    </td>
</tr>
<tr>
<th></th>
<th></th>
<th><?= gettext('Name') ?></th>
<th><?= gettext('Description') ?></th>
</tr>

<?php

for ($row = 1; $row <= $numRows; $row++) {
    if (array_key_exists($row, $aNameFields)) {
        echo '<tr>';
        echo '<td class="LabelColumn"><b>' . $row . '</b></td>';
        echo '<td class="TextColumn">';
        if ($row == 1) {
            echo '<a href="VolunteerOpportunityEditor.php?act=na&amp;row_num=' . $row . "\"><i class='fa fa-fw'></i></a>";
        } else {
            echo '<a href="VolunteerOpportunityEditor.php?act=up&amp;row_num=' . $row . "\"> <i class='fa fa-arrow-up'></i></a> ";
        }
        if ($row != $numRows) {
            echo '<a href="VolunteerOpportunityEditor.php?act=down&amp;row_num=' . $row . "\"> <i class='fa fa-arrow-down'></i></a> ";
        } else {
            echo '<a href="VolunteerOpportunityEditor.php?act=na&amp;row_num=' . $row . "\"> <i class='fa fa-fw'></i></a> ";
        }

        echo '<a href="VolunteerOpportunityEditor.php?act=delete&amp;Opp=' . $aIDFields[$row] . "\"> <i class='fa fa-times'></i></a></td>"; ?>

       <td class="TextColumn" align="center">
       <input type="text" name="<?= $row . 'name' ?>" value="<?= htmlentities(stripslashes($aNameFields[$row]), ENT_NOQUOTES, 'UTF-8') ?>" class="form-control" size="20" maxlength="30">
        <?php

        if (array_key_exists($row, $aNameErrors) && $aNameErrors[$row]) {
            echo '<span style="color: red;"><BR>' . gettext('You must enter a name') . ' </span>';
        } ?>
       </td>

       <td class="TextColumn">
       <input type="text" name="<?= $row ?>desc" value="<?= htmlentities(stripslashes($aDescFields[$row]), ENT_NOQUOTES, 'UTF-8') ?>" class="form-control" size="40" maxlength="100">
       </td>

       </tr>
        <?php
    }
}
?>

<tr>
<td colspan="5">
<table width="100%">
<tr>
<td width="30%"></td>
<td width="40%" align="center" valign="bottom">
<input type="submit" class="btn btn-primary" value="<?= gettext('Save Changes') ?>" Name="SaveChanges">
&nbsp;
<input type="button" class="btn btn-default" value="<?= gettext('Exit') ?>" Name="Exit" onclick="javascript:document.location='v2/dashboard'">
</td>
<td width="30%"></td>
</tr>
</table>
</td>
<td>
</tr>

<tr><td colspan="5"><hr></td></tr>
<tr>
<td colspan="5">
<table width="100%">
<tr>
<td width="15%"></td>
<td valign="top">
<div><?= gettext('Name') ?>:</div>
<input type="text" name="newFieldName" size="30" maxlength="30" class="form-control">
<?php if ($bNewNameError) {
    echo '<div><span style="color: red;"><BR>' . gettext('You must enter a name') . '</span></div>';
} ?>
&nbsp;
</td>
<td valign="top">
<div><?= gettext('Description') ?>:</div>
<input type="text" name="newFieldDesc" size="40" maxlength="100" class="form-control">
&nbsp;
</td>
<td>
<input type="submit" class="btn btn-primary" value="<?= gettext('Add New Opportunity') ?>" name="AddField">
</td>
<td width="15%"></td>
</tr>
</table>
</td>
</tr>
</table>
</form>
</div>

<?php require 'Include/Footer.php' ?>
