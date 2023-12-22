<?php

/*******************************************************************************
 *
 *  filename    : BatchWinnerEntry.php
 *  last change : 2011-04-01
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2011 Michael Wilt
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$linkBack = InputUtils::legacyFilterInput($_GET['linkBack']);
$iCurrentFundraiser = InputUtils::legacyFilterInput($_GET['CurrentFundraiser']);

if ($iCurrentFundraiser) {
    $_SESSION['iCurrentFundraiser'] = $iCurrentFundraiser;
} else {
    $iCurrentFundraiser = $_SESSION['iCurrentFundraiser'];
}

// Get the current fundraiser data
if ($iCurrentFundraiser) {
    $sSQL = 'SELECT * from fundraiser_fr WHERE fr_ID = ' . $iCurrentFundraiser;
    $rsDeposit = RunQuery($sSQL);
    extract(mysqli_fetch_array($rsDeposit));
}

//Set the page title
$sPageTitle = gettext('Batch Winner Entry');

//Is this the second pass?
if (isset($_POST['EnterWinners'])) {
    for ($row = 0; $row < 10; $row += 1) {
        $buyer = $_POST["Paddle$row"];
        $di = $_POST["Item$row"];
        $price = $_POST["SellPrice$row"];
        if ($buyer > 0 && $di > 0 && $price > 0) {
            $sSQL = "UPDATE donateditem_di SET di_buyer_id=$buyer, di_sellprice=$price WHERE di_ID=$di";
            RunQuery($sSQL);
        }
    }
    RedirectUtils::redirect($linkBack);
}

// Get Items for the drop-down
$sDonatedItemsSQL = "SELECT di_ID, di_Item, di_title
                     FROM donateditem_di
                     WHERE di_FR_ID = '" . $iCurrentFundraiser . "' ORDER BY SUBSTR(di_Item,1,1), CONVERT(SUBSTR(di_Item,2,3),SIGNED)";
$rsDonatedItems = RunQuery($sDonatedItemsSQL);

//Get Paddles for the drop-down
$sPaddleSQL = 'SELECT pn_Num, pn_per_ID,
                      a.per_FirstName AS buyerFirstName,
                      a.per_LastName AS buyerLastName
                      FROM paddlenum_pn
                      LEFT JOIN person_per a on a.per_ID=pn_per_ID
                      WHERE pn_fr_ID=' . $iCurrentFundraiser . ' ORDER BY pn_Num';
$rsPaddles = RunQuery($sPaddleSQL);

require 'Include/Header.php';

?>
<div class="card card-body">
<form method="post" action="BatchWinnerEntry.php?<?= 'CurrentFundraiser=' . '&linkBack=' . $linkBack ?>" name="BatchWinnerEntry">
<div class="table-responsive">
<table class="table" cellpadding="2" align="center">
    <tr>
        <td class="LabelColumn"><?= gettext('Item') ?></td>
        <td class="LabelColumn"><?= gettext('Winner') ?></td>
        <td class="LabelColumn"><?= gettext('Price') ?></td>
    </tr>
<?php
for ($row = 0; $row < 10; $row += 1) {
    echo '<tr>';
    echo '<td>';
    echo '<select name="Item' . $row . "\">\n";
    echo '<option value="0" selected>' . gettext('Unassigned') . "</option>\n";

    mysqli_data_seek($rsDonatedItems, 0);
    while ($itemArr = mysqli_fetch_array($rsDonatedItems)) {
        $di_ID = $itemArr['di_ID'];
        $di_Item = $itemArr['di_Item'];
        $di_title = $itemArr['di_title'];
        echo '<option value="' . $di_ID . '">' . $di_Item . ' ' . $di_title . "</option>\n";
    }
    echo "</select>\n";
    echo '</td>';

    echo '<td>';
    echo '<select name="Paddle' . $row . "\">\n";
    echo '<option value="0" selected>' . gettext('Unassigned') . "</option>\n";

    mysqli_data_seek($rsPaddles, 0);
    while ($paddleArr = mysqli_fetch_array($rsPaddles)) {
        $pn_per_ID = $paddleArr['pn_per_ID'];
        $pn_Num = $paddleArr['pn_Num'];
        $buyerFirstName = $paddleArr['buyerFirstName'];
        $buyerLastName = $paddleArr['buyerLastName'];
        echo '<option value="' . $pn_per_ID . '">' . $pn_Num . ' ' . $buyerFirstName . ' ' . $buyerLastName . "</option>\n";
    }
    echo "</select>\n";
    echo '</td>';

    echo "<td class=\"TextColumn\"><input type=\"text\" name=\"SellPrice$row\" id=\"SellPrice\"$row value=\"\"></td>\n";
    echo '</tr>';
}
?>
    <tr>
        <td colspan="2" align="center">
            <input type="submit" class="btn btn-primary" value="<?= gettext('Enter Winners') ?>" name="EnterWinners">
            <input type="button" class="btn btn-default" value="<?= gettext('Cancel') ?>" name="Cancel" onclick="javascript:document.location='<?php if (strlen($linkBack) > 0) {
                echo $linkBack;
                                                                } else {
                                                                    echo 'Menu.php';
                                                                } ?>';">
        </td>
    </tr>
    </table>
</div>
</form>
</div>

<?php require 'Include/Footer.php' ?>
