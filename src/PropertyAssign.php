<?php

/*******************************************************************************
 *
 *  filename    : PropertyAssign.php
 *  last change : 2003-06-04
 *  description : property assign
 *
 *  https://churchcrm.io/
 *  Copyright 2001-2003 Phillip Hullquist, Deane Barker, Chris Gebhardt
  *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have Manage Groups or Edit Records permissions
// Otherwise, re-direct them to the main menu.
if (!AuthenticationManager::getCurrentUser()->isManageGroupsEnabled() && !AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
    RedirectUtils::redirect('v2/dashboard');
}

$sValue = '';

// Get the new property value from the request
if (isset($_POST['PropertyID'])) {
    $iPropertyID = InputUtils::legacyFilterInput($_POST['PropertyID'], 'int');
    $sAction = 'add';
} else {
    $iPropertyID = InputUtils::legacyFilterInput($_GET['PropertyID'], 'int');
    $sAction = 'edit';
}

if (isset($_GET['PersonID']) && AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
    // Is there a PersonID in the querystring?
    $iPersonID = InputUtils::legacyFilterInput($_GET['PersonID'], 'int');
    $iRecordID = $iPersonID;
    $sQuerystring = '?PersonID=' . $iPersonID;
    $sTypeName = gettext('Person');
    $sBackPage = 'PersonView.php?PersonID=' . $iPersonID;

    // Get the name of the person
    $sSQL = 'SELECT per_FirstName, per_LastName FROM person_per WHERE per_ID = ' . $iPersonID;
    $rsName = RunQuery($sSQL);
    $aRow = mysqli_fetch_array($rsName);
    $sName = $aRow['per_LastName'] . ', ' . $aRow['per_FirstName'];
} elseif (isset($_GET['GroupID']) && AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
    // Is there a GroupID in the querystring?
    $iGroupID = InputUtils::legacyFilterInput($_GET['GroupID'], 'int');
    $iRecordID = $iGroupID;
    $sQuerystring = '?GroupID=' . $iGroupID;
    $sTypeName = gettext('Group');
    $sBackPage = 'GroupView.php?GroupID=' . $iGroupID;

    // Get the name of the group
    $sSQL = 'SELECT grp_Name FROM group_grp WHERE grp_ID = ' . $iGroupID;
    $rsName = RunQuery($sSQL);
    $aRow = mysqli_fetch_array($rsName);
    $sName = $aRow['grp_Name'];
} elseif (isset($_GET['FamilyID']) && AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
    // Is there a FamilyID in the querystring?
    $iFamilyID = InputUtils::legacyFilterInput($_GET['FamilyID'], 'int');
    $iRecordID = $iFamilyID;
    $sQuerystring = '?FamilyID=' . $iFamilyID;
    $sTypeName = gettext('Family');
    $sBackPage = 'v2/family/' . $iFamilyID;

    // Get the name of the family
    $sSQL = 'SELECT fam_Name FROM family_fam WHERE fam_ID = ' . $iFamilyID;
    $rsName = RunQuery($sSQL);
    $aRow = mysqli_fetch_array($rsName);
    $sName = $aRow['fam_Name'];
} else {
    // Somebody tried to call the script with no options
    RedirectUtils::redirect('v2/dashboard');
}

// If no property, return to previous page
if (!$iPropertyID) {
    RedirectUtils::redirect("$sBackPage");
}

function UpdateProperty($iRecordID, $sValue, $iPropertyID, $sAction)
{
    global $cnInfoCentral;

    if ($sAction === 'add') {
        // Make sure this property isn't already assigned
        $sSQL = "SELECT * FROM record2property_r2p WHERE r2p_record_ID = $iRecordID AND r2p_pro_ID = $iPropertyID";
        $rsExistingTest = RunQuery($sSQL);

        if (mysqli_num_rows($rsExistingTest) == 0) {
            $sSQL = "INSERT INTO record2property_r2p (r2p_record_ID,r2p_pro_ID,r2p_Value) VALUES ($iRecordID,$iPropertyID,'$sValue')";
            RunQuery($sSQL);
        }
    } else {
        $sSQL = "UPDATE record2property_r2p SET r2p_Value = '$sValue' WHERE r2p_record_ID = $iRecordID AND r2p_pro_ID = $iPropertyID";
        RunQuery($sSQL);
    }
}

// Was the form submitted?
if (isset($_POST['SecondPass'])) {
    // Get the action (this will overwrite the value set at the top of the page, which is fine)
    $sAction = $_POST['Action'];

    // Get the value
    $sValue = InputUtils::legacyFilterInput($_POST['Value']);

    // Update the property
    UpdateProperty($iRecordID, $sValue, $iPropertyID, $sAction);

    // Set the Global Message
    $_SESSION['sGlobalMessage'] = gettext('Property successfully assigned.');

    // Back to the PersonView
    RedirectUtils::redirect($sBackPage);
}

// Get the name of the property
$sSQL = 'SELECT pro_Name, pro_Prompt FROM property_pro WHERE pro_ID = ' . $iPropertyID;
$rsProperty = RunQuery($sSQL);
$aRow = mysqli_fetch_array($rsProperty);
$sPropertyName = $aRow['pro_Name'];
$sPrompt = $aRow['pro_Prompt'];

// If there's no prompt, then just do the insert
if (strlen($sPrompt) == 0) {
    UpdateProperty($iRecordID, '', $iPropertyID, $sAction);

    // Set the Global Message
    $_SESSION['sGlobalMessage'] = gettext('Property successfully assigned.');

    // Back to the PersonView
    RedirectUtils::redirect($sBackPage);
}

// If we're editing, get the value
if ($sAction === 'edit') {
    $sSQL = 'SELECT r2p_Value FROM record2property_r2p WHERE r2p_pro_ID = ' . $iPropertyID . ' AND r2p_record_ID = ' . $iRecordID;
    $rsValue = RunQuery($sSQL);
    $aRow = mysqli_fetch_array($rsValue);
    $sValue = $aRow['r2p_Value'];
}

// Set the page title and include HTML header
$sPageTitle = $sTypeName . ' : ' . gettext(' Property Assignment');
require 'Include/Header.php';
?>

<form method="post" action="PropertyAssign.php<?= $sQuerystring . '&PropertyID=' . $iPropertyID ?>">
<input type="hidden" name="SecondPass" value="True">
<input type="hidden" name="Action" value="<?= $sAction ?>">
<div class="table-responsive">
<table class="table table-striped">
    <tr>
        <td align="right"><b><?= $sTypeName ?>:</b></td>
        <td><?= $sName ?></td>
    </tr>
    <tr>
        <td align="right"><b><?= gettext('Assigning') ?>:</b></td>
        <td><?php echo $sPropertyName ?></td>
<?php if (strlen($sPrompt)) {
    ?>
        <tr>
            <td align="right" valign="top">
                <b><?= gettext('Value') ?>:</b>
            </td>
            <td>
                <?= $sPrompt ?>
                <p><br/></p>
                <textarea name="Value" cols="60" rows="10"><?= $sValue ?></textarea>
            </td>
        </tr>
    <?php
} ?>
</table>
</div>

<p align="center"><input type="submit" class="btn btn-primary" <?= 'value="'; if ($sAction === 'add') {
        echo gettext('Assign');
                                                               } else {
                                                                   echo gettext('Update');
                                                               } echo '"' ?> name="Submit"></p>

</form>

<?php require 'Include/Footer.php' ?>
