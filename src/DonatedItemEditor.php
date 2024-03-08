<?php

/*******************************************************************************
 *
 *  filename    : DonatedItemEditor.php
 *  last change : 2009-04-15
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2009 Michael Wilt
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\DonatedItem;
use ChurchCRM\model\ChurchCRM\DonatedItemQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$iDonatedItemID = InputUtils::legacyFilterInputArr($_GET, 'DonatedItemID', 'int');
$linkBack = InputUtils::legacyFilterInputArr($_GET, 'linkBack');
$iCurrentFundraiser = InputUtils::legacyFilterInputArr($_GET, 'CurrentFundraiser');

if ($iDonatedItemID > 0) {
    $sSQL = "SELECT * FROM donateditem_di WHERE di_ID = '$iDonatedItemID'";
    $rsDonatedItem = RunQuery($sSQL);
    $theDonatedItem = mysqli_fetch_array($rsDonatedItem);
    $iCurrentFundraiser = $theDonatedItem['di_FR_ID'];
}

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
$sPageTitle = gettext('Donated Item Editor');

//Is this the second pass?
if (isset($_POST['DonatedItemSubmit']) || isset($_POST['DonatedItemSubmitAndAdd'])) {
    //Get all the variables from the request object and assign them locally
    $sItem = InputUtils::legacyFilterInputArr($_POST, 'Item');
    $bMultibuy = InputUtils::legacyFilterInputArr($_POST, 'Multibuy', 'int');
    $iDonor = InputUtils::legacyFilterInputArr($_POST, 'Donor', 'int');
    $iBuyer = InputUtils::legacyFilterInputArr($_POST, 'Buyer', 'int');
    $sTitle = InputUtils::legacyFilterInputArr($_POST, 'Title');
    $sDescription = InputUtils::legacyFilterInputArr($_POST, 'Description');
    $nSellPrice = InputUtils::legacyFilterInputArr($_POST, 'SellPrice');
    $nEstPrice = InputUtils::legacyFilterInputArr($_POST, 'EstPrice');
    $nMaterialValue = InputUtils::legacyFilterInputArr($_POST, 'MaterialValue');
    $nMinimumPrice = InputUtils::legacyFilterInputArr($_POST, 'MinimumPrice');
    $sPictureURL = InputUtils::legacyFilterInputArr($_POST, 'PictureURL');

    if (!$bMultibuy) {
        $bMultibuy = 0;
    }
    if (!$iBuyer) {
        $iBuyer = 0;
    }
    // New DonatedItem or deposit
    if (strlen($iDonatedItemID) < 1) {
        $donatedItem = new DonatedItem();
        $donatedItem
            ->setFrId($iCurrentFundraiser)
            ->setItem($sItem)
            ->setMultibuy($bMultibuy)
            ->setDonorId($iDonor)
            ->setBuyerId($iBuyer)
            ->setTitle(html_entity_decode($sTitle))
            ->setDescription(html_entity_decode($sDescription))
            ->setSellprice($nSellPrice)
            ->setEstprice($nEstPrice)
            ->setMaterialValue($nMaterialValue)
            ->setMinimum($nMinimumPrice)
            ->setPicture($sPictureURL)
            ->setEnteredby(AuthenticationManager::getCurrentUser()->getId())
            ->setEntereddate(date('YmdHis'));
        $donatedItem->save();

        $bGetKeyBack = true;
    // Existing record (update)
    } else {
        $donatedItem = DonatedItemQuery::create()->findOneById($iDonatedItemID);
        $donatedItem
            ->setFrId($iCurrentFundraiser)
            ->setItem($sItem)
            ->setMultibuy($bMultibuy)
            ->setDonorId($iDonor)
            ->setBuyerId($iBuyer)
            ->setTitle(html_entity_decode($sTitle))
            ->setDescription(html_entity_decode($sDescription))
            ->setSellprice($nSellPrice)
            ->setEstprice($nEstPrice)
            ->setMaterialValue($nMaterialValue)
            ->setMinimum($nMinimumPrice)
            ->setPicture($sPictureURL)
            ->setEnteredby(AuthenticationManager::getCurrentUser()->getId())
            ->setEntereddate(date('YmdHis'));
        $donatedItem->save();
        $bGetKeyBack = false;
    }

    // If this is a new DonatedItem or deposit, get the key back
    if ($bGetKeyBack) {
        $sSQL = 'SELECT MAX(di_ID) AS iDonatedItemID FROM donateditem_di';
        $rsDonatedItemID = RunQuery($sSQL);
        extract(mysqli_fetch_array($rsDonatedItemID));
    }

    if (isset($_POST['DonatedItemSubmit'])) {
        // Check for redirection to another page after saving information: (ie. DonatedItemEditor.php?previousPage=prev.php?a=1;b=2;c=3)
        if ($linkBack != '') {
            RedirectUtils::redirect($linkBack);
        } else {
            //Send to the view of this DonatedItem
            RedirectUtils::redirect('DonatedItemEditor.php?DonatedItemID=' . $iDonatedItemID . '&linkBack=', $linkBack);
        }
    } elseif (isset($_POST['DonatedItemSubmitAndAdd'])) {
        //Reload to editor to add another record
        RedirectUtils::redirect("DonatedItemEditor.php?CurrentFundraiser=$iCurrentFundraiser&linkBack=", $linkBack);
    }
} else {
  //FirstPass
    //Are we editing or adding?
    if (strlen($iDonatedItemID) > 0) {
        //Editing....
        //Get all the data on this record

        $sSQL = "SELECT di_ID, di_Item, di_multibuy, di_donor_ID, di_buyer_ID,
		                   a.per_FirstName as donorFirstName, a.per_LastName as donorLastName,
	                       b.per_FirstName as buyerFirstName, b.per_LastName as buyerLastName,
	                       di_title, di_description, di_sellprice, di_estprice, di_materialvalue,
	                       di_minimum, di_picture
	         FROM donateditem_di
	         LEFT JOIN person_per a ON di_donor_ID=a.per_ID
	         LEFT JOIN person_per b ON di_buyer_ID=b.per_ID
	         WHERE di_ID = '" . $iDonatedItemID . "'";
        $rsDonatedItem = RunQuery($sSQL);
        extract(mysqli_fetch_array($rsDonatedItem));

        $sItem = $di_Item;
        $bMultibuy = $di_multibuy;
        $iDonor = $di_donor_ID;
        $iBuyer = $di_buyer_ID;
        $sTitle = $di_title;
        $sDescription = $di_description;
        $nSellPrice = $di_sellprice;
        $nEstPrice = $di_estprice;
        $nMaterialValue = $di_materialvalue;
        $nMinimumPrice = $di_minimum;
        $sPictureURL = $di_picture;
    } else {
        //Adding....
        //Set defaults
        $sItem = '';
        $bMultibuy = 0;
        $iDonor = 0;
        $iBuyer = 0;
        $sTitle = '';
        $sDescription = '';
        $nSellPrice = 0.0;
        $nEstPrice = 0.0;
        $nMaterialValue = 0.0;
        $nMinimumPrice = 0.0;
        $sPictureURL = '';
    }
}

//Get People for the drop-down
$sPeopleSQL = 'SELECT per_ID, per_FirstName, per_LastName, fam_Address1, fam_City, fam_State FROM person_per JOIN family_fam on per_fam_id=fam_id ORDER BY per_LastName, per_FirstName';

//Get Paddles for the drop-down
$sPaddleSQL = 'SELECT pn_ID, pn_Num, pn_per_ID,
                      a.per_FirstName AS buyerFirstName,
                      a.per_LastName AS buyerLastName
                      FROM paddlenum_pn
                      LEFT JOIN person_per a on a.per_ID=pn_per_ID
                      WHERE pn_fr_ID=' . $iCurrentFundraiser . ' ORDER BY pn_Num';

require 'Include/Header.php';
?>

<form method="post" action="DonatedItemEditor.php?<?= 'CurrentFundraiser=' . $iCurrentFundraiser . '&DonatedItemID=' . $iDonatedItemID . '&linkBack=' . $linkBack; ?>" name="DonatedItemEditor">
    <div class="card card-primary">
        <div class="card-body">
            <div class="form-group">
                <div class="row">
                    <div class="col-md-4 col-md-offset-2 col-xs-6">
                        <div class="form-group">
                            <label><?= gettext('Item') ?>:</label>
                            <input type="text" name="Item" id="Item" value="<?= $sItem ?>" class="form-control">
                        </div>

                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="Multibuy" value="1" <?= $bMultibuy ? 'checked' : ''; ?>>
                                <?= gettext('Sell to everyone'); ?> (<?= gettext('Multiple items'); ?>)
                            </label>
                        </div>

                        <div class="form-group">
                            <label><?= gettext('Donor'); ?>:</label>
                            <select name="Donor" id="Donor" class="form-control select2">
                                <option value="0" selected><?= gettext('Unassigned') ?></option>
<?php
$rsPeople = RunQuery($sPeopleSQL);
while ($aRow = mysqli_fetch_array($rsPeople)) {
    extract($aRow);
    echo '<option value="' . $per_ID . '"';
    if ($iDonor == $per_ID) {
        echo ' selected';
    }
    echo '>' . $per_LastName . ', ' . $per_FirstName;
    echo ' ' . FormatAddressLine($fam_Address1, $fam_City, $fam_State);
}
?>
                            </select>
                        </div>
<script nonce="<?= SystemURLs::getCSPNonce() ?>" >
    $(document).ready(function() {
    $("#Donor").select2();
});
</script>

                        <div class="form-group">
                            <label><?= gettext('Title') ?>:</label>
                            <input type="text" name="Title" id="Title" value="<?= htmlentities($sTitle) ?>" class="form-control"/>
                        </div>

                        <div class="form-group">
                            <label><?= gettext('Estimated Price') ?>:</label>
                            <input type="text" name="EstPrice" id="EstPrice" value="<?= $nEstPrice ?>" class="form-control">
                        </div>

                        <div class="form-group">
                            <label><?= gettext('Material Value') ?>:</label>
                            <input type="text" name="MaterialValue" id="MaterialValue" value="<?= $nMaterialValue ?>" class="form-control">
                        </div>

                        <div class="form-group">
                            <label><?= gettext('Minimum Price') ?>:</label>
                            <input type="text" name="MinimumPrice" id="MinimumPrice" value="<?= $nMinimumPrice ?>" class="form-control">
                        </div>

                    </div>

                    <div class="col-md-4 col-xs-6">
                        <div class="form-group">
                            <label><?= gettext('Buyer') ?>:</label>
<?php if ($bMultibuy) {
    echo gettext('Multiple');
} else {
    ?>
                        <select name="Buyer" class="form-control">
                          <option value="0" selected><?= gettext('Unassigned') ?></option>
    <?php
    $rsBuyers = RunQuery($sPaddleSQL);
    while ($aRow = mysqli_fetch_array($rsBuyers)) {
        extract($aRow);
        echo '<option value="' . $pn_per_ID . '"';
        if ($iBuyer == $pn_per_ID) {
            echo ' selected';
        }
        echo '>' . $pn_Num . ': ' . $buyerFirstName . ' ' . $buyerLastName;
    }
}
?>

                            </select>
                        </div>

                        <div class="form-group">
                            <label><?= gettext('Final Price') ?>:</label>
                            <input type="text" name="SellPrice" id="SellPrice" value="<?= $nSellPrice ?>" class="form-control">
                        </div>

                        <div class="form-group">
                            <label><?= gettext('Replicate item') ?></label>
                            <div class="input-group">
                                <input type="text" name="NumberCopies" id="NumberCopies" value="0" class="form-control">
                                <span class="input-group-btn">
                                    <input type="button" class="btn btn-primary" value="<?= gettext('Go') ?>" name="DonatedItemReplicate"
                                    onclick="javascript:document.location = 'DonatedItemReplicate.php?DonatedItemID=<?= $iDonatedItemID ?>&Count=' + NumberCopies.value">
                                </span>
                            </div>
                        </div>

                    </div>

                    <div class="col-md-6 col-md-offset-2 col-xs-12">
                        <div class="form-group">
                            <label><?= gettext('Description') ?>:</label>
                            <textarea name="Description" rows="5" cols="90" class="form-control"><?= htmlentities($sDescription) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label><?= gettext('Picture URL') ?>:</label>
                            <textarea name="PictureURL" rows="1" cols="90" class="form-control"><?= htmlentities($sPictureURL) ?></textarea>
                        </div>

                        <?php if ($sPictureURL != '') : ?>
                            <div class="form-group"><img src="<?= htmlentities($sPictureURL) ?>"/></div>
                        <?php endif; ?>

                    </div>

                </div> <!-- row -->
            </div>

            <div class="form-group text-center">
                <input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="DonatedItemSubmit">
                <?php if (AuthenticationManager::getCurrentUser()->isAddRecordsEnabled()) : ?>
                    <input type="submit" class="btn btn-primary" value="<?= gettext('Save and Add'); ?>" name="DonatedItemSubmitAndAdd">
                <?php endif; ?>
                <input type="button" class="btn btn-default" value="<?= gettext('Cancel') ?>" name="DonatedItemCancel"
                onclick="javascript:document.location = '<?= strlen($linkBack) > 0 ? $linkBack : 'v2/dashboard'; ?>';">
            </div>

        </div>
    </div>
</form>

<?php require 'Include/Footer.php'; ?>
