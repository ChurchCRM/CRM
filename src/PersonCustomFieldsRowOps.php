<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\model\ChurchCRM\PersonCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;

// Security: user must be administrator to use this page.
AuthenticationManager::redirectHomeIfNotAdmin();

// Get the Group, Property, and Action from the querystring or POST
$iOrderID = InputUtils::legacyFilterInput($_GET['OrderID'] ?? $_POST['OrderID'] ?? 1, 'int');
$sField = InputUtils::legacyFilterInput($_GET['Field'] ?? $_POST['Field'] ?? '');
$sAction = $_GET['Action'] ?? $_POST['Action'] ?? '';

switch ($sAction) {
    // Move a field up:  Swap the custom_Order (ordering) of the selected row and the one above it
    case 'up':
        $sSQL = "UPDATE person_custom_master SET custom_Order = '" . $iOrderID . "' WHERE custom_Order = '" . ($iOrderID - 1) . "'";
        RunQuery($sSQL);
        $sSQL = "UPDATE person_custom_master SET custom_Order = '" . ($iOrderID - 1) . "' WHERE custom_Field = '" . $sField . "'";
        RunQuery($sSQL);
        break;

        // Move a field down:  Swap the custom_Order (ordering) of the selected row and the one below it
    case 'down':
        $sSQL = "UPDATE person_custom_master SET custom_Order = '" . $iOrderID . "' WHERE custom_Order = '" . ($iOrderID + 1) . "'";
        RunQuery($sSQL);
        $sSQL = "UPDATE person_custom_master SET custom_Order = '" . ($iOrderID + 1) . "' WHERE custom_Field = '" . $sField . "'";
        RunQuery($sSQL);
        break;

        // Delete a field from the form
    case 'delete':
        // Verify CSRF token for POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRFUtils::verifyRequest($_POST, 'deletePersonCustomField')) {
                http_response_code(403);
                die(gettext('Invalid CSRF token'));
            }
        }
        
        // Fetch the custom field record by primary key (custom_field)
        $customField = PersonCustomMasterQuery::create()
            ->findOneById($sField);

        if ($customField === null) {
            // Field doesn't exist, redirect back
            RedirectUtils::redirect('PersonCustomFieldsEditor.php');
            break;
        }

        // Get the order ID for reordering after delete
        $iOrderID = (int)$customField->getOrder();

        // Check if this field is a custom list type (type_ID = 12).  If so, delete the list from list_lst
        if ($customField->getTypeId() == 12) {
            $listOption = ListOptionQuery::create()
                ->findOneById((int)$customField->getSpecial());
            if ($listOption !== null) {
                $listOption->delete();
            }
        }

        // Delete the custom field record
        $customField->delete();

        $sSQL = 'ALTER TABLE `person_custom` DROP IF EXISTS `' . $sField . '` ;';
        RunQuery($sSQL);

        // Fetch remaining custom fields to reorder
        $remainingFields = PersonCustomMasterQuery::create()
            ->orderByOrder()
            ->find();
        $numRows = count($remainingFields);

        // Shift the remaining rows up by one, unless we've just deleted the only row
        if ($numRows != 0) {
            for ($reorderRow = $iOrderID + 1; $reorderRow <= $numRows + 1; $reorderRow++) {
                $fieldToReorder = PersonCustomMasterQuery::create()
                    ->filterByOrder($reorderRow)
                    ->findOne();
                if ($fieldToReorder !== null) {
                    $fieldToReorder->setOrder($reorderRow - 1)
                        ->save();
                }
            }
        }
        break;

        // If no valid action was specified, abort and return to the GroupView
    default:
        RedirectUtils::redirect('PersonCustomFieldsEditor.php');
        break;
}

// Reload the Form Editor page
$redirectUrl = 'PersonCustomFieldsEditor.php';
if ($sAction === 'delete') {
    $redirectUrl .= '?deleted=1';
}
RedirectUtils::redirect($redirectUrl);
