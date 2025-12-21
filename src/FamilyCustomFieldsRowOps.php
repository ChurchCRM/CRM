<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: user must be administrator to use this page.
AuthenticationManager::redirectHomeIfNotAdmin();

// Get the Group, Property, and Action from the querystring or POST
$iOrderID = InputUtils::legacyFilterInput($_GET['OrderID'] ?? $_POST['OrderID'] ?? null, 'int');
$sField = InputUtils::legacyFilterInput($_GET['Field'] ?? $_POST['Field'] ?? '');
$sAction = $_GET['Action'] ?? $_POST['Action'] ?? '';

switch ($sAction) {
    // Move a field up: Swap the fam_custom_Order (ordering) of the selected row and the one above it
    case 'up':
        $sSQL = "UPDATE family_custom_master SET fam_custom_Order = '" . $iOrderID . "' WHERE fam_custom_Order = '" . ($iOrderID - 1) . "'";
        RunQuery($sSQL);
        $sSQL = "UPDATE family_custom_master SET fam_custom_Order = '" . ($iOrderID - 1) . "' WHERE fam_custom_Field = '" . $sField . "'";
        RunQuery($sSQL);
        break;

        // Move a field down: Swap the fam_custom_Order (ordering) of the selected row and the one below it
    case 'down':
        $sSQL = "UPDATE family_custom_master SET fam_custom_Order = '" . $iOrderID . "' WHERE fam_custom_Order = '" . ($iOrderID + 1) . "'";
        RunQuery($sSQL);
        $sSQL = "UPDATE family_custom_master SET fam_custom_Order = '" . ($iOrderID + 1) . "' WHERE fam_custom_Field = '" . $sField . "'";
        RunQuery($sSQL);
        break;

        // Delete a field from the form
    case 'delete':
        // Verify CSRF token for POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRFUtils::verifyRequest($_POST, 'deleteFamilyCustomField')) {
                http_response_code(403);
                die(gettext('Invalid CSRF token'));
            }
        }
        
        // Get the order ID for this field first (needed for reordering after delete)
        $sSQL = "SELECT fam_custom_Order, type_ID, fam_custom_Special FROM family_custom_master WHERE fam_custom_Field = '" . $sField . "'";
        $rsTemp = RunQuery($sSQL);
        $aTemp = mysqli_fetch_array($rsTemp);
        
        if ($aTemp === null) {
            // Field doesn't exist, redirect back
            RedirectUtils::redirect('FamilyCustomFieldsEditor.php');
            break;
        }
        
        $iOrderID = (int)$aTemp['fam_custom_Order'];
        
        // Check if this field is a custom list type. If so, the list needs to be deleted from list_lst.
        if ($aTemp['type_ID'] == 12) {
            $sSQL = "DELETE FROM list_lst WHERE lst_ID = " . (int)$aTemp['fam_custom_Special'];
            RunQuery($sSQL);
        }

        $sSQL = 'ALTER TABLE `family_custom` DROP `' . $sField . '` ;';
        RunQuery($sSQL);

        $sSQL = "DELETE FROM family_custom_master WHERE fam_custom_Field = '" . $sField . "'";
        RunQuery($sSQL);

        $sSQL = 'SELECT * FROM family_custom_master';
        $rsPropList = RunQuery($sSQL);
        $numRows = mysqli_num_rows($rsPropList);

        // Shift the remaining rows up by one, unless we've just deleted the only row
        if ($numRows > 0) {
            for ($reorderRow = $iOrderID + 1; $reorderRow <= $numRows + 1; $reorderRow++) {
                $sSQL = "UPDATE family_custom_master SET fam_custom_Order = '" . ($reorderRow - 1) . "' WHERE fam_custom_Order = '" . $reorderRow . "'";
                RunQuery($sSQL);
            }
        }
        break;

        // If no valid action was specified, abort and return to the GroupView
    default:
        RedirectUtils::redirect('FamilyCustomFieldsEditor.php');
        break;
}

// Reload the Form Editor page
$redirectUrl = 'FamilyCustomFieldsEditor.php';
if ($sAction === 'delete') {
    $redirectUrl .= '?deleted=1';
}
RedirectUtils::redirect($redirectUrl);
