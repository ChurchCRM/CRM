<?php
/*******************************************************************************
 *
 *  filename    : AutoPaymentDelete.php
 *  last change : 2004-6-12
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002, 2003 Deane Barker, Chris Gebhardt, Michael Wilt
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

//Set the page title
$sPageTitle = gettext('Confirm Delete Automatic payment');

$iAutID = InputUtils::LegacyFilterInput($_GET['AutID'], 'int');
$linkBack = InputUtils::LegacyFilterInput($_GET['linkBack']);

// Security: User must have Add or Edit Records permission to use this form in those manners
// Clean error handling: (such as somebody typing an incorrect URL ?PersonID= manually)
if (strlen($iAutID) > 0) {
    if (!$_SESSION['bEditRecords']) {
        RedirectUtils::Redirect('Menu.php');
        exit;
    }
    $sSQL = "SELECT '' FROM autopayment_aut WHERE aut_ID = ".$iAutID;
    if (mysqli_num_rows(RunQuery($sSQL)) == 0) {
        RedirectUtils::Redirect('Menu.php');
        exit;
    }
} elseif (!$_SESSION['bAddRecords']) {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

//Is this the second pass?
if (isset($_POST['Delete'])) {
    $sSQL = "DELETE FROM `autopayment_aut` WHERE `aut_ID` = '".$iAutID."' LIMIT 1;";
    //Execute the SQL
    RunQuery($sSQL);
    if ($linkBack != '') {
        RedirectUtils::Redirect($linkBack);
    }
} elseif (isset($_POST['Cancel'])) {
    RedirectUtils::Redirect($linkBack);
}

require 'Include/Header.php';

?>

<form method="post" action="AutoPaymentDelete.php?<?= 'AutID='.$iAutID.'&linkBack='.$linkBack ?>" name="AutoPaymentDelete">

<table cellpadding="3" align="center">

	<tr>
		<td align="center">
			<input type="submit" class="btn" value="<?= gettext('Delete') ?>" name="Delete">
			<input type="submit" class="btn" value="<?= gettext('Cancel') ?>" name="Cancel">
		</td>
	</tr>
</table>
</form>

<?php require 'Include/Footer.php' ?>
