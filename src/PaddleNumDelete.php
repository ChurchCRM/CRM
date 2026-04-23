<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

// Security: require Delete Records + Finance permissions (GHSA-3xq9-c86x-cwpp)
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled(), 'DeleteRecords');
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled(), 'Finance');

// Read the ID from $_POST on the confirmed-delete submit, $_GET when rendering
// the confirmation page. $_REQUEST depends on request_order and can include
// cookies — unsafe for destructive operations.
$idSource = isset($_POST['Delete']) ? $_POST : $_GET;
$iPaddleNumID = (int) InputUtils::legacyFilterInput($idSource['PaddleNumID'] ?? 0, 'int');
$linkBack = RedirectUtils::getLinkBackFromRequest('FindFundRaiser.php');
$iFundRaiserID = (int) ($_SESSION['iCurrentFundraiser'] ?? 0);

// Confirmed deletion (second pass, POST with CSRF token)
if (isset($_POST['Delete'])) {
    // Security: CSRF token validation (GHSA-3xq9-c86x-cwpp)
    if (!CSRFUtils::verifyRequest($_POST, 'paddle_num_delete')) {
        http_response_code(403);
        exit(gettext('Invalid security token. Please try again.'));
    }

    if ($iPaddleNumID > 0 && $iFundRaiserID > 0) {
        // No Propel-generated model exists for paddlenum_pn; raw SQL is safe
        // here because both IDs are hard-cast to int before interpolation.
        $sSQL = 'DELETE FROM paddlenum_pn WHERE pn_id=' . $iPaddleNumID . ' AND pn_fr_id=' . $iFundRaiserID;
        RunQuery($sSQL);
    }
    RedirectUtils::redirect($linkBack);
} elseif (isset($_POST['Cancel'])) {
    RedirectUtils::redirect($linkBack);
}

$sPageTitle = gettext('Confirm Delete');
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Fundraiser'), 'FindFundRaiser.php'],
    [gettext('Delete Paddle Number')],
]);
require_once __DIR__ . '/Include/Header.php';

?>

<div class="card-body text-center">
    <p class="lead mb-4"><?= gettext('Are you sure you want to permanently delete this paddle number?') ?></p>
    <form method="post" action="PaddleNumDelete.php?PaddleNumID=<?= $iPaddleNumID ?>&amp;linkBack=<?= urlencode($linkBack) ?>" name="PaddleNumDelete">
        <?= CSRFUtils::getTokenInputField('paddle_num_delete') ?>
        <input type="hidden" name="PaddleNumID" value="<?= $iPaddleNumID ?>">
        <input type="submit" class="btn btn-danger" value="<?= gettext('Delete') ?>" name="Delete">
        <input type="submit" class="btn btn-secondary ms-2" value="<?= gettext('Cancel') ?>" name="Cancel">
    </form>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
