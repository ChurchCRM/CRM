<?php

/*******************************************************************************
 *
 *  filename    : PledgeDelete.php
 *  last change : 2004-6-12
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2001, 2002, 2003 Deane Barker, Chris Gebhardt, Michael Wilt
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

//Set the page title
$sPageTitle = gettext('Confirm Delete');

$linkBack = InputUtils::legacyFilterInput($_GET['linkBack']);
$sGroupKey = InputUtils::legacyFilterInput($_GET['GroupKey'], 'string');

// Security: User must have Add or Edit Records permission to use this form in those manners
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
if (!AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled()) {
    RedirectUtils::redirect('Menu.php');
    exit;
}

//Is this the second pass?
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

<?php require 'Include/Footer.php' ?>
