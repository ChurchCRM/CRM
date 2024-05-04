<?php

/*******************************************************************************
 *
 *  filename    : DonationFundEditor.php
 *  last change : 2003-03-29
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2003 Chris Gebhardt (http://www.openserve.org)
 *
 *  function    : Editor for donation funds
  *
 ******************************************************************************/

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\DonationFund;
use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: user must be administrator to use this page
AuthenticationManager::redirectHomeIfNotAdmin();

if (isset($_GET['Action'])) {
    $sAction = $_GET['Action'];
} else {
    $sAction = '';
}
if (isset($_GET['Fund'])) {
    $sFund = InputUtils::legacyFilterInput($_GET['Fund'], 'int');
} else {
    $sFund = '';
}

$sDeleteError = '';
$bErrorFlag = false;
$aNameErrors = [];
$bNewNameError = false;

if ($sAction = 'delete' && strlen($sFund) > 0) {
    DonationFundQuery::create()
    ->findById($sFund)
    ->delete();
}

$sPageTitle = gettext('Donation Fund Editor');

require 'Include/Header.php'; ?>


<div class="card card-body">


<?php

// Get data for the form as it now exists..

$donationFunds = DonationFundQuery::create()
  ->orderByName()
  ->find();

// Does the user want to save changes to text fields?
if (isset($_POST['SaveChanges'])) {
    for ($iFieldID = 0; $iFieldID < $donationFunds->count(); $iFieldID++) {
        $donation = $donationFunds[$iFieldID];
        $donation->setName(InputUtils::filterString($_POST[$iFieldID . 'name']));
        $donation->setDescription(InputUtils::legacyFilterInput($_POST[$iFieldID . 'desc']));
        $donation->setActive($_POST[$iFieldID . 'active'] == 1);
        if (strlen($donation->getName()) == 0) {
            $aNameErrors[$iFieldID] = true;
            $bErrorFlag &= $aNameErrors[$iFieldID];
        }
    }

    // If no errors, then update.
    if (!$bErrorFlag) {
        $donationFunds->save();
    }
} else {
    // Check if we're adding a fund
    if (isset($_POST['AddField'])) {
        $checkExisting = DonationFundQuery::create()->filterByName($_POST['newFieldName'])->findOne();
        if (count($checkExisting) > 0) {
            $bNewNameError = true;
        } else {
            $donation = new DonationFund();
            $donation->setName(InputUtils::legacyFilterInput($_POST['newFieldName']));
            $donation->setDescription(InputUtils::legacyFilterInput($_POST['newFieldDesc']));
            $donation->save();
            $donationFunds = DonationFundQuery::create()
            ->orderByName()
            ->find();
        }
    }
}

// Create arrays of the funds.
for ($row = 0; $row < $donationFunds->count(); $row++) {
    $donation = $donationFunds[$row];
    $aIDFields[$row] = $donation->getId();
    $aNameFields[$row] = $donation->getName();
    $aDescFields[$row] = $donation->getDescription();
    $aActiveFields[$row] = boolval($donation->getActive());
}

// Construct the form
?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>" >

function confirmDeleteFund( Fund ) {
var answer = confirm (<?= '"' . gettext('Are you sure you want to delete this fund?') . '"' ?>)
if ( answer )
    window.location="DonationFundEditor.php?Fund=" + Fund + "&Action=delete"
}
</script>

<form method="post" action="DonationFundEditor.php" name="FundsEditor">

<div class="alert alert-warning">
        <i class="fa fa-ban"></i>
        <?= gettext("Warning: Field changes will be lost if you do not 'Save Changes' before using a delete or 'add new' button!") ?>

</div>

<?php
if ($bErrorFlag) {
    echo gettext('Invalid fields or selections. Changes not saved! Please correct and try again!');
}
if (strlen($sDeleteError) > 0) {
    echo $sDeleteError;
}
?>

<table class="table">

<?php
if ($donationFunds->count() == 0) {
    ?>
    <center><h2><?= gettext('No funds have been added yet') ?></h2>
    </center>
    <?php
} else {
    ?>
        <tr>
            <th><?= gettext('Name') ?></th>
            <th><?= gettext('Description') ?></th>
            <th><?= gettext('Active') ?></th>
            <th><?= gettext('Delete') ?></th>
        </tr>

    <?php

    for ($row = 0; $row < $donationFunds->count(); $row++) {
        ?>
        <tr>


            <td class="TextColumn" align="center">
                <input type="text" name="<?= $row . 'name' ?>" value="<?= htmlentities(stripslashes($aNameFields[$row]), ENT_NOQUOTES, 'UTF-8') ?>" size="20" maxlength="30">
                <?php
                if ($aNameErrors[$row]) {
                    echo '<span style="color: red;"><BR>' . gettext('You must enter a name') . ' .</span>';
                } ?>
            </td>

            <td class="TextColumn">
                <input type="text" Name="<?php echo $row . 'desc' ?>" value="<?= htmlentities(stripslashes($aDescFields[$row]), ENT_NOQUOTES, 'UTF-8') ?>" size="40" maxlength="100">
            </td>
            <td class="TextColumn" align="center" nowrap>
                <input type="radio" Name="<?= $row ?>active" value="1" <?php if ($aActiveFields[$row]) {
                    echo ' checked';
                                          } ?>><?= gettext('Yes') ?>
                <input type="radio" Name="<?= $row ?>active" value="0" <?php if (!$aActiveFields[$row]) {
                    echo ' checked';
                                          } ?>><?= gettext('No') ?>
            </td>
            <td class="TextColumn" width="5%">
                <input type="button" class="btn btn-danger" value="<?= gettext('Delete') ?>" Name="delete" onclick="confirmDeleteFund('<?= $aIDFields[$row] ?>');" >
            </td>

        </tr>
        <?php
    } ?>

        <tr>
            <td colspan="5">
            <table width="100%">
                <tr>
                    <td width="30%"></td>
                    <td width="40%" align="center" valign="bottom">
                        <input type="submit" class="btn btn-primary" value="<?= gettext('Save Changes') ?>" Name="SaveChanges">
                    </td>
                    <td width="30%"></td>
                </tr>
            </table>
            </td>
            <td>
        </tr>
    <?php
} ?>
        <tr><td colspan="5"><hr></td></tr>
        <tr>
            <td colspan="5">
            <table width="100%">
                <tr>
                    <td width="15%"></td>
                    <td valign="top">
                        <div><?= gettext('Name') ?>:</div>
                        <input type="text" name="newFieldName" size="30" maxlength="30">
                        <?php if ($bNewNameError) {
                            echo '<div><span style="color: red;"><BR>' . gettext('You must enter a name') . '</span></div>';
                        } ?>
                        &nbsp;
                    </td>
                    <td valign="top">
                        <div><?= gettext('Description') ?>:</div>
                        <input type="text" name="newFieldDesc" size="40" maxlength="100">
                        &nbsp;
                    </td>
                    <td>
                        <input type="submit" class="btn btn-primary" value="<?= gettext('Add New Fund') ?>" Name="AddField">
                    </td>
                    <td width="15%"></td>
                </tr>
            </table>
            </td>
        </tr>

    </table>
    </form>
</div>

<?php require 'Include/Footer.php' ?>
