<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$sPageTitle = gettext('Electronic Transaction Details');

// Get the PledgeID out of the querystring
$iPledgeID = InputUtils::legacyFilterInput($_GET['PledgeID'], 'int');
$linkBack = InputUtils::legacyFilterInput($_GET['linkBack']);

// Security: User must have Finance permission to use this form.
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isFinanceEnabled());

// Is this the second pass?
if (isset($_POST['Back'])) {
    RedirectUtils::redirect($linkBack);
}

$sSQL = 'SELECT * FROM pledge_plg WHERE plg_plgID = ' . $iPledgeID;
$rsPledgeRec = RunQuery($sSQL);
extract(mysqli_fetch_array($rsPledgeRec));

$sSQL = 'SELECT * FROM result_res WHERE res_ID=' . $plg_aut_ResultID;
$rsResultRec = RunQuery($sSQL);

require_once 'Include/Header.php';

$resArr = mysqli_fetch_array($rsResultRec);
if ($resArr) {
    extract($resArr);
    echo $res_echotype2;
}

?>

<form method="post" action="PledgeDetails.php?<?= 'PledgeID=' . $iPledgeID . '&linkBack=' . $linkBack ?>" name="PledgeDelete">

<table cellpadding="3" align="center">

    <tr>
        <td align="center">
            <input type="submit" class="btn btn-default" value="<?= gettext('Back') ?>" name="Back">
        </td>
    </tr>
</table>
<?php
require_once 'Include/Footer.php';
