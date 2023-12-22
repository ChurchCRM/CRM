<?php

/*******************************************************************************
 *
 *  filename    : DepositSlipEditor.php
 *  last change : 2014-12-14
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2001, 2002, 2003-2014 Deane Barker, Chris Gebhardt, Michael Wilt
 *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$iDepositSlipID = 0;
$thisDeposit = 0;

if (array_key_exists('DepositSlipID', $_GET)) {
    $iDepositSlipID = InputUtils::legacyFilterInput($_GET['DepositSlipID'], 'int');
}

if ($iDepositSlipID) {
    $thisDeposit = DepositQuery::create()->findOneById($iDepositSlipID);
    // Set the session variable for default payment type so the new payment form will come up correctly
    if ($thisDeposit->getType() == 'Bank') {
        $_SESSION['idefaultPaymentMethod'] = 'CHECK';
    } elseif ($thisDeposit->getType() == 'CreditCard') {
        $_SESSION['idefaultPaymentMethod'] = 'CREDITCARD';
    } elseif ($thisDeposit->getType() == 'BankDraft') {
        $_SESSION['idefaultPaymentMethod'] = 'BANKDRAFT';
    } elseif ($thisDeposit->getType() == 'eGive') {
        $_SESSION['idefaultPaymentMethod'] = 'EGIVE';
    }

    // Security: User must have finance permission or be the one who created this deposit
    if (!(AuthenticationManager::getCurrentUser()->isFinanceEnabled() || AuthenticationManager::getCurrentUser()->getId() == $thisDeposit->getEnteredby())) {
        RedirectUtils::redirect('Menu.php');
        exit;
    }
} elseif ($iDepositSlipID == 0) {
    RedirectUtils::redirect('FindDepositSlip.php');
    exit;
} else {
    RedirectUtils::redirect('Menu.php');
}

//Set the page title
$sPageTitle = $thisDeposit->getType() . ' ' . gettext('Deposit Slip Number: ') . $iDepositSlipID;

//Is this the second pass?
if (isset($_POST['DepositSlipLoadAuthorized'])) {
    $thisDeposit->loadAuthorized();
} elseif (isset($_POST['DepositSlipRunTransactions'])) {
    $thisDeposit->runTransactions();
}

$_SESSION['iCurrentDeposit'] = $iDepositSlipID;  // Probably redundant

/* @var $currentUser User */
$currentUser = AuthenticationManager::getCurrentUser();
$currentUser->setCurrentDeposit($iDepositSlipID);
$currentUser->save();

require 'Include/Header.php';
?>
<div class="row">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header with-border">
        <h3 class="card-title"><?php echo gettext('Deposit Details: '); ?></h3>
      </div>
      <div class="card-body">
        <form method="post" action="#" name="DepositSlipEditor" id="DepositSlipEditor">
          <div class="row">
            <div class="col-lg-4">
              <label for="Date"><?= gettext('Date'); ?>:</label>
              <input type="text" class="form-control date-picker" name="Date" value="<?php echo $thisDeposit->getDate('Y-m-d'); ?>" id="DepositDate" >
            </div>
            <div class="col-lg-4">
              <label for="Comment"><?php echo gettext('Comment:'); ?></label>
              <input type="text" class="form-control" name="Comment" id="Comment" value="<?php echo $thisDeposit->getComment(); ?>"/>
            </div>
            <div class="col-lg-4">
              <label for="Closed"><?php echo gettext('Closed:'); ?></label>
              <input type="checkbox"  name="Closed" id="Closed" value="1" <?php if ($thisDeposit->getClosed()) {
                    echo ' checked';
                                                                          } ?>/><?php echo gettext('Close deposit slip (remember to press Save)'); ?>
            </div>
          </div>
          <div class="row p-2">
            <div class="col-lg-5 m-2" style="text-align:center">
              <input type="submit" class="btn btn-default" value="<?php echo gettext('Save'); ?>" name="DepositSlipSubmit">
            </div>
            <div class="col-lg-5 m-2" style="text-align:center">
              <input type="button" class="btn btn-default" value="<?php echo gettext('Deposit Slip Report'); ?>" name="DepositSlipGeneratePDF" onclick="window.CRM.VerifyThenLoadAPIContent(window.CRM.root + '/api/deposits/<?php echo $thisDeposit->getId() ?>/pdf');">
            </div>
          </div>
          <?php
            if ($thisDeposit->getType() == 'BankDraft' || $thisDeposit->getType() == 'CreditCard') {
                echo '<p>' . gettext('Important note: failed transactions will be deleted permanently when the deposit slip is closed.') . '</p>';
            }
            ?>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header with-border">
        <h3 class="card-title"><?php echo gettext('Deposit Summary: '); ?></h3>
      </div>
      <div class="card-body">
        <div class="col-lg-6">
          <canvas id="type-donut" style="height: 250px;"></canvas>
          <ul style="margin:0px; border:0px; padding:0px;">
          <?php
          // Get deposit totals
            echo '<li><b>TOTAL (' . $thisDeposit->getPledges()->count() . '):</b> $' . $thisDeposit->getVirtualColumn('totalAmount') . '</li>';
            if ($thisDeposit->getCountCash()) {
                echo '<li><b>CASH (' . $thisDeposit->getCountCash() . '):</b> $' . $thisDeposit->getTotalCash() . '</li>';
            }
            if ($thisDeposit->getCountChecks()) {
                echo '<li><b>CHECKS (' . $thisDeposit->getCountChecks() . '):</b> $' . $thisDeposit->getTotalChecks() . ' </li>';
            }
            ?>
            </ul>
        </div>
         <div class="col-lg-6">
          <canvas id="fund-donut" style="height:250px"></canvas>
          <ul style="margin:0px; border:0px; padding:0px;">
          <?php
            foreach ($thisDeposit->getFundTotals() as $fund) {
                echo '<li><b>' . $fund['Name'] . '</b>: $' . $fund['Total'] . '</li>';
            }
            ?>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="card">
  <div class="card-header with-border">
    <h3 class="card-title"><?php echo gettext('Payments on this deposit slip:'); ?></h3>
    <div class="pull-right">
      <?php
        if ($iDepositSlipID && $thisDeposit->getType() && !$thisDeposit->getClosed()) {
            if ($thisDeposit->getType() == 'eGive') {
                echo '<input type=button class=btn value="' . gettext('Import eGive') . "\" name=ImporteGive onclick=\"javascript:document.location='eGive.php?DepositSlipID=$iDepositSlipID&linkBack=DepositSlipEditor.php?DepositSlipID=$iDepositSlipID&PledgeOrPayment=Payment&CurrentDeposit=$iDepositSlipID';\">";
            } else {
                echo '<input type=button class="btn btn-success" value="' . gettext('Add Payment') . "\" name=AddPayment onclick=\"javascript:document.location='PledgeEditor.php?CurrentDeposit=$iDepositSlipID&PledgeOrPayment=Payment&linkBack=DepositSlipEditor.php?DepositSlipID=$iDepositSlipID&PledgeOrPayment=Payment&CurrentDeposit=$iDepositSlipID';\">";
            }
            if ($thisDeposit->getType() == 'BankDraft' || $thisDeposit->getType() == 'CreditCard') {
                ?>
          <input type="submit" class="btn btn-success" value="<?php echo gettext('Load Authorized Transactions'); ?>" name="DepositSlipLoadAuthorized">
          <input type="submit" class="btn btn-warning" value="<?php echo gettext('Run Transactions'); ?>" name="DepositSlipRunTransactions">
                <?php
            }
        }
        ?>
    </div>
  </div>
  <div class="card-body">
    <table class="table" id="paymentsTable" width="100%"></table>
    <?php
    if ($iDepositSlipID && $thisDeposit->getType() && !$thisDeposit->getClosed()) {
        if ($thisDeposit->getType() == 'Bank') {
            ?>
        <button type="button" id="deleteSelectedRows"  class="btn btn-danger" disabled>Delete Selected Rows</button>
            <?php
        }
    }
    ?>
  </div>
</div>


<script  src="<?= SystemURLs::getRootPath() ?>/skin/js/DepositSlipEditor.js"></script>
<?php
  $fundLabels = [];
  $fundData = [];
  $fundBackgroundColor = [];
foreach ($thisDeposit->getFundTotals() as $tmpfund) {
    $label = $tmpfund['Name'];
    $data = $tmpfund['Total'];
    $backgroundColor = '#' . random_color();

    $fundLabels[] = $label;
    $fundData[] = $data;
    $fundBackgroundColor[] = $backgroundColor;
}

  $pledgeData = [];

  $data = $thisDeposit->getTotalamount() ? $thisDeposit->getTotalCash() : '0';
  $pledgeData[] = $data;

  $data = $thisDeposit->getTotalamount() ? $thisDeposit->getTotalChecks() : '0';
  $pledgeData[] = $data;

?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  var depositType = '<?php echo $thisDeposit->getType(); ?>';
  var depositSlipID = <?php echo $iDepositSlipID; ?>;
  var isDepositClosed = Boolean(<?=  $thisDeposit->getClosed(); ?>);
  var fundLabels = <?= json_encode(array_values($fundLabels)) ?>;
  var fundData = <?= json_encode(array_values($fundData)) ?>;
  var fundBackgroundColor = <?= json_encode(array_values($fundBackgroundColor)) ?>;
  var pledgeLabels = ['Cash', 'Check'];
  var pledgeData = <?= json_encode($pledgeData) ?>;
  var pledgeBackgroundColor = ['#197A05','#003399'];
  $(document).ready(function() {
    initPaymentTable();
    var pledgeLabels = <?= json_encode(array_values($fundData)); ?>;
    initCharts(pledgeLabels,
               pledgeData,
               pledgeBackgroundColor,
               fundLabels,
               fundData,
               fundBackgroundColor);
    initDepositSlipEditor();

    $('#deleteSelectedRows').click(function() {
      var deletedRows = dataT.rows('.selected').data();
      bootbox.confirm({
        title:'<?= gettext("Confirm Delete")?>',
        message: '<p><?= gettext("Are you sure you want to delete the selected")?> ' + deletedRows.length + ' <?= gettext("payments(s)?") ?></p>' +
        '<p><?= gettext("This action CANNOT be undone, and may have legal implications!") ?></p>'+
        '<p><?= gettext("Please ensure this what you want to do.</p>") ?>',
        buttons: {
          cancel : {
            label: '<?= gettext("Close"); ?>'
          },
          confirm: {
            label: '<?php echo gettext("Delete"); ?>'
          }
        },
        callback: function ( result ) {
          if ( result )
          {
            window.CRM.deletesRemaining = deletedRows.length;
            $.each(deletedRows, function(index, value) {
              window.CRM.APIRequest({
                method: 'DELETE',
                path: 'payments/' + value.GroupKey,
              })
              .done(function(data) {
                dataT.rows('.selected').remove().draw(false);
                window.CRM.deletesRemaining --;
                if ( window.CRM.deletesRemaining == 0 )
                {
                  location.reload();
                }
              });
              });
          }
        }
      })
    });
  });
</script>
<?php
  require 'Include/Footer.php';
?>
