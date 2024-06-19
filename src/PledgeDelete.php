<?php

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$sPageTitle = gettext('Confirm Delete');

$linkBack = InputUtils::legacyFilterInput($_GET['linkBack']);
$sGroupKey = InputUtils::legacyFilterInput($_GET['GroupKey'], 'string');

// Security: User must have Add or Edit Records permission to use this form in those manners
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled());

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

require 'Include/Header.php';

?>

<form method="post" action="PledgeDelete.php?<?= 'GroupKey=' . $sGroupKey . '&linkBack=' . $linkBack ?>" name="PledgeDelete">

<table cellpadding="3" align="center">

    <tr>
        <td align="center">
            <input type="submit" class="btn btn-default" value="<?= gettext('Delete') ?>" name="Delete">
            <input type="submit" class="btn btn-default" value="<?= gettext('Cancel') ?>" name="Cancel">
        </td>
    </tr>
</table>
<?php
require 'Include/Footer.php';
