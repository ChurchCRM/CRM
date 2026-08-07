<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

$iDepositSlipID = $_SESSION['iCurrentDeposit'];

$sPageTitle = gettext('Deposit Listing');
$sPageSubtitle = gettext('Search and view deposit slip records');

// Security: User must have finance permission to use this form
if (!AuthenticationManager::getCurrentUser()->isFinanceEnabled()) {
    RedirectUtils::redirect('index.php');
}

// Fiscal Year selector data
$financialService   = new FinancialService();
$currentFyid        = FiscalYearUtils::getCurrentFiscalYearId();
$availableYears     = $financialService->getAvailableDepositFiscalYears();
// Selected FY: from GET param; 0 = current FY (default)
$selectedFyid       = isset($_GET['fyid']) ? (int) $_GET['fyid'] : $currentFyid;

$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Finance'), '/finance/'],
    [gettext('Deposits')],
]);
require_once __DIR__ . '/Include/Header.php';
?>

<div class="card">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title"><?php echo gettext('Add New Deposit') . ': '; ?></h3>
  </div>
  <div class="card-body">
    <form action="#" method="get" class="form">
      <div class="row">
        <div class="container-fluid">
          <div class="col-lg-4">
            <label for="depositComment"><?= gettext('Deposit Comment') ?></label>
            <input class="form-control newDeposit w-100" name="depositComment" id="depositComment">
          </div>
          <div class="col-lg-3">
            <label for="depositType"><?= gettext('Deposit Type') ?></label>
            <select class="form-select" id="depositType" name="depositType">
              <option value="Bank" selected><?= gettext('Bank') ?></option>
              <option value="CreditCard">Credit Card</option>
              <option value="BankDraft">Bank Draft</option>
            </select>
          </div>
          <div class="col-lg-3">
            <label for="depositDate"><?= gettext('Deposit Date') ?></label>
            <input class="form-control w-100 date-picker" name="depositDate" id="depositDate">
          </div>
        </div>
      </div>
      <p>
      <div class="row">
        <div class="col-3">
          <button type="button" class="btn btn-primary" id="addNewDeposit"><?= gettext('Add New Deposit') ?></button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title"><?php echo gettext('Deposits') . ': '; ?></h3>
    <!-- Fiscal Year filter -->
    <div class="ms-3 d-inline-flex align-items-center gap-2">
      <label for="deposit-slip-fyid" class="form-label mb-0 small text-body-secondary fw-semibold"><?= gettext('Fiscal Year') ?>:</label>
      <select id="deposit-slip-fyid" class="form-select form-select-sm" style="width: auto;">
        <option value="0"><?= gettext('All Time') ?></option>
        <?php foreach ($availableYears as $year): ?>
        <option value="<?= (int) $year['id'] ?>" <?= (int) $year['id'] === $selectedFyid ? 'selected' : '' ?>>
          <?= InputUtils::escapeHTML($year['label']) ?>
          <?php if ((int) $year['id'] === $currentFyid): ?> (<?= gettext('Current') ?>)<?php endif; ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="card-body">
    <div class="container-fluid">
      <table class="display responsive text-nowrap data-table table table-hover" id="depositsTable" width="100%"></table>

      <button type="button" id="deleteSelectedRows" class="btn btn-danger"
              disabled> <?= gettext('Delete Selected Rows') ?> </button>
      <button type="button" id="exportSelectedRows" class="btn btn-success exportButton" data-exportType="ofx"
              disabled><i class="fa-solid fa-download"></i><?= gettext('Export Selected Rows (OFX)') ?></button>
      <button type="button" id="exportSelectedRowsCSV" class="btn btn-success exportButton" data-exportType="csv"
              disabled><i class="fa-solid fa-download"></i><?= gettext('Export Selected Rows (CSV)') ?></button>
      <button type="button" id="generateDepositSlip" class="btn btn-success exportButton" data-exportType="pdf"
              disabled> <?= gettext('Generate Deposit Slip for Selected Rows (PDF)') ?></button>
    </div>
  </div>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/js/FindDepositSlip.js') ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  window.CRM.depositCurrentFyid = <?= (int) $currentFyid ?>;
  window.CRM.depositSelectedFyid = <?= htmlspecialchars((string) $selectedFyid, ENT_QUOTES, 'UTF-8') ?>;
</script>
<?php
require_once __DIR__ . '/Include/Footer.php';
