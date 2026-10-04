<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\Utils\InputUtils;

/**
 * Variables injected by the deposits.php route handler:
 *
 * @var string                                  $sRootPath
 * @var string                                  $sPageTitle
 * @var string                                  $sPageSubtitle
 * @var array                                   $aBreadcrumbs
 * @var \ChurchCRM\model\ChurchCRM\Deposit      $deposit
 * @var int                                     $depositId
 * @var \ChurchCRM\model\ChurchCRM\Deposit|null $prevDeposit
 * @var \ChurchCRM\model\ChurchCRM\Deposit|null $nextDeposit
 * @var string[]                                $fundLabels
 * @var float[]                                 $fundData
 */

$isClosed = (bool) $deposit->getClosed();
$type = (string) $deposit->getType();
$canDelete = $type === 'Bank' && !$isClosed;
$cashCount = (int) $deposit->getCountCash();
$checkCount = (int) $deposit->getCountChecks();
$paymentCount = $cashCount + $checkCount;

$addPaymentUrl = $sRootPath . '/finance/pledge/new?type=Payment&depositId=' . $depositId
    . '&linkBack=' . urlencode('/finance/deposit/' . $depositId);

ob_start();
?>
<div class="btn-list">
  <a href="<?= InputUtils::escapeAttribute($prevDeposit ? $sRootPath . '/finance/deposit/' . $prevDeposit->getId() : '#') ?>"
     class="btn btn-sm btn-outline-secondary<?= $prevDeposit ? '' : ' disabled' ?>"<?= $prevDeposit ? '' : ' aria-disabled="true" tabindex="-1"' ?>>
    <i class="fa-solid fa-chevron-left me-1"></i><?= gettext('Previous') ?>
  </a>
  <a href="<?= InputUtils::escapeAttribute($nextDeposit ? $sRootPath . '/finance/deposit/' . $nextDeposit->getId() : '#') ?>"
     class="btn btn-sm btn-outline-secondary<?= $nextDeposit ? '' : ' disabled' ?>"<?= $nextDeposit ? '' : ' aria-disabled="true" tabindex="-1"' ?>>
    <?= gettext('Next') ?><i class="fa-solid fa-chevron-right ms-1"></i>
  </a>
</div>
<?php
$sPageHeaderButtons = ob_get_clean();

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="container-xl">

  <div class="row row-cards mb-3">
    <div class="col-6 col-lg-3">
      <div class="card card-sm">
        <div class="card-body">
          <div class="row align-items-center">
            <div class="col-auto">
              <span class="bg-primary text-white avatar rounded-circle"><i class="fa-solid fa-file-invoice-dollar icon"></i></span>
            </div>
            <div class="col">
              <div class="fw-medium"><?= CurrencyFormatter::formatHtml($deposit->getVirtualColumn('totalAmount') ?? 0) ?></div>
              <div class="text-body-secondary"><?= gettext('Total Deposit') ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card card-sm">
        <div class="card-body">
          <div class="row align-items-center">
            <div class="col-auto">
              <span class="bg-success text-white avatar rounded-circle"><i class="fa-solid fa-money-bill icon"></i></span>
            </div>
            <div class="col">
              <div class="fw-medium"><?= CurrencyFormatter::formatHtml($cashCount > 0 ? $deposit->getTotalCash() : 0) ?></div>
              <div class="text-body-secondary"><?= gettext('Cash') ?> (<?= $cashCount ?>)</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card card-sm">
        <div class="card-body">
          <div class="row align-items-center">
            <div class="col-auto">
              <span class="bg-info text-white avatar rounded-circle"><i class="fa-solid fa-money-check icon"></i></span>
            </div>
            <div class="col">
              <div class="fw-medium"><?= CurrencyFormatter::formatHtml($checkCount > 0 ? $deposit->getTotalChecks() : 0) ?></div>
              <div class="text-body-secondary"><?= gettext('Checks') ?> (<?= $checkCount ?>)</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card card-sm">
        <div class="card-body">
          <div class="row align-items-center">
            <div class="col-auto">
              <span class="bg-secondary text-white avatar rounded-circle"><i class="fa-solid fa-receipt icon"></i></span>
            </div>
            <div class="col">
              <div class="fw-medium"><?= $paymentCount ?></div>
              <div class="text-body-secondary"><?= gettext('Total Payments') ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row row-cards mb-3">
    <div class="col-lg-5">
      <div class="card h-100">
        <div class="card-header">
          <h3 class="card-title"><i class="fa-solid fa-file-invoice-dollar me-2"></i><?= gettext('Deposit Details') ?></h3>
          <div class="card-actions">
            <?php if ($isClosed) : ?>
            <span class="badge bg-secondary-lt text-secondary"><?= gettext('Closed') ?></span>
            <?php else : ?>
            <span class="badge bg-azure-lt text-azure"><?= gettext('Open') ?></span>
            <?php endif; ?>
          </div>
        </div>
        <form id="DepositSlipEditor" name="DepositSlipEditor">
          <div class="card-body">
            <div class="mb-3">
              <label for="DepositDate" class="form-label required"><?= gettext('Date') ?></label>
              <input type="date" class="form-control" id="DepositDate" name="Date" value="<?= InputUtils::escapeAttribute($deposit->getDate('Y-m-d')) ?>" required>
            </div>
            <div class="mb-3">
              <label for="Comment" class="form-label"><?= gettext('Comment') ?></label>
              <textarea class="form-control" name="Comment" id="Comment" rows="3" placeholder="<?= InputUtils::escapeAttribute(gettext('Add any additional notes about this deposit')) ?>"><?= InputUtils::escapeHTML($deposit->getComment()) ?></textarea>
            </div>
            <label class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" name="Closed" id="Closed" value="1"<?= $isClosed ? ' checked' : '' ?>>
              <span class="form-check-label"><?= gettext('Closed') ?></span>
            </label>
            <?php if ($type === 'BankDraft' || $type === 'CreditCard') : ?>
            <div class="alert alert-warning mt-3 mb-0" role="alert">
              <i class="fa-solid fa-triangle-exclamation me-1"></i><?= gettext('Important: Failed transactions will be deleted permanently when the deposit slip is closed.') ?>
            </div>
            <?php endif; ?>
          </div>
          <div class="card-footer d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary" id="saveDeposit">
              <i class="fa-solid fa-floppy-disk me-1"></i><?= gettext('Save') ?>
            </button>
            <?php if (!$isClosed) : ?>
            <a href="<?= InputUtils::escapeAttribute($addPaymentUrl) ?>" class="btn btn-success">
              <i class="fa-solid fa-circle-plus me-1"></i><?= gettext('Add Payment') ?>
            </a>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-secondary ms-auto" id="generateDepositReport" data-deposit-id="<?= $depositId ?>">
              <i class="fa-solid fa-file-pdf me-1"></i><?= gettext('Generate Report') ?>
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card h-100">
        <div class="card-header">
          <h3 class="card-title"><i class="fa-solid fa-chart-bar me-2"></i><?= gettext('Funds') ?></h3>
          <div class="card-actions">
            <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="clearFundFilter">
              <i class="fa-solid fa-xmark me-1"></i><?= gettext('Clear Filter') ?>
            </button>
          </div>
        </div>
        <div class="card-body">
          <?php if ($fundLabels === []) : ?>
          <div class="empty">
            <p class="empty-title"><?= gettext('No payments yet') ?></p>
            <p class="empty-subtitle text-body-secondary"><?= gettext('Fund totals appear here once payments are added.') ?></p>
          </div>
          <?php else : ?>
          <div class="text-body-secondary small mb-2"><?= gettext('Click a bar to filter payments') ?></div>
          <?php endif; ?>
          <div id="fund-bar"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <h3 class="card-title">
        <i class="fa-solid fa-receipt me-2"></i><?= gettext('Payments') ?>
        <span class="badge bg-blue-lt text-blue ms-2" id="payment-count"><?= $paymentCount ?></span>
      </h3>
      <?php if ($canDelete) : ?>
      <div class="card-actions">
        <button type="button" id="deleteSelectedRows" class="btn btn-sm btn-danger" disabled>
          <i class="fa-solid fa-trash-can me-1"></i><?= gettext('Delete Selected') ?>
        </button>
      </div>
      <?php endif; ?>
    </div>
    <div style="overflow-x: clip; overflow-y: visible;">
      <table class="table table-vcenter table-hover card-table" id="paymentsTable"></table>
    </div>
  </div>

</div><!-- /.container-xl -->

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  window.depositEditorConfig = <?= InputUtils::jsonEncodeForScript([
      'depositId' => $depositId,
      'depositType' => $type,
      'isClosed' => $isClosed,
      'canDelete' => $canDelete,
      'fundLabels' => array_values($fundLabels),
      'fundData' => array_values($fundData),
  ]) ?>;
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/finance-deposit-editor.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
