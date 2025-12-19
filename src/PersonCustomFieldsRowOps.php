<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\model\ChurchCRM\PersonCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;

// Security: user must be administrator to use this page.
AuthenticationManager::redirectHomeIfNotAdmin();

// Get the Group, Property, and Action from the querystring
$iOrderID = InputUtils::legacyFilterInput($_GET['OrderID'] ?? 1, 'int');
$sField = InputUtils::legacyFilterInput($_GET['Field']);
$sAction = $_GET['Action'];

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
        // Fetch the custom field record by primary key (custom_field)
        $customField = PersonCustomMasterQuery::create()
            ->findOneById($sField);

        if ($customField !== null) {
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
        }

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
RedirectUtils::redirect('PersonCustomFieldsEditor.php');
