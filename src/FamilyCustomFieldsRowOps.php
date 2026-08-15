<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\FamilyCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: user must be administrator to use this page.
AuthenticationManager::redirectHomeIfNotAdmin();

// Get the Group, Property, and Action from the querystring or POST
$iOrderID = InputUtils::legacyFilterInput($_GET['OrderID'] ?? $_POST['OrderID'] ?? null, 'int');
$sField = InputUtils::legacyFilterInput($_GET['Field'] ?? $_POST['Field'] ?? '');
$sAction = $_GET['Action'] ?? $_POST['Action'] ?? '';

$iOrderID = (int)$iOrderID;

// All state-changing operations (up, down, delete) require POST + a valid
// CSRF token.  The previous check was gated on REQUEST_METHOD === 'POST',
// which meant a cross-site GET navigation bypassed it entirely (CWE-352 /
// CWE-650 — see GHSA-v4x7-hgpq-29r6).
if (in_array($sAction, ['up', 'down', 'delete'], true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die(gettext('Method Not Allowed'));
    }
    if (!CSRFUtils::verifyRequest($_POST, 'familyCustomFieldsAction')) {
        http_response_code(403);
        die(gettext('Invalid CSRF token'));
    }
}

// Validate field name to prevent DDL injection (column names follow pattern c1, c2, etc.)
if ($sField !== '' && !preg_match('/^c\d+$/', $sField)) {
    RedirectUtils::redirect('FamilyCustomFieldsEditor.php');
    exit;
}

switch ($sAction) {
    // Move a field up: Swap the fam_custom_Order (ordering) of the selected row and the one above it
    case 'up':
        $neighbor = FamilyCustomMasterQuery::create()->filterByOrder($iOrderID - 1)->findOne();
        if ($neighbor !== null) {
            $neighbor->setOrder($iOrderID)->save();
        }
        $current = FamilyCustomMasterQuery::create()->filterByField($sField)->findOne();
        if ($current !== null) {
            $current->setOrder($iOrderID - 1)->save();
        }
        break;

        // Move a field down: Swap the fam_custom_Order (ordering) of the selected row and the one below it
    case 'down':
        $neighbor = FamilyCustomMasterQuery::create()->filterByOrder($iOrderID + 1)->findOne();
        if ($neighbor !== null) {
            $neighbor->setOrder($iOrderID)->save();
        }
        $current = FamilyCustomMasterQuery::create()->filterByField($sField)->findOne();
        if ($current !== null) {
            $current->setOrder($iOrderID + 1)->save();
        }
        break;

        // Delete a field from the form
    case 'delete':
        // CSRF + POST already verified above for all state-changing actions.

        // Fetch the custom field record by primary key (fam_custom_Field)
        $customField = FamilyCustomMasterQuery::create()
            ->filterByField($sField)
            ->findOne();

        if ($customField === null) {
            // Field doesn't exist, redirect back
            RedirectUtils::redirect('FamilyCustomFieldsEditor.php');
            break;
        }

        // Get the order ID for reordering after delete
        $iOrderID = (int)$customField->getOrder();

        // Check if this field is a custom list type (type_ID = 12). If so, delete all options for the list from list_lst
        if ($customField->getTypeId() === 12) {
            // filterById() matches all rows WHERE lst_ID = special (entire list), not just the first one
            ListOptionQuery::create()->filterById((int)$customField->getCustomSpecial())->delete();
        }

        // Drop the column from the family_custom table first (DDL-first order matches original behaviour).
        // If the ORM delete ran first and ALTER TABLE failed, the master record would be gone
        // while the physical column with user data remained — an unrecoverable orphan.
        // $sField is regex-validated (^c\d+$) above; DDL identifiers cannot be parameterised.
        $sSQL = 'ALTER TABLE `family_custom` DROP IF EXISTS `' . $sField . '` ;'; // nosemgrep: php.lang.security.injection.tainted-sql-string.tainted-sql-string
        RunQuery($sSQL); // nosemgrep: php.lang.security.injection.tainted-sql-string.tainted-sql-string

        // Delete the custom field record only after the DDL succeeds
        $customField->delete();

        // Fetch remaining custom fields to reorder
        $remainingFields = FamilyCustomMasterQuery::create()
            ->orderByOrder()
            ->find();
        $numRows = count($remainingFields);

        // Shift the remaining rows up by one, unless we've just deleted the only row
        if ($numRows !== 0) {
            for ($reorderRow = $iOrderID + 1; $reorderRow <= $numRows + 1; $reorderRow++) {
                $fieldToReorder = FamilyCustomMasterQuery::create()
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
        RedirectUtils::redirect('FamilyCustomFieldsEditor.php');
        break;
}

// Reload the Form Editor page
$redirectUrl = 'FamilyCustomFieldsEditor.php';
if ($sAction === 'delete') {
    $redirectUrl .= '?deleted=1';
}
RedirectUtils::redirect($redirectUrl);
