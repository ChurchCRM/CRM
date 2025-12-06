<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Deposit;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have finance permission or be the one who created this deposit
if (!(AuthenticationManager::getCurrentUser()->isFinanceEnabled() || AuthenticationManager::getCurrentUser()->getId() === $thisDeposit->getEnteredby())) {
    RedirectUtils::securityRedirect('Finance');
}

$iDepositSlipID = 0;
$thisDeposit = 0;

if (array_key_exists('DepositSlipID', $_GET)) {
    $iDepositSlipID = InputUtils::legacyFilterInput($_GET['DepositSlipID'], 'int');
}

$noDeposit = true;
if ($iDepositSlipID) {
    $thisDeposit = DepositQuery::create()->findOneById($iDepositSlipID);
    if ($thisDeposit) {
        $noDeposit = false;
        // Set the session variable for default payment type so the new payment form will come up correctly
        if ($thisDeposit->getType() === 'Bank') {
            $_SESSION['idefaultPaymentMethod'] = 'CHECK';
        } elseif ($thisDeposit->getType() === 'CreditCard') {
            $_SESSION['idefaultPaymentMethod'] = 'CREDITCARD';
        } elseif ($thisDeposit->getType() === 'BankDraft') {
            $_SESSION['idefaultPaymentMethod'] = 'BANKDRAFT';
        } elseif ($thisDeposit->getType() === 'eGive') {
            $_SESSION['idefaultPaymentMethod'] = 'EGIVE';
        }
    }
}

if ($noDeposit) {
    RedirectUtils::redirect('FindDepositSlip.php');
}

$sPageTitle = $thisDeposit->getType() . ' ' . gettext('Deposit Slip Number: ') . $iDepositSlipID;

// Get previous and next deposits for navigation
$prevDeposit = Deposit::getPreviousDeposit($iDepositSlipID);
$nextDeposit = Deposit::getNextDeposit($iDepositSlipID);

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

require_once 'Include/Header.php';
?>
<!-- Deposit Navigation -->
<div class="row mb-3">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center">
      <a href="FindDepositSlip.php" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left"></i> <?= gettext('Back to Deposits'); ?>
      </a>
      <div class="btn-group" role="group" aria-label="<?= gettext('Deposit Navigation'); ?>">
        <a href="<?= $prevDeposit ? 'DepositSlipEditor.php?DepositSlipID=' . $prevDeposit->getId() : '#'; ?>" 
           class="btn btn-outline-primary <?= $prevDeposit ? '' : 'disabled'; ?>"
           <?= $prevDeposit ? '' : 'aria-disabled="true"'; ?>>
          <i class="fa-solid fa-chevron-left"></i> <?= gettext('Previous'); ?>
        </a>
        <a href="<?= $nextDeposit ? 'DepositSlipEditor.php?DepositSlipID=' . $nextDeposit->getId() : '#'; ?>" 
           class="btn btn-outline-primary <?= $nextDeposit ? '' : 'disabled'; ?>"
           <?= $nextDeposit ? '' : 'aria-disabled="true"'; ?>>
          <?= gettext('Next'); ?> <i class="fa-solid fa-chevron-right"></i>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Deposit Slip Editor Main Container -->
<div id="depositSlipEditorContainer">
  <div class="row">
    <!-- Deposit Details Column -->
    <div class="col-lg-7 col-md-12 mb-3">
      <div class="card">
        <div class="card-header with-border bg-primary text-white">
          <h3 class="card-title mb-0">
            <i class="fa-solid fa-file-invoice-dollar"></i> <?php echo gettext('Deposit Details'); ?>
          </h3>
        </div>
        <div class="card-body">
          <form method="post" action="DepositSlipEditor.php?DepositSlipID=<?= $iDepositSlipID ?>" name="DepositSlipEditor" id="DepositSlipEditor">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="DepositDate" class="form-label"><?= gettext('Date'); ?>:</label>
                <input type="text" class="form-control date-picker" name="Date" value="<?php echo $thisDeposit->getDate('Y-m-d'); ?>" id="DepositDate" required>
              </div>
              <div class="col-md-6 mb-3">
                <label for="Closed" class="form-label"><?php echo gettext('Status'); ?>:</label>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox"  name="Closed" id="Closed" value="1" <?php if ($thisDeposit->getClosed()) {
                        echo ' checked';
                                                                              } ?>>
                  <label class="form-check-label" for="Closed">
                    <?php echo $thisDeposit->getClosed() ? '<span class="badge badge-danger">Closed</span>' : '<span class="badge badge-success">Open</span>'; ?>
                  </label>
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label for="Comment" class="form-label"><?php echo gettext('Comment'); ?>:</label>
              <textarea class="form-control" name="Comment" id="Comment" rows="3" placeholder="<?= gettext('Add any additional notes about this deposit'); ?>"><?php echo InputUtils::escapeHTML($thisDeposit->getComment()); ?></textarea>
            </div>
            <?php
            if ($thisDeposit->getType() == 'BankDraft' || $thisDeposit->getType() == 'CreditCard') {
                echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">';
                echo '<i class="fa-solid fa-triangle-exclamation"></i> ' . gettext('Important: Failed transactions will be deleted permanently when the deposit slip is closed.');
                echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
                echo '</div>';
            }
            ?>
            <div class="row mt-4">
              <div class="col-12">
                <div class="d-flex flex-wrap align-items-center">
                  <!-- Primary Actions -->
                  <button type="submit" class="btn btn-primary mr-2" name="DepositSlipSubmit">
                    <i class="fa-solid fa-save"></i> <?= gettext('Save'); ?>
                  </button>
                  <?php if (!$thisDeposit->getClosed()): ?>
                  <?php if ($thisDeposit->getType() == 'eGive'): ?>
                  <a href="eGive.php?DepositSlipID=<?= $iDepositSlipID ?>&linkBack=DepositSlipEditor.php?DepositSlipID=<?= $iDepositSlipID ?>&PledgeOrPayment=Payment&CurrentDeposit=<?= $iDepositSlipID ?>" class="btn btn-success mr-2">
                    <i class="fa-solid fa-download"></i> <?= gettext('Import eGive'); ?>
                  </a>
                  <?php else: ?>
                  <a href="PledgeEditor.php?CurrentDeposit=<?= $iCurrentDeposit ?>&PledgeOrPayment=Payment&linkBack=DepositSlipEditor.php?DepositSlipID=<?= $iDepositSlipID ?>&PledgeOrPayment=Payment&CurrentDeposit=<?= $iDepositSlipID ?>" class="btn btn-success mr-2">
                    <i class="fa-solid fa-plus-circle"></i> <?= gettext('Add Payment'); ?>
                  </a>
                  <?php endif; ?>
                  <?php endif; ?>
                  
                  <!-- Secondary Action -->
                  <button type="button" class="btn btn-outline-secondary ml-auto" name="DepositSlipGeneratePDF" data-deposit-id="<?= $thisDeposit->getId() ?>">
                    <i class="fa-solid fa-file-pdf"></i> <?= gettext('Generate Report'); ?>
                  </button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
    
    <!-- Summary Stats Column -->
    <div class="col-lg-5 col-md-12 mb-3">
      <!-- Summary Stats Section -->
      <div class="row">
        <!-- Total Deposit - Hero Stat -->
        <div class="col-12 mb-3">
          <div class="stat-card text-center py-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);">
            <div style="font-size: 3rem; font-weight: 700; color: white; letter-spacing: -1px;">
              $<?= number_format($thisDeposit->getVirtualColumn('totalAmount'), 2); ?>
            </div>
            <div class="text-white-50 text-uppercase small font-weight-bold mt-2" style="letter-spacing: 1px;"><?= gettext('Total Deposit'); ?></div>
          </div>
        </div>
        
        <!-- Bottom Row: 3 columns for breakdown -->
        <div class="col-md-4 mb-3">
          <div class="stat-card text-center h-100">
            <?php if ($thisDeposit->getCountCash()): ?>
            <div class="mb-3">
              <i class="fa-solid fa-money-bill fa-2x text-success mb-2"></i>
              <div class="font-weight-bold h5 text-success">$<?= number_format($thisDeposit->getTotalCash(), 2); ?></div>
              <div class="small text-muted"><?= gettext('Cash'); ?> (<?= $thisDeposit->getCountCash(); ?>)</div>
            </div>
            <?php endif; ?>
            <?php if (!$thisDeposit->getCountCash()): ?>
            <div class="text-muted small">
              <i class="fa-solid fa-money-bill fa-2x mb-2 opacity-25"></i>
              <div><?= gettext('No Cash'); ?></div>
            </div>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="col-md-4 mb-3">
          <div class="stat-card text-center h-100">
            <?php if ($thisDeposit->getCountChecks()): ?>
            <div class="mb-3">
              <i class="fa-solid fa-money-check fa-2x text-info mb-2"></i>
              <div class="font-weight-bold h5 text-info">$<?= number_format($thisDeposit->getTotalChecks(), 2); ?></div>
              <div class="small text-muted"><?= gettext('Checks'); ?> (<?= $thisDeposit->getCountChecks(); ?>)</div>
            </div>
            <?php endif; ?>
            <?php if (!$thisDeposit->getCountChecks()): ?>
            <div class="text-muted small">
              <i class="fa-solid fa-money-check fa-2x mb-2 opacity-25"></i>
              <div><?= gettext('No Checks'); ?></div>
            </div>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="col-md-4 mb-3">
          <div class="stat-card text-center h-100 d-flex flex-column justify-content-center">
            <div class="mb-2">
              <i class="fa-solid fa-receipt fa-2x text-primary mb-2"></i>
            </div>
            <div class="stat-value text-dark" style="font-size: 2.5rem;"><?= $thisDeposit->getCountChecks() + $thisDeposit->getCountCash(); ?></div>
            <div class="stat-label"><?= gettext('Total Payments'); ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Charts Section Above Payments -->
<div class="row mt-4">
  <div class="col-12">
    <div class="card card-sm">
      <div class="card-header with-border bg-light d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
          <i class="fa-solid fa-chart-bar"></i> <?= gettext('Funds'); ?>
          <small class="text-muted ms-2"><?= gettext('Click a bar to filter payments'); ?></small>
        </h5>
        <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="clearFundFilter">
          <i class="fa-solid fa-times"></i> <?= gettext('Clear Filter'); ?>
        </button>
      </div>
      <div class="card-body" style="padding: 1rem 0.75rem 1rem 1.5rem;">
        <canvas id="fund-bar"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header with-border bg-secondary text-white d-flex justify-content-between align-items-center">
    <h3 class="card-title mb-0">
      <i class="fa-solid fa-receipt"></i> <?php echo gettext('Payments'); ?> 
      <span class="badge badge-light text-dark" id="payment-count">0</span>
    </h3>
    <?php if ($iDepositSlipID && $thisDeposit->getType() && !$thisDeposit->getClosed() && ($thisDeposit->getType() == 'BankDraft' || $thisDeposit->getType() == 'CreditCard')): ?>
    <div class="btn-group" role="group">
      <button type="submit" class="btn btn-sm btn-primary" name="DepositSlipLoadAuthorized">
        <i class="fa-solid fa-sync"></i> <?= gettext('Load Authorized'); ?>
      </button>
      <button type="submit" class="btn btn-sm btn-warning" name="DepositSlipRunTransactions">
        <i class="fa-solid fa-play-circle"></i> <?= gettext('Run Transactions'); ?>
      </button>
    </div>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <div class="px-3 py-3 border-bottom bg-light">
      <!-- Subtle spacing -->
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="paymentsTable"></table>
    </div>
    <?php
    if ($iDepositSlipID && $thisDeposit->getType() && !$thisDeposit->getClosed()) {
        if ($thisDeposit->getType() == 'Bank') {
            ?>
    <div class="card-footer">
      <button type="button" id="deleteSelectedRows" class="btn btn-sm btn-danger" disabled>
        <i class="fa-solid fa-trash-can"></i> <?php echo gettext('Delete Selected'); ?>
      </button>
    </div>
            <?php
        }
    }
    ?>
  </div>
</div>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/DepositSlipEditor.js"></script>
<style>
.card {
    margin-bottom: 1.5rem;
    border: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: box-shadow 0.3s ease;
}
.card:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}
.card-header {
    background-color: #f8f9fa;
    border-bottom: 2px solid #e9ecef;
    padding: 1.25rem;
}
.card-header.bg-primary {
    background-color: #0d6efd !important;
    border-bottom: none;
}
#paymentsTable {
    margin-bottom: 0;
}
#paymentsTable_wrapper .dataTables_length,
#paymentsTable_wrapper .dataTables_filter {
    padding: 1rem 0.75rem;
}
#paymentsTable_wrapper .dataTables_info,
#paymentsTable_wrapper .dataTables_paginate {
    padding: 1rem 0.75rem;
}
.dt-buttons {
    padding: 1rem 0.75rem !important;
}
#paymentsTable tbody tr {
    transition: background-color 0.2s ease;
}
#paymentsTable tbody tr.paymentRow {
    cursor: pointer;
}
#paymentsTable tbody tr:hover {
    background-color: #f8f9fa;
}
#paymentsTable tbody tr.selected,
#paymentsTable tbody tr.table-active {
    background-color: #e7f3ff;
    border-left: 3px solid #0d6efd;
}
#paymentsTable tbody tr td {
    vertical-align: middle;
    padding: 0.875rem 0.75rem !important;
}
#paymentsTable thead th {
    padding: 1rem 0.75rem !important;
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
}
#paymentsTable tbody td {
    line-height: 1.6;
}
#paymentsTable .badge {
    font-size: 0.75rem;
    padding: 0.35rem 0.65rem;
    font-weight: 500;
}
.stat-card {
    padding: 1.25rem 1rem;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-radius: 0.5rem;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.stat-card:hover {
    transform: translateY(-3px);
    border-color: #dee2e6;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
.stat-value {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: 0.75rem;
    letter-spacing: -0.5px;
}
.stat-label {
    font-size: 0.8rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
}
.row-select {
    cursor: pointer;
    margin: 0;
}
code {
    background-color: #f4f4f4;
    padding: 0.2rem 0.4rem;
    border-radius: 3px;
    font-size: 0.875rem;
}
.form-label {
    font-weight: 600;
    color: #212529;
    margin-bottom: 0.5rem;
}
.form-control, .form-select {
    border: 1px solid #e0e0e0;
    transition: all 0.2s ease;
}
.form-control:focus, .form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.1);
}
.btn {
    font-weight: 600;
    transition: all 0.2s ease;
    border-radius: 0.375rem;
}
.btn-primary {
    box-shadow: 0 2px 6px rgba(13, 110, 253, 0.2);
}
.btn-primary:hover {
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
    transform: translateY(-1px);
}
</style>
<?php
  $fundLabels = [];
  $fundData = [];
  $fundBackgroundColor = [];
  
  foreach ($thisDeposit->getFundTotals() as $tmpfund) {
    $label = $tmpfund['Name'];
    $data = (float)$tmpfund['Total'];
    $backgroundColor = '#' . random_color();

    $fundLabels[] = $label;
    $fundData[] = $data;
    $fundBackgroundColor[] = $backgroundColor;
  }

?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  var depositType = '<?php echo $thisDeposit->getType(); ?>';
  var depositSlipID = <?php echo $iDepositSlipID; ?>;
  var isDepositClosed = Boolean(<?=  $thisDeposit->getClosed(); ?>);
  var fundLabels = <?= json_encode(array_values($fundLabels)) ?>;
  var fundData = <?= json_encode(array_values($fundData)) ?>;
  var fundBackgroundColor = <?= json_encode(array_values($fundBackgroundColor)) ?>;
  $(document).ready(function() {
    window.CRM.onLocalesReady(function() {
      initPaymentTable();
      initCharts(null,
                 null,
                 null,
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
  });
</script>
<?php
require_once 'Include/Footer.php';
