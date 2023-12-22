<?php

/*******************************************************************************
 *
 *  filename    : OptionManagerRowOps.php
 *  last change : 2003-04-09
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2003 Chris Gebhardt (http://www.openserve.org)
 *
 *  function    : Row operations for the option manager
 *******************************************************************************/

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Get the Order, ID, Mode, and Action from the querystring
if (array_key_exists('Order', $_GET)) {
    $iOrder = InputUtils::legacyFilterInput($_GET['Order'], 'int');
}  // the option Sequence
$sAction = $_GET['Action'];
$iID = InputUtils::legacyFilterInput($_GET['ID'], 'int');  // the option ID
$mode = trim($_GET['mode']);

// Check security for the mode selected.
switch ($mode) {
    case 'famroles':
    case 'classes':
        if (!AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()) {
            RedirectUtils::redirect('Menu.php');
            exit;
        }
        break;

    case 'grptypes':
    case 'grproles':
        if (!AuthenticationManager::getCurrentUser()->isManageGroupsEnabled()) {
            RedirectUtils::redirect('Menu.php');
            exit;
        }
        break;

    case 'custom':
    case 'famcustom':
        if (!AuthenticationManager::getCurrentUser()->isAdmin()) {
            RedirectUtils::redirect('Menu.php');
            exit;
        }
        break;
    default:
        RedirectUtils::redirect('Menu.php');
        break;
}

// Set appropriate table and field names for the editor mode
switch ($mode) {
    case 'famroles':
        $deleteCleanupTable = 'person_per';
        $deleteCleanupColumn = 'per_fmr_ID';
        $deleteCleanupResetTo = 0;
        $listID = 2;
        break;
    case 'classes':
        $deleteCleanupTable = 'person_per';
        $deleteCleanupColumn = 'per_cls_ID';
        $deleteCleanupResetTo = 0;
        $listID = 1;
        break;
    case 'grptypes':
        $deleteCleanupTable = 'group_grp';
        $deleteCleanupColumn = 'grp_Type';
        $deleteCleanupResetTo = 0;
        $listID = 3;
        break;
    case 'grproles':
        $listID = InputUtils::legacyFilterInput($_GET['ListID'], 'int');

        // Validate that this list ID is really for a group roles list. (for security)
        $sSQL = "SELECT '' FROM group_grp WHERE grp_RoleListID = " . $listID;
        $rsTemp = RunQuery($sSQL);
        if (mysqli_num_rows($rsTemp) == 0) {
            RedirectUtils::redirect('Menu.php');
            break;
        }

        break;
    case 'custom':
    case 'famcustom':
        $listID = InputUtils::legacyFilterInput($_GET['ListID'], 'int');
        break;
}

switch ($sAction) {
    // Move a field up:  Swap the OptionSequence (ordering) of the selected row and the one above it
    case 'up':
        $sSQL = "UPDATE list_lst SET lst_OptionSequence = '" . $iOrder . "' WHERE lst_ID = $listID AND lst_OptionSequence = '" . ($iOrder - 1) . "'";
        RunQuery($sSQL);
        $sSQL = "UPDATE list_lst SET lst_OptionSequence = '" . ($iOrder - 1) . "' WHERE lst_ID = $listID AND lst_OptionID = '" . $iID . "'";
        RunQuery($sSQL);
        break;

        // Move a field down:  Swap the OptionSequence (ordering) of the selected row and the one below it
    case 'down':
        $sSQL = "UPDATE list_lst SET lst_OptionSequence = '" . $iOrder . "' WHERE lst_ID = $listID AND lst_OptionSequence = '" . ($iOrder + 1) . "'";
        RunQuery($sSQL);
        $sSQL = "UPDATE list_lst SET lst_OptionSequence = '" . ($iOrder + 1) . "' WHERE lst_ID = $listID AND lst_OptionID = '" . $iID . "'";
        RunQuery($sSQL);
        break;

        // Delete a field from the form
    case 'delete':
        $sSQL = "SELECT '' FROM list_lst WHERE lst_ID = $listID";
        $rsPropList = RunQuery($sSQL);
        $numRows = mysqli_num_rows($rsPropList);

        // Make sure we never delete the only option
        if ($numRows > 1) {
            $sSQL = "DELETE FROM list_lst WHERE lst_ID = $listID AND lst_OptionSequence = '" . $iOrder . "'";
            RunQuery($sSQL);

            // Shift the remaining rows up by one
            for ($reorderRow = $iOrder + 1; $reorderRow <= $numRows + 1; $reorderRow++) {
                $sSQL = "UPDATE list_lst SET lst_OptionSequence = '" . ($reorderRow - 1) . "' WHERE lst_ID = $listID AND lst_OptionSequence = '" . $reorderRow . "'";
                RunQuery($sSQL);
            }

            // If group roles mode, check if we've deleted the old group default role.  If so, reset default to role ID 1
            // Next, if any group members were using the deleted role, reset their role to the group default.
            if ($mode === 'grproles') {
                // Reset if default role was just removed.
                $sSQL = "UPDATE group_grp SET grp_DefaultRole = 1 WHERE grp_RoleListID = $listID AND grp_DefaultRole = $iID";
                RunQuery($sSQL);

                // Get the current default role and Group ID (so we can update the p2g2r table)
                // This seems backwards, but grp_RoleListID is unique, having a 1-1 relationship with grp_ID.
                $sSQL = "SELECT grp_ID,grp_DefaultRole FROM group_grp WHERE grp_RoleListID = $listID";
                $rsTemp = RunQuery($sSQL);
                $aTemp = mysqli_fetch_array($rsTemp);

                $sSQL = "UPDATE person2group2role_p2g2r SET p2g2r_rle_ID = $aTemp[1] WHERE p2g2r_grp_ID = $aTemp[0] AND p2g2r_rle_ID = $iID";
                RunQuery($sSQL);
            } else {
                // Otherwise, for other types of assignees having a deleted option, reset them to default of 0 (undefined).
                if ($deleteCleanupTable != 0) {
                    $sSQL = "UPDATE $deleteCleanupTable SET $deleteCleanupColumn = $deleteCleanupResetTo WHERE $deleteCleanupColumn = " . $iID;
                    RunQuery($sSQL);
                }
            }
        }
        break;

        // Currently this is used solely for group roles
    case 'makedefault':
        $sSQL = "UPDATE group_grp SET grp_DefaultRole = $iID WHERE grp_RoleListID = $listID";
        RunQuery($sSQL);
        break;

        // If no valid action was specified, abort
    default:
        RedirectUtils::redirect('Menu.php');
        break;
}

// Reload the option manager page
RedirectUtils::redirect("OptionManager.php?mode=$mode&ListID=$listID");
exit;
