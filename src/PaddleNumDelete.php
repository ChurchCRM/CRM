<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\PaddlenumPnQuery;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

// Security: require Delete Records + Finance permissions (GHSA-3xq9-c86x-cwpp)
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled(), 'DeleteRecords');
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled(), 'Finance');

// Read destructive-action inputs from the right superglobal per request method:
// GET renders the confirmation page, POST performs the delete. Using $_REQUEST
// would let PHP's request_order/cookies influence a destructive operation.
$isPostAction = isset($_POST['Delete']) || isset($_POST['Cancel']);
$paddleNumIDSource = $isPostAction ? ($_POST['PaddleNumID'] ?? 0) : ($_GET['PaddleNumID'] ?? 0);
$iPaddleNumID = (int) InputUtils::legacyFilterInput($paddleNumIDSource, 'int');
$linkBack = $isPostAction
    ? RedirectUtils::validateRedirectUrl(
        InputUtils::legacyFilterInput($_POST['linkBack'] ?? '', 'string') ?? '',
        'FindFundRaiser.php'
    )
    : RedirectUtils::getLinkBackFromRequest('FindFundRaiser.php');
$iFundRaiserID = (int) ($_SESSION['iCurrentFundraiser'] ?? 0);

// Confirmed deletion (second pass, POST with CSRF token)
if (isset($_POST['Delete'])) {
    // Security: CSRF token validation (GHSA-3xq9-c86x-cwpp)
    if (!CSRFUtils::verifyRequest($_POST, 'paddle_num_delete')) {
        http_response_code(403);
        exit(gettext('Invalid security token. Please try again.'));
    }

    if ($iPaddleNumID > 0 && $iFundRaiserID > 0) {
        PaddlenumPnQuery::create()
            ->filterByPnId($iPaddleNumID)
            ->filterByPnFrId($iFundRaiserID)
            ->delete();
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
    <form method="post" action="PaddleNumDelete.php" name="PaddleNumDelete">
        <?= CSRFUtils::getTokenInputField('paddle_num_delete') ?>
        <input type="hidden" name="PaddleNumID" value="<?= $iPaddleNumID ?>">
        <input type="hidden" name="linkBack" value="<?= InputUtils::escapeAttribute($linkBack) ?>">
        <input type="submit" class="btn btn-danger" value="<?= gettext('Delete') ?>" name="Delete">
        <input type="submit" class="btn btn-secondary ms-2" value="<?= gettext('Cancel') ?>" name="Cancel">
    </form>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
