<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

$linkBack = RedirectUtils::getLinkBackFromRequest('');

$iFundRaiserID = $_SESSION['iCurrentFundraiser'];

if ($iFundRaiserID > 0) {
    //Get the paddlenum records for this fundraiser
    $sSQL ="SELECT pn_ID, pn_fr_ID, pn_Num, pn_per_ID,
                    a.per_FirstName as buyerFirstName, a.per_LastName as buyerLastName
             FROM paddlenum_pn
             LEFT JOIN person_per a ON pn_per_ID=a.per_ID
             WHERE pn_FR_ID = '" . $iFundRaiserID ."' ORDER BY pn_Num";
    $rsPaddleNums = RunQuery($sSQL);
} else {
    $rsPaddleNums = 0;
}

$sPageTitle = gettext('Buyers for this fundraiser:');
$sPageSubtitle = gettext('View buyer numbers and paddle assignments');
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Fundraiser'), '/FindFundRaiser.php'],
    [gettext('Buyers')],
]);
require_once __DIR__ . '/Include/Header.php';
?>
<div class="card-body">
    <?php
    echo"<form method=\"post\" action=\"Reports/FundRaiserStatement.php?CurrentFundraiser=$iFundRaiserID&linkBack=FundRaiserEditor.php?FundRaiserID=$iFundRaiserID&CurrentFundraiser=$iFundRaiserID\">\n";
    if ($iFundRaiserID > 0) {
        echo '<input type=button class=btn value="' . gettext('Select all') ."\" name=SelectAll onclick=\"javascript:document.location='PaddleNumList.php?CurrentFundraiser=$iFundRaiserID&SelectAll=1&linkBack=PaddleNumList.php?FundRaiserID=$iFundRaiserID&CurrentFundraiser=$iFundRaiserID';\">\n";
    }
    echo '<input type=button class=btn value="' . gettext('Select none') ."\" name=SelectNone onclick=\"javascript:document.location='PaddleNumList.php?CurrentFundraiser=$iFundRaiserID&linkBack=PaddleNumList.php?FundRaiserID=$iFundRaiserID&CurrentFundraiser=$iFundRaiserID';\">\n";
    echo '<input type=button class=btn value="' . gettext('Add Buyer') ."\" name=AddBuyer onclick=\"javascript:document.location='PaddleNumEditor.php?CurrentFundraiser=$iFundRaiserID&linkBack=PaddleNumList.php?FundRaiserID=$iFundRaiserID&CurrentFundraiser=$iFundRaiserID';\">\n";
    echo '<input type=submit class=btn value="' . gettext('Generate Statements for Selected') ."\" name=GenerateStatements>\n";
    ?>
</div>
<div class="card-body">

    <table class="table">

        <tr class="TableHeader">
            <td><?= gettext('Select') ?></td>
            <td><?= gettext('Number') ?></td>
            <td><?= gettext('Buyer') ?></td>
            <td class="text-center no-export w-1"><?= gettext('Actions') ?></td>
        </tr>

        <?php
        $tog = 0;

        //Loop through all buyers
        if ($rsPaddleNums) {
            while ($aRow = mysqli_fetch_array($rsPaddleNums)) {
                extract($aRow);

                ?>
                <tr>
                    <td>
                        <input type="checkbox" name="Chk<?= (int)$pn_ID . '"';
                        if (isset($_GET['SelectAll'])) {
                            echo ' checked="yes"';
                        } ?>></input>
            </td>
            <td>
                <?= '<a href="PaddleNumEditor.php?PaddleNumID=' . (int)$pn_ID . '&linkBack=PaddleNumList.php"> ' . (int)$pn_Num ."</a>\n" ?>
            </td>

            <td>
                <?= InputUtils::escapeHTML($buyerFirstName) . ' ' . InputUtils::escapeHTML($buyerLastName) ?>&nbsp;
            </td>
            <td class="w-1">
                <div class="dropdown">
                    <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="ti ti-dots-vertical"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="PaddleNumEditor.php?PaddleNumID=<?= (int)$pn_ID ?>&linkBack=PaddleNumList.php">
                            <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger" href="PaddleNumDelete.php?PaddleNumID=<?= (int)$pn_ID ?>&linkBack=PaddleNumList.php?FundRaiserID=<?= (int)$iFundRaiserID ?>">
                            <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                        </a>
                    </div>
                </div>
            </td>
                </tr>
                <?php
            } // while
        } // if
        ?>

    </table>
</div>
</form>
<?php
require_once __DIR__ . '/Include/Footer.php';
