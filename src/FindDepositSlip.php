<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

$iDepositSlipID = $_SESSION['iCurrentDeposit'];

$sPageTitle = gettext('Deposit Listing');
$sPageSubtitle = gettext('Search and view deposit slip records');

// Security: User must have finance permission to use this form
if (!AuthenticationManager::getCurrentUser()->isFinanceEnabled()) {
    RedirectUtils::redirect('index.php');
}

$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Finance'), '/finance/'],
    [gettext('Deposits')],
]);
require_once __DIR__ . '/Include/Header.php';
?>

<div class="card">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title"><?php echo gettext('Add New') . ' ' . gettext('Deposit') . ': '; ?></h3>
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
          <button type="button" class="btn btn-primary" id="addNewDeposit"><?= gettext('Add New') . ' ' . gettext('Deposit') ?></button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title"><?php echo gettext('Deposits') . ': '; ?></h3>
  </div>
  <div class="card-body">
    <div class="container-fluid">
      <table class="display responsive text-nowrap data-table table table-hover" id="depositsTable" width="100%"></table>

      <button type="button" id="deleteSelectedRows" class="btn btn-danger"
              disabled> <?= gettext('Delete Selected Rows') ?> </button>
      <button type="button" id="exportSelectedRows" class="btn btn-success exportButton" data-exportType="ofx"
              disabled><i class="fa-solid fa-download"></i> <?= gettext('Export Selected Rows (OFX)') ?></button>
      <button type="button" id="exportSelectedRowsCSV" class="btn btn-success exportButton" data-exportType="csv"
              disabled><i class="fa-solid fa-download"></i> <?= gettext('Export Selected Rows (CSV)') ?></button>
      <button type="button" id="generateDepositSlip" class="btn btn-success exportButton" data-exportType="pdf"
              disabled> <?= gettext('Generate Deposit Slip for Selected Rows (PDF)') ?></button>
    </div>
  </div>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/js/FindDepositSlip.js') ?>"></script>
<?php
require_once __DIR__ . '/Include/Footer.php';
