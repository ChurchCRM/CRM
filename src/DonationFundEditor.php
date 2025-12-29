<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

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
$bDuplicateNameError = false;

if ($sAction = 'delete' && strlen($sFund) > 0) {
    DonationFundQuery::create()
    ->findById($sFund)
    ->delete();
}

$sPageTitle = gettext('Donation Fund Editor');

require_once __DIR__ . '/Include/Header.php'; ?>

<div class="card card-body">
    <?php

    // Get data for the form as it now exists..
    $donationFunds = DonationFundQuery::create()
        ->orderByOrder()
        ->find();

    // Does the user want to save changes to text fields?
    if (isset($_POST['SaveChanges'])) {
        for ($iFieldID = 0; $iFieldID < $donationFunds->count(); $iFieldID++) {
            $donation = $donationFunds[$iFieldID];
            $donation->setName(InputUtils::sanitizeText($_POST[$iFieldID . 'name']));
            $donation->setDescription(InputUtils::legacyFilterInput($_POST[$iFieldID . 'desc']));
            $donation->setActive($_POST[$iFieldID . 'active'] == 1);
            if (strlen($donation->getName()) === 0) {
                $aNameErrors[$iFieldID] = true;
                $bErrorFlag = true;
            } else {
                $aNameErrors[$iFieldID] = false;
            }
        }

        // If no errors, then update.
        if (!$bErrorFlag) {
            $donationFunds->save();
        }
    } else {
        // Check if we're adding a fund
        if (isset($_POST['AddField'])) {
            $newFieldName = InputUtils::sanitizeText($_POST['newFieldName']);
            $newFieldDesc = InputUtils::legacyFilterInput($_POST['newFieldDesc']);
            
            if (strlen($newFieldName) === 0) {
                $bNewNameError = true;
            } else {
                $checkExisting = DonationFundQuery::create()->findOneByName($newFieldName);
                if ($checkExisting !== null) {
                    $bDuplicateNameError = true;
                } else {
                    // Get the next available order number
                    $maxOrderFund = DonationFundQuery::create()
                        ->orderByOrder('desc')
                        ->findOne();
                    $nextOrder = $maxOrderFund !== null ? $maxOrderFund->getOrder() + 1 : 1;
                    
                    $donation = new DonationFund();
                    $donation->setName($newFieldName);
                    $donation->setDescription($newFieldDesc);
                    $donation->setOrder($nextOrder);
                    $donation->save();
                    $donationFunds = DonationFundQuery::create()
                        ->orderByOrder()
                        ->find();
                    $bNewNameError = false;
                    $bDuplicateNameError = false;
                }
            }
        }
    }

    // Create arrays of the funds.
    $aIDFields = [];
    $aNameFields = [];
    $aDescFields = [];
    $aActiveFields = [];
    
    for ($row = 0; $row < $donationFunds->count(); $row++) {
        $donation = $donationFunds[$row];
        $aIDFields[$row] = $donation->getId();
        $aNameFields[$row] = $donation->getName();
        $aDescFields[$row] = $donation->getDescription();
        $aActiveFields[$row] = boolval($donation->getActive());
    }

    // Construct the form
    ?>

    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
        function confirmDeleteFund(fundName, fundId) {
            var msg = <?= json_encode(gettext('Are you sure you want to delete')) ?> + ' "' + fundName + '"?';
            msg += '<br><br><strong>' + <?= json_encode(gettext('Warning:')) ?> + '</strong> ';
            msg += <?= json_encode(gettext('By deleting this fund, you may affect historical donation records!')) ?>;
            bootbox.confirm({
                title: <?= json_encode(gettext('Delete Confirmation')) ?>,
                message: msg,
                buttons: {
                    cancel: { label: <?= json_encode(gettext('Cancel')) ?>, className: 'btn-secondary' },
                    confirm: { label: <?= json_encode(gettext('Delete')) ?>, className: 'btn-danger' }
                },
                callback: function(result) {
                    if (result) {
                        window.location = "DonationFundEditor.php?Fund=" + fundId + "&Action=delete";
                    }
                }
            });
            return false;
        }

        <?php if (isset($_GET['Action']) && $_GET['Action'] === 'delete'): ?>
        $(document).ready(function() {
            window.CRM.notify(
                <?= json_encode(gettext('Fund deleted successfully')) ?>,
                { type: 'success' }
            );
        });
        <?php endif; ?>
    </script>

    <form method="post" action="DonationFundEditor.php" name="FundsEditor">
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fa-solid fa-plus"></i>
                    <?= gettext('Add New') . ' ' . gettext('Fund') ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label for="newFieldName"><?= gettext('Name') ?>:</label>
                        <input type="text" id="newFieldName" class="form-control" name="newFieldName" maxlength="30">
                        <?php
                        if ($bNewNameError) {
                            echo '<small class="text-danger d-block mt-1">' . gettext('You must enter a name') . '</small>';
                        }
                        if ($bDuplicateNameError) {
                            echo '<small class="text-danger d-block mt-1">' . gettext('That fund name already exists.') . '</small>';
                        }
                        ?>
                    </div>
                    <div class="col-md-5">
                        <label for="newFieldDesc"><?= gettext('Description') ?>:</label>
                        <input type="text" id="newFieldDesc" class="form-control" name="newFieldDesc" maxlength="100">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-success btn-block" name="AddField">
                            <i class="fa-solid fa-plus"></i>
                            <?= gettext('Add New') . ' ' . gettext('Fund') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php
        if ($donationFunds->count() == 0) {
        ?>
            <div class="alert alert-info" role="alert">
                <i class="fa-solid fa-info-circle"></i>
                <?= gettext('No funds have been added yet') ?>
            </div>
        <?php
        } else {
        ?>
            <div class="alert alert-warning" role="alert">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <strong><?= gettext('Warning:') ?></strong>
                <?= gettext("Field changes will be lost if you do not 'Save Changes' before using a delete or 'Add New' button!") ?>
            </div>
            <?php
            if ($bErrorFlag) {
            ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <strong><?= gettext('Invalid fields or selections.') ?></strong>
                    <?= gettext('Changes not saved! Please correct and try again!') ?>
                </div>
            <?php
            }
            if (strlen($sDeleteError) > 0) {
            ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= $sDeleteError ?>
                </div>
            <?php
            } ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-list"></i>
                        <?= gettext('Existing Donation Funds') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th><?= gettext('Name') ?></th>
                                    <th><?= gettext('Description') ?></th>
                                    <th><?= gettext('Active') ?></th>
                                    <th><?= gettext('Actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                for ($row = 0; $row < $donationFunds->count(); $row++) {
                                    ?>
                                    <tr>
                                        <td>
                                            <input type="text" name="<?= $row . 'name' ?>" value="<?= InputUtils::escapeAttribute($aNameFields[$row]) ?>" class="form-control form-control-sm" maxlength="30">
                                            <?php
                                            if (array_key_exists($row, $aNameErrors) && $aNameErrors[$row]) {
                                                echo '<small class="text-danger d-block mt-1">' . gettext('You must enter a name') . '</small>';
                                            } ?>
                                        </td>
                                        <td>
                                            <input type="text" name="<?= $row . 'desc' ?>" value="<?= InputUtils::escapeAttribute($aDescFields[$row]) ?>" class="form-control form-control-sm" maxlength="100">
                                        </td>
                                        <td>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="<?= $row ?>active" id="<?= $row ?>active_yes" value="1" <?php if ($aActiveFields[$row]) {
                                                    echo ' checked';
                                                } ?>>
                                                <label class="form-check-label" for="<?= $row ?>active_yes"><?= gettext('Yes') ?></label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="<?= $row ?>active" id="<?= $row ?>active_no" value="0" <?php if (!$aActiveFields[$row]) {
                                                    echo ' checked';
                                                } ?>>
                                                <label class="form-check-label" for="<?= $row ?>active_no"><?= gettext('No') ?></label>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $fundNameJs = htmlspecialchars(json_encode($aNameFields[$row]), ENT_QUOTES, 'UTF-8');
                                            $fundIdJs = htmlspecialchars(json_encode($aIDFields[$row]), ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteFund(<?= $fundNameJs ?>, <?= $fundIdJs ?>)">
                                                <i class="fa-solid fa-trash"></i>
                                                <?= gettext('Delete') ?>
                                            </button>
                                            <?php
                                            if ($row !== 0) {
                                                echo '<a href="DonationFundRowOps.php?FundID=' . $aIDFields[$row] . '&Action=up" class="btn btn-sm btn-outline-secondary" title="' . gettext('Move up') . '"><i class="fa-solid fa-arrow-up"></i></a>';
                                            }
                                            if ($row < $donationFunds->count() - 1) {
                                                echo '<a href="DonationFundRowOps.php?FundID=' . $aIDFields[$row] . '&Action=down" class="btn btn-sm btn-outline-secondary" title="' . gettext('Move down') . '"><i class="fa-solid fa-arrow-down"></i></a>';
                                            } ?>
                                        </td>
                                    </tr>
                                    <?php
                                } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-center my-3">
                <button type="submit" class="btn btn-primary" name="SaveChanges">
                    <i class="fa-solid fa-save"></i>
                    <?= gettext('Save Changes') ?>
                </button>
            </div>
        <?php
        } ?>
    </form>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
