<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\Utils\InputUtils;

/**
 * Variables injected by deposits.php route handler:
 *
 * @var string   $sRootPath
 * @var string   $sPageTitle
 * @var string   $sPageSubtitle
 * @var array    $aBreadcrumbs
 * @var array    $deposits       Array of deposit rows from DepositService::searchDeposits()
 * @var \Propel\Runtime\Collection\ObjectCollection $funds  Active DonationFund objects
 * @var array    $tellerList     [personId => 'First Last']
 * @var array    $filters        Active filter values [key => value]
 */

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="container-xl">

  <!-- New Deposit Modal -->
  <div class="modal fade" id="newDepositModal" tabindex="-1" aria-labelledby="newDepositModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="newDepositModalLabel"><?= gettext('Add New Deposit') ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="depositComment" class="form-label"><?= gettext('Deposit Comment') ?></label>
            <input type="text" class="form-control" id="depositComment" name="depositComment">
          </div>
          <div class="mb-3">
            <label for="depositType" class="form-label"><?= gettext('Deposit Type') ?></label>
            <select class="form-select" id="depositType" name="depositType">
              <option value="Bank" selected><?= gettext('Bank') ?></option>
              <option value="CreditCard"><?= gettext('Credit Card') ?></option>
              <option value="BankDraft"><?= gettext('Bank Draft') ?></option>
            </select>
          </div>
          <div class="mb-3">
            <label for="depositDate" class="form-label"><?= gettext('Deposit Date') ?></label>
            <input type="date" class="form-control" id="depositDate" name="depositDate">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
          <button type="button" class="btn btn-primary" id="addNewDeposit"><?= gettext('Add New Deposit') ?></button>
        </div>
      </div>
    </div>
  </div>

  <!-- Search/Filter Form -->
  <div class="card mb-3">
    <div class="card-header">
      <h3 class="card-title"><?= gettext('Search Deposits') ?></h3>
      <div class="card-options">
        <a href="#" class="card-options-collapse" data-bs-toggle="collapse" data-bs-target="#depositSearchForm">
          <i class="ti ti-chevron-up"></i>
        </a>
      </div>
    </div>
    <div id="depositSearchForm" class="collapse show">
      <div class="card-body">
        <form id="depositFilterForm" method="get" action="<?= InputUtils::escapeAttribute($sRootPath) ?>/finance/deposit/search">
          <div class="row g-3">
            <div class="col-md-3">
              <label for="dateStart" class="form-label"><?= gettext('From Date') ?></label>
              <input type="date" class="form-control" id="dateStart" name="dateStart"
                     value="<?= InputUtils::escapeAttribute($filters['dateStart']) ?>">
            </div>
            <div class="col-md-3">
              <label for="dateEnd" class="form-label"><?= gettext('To Date') ?></label>
              <input type="date" class="form-control" id="dateEnd" name="dateEnd"
                     value="<?= InputUtils::escapeAttribute($filters['dateEnd']) ?>">
            </div>
            <div class="col-md-2">
              <label for="depositId" class="form-label"><?= gettext('Deposit ID') ?></label>
              <input type="number" class="form-control" id="depositId" name="depositId"
                     min="1" value="<?= InputUtils::escapeAttribute($filters['depositId']) ?>">
            </div>
            <div class="col-md-2">
              <label for="amountMin" class="form-label"><?= gettext('Amount Min') ?></label>
              <input type="number" class="form-control" id="amountMin" name="amountMin"
                     min="0" step="0.01" value="<?= InputUtils::escapeAttribute($filters['amountMin']) ?>">
            </div>
            <div class="col-md-2">
              <label for="amountMax" class="form-label"><?= gettext('Amount Max') ?></label>
              <input type="number" class="form-control" id="amountMax" name="amountMax"
                     min="0" step="0.01" value="<?= InputUtils::escapeAttribute($filters['amountMax']) ?>">
            </div>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-md-3">
              <label for="fundId" class="form-label"><?= gettext('Fund') ?></label>
              <select class="form-select" id="fundId" name="fundId">
                <option value=""><?= gettext('All Funds') ?></option>
                <?php foreach ($funds as $fund) : ?>
                  <option value="<?= (int) $fund->getId() ?>"
                    <?= ((int) $filters['fundId'] === (int) $fund->getId()) ? 'selected' : '' ?>>
                    <?= InputUtils::escapeHTML($fund->getName()) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label for="closed" class="form-label"><?= gettext('Status') ?></label>
              <select class="form-select" id="closed" name="closed">
                <option value="" <?= $filters['closed'] === '' ? 'selected' : '' ?>><?= gettext('All') ?></option>
                <option value="0" <?= $filters['closed'] === '0' ? 'selected' : '' ?>><?= gettext('Open') ?></option>
                <option value="1" <?= $filters['closed'] === '1' ? 'selected' : '' ?>><?= gettext('Closed') ?></option>
              </select>
            </div>
            <div class="col-md-3">
              <label for="enteredBy" class="form-label"><?= gettext('Teller') ?></label>
              <select class="form-select" id="enteredBy" name="enteredBy">
                <option value=""><?= gettext('All Tellers') ?></option>
                <?php foreach ($tellerList as $tellerId => $tellerName) : ?>
                  <option value="<?= (int) $tellerId ?>"
                    <?= ((int) $filters['enteredBy'] === (int) $tellerId) ? 'selected' : '' ?>>
                    <?= InputUtils::escapeHTML($tellerName) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 d-flex align-items-end gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="ti ti-search me-1"></i><?= gettext('Search') ?>
              </button>
              <a href="<?= InputUtils::escapeAttribute($sRootPath) ?>/finance/deposit/search" class="btn btn-secondary">
                <i class="ti ti-x me-1"></i><?= gettext('Clear') ?>
              </a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Results Table -->
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <h3 class="card-title"><?= gettext('Deposits') ?></h3>
      <div class="card-options d-flex align-items-center gap-2">
        <span id="selectedCount" class="badge bg-blue me-2" style="display:none!important"></span>
        <button type="button" id="btnSelectAll" class="btn btn-sm btn-outline-secondary">
          <?= gettext('Select All') ?>
        </button>
        <button type="button" id="btnDeleteSelected" class="btn btn-sm btn-danger" disabled>
          <i class="ti ti-trash me-1"></i><?= gettext('Delete') ?>
        </button>
        <button type="button" id="btnExportCSV" class="btn btn-sm btn-success" disabled data-export-type="csv">
          <i class="ti ti-download me-1"></i><?= gettext('CSV') ?>
        </button>
        <button type="button" id="btnExportOFX" class="btn btn-sm btn-success" disabled data-export-type="ofx">
          <i class="ti ti-download me-1"></i><?= gettext('OFX') ?>
        </button>
        <button type="button" id="btnExportPDF" class="btn btn-sm btn-success" disabled data-export-type="pdf">
          <i class="ti ti-file-type-pdf me-1"></i><?= gettext('PDF') ?>
        </button>
        <a href="#" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newDepositModal">
          <i class="ti ti-plus me-1"></i><?= gettext('New Deposit') ?>
        </a>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table id="depositsTable" class="table table-hover table-vcenter card-table data-table">
          <thead>
            <tr>
              <th class="w-1">
                <input type="checkbox" id="selectAllCheckbox" class="form-check-input">
              </th>
              <th><?= gettext('ID') ?></th>
              <th><?= gettext('Date') ?></th>
              <th><?= gettext('Type') ?></th>
              <th><?= gettext('Comment') ?></th>
              <th>
                <?php if (!empty($filters['fundId'])) : ?>
                  <?= gettext('Fund Total') ?> <span class="badge bg-orange ms-1" title="<?= InputUtils::escapeAttribute(gettext('Showing total for selected fund only')) ?>"><?= gettext('Fund') ?></span>
                <?php else : ?>
                  <?= gettext('Total') ?>
                <?php endif; ?>
              </th>
              <th><?= gettext('Status') ?></th>
              <th><?= gettext('Teller') ?></th>
              <th class="w-1"><?= gettext('Actions') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($deposits as $deposit) : ?>
              <?php
                $depositId    = (int) ($deposit['Id'] ?? 0);
                $depositDate  = InputUtils::escapeHTML($deposit['Date'] ?? '');
                $depositType  = InputUtils::escapeHTML($deposit['Type'] ?? '');
                $depositComm  = InputUtils::escapeHTML($deposit['Comment'] ?? '');
                $totalAmount  = CurrencyFormatter::formatHtml($deposit['totalAmount'] ?? 0);
                $isClosed     = (bool) ($deposit['Closed'] ?? false);
                $tellerName   = InputUtils::escapeHTML($deposit['tellerName'] ?? '');
                $editUrl      = InputUtils::escapeAttribute($sRootPath . '/DepositSlipEditor.php?DepositSlipID=' . $depositId);
                $addPayUrl    = InputUtils::escapeAttribute(
                    $sRootPath . '/finance/pledge/new?type=Payment&depositId=' . $depositId
                    . '&linkBack=' . urlencode('/finance/deposit/search')
                );
              ?>
              <tr data-deposit-id="<?= $depositId ?>">
                <td>
                  <input type="checkbox" class="form-check-input row-select"
                         data-deposit-id="<?= $depositId ?>">
                </td>
                <td><?= $depositId ?></td>
                <td><?= $depositDate ?></td>
                <td><?= $depositType ?></td>
                <td><?= $depositComm ?></td>
                <td data-order="<?= htmlspecialchars((string) ($deposit['totalAmount'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"><?= $totalAmount ?></td>
                <td>
                  <?php if ($isClosed) : ?>
                    <span class="badge bg-secondary"><?= gettext('Closed') ?></span>
                  <?php else : ?>
                    <span class="badge bg-success"><?= gettext('Open') ?></span>
                  <?php endif; ?>
                </td>
                <td><?= $tellerName ?></td>
                <td>
                  <div class="dropdown">
                    <button class="btn btn-sm btn-ghost-secondary" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                      <i class="ti ti-dots-vertical"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                      <a class="dropdown-item" href="<?= $editUrl ?>">
                        <i class="ti ti-eye me-2"></i><?= gettext('View') ?>
                      </a>
                      <a class="dropdown-item" href="<?= $addPayUrl ?>">
                        <i class="ti ti-plus me-2"></i><?= gettext('Add Payment') ?>
                      </a>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div><!-- /.container-xl -->

<script src="<?= SystemURLs::assetVersioned('/skin/js/finance-deposit-search.js') ?>"></script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
