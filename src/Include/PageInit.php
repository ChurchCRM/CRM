<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Cart;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\TokenQuery;
use ChurchCRM\model\ChurchCRM\Token;
use ChurchCRM\Service\PersonService;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Utils\FunctionsUtils;
use ChurchCRM\Utils\RedirectUtils;

$personService = new PersonService();
$systemService = new SystemService();

// Basic security checks:
if (empty($bSuppressSessionTests)) {  // This is used for the login page only.
    AuthenticationManager::ensureAuthentication();

    // Block EditSelf-only users — redirect to family verify or show limited access page
    // See https://github.com/ChurchCRM/CRM/issues/8617
    $currentUser = AuthenticationManager::getCurrentUser();
    if ($currentUser->isEditSelfOnly()) {
        $person = $currentUser->getPerson();
        $familyId = $person ? $person->getFamId() : 0;
        if ($familyId > 0) {
            // Generate or reuse a family verify token and redirect
            $existingToken = TokenQuery::create()
                ->filterByType('verifyFamily')
                ->filterByReferenceId($familyId)
                ->findOne();
            if ($existingToken === null || !$existingToken->isValid()) {
                $token = new Token();
                $token->build('verifyFamily', $familyId);
                $token->save();
                $tokenStr = $token->getToken();
            } else {
                $tokenStr = $existingToken->getToken();
            }
            RedirectUtils::redirect(SystemURLs::getRootPath() . '/external/verify/' . $tokenStr);
        } else {
            // No family — show a static limited-access message
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html><head><title>' . gettext('Limited Access') . '</title></head><body>';
            echo '<div style="max-width:500px;margin:80px auto;text-align:center;font-family:sans-serif">';
            echo '<h2>' . gettext('Limited Access') . '</h2>';
            echo '<p>' . gettext('Your account has limited permissions. Please contact an administrator for access.') . '</p>';
            echo '<a href="' . SystemURLs::getRootPath() . '/session/end">' . gettext('Log out') . '</a>';
            echo '</div></body></html>';
            exit;
        }
    }
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
