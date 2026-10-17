<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Deposit;
use ChurchCRM\view\PageHeader;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\Utils\CurrencyFormatter;
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
        }
    }
}

if ($noDeposit) {
    RedirectUtils::redirect('finance/deposit/search');
}

$sPageTitle = $thisDeposit->getType() . ' ' . gettext('Deposit Slip Number') . ': ' . $iDepositSlipID;
$sPageSubtitle = gettext('View and manage deposit slip payments');

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

$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Finance'), '/finance/'],
    [gettext('Deposits'), '/finance/deposit/search'],
    [gettext('Edit Deposit')],
]);
require_once __DIR__ . '/Include/Header.php';
?>
<!-- Deposit Navigation -->
<div class="deposit-slip-editor">
<div class="row mb-3">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center">
      <a href="<?= SystemURLs::getRootPath() ?>/finance/deposit/search" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left"></i><?= gettext('Back to Deposits'); ?>
      </a>
      <div class="btn-group" role="group" aria-label="<?= gettext('Deposit Navigation'); ?>">
        <a href="<?= $prevDeposit ? 'DepositSlipEditor.php?DepositSlipID=' . $prevDeposit->getId() : '#'; ?>" 
           class="btn btn-outline-primary <?= $prevDeposit ? '' : 'disabled'; ?>"
           <?= $prevDeposit ? '' : 'aria-disabled="true"'; ?>>
          <i class="fa-solid fa-chevron-left"></i><?= gettext('Previous'); ?>
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
        <div class="card-status-top bg-primary"></div>
        <div class="card-header">
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
                    <?php echo $thisDeposit->getClosed() ? '<span class="badge bg-light text-dark">Closed</span>' : '<span class="badge bg-azure-lt text-azure">Open</span>'; ?>
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
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                echo '</div>';
            }
            ?>
            <div class="row mt-4">
              <div class="col-12">
                <div class="d-flex flex-wrap align-items-center">
                  <!-- Primary Actions -->
                  <button type="submit" class="btn btn-primary me-2" name="DepositSlipSubmit">
                    <i class="fa-solid fa-floppy-disk"></i><?= gettext('Save'); ?>
                  </button>
                  <?php if (!$thisDeposit->getClosed()): ?>
                  <?php
                  $addPaymentLinkBack = '/DepositSlipEditor.php?DepositSlipID=' . $iDepositSlipID;
                  $addPaymentUrl = SystemURLs::getRootPath() . '/finance/pledge/new?type=Payment&depositId=' . $iDepositSlipID . '&linkBack=' . urlencode($addPaymentLinkBack);
                  ?>
                  <a href="<?= InputUtils::escapeAttribute($addPaymentUrl) ?>" class="btn btn-success me-2">
                    <i class="fa-solid fa-circle-plus"></i><?= gettext('Add Payment'); ?>
                  </a>
                  <?php endif; ?>
                  
                  <!-- Secondary Action -->
                  <button type="button" class="btn btn-outline-secondary ms-auto" name="DepositSlipGeneratePDF" data-deposit-id="<?= $thisDeposit->getId() ?>">
                    <i class="fa-solid fa-file-pdf"></i><?= gettext('Generate Report'); ?>
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
          <div class="stat-card stat-card-total text-center py-4 border-0 shadow-sm">
            <div class="stat-card-total-amount">
              <?= CurrencyFormatter::formatHtml($thisDeposit->getVirtualColumn('totalAmount') ?? 0); ?>
            </div>
            <div class="text-white-50 text-uppercase small fw-bold mt-2 letter-spacing-wide-lg"><?= gettext('Total Deposit'); ?></div>
          </div>
        </div>
        
        <!-- Bottom Row: 3 columns for breakdown -->
        <div class="col-md-4 mb-3">
          <div class="stat-card text-center h-100">
            <?php if ($thisDeposit->getCountCash()): ?>
            <div class="mb-3">
              <i class="fa-solid fa-money-bill fa-2x text-success mb-2"></i>
              <div class="fw-bold h5 text-success"><?= CurrencyFormatter::formatHtml($thisDeposit->getTotalCash()); ?></div>
              <div class="small text-body-secondary"><?= gettext('Cash'); ?> (<?= $thisDeposit->getCountCash(); ?>)</div>
            </div>
            <?php endif; ?>
            <?php if (!$thisDeposit->getCountCash()): ?>
            <div class="text-body-secondary small">
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
              <div class="fw-bold h5 text-info"><?= CurrencyFormatter::formatHtml($thisDeposit->getTotalChecks()); ?></div>
              <div class="small text-body-secondary"><?= gettext('Checks'); ?> (<?= $thisDeposit->getCountChecks(); ?>)</div>
            </div>
            <?php endif; ?>
            <?php if (!$thisDeposit->getCountChecks()): ?>
            <div class="text-body-secondary small">
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
            <div class="stat-value stat-value-lg text-dark"><?= (int) ($thisDeposit->getCountChecks() + $thisDeposit->getCountCash()) ?></div>
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
    <div class="card-sm">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
          <i class="fa-solid fa-chart-bar"></i> <?= gettext('Funds'); ?>
          <small class="text-body-secondary ms-2"><?= gettext('Click a bar to filter payments'); ?></small>
        </h5>
        <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="clearFundFilter">
          <i class="fa-solid fa-xmark"></i><?= gettext('Clear Filter'); ?>
        </button>
      </div>
      <div class="card-body fund-chart-body">
        <div id="fund-bar"></div>
      </div>
    </div>
  </div>
</div>

<div class="card mt-3">
  <div class="card-status-top bg-secondary"></div>
  <div class="card-header d-flex justify-content-between align-items-center">
    <h3 class="card-title mb-0">
      <i class="fa-solid fa-receipt"></i> <?php echo gettext('Payments'); ?>
      <span class="badge bg-blue-lt text-blue" id="payment-count">0</span>
    </h3>
    <div class="d-flex gap-2">
      <?php if ($iDepositSlipID && $thisDeposit->getType() === 'Bank' && !$thisDeposit->getClosed()): ?>
        <button type="button" id="deleteSelectedRows" class="btn btn-sm btn-danger" disabled>
          <i class="fa-solid fa-trash-can"></i><?php echo gettext('Delete Selected'); ?>
        </button>
      <?php endif; ?>
      <?php if ($iDepositSlipID && $thisDeposit->getType() && !$thisDeposit->getClosed() && ($thisDeposit->getType() == 'BankDraft' || $thisDeposit->getType() == 'CreditCard')): ?>
      <div class="btn-group" role="group">
        <button type="submit" class="btn btn-sm btn-primary" name="DepositSlipLoadAuthorized">
          <i class="fa-solid fa-arrows-rotate"></i><?= gettext('Load Authorized'); ?>
        </button>
        <button type="submit" class="btn btn-sm btn-warning" name="DepositSlipRunTransactions">
          <i class="fa-solid fa-play-circle"></i><?= gettext('Run Transactions'); ?>
        </button>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-clip-x">
      <table class="table table-hover mb-0" id="paymentsTable"></table>
    </div>
  </div>
</div>

</div>
<script src="<?= SystemURLs::assetVersioned('/skin/js/DepositSlipEditor.js') ?>"></script>
<?php
  $fundLabels = [];
  $fundData = [];

  foreach ($thisDeposit->getFundTotals() as $tmpfund) {
    $fundLabels[] = $tmpfund['Name'];
    $fundData[] = (float)$tmpfund['Total'];
  }

?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  var depositType = '<?php echo $thisDeposit->getType(); ?>';
  var depositSlipID = <?php echo $iDepositSlipID; ?>;
  var isDepositClosed = <?= $thisDeposit->getClosed() ? 'true' : 'false' ?>;
  var fundLabels = <?= InputUtils::jsonEncodeForScript(array_values($fundLabels)) ?>;
  var fundData = <?= InputUtils::jsonEncodeForScript(array_values($fundData)) ?>;
  $(document).ready(function() {
    window.CRM.onLocalesReady(function() {
      initPaymentTable();
      initCharts(null, null, fundLabels, fundData);
      initDepositSlipEditor();

      $('#deleteSelectedRows').click(function() {
        var deletedRows = dataT.rows('.selected').data();
        bootbox.confirm({
          title:'<?= gettext("Confirm Delete")?>',
          message: '<p><?= gettext("Are you sure you want to delete the selected")?> ' + deletedRows.length + ' <?= gettext("payments?") ?></p>' +
          '<p><?= gettext("This action CANNOT be undone, and may have legal implications!") ?></p>'+
          '<p><?= gettext("Please ensure this what you want to do.") ?></p>',
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
require_once __DIR__ . '/Include/Footer.php';
