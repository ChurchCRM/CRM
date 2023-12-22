<?php

/*******************************************************************************
 *
 *  filename    : PaddleNumList.php
 *  last change : 2009-04-15
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2009 Michael Wilt
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Utils\InputUtils;

$linkBack = InputUtils::legacyFilterInputArr($_GET, 'linkBack');

$iFundRaiserID = $_SESSION['iCurrentFundraiser'];

if ($iFundRaiserID > 0) {
    //Get the paddlenum records for this fundraiser
    $sSQL = "SELECT pn_ID, pn_fr_ID, pn_Num, pn_per_ID,
	                a.per_FirstName as buyerFirstName, a.per_LastName as buyerLastName
	         FROM paddlenum_pn
	         LEFT JOIN person_per a ON pn_per_ID=a.per_ID
	         WHERE pn_FR_ID = '" . $iFundRaiserID . "' ORDER BY pn_Num";
    $rsPaddleNums = RunQuery($sSQL);
} else {
    $rsPaddleNums = 0;
}

$sPageTitle = gettext('Buyers for this fundraiser:');
require 'Include/Header.php';
?>
<div class="card card-body">
<?php
echo "<form method=\"post\" action=\"Reports/FundRaiserStatement.php?CurrentFundraiser=$iFundRaiserID&linkBack=FundRaiserEditor.php?FundRaiserID=$iFundRaiserID&CurrentFundraiser=$iFundRaiserID\">\n";
if ($iFundRaiserID > 0) {
    echo '<input type=button class=btn value="' . gettext('Select all') . "\" name=SelectAll onclick=\"javascript:document.location='PaddleNumList.php?CurrentFundraiser=$iFundRaiserID&SelectAll=1&linkBack=PaddleNumList.php?FundRaiserID=$iFundRaiserID&CurrentFundraiser=$iFundRaiserID';\">\n";
}
    echo '<input type=button class=btn value="' . gettext('Select none') . "\" name=SelectNone onclick=\"javascript:document.location='PaddleNumList.php?CurrentFundraiser=$iFundRaiserID&linkBack=PaddleNumList.php?FundRaiserID=$iFundRaiserID&CurrentFundraiser=$iFundRaiserID';\">\n";
    echo '<input type=button class=btn value="' . gettext('Add Buyer') . "\" name=AddBuyer onclick=\"javascript:document.location='PaddleNumEditor.php?CurrentFundraiser=$iFundRaiserID&linkBack=PaddleNumList.php?FundRaiserID=$iFundRaiserID&CurrentFundraiser=$iFundRaiserID';\">\n";
    echo '<input type=submit class=btn value="' . gettext('Generate Statements for Selected') . "\" name=GenerateStatements>\n";
?>
</div>
<div class="card card-body">

<table cellpadding="5" cellspacing="5">

<tr class="TableHeader">
    <td><?= gettext('Select') ?></td>
    <td><?= gettext('Number') ?></td>
    <td><?= gettext('Buyer') ?></td>
    <td><?= gettext('Delete') ?></td>
</tr>

<?php
$tog = 0;

//Loop through all buyers
if ($rsPaddleNums) {
    while ($aRow = mysqli_fetch_array($rsPaddleNums)) {
        extract($aRow);

        $sRowClass = 'RowColorA'; ?>
        <tr class="<?= $sRowClass ?>">
            <td>
                <input type="checkbox" name="Chk<?= $pn_ID . '"';
                if (isset($_GET['SelectAll'])) {
                    echo ' checked="yes"';
                } ?>></input>
            </td>
            <td>
                <?= "<a href=\"PaddleNumEditor.php?PaddleNumID=$pn_ID&linkBack=PaddleNumList.php\"> $pn_Num</a>\n" ?>
            </td>

            <td>
                <?= $buyerFirstName . ' ' . $buyerLastName ?>&nbsp;
            </td>
            <td>
                <a href="PaddleNumDelete.php?PaddleNumID=<?= $pn_ID . '&linkBack=PaddleNumList.php?FundRaiserID=' . $iFundRaiserID ?>">Delete</a>
            </td>
        </tr>
        <?php
    } // while
} // if
?>

</table>
  </div>
</form>

<?php require 'Include/Footer.php' ?>
