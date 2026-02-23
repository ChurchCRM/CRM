<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$sPageTitle = gettext('Electronic Transaction Details');

// Get the PledgeID out of the querystring
$iPledgeID = InputUtils::legacyFilterInput($_GET['PledgeID'], 'int');
$linkBack = RedirectUtils::getLinkBackFromRequest('v2/dashboard');

// Security: User must have Finance permission to use this form.
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled(), 'Finance');

// Is this the second pass?
if (isset($_POST['Back'])) {
    RedirectUtils::redirect($linkBack);
}

$sSQL = 'SELECT * FROM pledge_plg WHERE plg_plgID = ' . $iPledgeID;
$rsPledgeRec = RunQuery($sSQL);
extract(mysqli_fetch_array($rsPledgeRec));

$sSQL = 'SELECT * FROM result_res WHERE res_ID=' . $plg_aut_ResultID;
$rsResultRec = RunQuery($sSQL);

require_once __DIR__ . '/Include/Header.php';

$resArr = mysqli_fetch_array($rsResultRec);
if ($resArr) {
    extract($resArr);
    echo $res_echotype2;
}

?>

<div class="card card-body">
    <form method="post" action="PledgeDetails.php?<?= 'PledgeID=' . $iPledgeID . '&linkBack=' . $linkBack ?>" name="PledgeDelete">
        <input type="submit" class="btn btn-secondary" value="<?= gettext('Back') ?>" name="Back">
    </form>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
