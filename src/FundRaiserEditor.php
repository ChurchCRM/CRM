<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\FundRaiser;
use ChurchCRM\model\ChurchCRM\FundRaiserQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$sPageTitle = gettext('Create New Fund Raiser');

$linkBack = InputUtils::legacyFilterInputArr($_GET, 'linkBack');
$iFundRaiserID = InputUtils::legacyFilterInputArr($_GET, 'FundRaiserID');

$fundraiser = null;
if ($iFundRaiserID > 0) {
    // Get the current fundraiser record
    $fundraiser = FundRaiserQuery::create()->findOneById($iFundRaiserID);
    $sPageTitle = gettext('Fundraiser') . ' #' . $iFundRaiserID . ' ' . $fundraiser->getTitle();
    // Set current fundraiser
    $_SESSION['iCurrentFundraiser'] = $iFundRaiserID;
}

$sDateError = '';

// Is this the second pass?
if (isset($_POST['FundRaiserSubmit'])) {
    //Get all the variables from the request object and assign them locally
    $dDate = InputUtils::legacyFilterInputArr($_POST, 'Date');
    $sTitle = InputUtils::legacyFilterInputArr($_POST, 'Title');
    $sDescription = InputUtils::legacyFilterInputArr($_POST, 'Description');

    // Initialize the error flag
    $bErrorFlag = false;

    // Validate Date
    if (strlen($dDate) > 0) {
        list($iYear, $iMonth, $iDay) = sscanf($dDate, '%04d-%02d-%02d');
        if (!checkdate($iMonth, $iDay, $iYear)) {
            $sDateError = '<span style="color: red; ">' . gettext('Not a valid date') . '</span>';
            $bErrorFlag = true;
        }
    }

    // If no errors, then let's update...
    if (!$bErrorFlag) {
        // New deposit slip
        if ($iFundRaiserID <= 0) {
            $fundraiser = new FundRaiser();
            $fundraiser
                ->setDate($dDate)
                ->setTitle($sTitle)
                ->setDescription($sDescription)
                ->setEnteredBy(AuthenticationManager::getCurrentUser()->getId())
                ->setEnteredDate(date('YmdHis'));
            $fundraiser->save();
            $fundraiser->reload();

            $iFundRaiserID = $fundraiser->getId();
            // Existing record (update)
        } else {
            $fundraiser = FundRaiserQuery::create()->findOneById($iFundRaiserID);
            $fundraiser
                ->setDate($dDate)
                ->setTitle($sTitle)
                ->setDescription($sDescription)
                ->setEnteredBy(AuthenticationManager::getCurrentUser()->getId())
                ->setEnteredDate(date('YmdHis'));
            $fundraiser->save();
        }

        $_SESSION['iCurrentFundraiser'] = $iFundRaiserID;

        if (isset($_POST['FundRaiserSubmit'])) {
            if ($linkBack != '') {
                RedirectUtils::redirect($linkBack);
            } else {
                //Send to the view of this FundRaiser
                RedirectUtils::redirect('FundRaiserEditor.php?linkBack=' . $linkBack . '&FundRaiserID=' . $iFundRaiserID);
            }
        }
    }
} else {
    //FirstPass
    //Are we editing or adding?
    if ($fundraiser) {
        //Editing....
        //Get all the data on this record
        $dDate = $fundraiser->getDate();
        $sTitle = $fundraiser->getTitle();
        $sDescription = $fundraiser->getDescription();


        $sSQL = "SELECT di_ID, di_Item, di_multibuy,
        a.per_FirstName as donorFirstName, a.per_LastName as donorLastName,
        b.per_FirstName as buyerFirstName, b.per_LastName as buyerLastName,
        di_title, di_sellprice, di_estprice, di_materialvalue, di_minimum
        FROM donateditem_di
        LEFT JOIN person_per a ON di_donor_ID=a.per_ID
        LEFT JOIN person_per b ON di_buyer_ID=b.per_ID
        WHERE di_FR_ID = '" . $iFundRaiserID . "' ORDER BY di_multibuy,SUBSTR(di_item,1,1),cast(SUBSTR(di_item,2) as unsigned integer),SUBSTR(di_item,4)";
        $rsDonatedItems = RunQuery($sSQL);
        $_SESSION['iCurrentFundraiser'] = $iFundRaiserID;        // Probably redundant

    } else {
        $dDate = date_create('now');    // Set default date to today
        $sTitle = '';
        $sDescription = '';
        $rsDonatedItems = 0;
    }
}

require_once 'Include/Header.php';

?>
<div class="card card-body">
    <form method="post" action="FundRaiserEditor.php?<?= 'linkBack=' . $linkBack . '&FundRaiserID=' . $iFundRaiserID ?>" name="FundRaiserEditor">

        <table cellpadding="3" width="100%">
            <tr>
                <td>
                    <table cellpadding="3">
                        <tr>
                            <td class="LabelColumn"><?= gettext('Date') ?>:</td>
                            <td class="TextColumn"><input type="text" name="Date" value="<?= $dDate->format("Y-m-d") ?>" maxlength="10" id="Date" size="11" class="date-picker"><span style="color: red;"><?= $sDateError ?></span></td>
                        </tr>

                        <tr>
                            <td class="LabelColumn"><?= gettext('Title') ?>:</td>
                            <td class="TextColumn"><input type="text" size="50" name="Title" id="Title" value="<?= $sTitle ?>"></td>
                        </tr>

                        <tr>
                            <td class="LabelColumn"><?= gettext('Description') ?>:</td>
                            <td class="TextColumn"><textarea name="Description" id="Description" cols="50" rows="5"><?= $sDescription ?></textarea></td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td align="right">
                    <input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="FundRaiserSubmit">
                    <input type="button" class="btn btn-danger" value="<?= gettext('Cancel') ?>" name="FundRaiserCancel" onclick="javascript:document.location='FindFundRaiser.php';">
                </td>
            </tr>
         
    </form>
    </table>

</div>
<div class="card card-body">
    <table cellpadding="3" width="100%">
    <tr>
        <td>
        <?php
            if ($iFundRaiserID > 0) {
                echo '<input id=addItem type=button class="btn btn-default" value="' . gettext('Add Donated Item') . "\" name=AddDonatedItem onclick=\"javascript:document.location='DonatedItemEditor.php?CurrentFundraiser=$iFundRaiserID&linkBack=FundRaiserEditor.php?FundRaiserID=$iFundRaiserID&CurrentFundraiser=$iFundRaiserID';\">\n";
                echo '<input type=button class="btn btn-default" value="' . gettext('Generate Catalog') . "\" name=GenerateCatalog onclick=\"javascript:document.location='Reports/FRCatalog.php?CurrentFundraiser=$iFundRaiserID';\">\n";
                echo '<input type=button class="btn btn-default" value="' . gettext('Generate Bid Sheets') . "\" name=GenerateBidSheets onclick=\"javascript:document.location='Reports/FRBidSheets.php?CurrentFundraiser=$iFundRaiserID';\">\n";
                echo '<input type=button class="btn btn-default" value="' . gettext('Generate Certificates') . "\" name=GenerateCertificates onclick=\"javascript:document.location='Reports/FRCertificates.php?CurrentFundraiser=$iFundRaiserID';\">\n";
                echo '<input type=button class="btn btn-default" value="' . gettext('Batch Winner Entry') . "\" name=BatchWinnerEntry onclick=\"javascript:document.location='BatchWinnerEntry.php?CurrentFundraiser=$iFundRaiserID&linkBack=FundRaiserEditor.php?FundRaiserID=$iFundRaiserID&CurrentFundraiser=$iFundRaiserID';\">\n";
            }
        ?></td>
    </tr>
    </table>            
</div>
<div class="card card-body">
    <b><?= gettext('Donated items for this fundraiser') ?>:</b>
    <br>
    <div class="table-responsive">
        <table class="table" cellpadding="5" cellspacing="0" width="100%">

            <tr class="TableHeader">
                <td><?= gettext('Item') ?></td>
                <td><?= gettext('Multiple') ?></td>
                <td><?= gettext('Donor') ?></td>
                <td><?= gettext('Buyer') ?></td>
                <td><?= gettext('Title') ?></td>
                <td><?= gettext('Sale Price') ?></td>
                <td><?= gettext('Estimated value') ?></td>
                <td><?= gettext('Material Value') ?></td>
                <td><?= gettext('Minimum Price') ?></td>
                <td><?= gettext('Delete') ?></td>
            </tr>

            <?php
            $tog = 0;

            //Loop through all donated items
            if ($rsDonatedItems != 0) {
                while ($aRow = mysqli_fetch_array($rsDonatedItems)) {
                    extract($aRow);

                    if ($di_Item == '') {
                        $di_Item = '~';
                    }

                    $sRowClass = 'RowColorA'; ?>
                    <tr class="<?= $sRowClass ?>">
                        <td>
                            <a href="DonatedItemEditor.php?DonatedItemID=<?= $di_ID . '&linkBack=FundRaiserEditor.php?FundRaiserID=' . $iFundRaiserID ?>"><?= $di_Item ?></a>
                        </td>
                        <td>
                            <?php if ($di_multibuy) {
                                echo 'X';
                            } ?>&nbsp;
                        </td>
                        <td>
                            <?= $donorFirstName . ' ' . $donorLastName ?>&nbsp;
                        </td>
                        <td>
                            <?php if ($di_multibuy) {
                                echo gettext('Multiple');
                            } else {
                                echo $buyerFirstName . ' ' . $buyerLastName;
                            } ?>&nbsp;
                        </td>
                        <td>
                            <?= $di_title ?>&nbsp;
                        </td>
                        <td align=center>
                            <?= $di_sellprice ?>&nbsp;
                        </td>
                        <td align=center>
                            <?= $di_estprice ?>&nbsp;
                        </td>
                        <td align=center>
                            <?= $di_materialvalue ?>&nbsp;
                        </td>
                        <td align=center>
                            <?= $di_minimum ?>&nbsp;
                        </td>
                        <td>
                            <a href="DonatedItemDelete.php?DonatedItemID=<?= $di_ID . '&linkBack=FundRaiserEditor.php?FundRaiserID=' . $iFundRaiserID ?>">Delete</a>
                        </td>
                    </tr>
                    <?php
                } // while
            } // if
            ?>

        </table>
    </div>
</div>
<?php
require_once 'Include/Footer.php';
