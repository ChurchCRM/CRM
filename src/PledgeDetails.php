<?php

/*******************************************************************************
 *
 *  filename    : PledgeDetails.php
 *  copyright   : Copyright 2001, 2002, 2003, 2004 Deane Barker, Chris Gebhardt, Michael Wilt
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

//Set the page title
$sPageTitle = gettext('Electronic Transaction Details');

//Get the PledgeID out of the querystring
$iPledgeID = InputUtils::legacyFilterInput($_GET['PledgeID'], 'int');
$linkBack = InputUtils::legacyFilterInput($_GET['linkBack']);

// Security: User must have Finance permission to use this form.
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
if (!AuthenticationManager::getCurrentUser()->isFinanceEnabled()) {
    RedirectUtils::redirect('Menu.php');
    exit;
}

//Is this the second pass?
if (isset($_POST['Back'])) {
    RedirectUtils::redirect($linkBack);
}

$sSQL = 'SELECT * FROM pledge_plg WHERE plg_plgID = ' . $iPledgeID;
$rsPledgeRec = RunQuery($sSQL);
extract(mysqli_fetch_array($rsPledgeRec));

$sSQL = 'SELECT * FROM result_res WHERE res_ID=' . $plg_aut_ResultID;
$rsResultRec = RunQuery($sSQL);

require 'Include/Header.php';

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

<?php require 'Include/Footer.php' ?>
