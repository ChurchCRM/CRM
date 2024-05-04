<?php

/*******************************************************************************
 *
 *  filename    : GroupPropsFormRowOps.php
 *  last change : 2013-02-09
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2003 Chris Gebhardt (http://www.openserve.org)
 *
 *  function    : Row operations for the group-specific properties form
 *******************************************************************************/

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: user must be allowed to edit records to use this page.
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isManageGroupsEnabled());

// Get the Group, Property, and Action from the querystring
$iGroupID = InputUtils::legacyFilterInput($_GET['GroupID'], 'int');
$iPropID = InputUtils::legacyFilterInput($_GET['PropID'], 'int');
$sField = InputUtils::legacyFilterInput($_GET['Field']);
$sAction = $_GET['Action'];

// Get the group information
$sSQL = 'SELECT * FROM group_grp WHERE grp_ID = ' . $iGroupID;
$rsGroupInfo = RunQuery($sSQL);
extract(mysqli_fetch_array($rsGroupInfo));

// Abort if user tries to load with group having no special properties.
if ($grp_hasSpecialProps == false) {
    RedirectUtils::redirect('GroupView.php?GroupID=' . $iGroupID);
}

switch ($sAction) {
    // Move a field up:  Swap the prop_ID (ordering) of the selected row and the one above it
    case 'up':
        $sSQL = "UPDATE groupprop_master SET prop_ID = '" . $iPropID . "' WHERE grp_ID = '" . $iGroupID . "' AND prop_ID = '" . ($iPropID - 1) . "'";
        RunQuery($sSQL);
        $sSQL = "UPDATE groupprop_master SET prop_ID = '" . ($iPropID - 1) . "' WHERE grp_ID = '" . $iGroupID . "' AND prop_Field = '" . $sField . "'";
        RunQuery($sSQL);
        break;

        // Move a field down:  Swap the prop_ID (ordering) of the selected row and the one below it
    case 'down':
        $sSQL = "UPDATE groupprop_master SET prop_ID = '" . $iPropID . "' WHERE grp_ID = '" . $iGroupID . "' AND prop_ID = '" . ($iPropID + 1) . "'";
        RunQuery($sSQL);
        $sSQL = "UPDATE groupprop_master SET prop_ID = '" . ($iPropID + 1) . "' WHERE grp_ID = '" . $iGroupID . "' AND prop_Field = '" . $sField . "'";
        RunQuery($sSQL);
        break;

        // Delete a field from the form
    case 'delete':
        // Check if this field is a custom list type.  If so, the list needs to be deleted from list_lst.
        $sSQL = "SELECT type_ID,prop_Special FROM groupprop_master WHERE grp_ID = '" . $iGroupID . "' AND prop_Field = '" . $sField . "'";
        $rsTemp = RunQuery($sSQL);
        $aTemp = mysqli_fetch_array($rsTemp);
        if ($aTemp[0] == 12) {
            $sSQL = "DELETE FROM list_lst WHERE lst_ID = $aTemp[1]";
            RunQuery($sSQL);
        }

        $sSQL = 'ALTER TABLE `groupprop_' . $iGroupID . '` DROP `' . $sField . '` ;';
        RunQuery($sSQL);

        $sSQL = "DELETE FROM groupprop_master WHERE grp_ID = '" . $iGroupID . "' AND prop_ID = '" . $iPropID . "'";
        RunQuery($sSQL);

        $sSQL = 'SELECT *	FROM groupprop_master WHERE grp_ID = ' . $iGroupID;
        $rsPropList = RunQuery($sSQL);
        $numRows = mysqli_num_rows($rsPropList);

        // Shift the remaining rows up by one, unless we've just deleted the only row
        if ($numRows != 0) {
            for ($reorderRow = $iPropID + 1; $reorderRow <= $numRows + 1; $reorderRow++) {
                $sSQL = "UPDATE groupprop_master SET prop_ID = '" . ($reorderRow - 1) . "' WHERE grp_ID = '" . $iGroupID . "' AND prop_ID = '" . $reorderRow . "'";
                RunQuery($sSQL);
            }
        }
        break;

        // If no valid action was specified, abort and return to the GroupView
    default:
        RedirectUtils::redirect('GroupView.php?GroupID=' . $iGroupID);
        break;
}

// Reload the Form Editor page
RedirectUtils::redirect('GroupPropsFormEditor.php?GroupID=' . $iGroupID);
