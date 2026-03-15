<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$sPageTitle = gettext('Confirm Delete');

$linkBack = RedirectUtils::getLinkBackFromRequest('v2/dashboard');
$sGroupKey = InputUtils::legacyFilterInput($_GET['GroupKey'], 'string');

// Security: User must have Add or Edit Records permission to use this form in those manners
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled(), 'DeleteRecords');

// Is this the second pass?
if (isset($_POST['Delete'])) {
    $sSQL = "DELETE FROM `pledge_plg` WHERE `plg_GroupKey` = '" . $sGroupKey . "';";
    RunQuery($sSQL);

    if ($linkBack != '') {
        RedirectUtils::redirect($linkBack);
    }
} elseif (isset($_POST['Cancel'])) {
    RedirectUtils::redirect($linkBack);
}

require_once __DIR__ . '/Include/Header.php';

?>

<div class="card card-body text-center">
    <p class="lead mb-4"><?= gettext('Are you sure you want to permanently delete this pledge record?') ?></p>
    <form method="post" action="PledgeDelete.php?<?= 'GroupKey=' . $sGroupKey . '&linkBack=' . $linkBack ?>" name="PledgeDelete">
        <input type="submit" class="btn btn-danger" value="<?= gettext('Delete') ?>" name="Delete">
        <input type="submit" class="btn btn-secondary ml-2" value="<?= gettext('Cancel') ?>" name="Cancel">
    </form>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
