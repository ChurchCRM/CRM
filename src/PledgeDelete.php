<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

$sPageTitle = gettext('Confirm Delete');

// Read inputs from the right superglobal per request method so a destructive
// POST never depends on $_GET parameters that an attacker could try to preserve.
$isPostAction = isset($_POST['Delete']) || isset($_POST['Cancel']);
$sGroupKey = InputUtils::legacyFilterInput(
    $isPostAction ? ($_POST['GroupKey'] ?? '') : ($_GET['GroupKey'] ?? ''),
    'string'
);
$linkBack = $isPostAction
    ? RedirectUtils::validateRedirectUrl(
        InputUtils::legacyFilterInput($_POST['linkBack'] ?? '', 'string') ?? '',
        'v2/dashboard'
    )
    : RedirectUtils::getLinkBackFromRequest('v2/dashboard');

// Security: User must have Add or Edit Records permission to use this form in those manners
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled(), 'DeleteRecords');

// Is this the second pass?
if (isset($_POST['Delete'])) {
    // Security: CSRF token validation (GHSA-3xq9-c86x-cwpp)
    if (!CSRFUtils::verifyRequest($_POST, 'pledge_delete')) {
        http_response_code(403);
        exit(gettext('Invalid security token. Please try again.'));
    }

    PledgeQuery::create()->filterByGroupKey($sGroupKey)->delete();

    if ($linkBack !== '') {
        RedirectUtils::redirect($linkBack);
    }
} elseif (isset($_POST['Cancel'])) {
    RedirectUtils::redirect($linkBack);
}

$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Finance'), '/finance/'],
    [gettext('Delete Pledge')],
]);
require_once __DIR__ . '/Include/Header.php';

?>

<div class="card-body text-center">
    <p class="lead mb-4"><?= gettext('Are you sure you want to permanently delete this pledge record?') ?></p>
    <form method="post" action="PledgeDelete.php" name="PledgeDelete">
        <?= CSRFUtils::getTokenInputField('pledge_delete') ?>
        <input type="hidden" name="GroupKey" value="<?= InputUtils::escapeAttribute($sGroupKey) ?>">
        <input type="hidden" name="linkBack" value="<?= InputUtils::escapeAttribute($linkBack) ?>">
        <input type="submit" class="btn btn-danger" value="<?= gettext('Delete') ?>" name="Delete">
        <input type="submit" class="btn btn-secondary ms-2" value="<?= gettext('Cancel') ?>" name="Cancel">
    </form>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
