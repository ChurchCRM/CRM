<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Cart;
use ChurchCRM\Service\PersonService;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Utils\FunctionsUtils;

$personService = new PersonService();
$systemService = new SystemService();

// Basic security checks:
if (empty($bSuppressSessionTests)) {  // This is used for the login page only.
    AuthenticationManager::ensureAuthentication();
}

$sGlobalMessageClass = 'success';

// Handle session-based messages (from redirects)
if (isset($_SESSION['sGlobalMessage'])) {
    $sGlobalMessage = $_SESSION['sGlobalMessage'];
    $sGlobalMessageClass = $_SESSION['sGlobalMessageClass'] ?? 'success';
    unset($_SESSION['sGlobalMessage']);
    unset($_SESSION['sGlobalMessageClass']);
}

if (isset($_GET['PDFEmailed'])) {
    if ($_GET['PDFEmailed'] == 1) {
        $sGlobalMessage = gettext('PDF successfully emailed to family members.');
        $sGlobalMessageClass = 'success';
    } else {
        $sGlobalMessage = gettext('Failed to email PDF to family members.');
        $sGlobalMessageClass = 'danger';
    }
}

// Are they adding an entire group to the cart?
// Note: AddGroupToPeopleCart is legacy - cart now managed through API routes
if (isset($_GET['AddGroupToPeopleCart'])) {
    $sGlobalMessage = gettext('Group successfully added to the Cart.');
    $sGlobalMessageClass = 'success';
}

// Are they removing an entire group from the Cart?
// Note: RemoveGroupFromPeopleCart is legacy - cart now managed through API routes
if (isset($_GET['RemoveGroupFromPeopleCart'])) {
    $sGlobalMessage = gettext('Group successfully removed from the Cart.');
    $sGlobalMessageClass = 'success';
}

// Are they removing a person from the Cart?
// Note: RemoveFromPeopleCart is legacy - cart now managed through API routes
if (isset($_GET['RemoveFromPeopleCart'])) {
    $sGlobalMessage = gettext('Selected record successfully removed from the Cart.');
    $sGlobalMessageClass = 'success';
}

if (isset($_POST['BulkAddToCart'])) {
    $aItemsToProcess = explode(',', $_POST['BulkAddToCart']);

    if (isset($_POST['AndToCartSubmit'])) {
        if (isset($_SESSION['aPeopleCart'])) {
            $_SESSION['aPeopleCart'] = array_intersect($_SESSION['aPeopleCart'], $aItemsToProcess);
        }
    } elseif (isset($_POST['NotToCartSubmit'])) {
        if (isset($_SESSION['aPeopleCart'])) {
            $_SESSION['aPeopleCart'] = array_diff($_SESSION['aPeopleCart'], $aItemsToProcess);
        }
    } else {
        for ($iCount = 0; $iCount < count($aItemsToProcess); $iCount++) {
            Cart::addPerson(str_replace(',', '', $aItemsToProcess[$iCount]));
        }
        $sGlobalMessage = sprintf(ngettext('%d Person added to the Cart.', '%d People added to the Cart.', $iCount), $iCount);
        $sGlobalMessageClass = 'success';
    }
}

//
// Global function shim — delegates to FunctionsUtils::runQuery().
// Legacy pages call RunQuery() directly; actual implementation lives in the Utils class.
// Call sites can be updated over time to use FunctionsUtils::runQuery() directly.
//
function RunQuery(string $sSQL, bool $bStopOnError = true)
{
    return FunctionsUtils::runQuery($sSQL, $bStopOnError);
}
