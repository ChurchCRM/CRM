<?php

/*******************************************************************************
 *
 *  filename    : PropertyUnassign.php
 *  last change : 2003-01-07
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2001, 2002 Deane Barker
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have Manage Groups or Edit Records permissions
// Otherwise, re-direct them to the main menu.
if (!AuthenticationManager::getCurrentUser()->isManageGroupsEnabled() && !AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
    RedirectUtils::redirect('Menu.php');
    exit;
}

//Get the new property value from the post collection
$iPropertyID = InputUtils::legacyFilterInput($_GET['PropertyID'], 'int');

// Is there a PersonID in the querystring?
if (isset($_GET['PersonID']) && AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
    $iPersonID = InputUtils::legacyFilterInput($_GET['PersonID'], 'int');
    $iRecordID = $iPersonID;
    $sQuerystring = '?PersonID=' . $iPersonID;
    $sTypeName = 'Person';
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
    $sTypeName = 'Group';
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
    $sTypeName = 'Family';
    $sBackPage = 'v2/family/' . $iFamilyID;

    // Get the name of the family
    $sSQL = 'SELECT fam_Name FROM family_fam WHERE fam_ID = ' . $iFamilyID;
    $rsName = RunQuery($sSQL);
    $aRow = mysqli_fetch_array($rsName);
    $sName = $aRow['fam_Name'];
} else {
    // Somebody tried to call the script with no options
    RedirectUtils::redirect('Menu.php');
    exit;
}

//Do we have confirmation?
if (isset($_GET['Confirmed'])) {
    $sSQL = 'DELETE FROM record2property_r2p WHERE r2p_record_ID = ' . $iRecordID . ' AND r2p_pro_ID = ' . $iPropertyID;
    RunQuery($sSQL);
    RedirectUtils::redirect($sBackPage);
    exit;
}

//Get the name of the property
$sSQL = 'SELECT pro_Name FROM property_pro WHERE pro_ID = ' . $iPropertyID;
$rsProperty = RunQuery($sSQL);
$aRow = mysqli_fetch_array($rsProperty);
$sPropertyName = $aRow['pro_Name'];

//Set the page title
$sPageTitle = $sTypeName . gettext(' Property Unassignment');

//Include the header
require 'Include/Header.php';

?>

<?= gettext('Please confirm removal of this property from this') . ' ' . $sTypeName ?>:


<table class="table table-striped">
    <tr>
        <td><b><?php echo $sTypeName ?>:</b></td>
        <td><?= $sName ?></td>
    </tr>
    <tr>
        <td><b><?= gettext('Unassigning') ?>:</b></td>
        <td><?= $sPropertyName ?></td>
    </tr>
</table>

<div class="text-center">
    <a class="btn btn-default" href="<?= $sBackPage ?>"><?= gettext('No, retain this assignment') ?></a>

    <a class="btn btn-danger" href="PropertyUnassign.php<?= $sQuerystring . '&PropertyID=' . $iPropertyID . '&Confirmed=Yes' ?>"><?= gettext('Yes, unassign this Property') ?></a>
</div>

<?php require 'Include/Footer.php' ?>
