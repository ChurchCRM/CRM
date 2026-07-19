<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\Utils\InputUtils;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();
require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Stat widgets
$activeCount    = (int) ($widgetStats['activeCount']    ?? 0);
$raisedThisYear = (float) ($widgetStats['raisedThisYear'] ?? 0);
$itemsThisYear  = (int) ($widgetStats['itemsThisYear']  ?? 0);
$buyersThisYear = (int) ($widgetStats['buyersThisYear'] ?? 0);

// Helpers (assigned as closures to avoid global function redeclaration errors)
$frDateRange = static function (\ChurchCRM\model\ChurchCRM\FundRaiser $fr, string $fmt): string {
    $start = $fr->getDate();
    $end   = $fr->getEndDate();
    if ($start === null) {
        return '';
    }
    $startStr = $start->format($fmt);
    if ($end === null || $end == $start) {
        return $startStr;
    }
    return $startStr . ' – ' . $end->format($fmt);
};

$frStatusBadgeClass = static function (string $status): string {
    return match ($status) {
        'Active'   => 'badge bg-green-lt text-green',
        'Planning' => 'badge bg-azure-lt text-azure',
        default    => 'badge bg-secondary-lt',
    };
};

// Bid sheets are a silent-auction bidding artifact — not applicable to other types.
$frShowBidSheets = static fn (string $type): bool => in_array($type, ['Auction', 'Silent Auction'], true);
// Catalog/Certificates are pre-event browsable bid-display artifacts — not applicable
// to a raffle drawing.
$frShowItemCatalog = static fn (string $type): bool => $type !== 'Raffle';

// When a status filter is applied, the top table holds every matching
// fundraiser regardless of archive state (see fundraiser.php's $isArchived
// gate), so the header must say so instead of always claiming "Active".
$fundraiserTableTitle = $filterStatus !== ''
    ? sprintf(gettext('%s Fundraisers'), gettext($filterStatus))
    : gettext('Active Fundraisers');
?>

<!-- ==================== STAT WIDGETS ==================== -->
<div class="row mb-3">
  <div class="col-6 col-lg-3">
    <div class="card card-sm">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-auto">
            <span class="bg-primary text-white avatar rounded-circle">
              <i class="fa-solid fa-gavel icon"></i>
            </span>
          </div>
          <div class="col">
            <div class="fw-medium"><?= $activeCount ?></div>
            <div class="text-body-secondary"><?= gettext('Active Fundraisers') ?></div>
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
            <span class="bg-success text-white avatar rounded-circle">
              <i class="fa-solid fa-hand-holding-dollar icon"></i>
            </span>
          </div>
          <div class="col">
            <div class="fw-medium"><?= CurrencyFormatter::formatHtml($raisedThisYear) ?></div>
            <div class="text-body-secondary"><?= gettext('Raised This Year') ?></div>
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
            <span class="bg-info text-white avatar rounded-circle">
              <i class="fa-solid fa-box-open icon"></i>
            </span>
          </div>
          <div class="col">
            <div class="fw-medium"><?= number_format($itemsThisYear) ?></div>
            <div class="text-body-secondary"><?= gettext('Items Donated (This Year)') ?></div>
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
            <span class="bg-warning text-white avatar rounded-circle">
              <i class="fa-solid fa-users icon"></i>
            </span>
          </div>
          <div class="col">
            <div class="fw-medium"><?= number_format($buyersThisYear) ?></div>
            <div class="text-body-secondary"><?= gettext('Buyers This Year') ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ==================== FILTER CARD ==================== -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form id="fundraiserFilterForm" method="get" action="<?= $sRootPath ?>/fundraiser/">
      <div class="row g-2 align-items-end">
        <div class="col-auto">
          <label class="form-label mb-1" for="filterStatus"><?= gettext('Status') ?></label>
          <select name="filterStatus" id="filterStatus" class="form-select form-select-sm">
            <option value=""><?= gettext('All Statuses') ?></option>
            <option value="Planning" <?= $filterStatus === 'Planning' ? 'selected' : '' ?>><?= gettext('Planning') ?></option>
            <option value="Active"   <?= $filterStatus === 'Active'   ? 'selected' : '' ?>><?= gettext('Active') ?></option>
            <option value="Closed"   <?= $filterStatus === 'Closed'   ? 'selected' : '' ?>><?= gettext('Closed') ?></option>
          </select>
        </div>
        <div class="col-auto">
          <label class="form-label mb-1" for="filterType"><?= gettext('Type') ?></label>
          <select name="filterType" id="filterType" class="form-select form-select-sm">
            <option value=""><?= gettext('All Types') ?></option>
            <?php foreach (['Auction', 'Silent Auction', 'Live Auction', 'Raffle', 'Gala', 'Mixed'] as $t): ?>
            <option value="<?= InputUtils::escapeAttribute($t) ?>" <?= $filterType === $t ? 'selected' : '' ?>><?= InputUtils::escapeHTML(gettext($t)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto">
          <label class="form-label mb-1" for="dateStart"><?= gettext('Date Start') ?></label>
          <input type="text" class="form-control form-control-sm date-picker" name="dateStart" id="dateStart" maxlength="10" value="<?= InputUtils::escapeAttribute($dateStart) ?>">
        </div>
        <div class="col-auto">
          <label class="form-label mb-1" for="dateEnd"><?= gettext('Date End') ?></label>
          <input type="text" class="form-control form-control-sm date-picker" name="dateEnd" id="dateEnd" maxlength="10" value="<?= InputUtils::escapeAttribute($dateEnd) ?>">
        </div>
        <div class="col-auto">
          <input type="submit" class="btn btn-sm btn-primary" value="<?= gettext('Apply') ?>">
          <a href="<?= $sRootPath ?>/fundraiser/" class="btn btn-sm btn-secondary ms-1"><?= gettext('Clear') ?></a>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ==================== ACTIVE FUNDRAISERS ==================== -->
<div class="card mb-3">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title"><?= InputUtils::escapeHTML($fundraiserTableTitle) ?></h3>
    <a href="<?= $sRootPath ?>/fundraiser/editor" class="btn btn-primary btn-sm ms-auto">
      <i class="ti ti-plus me-1"></i><?= gettext('Add Fundraiser') ?>
    </a>
  </div>
  <div class="card-body p-0">
    <div style="overflow: visible;">
      <table id="fundraisers" class="table table-hover table-vcenter mb-0 w-100">
        <thead>
          <tr>
            <th><?= gettext('Title') ?></th>
            <th><?= gettext('Type') ?></th>
            <th><?= gettext('Date') ?></th>
            <th><?= gettext('Status') ?></th>
            <th class="text-end"><?= gettext('Items') ?></th>
            <th class="text-end"><?= gettext('Raised') ?></th>
            <th><?= gettext('Goal %') ?></th>
            <th class="text-end"><?= gettext('Buyers') ?></th>
            <th class="text-center w-1 no-export"><?= gettext('Actions') ?></th>
          </tr>
        </thead>
        <tbody>
        <?php
        $csrfFundraiserDeleteField = \ChurchCRM\Utils\CSRFUtils::getTokenInputField('fundraiser_delete');
        ?>
        <?php foreach ($activeFundraisers as $fundraiser):
            $frId    = $fundraiser->getId();
            $summary = $summaries[$frId] ?? ['items' => 0, 'raised' => 0.0, 'est' => 0.0, 'buyers' => 0];
            $raised  = (float) $summary['raised'];
            $goal    = $fundraiser->getGoalAmount() !== null ? (float) $fundraiser->getGoalAmount() : null;
            $goalPct = ($goal !== null && $goal > 0) ? min(round(($raised / $goal) * 100), 100) : null;
            $frType  = $fundraiser->getType() ?: 'Auction';
            $status  = $fundraiser->getStatus() ?: 'Active';
        ?>
          <tr>
            <td class="fw-medium">
              <a href="<?= $sRootPath ?>/fundraiser/view/<?= $frId ?>" class="text-reset text-decoration-none">
                <?= InputUtils::escapeHTML($fundraiser->getTitle()) ?>
              </a>
            </td>
            <td><span class="badge bg-azure-lt"><?= InputUtils::escapeHTML(gettext($frType)) ?></span></td>
            <td><?= InputUtils::escapeHTML($frDateRange($fundraiser, $sDateFormat)) ?></td>
            <td><span class="<?= $frStatusBadgeClass($status) ?>"><?= InputUtils::escapeHTML(gettext($status)) ?></span></td>
            <td class="text-end"><?= (int) $summary['items'] ?></td>
            <td class="text-end"><?= CurrencyFormatter::formatHtml($raised) ?></td>
            <td style="min-width:80px">
              <?php if ($goalPct !== null): ?>
              <div class="progress" style="height:8px" title="<?= $goalPct ?>%">
                <div class="progress-bar bg-success" role="progressbar" style="width:<?= $goalPct ?>%" aria-valuenow="<?= $goalPct ?>" aria-valuemin="0" aria-valuemax="100"></div>
              </div>
              <small class="text-body-secondary"><?= $goalPct ?>%</small>
              <?php else: ?>
              <span class="text-body-secondary">—</span>
              <?php endif; ?>
            </td>
            <td class="text-end"><?= (int) $summary['buyers'] ?></td>
            <td class="text-center w-1">
              <div class="dropdown">
                <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                  <i class="ti ti-dots-vertical"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                  <a class="dropdown-item" href="<?= $sRootPath ?>/fundraiser/view/<?= $frId ?>">
                    <i class="ti ti-eye me-2"></i><?= gettext('View') ?>
                  </a>
                  <a class="dropdown-item" href="<?= $sRootPath ?>/fundraiser/editor/<?= $frId ?>">
                    <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                  </a>
                  <div class="dropdown-divider"></div>
                  <?php if ($frShowItemCatalog($frType)): ?>
                  <a class="dropdown-item" href="<?= $sRootPath ?>/fundraiser/<?= $frId ?>/reports/catalog">
                    <i class="ti ti-book me-2"></i><?= gettext('Catalog') ?>
                  </a>
                  <?php endif; ?>
                  <?php if ($frShowBidSheets($frType)): ?>
                  <a class="dropdown-item" href="<?= $sRootPath ?>/fundraiser/<?= $frId ?>/reports/bid-sheets">
                    <i class="ti ti-list me-2"></i><?= gettext('Bid Sheets') ?>
                  </a>
                  <?php endif; ?>
                  <?php if ($frShowItemCatalog($frType)): ?>
                  <a class="dropdown-item" href="<?= $sRootPath ?>/fundraiser/<?= $frId ?>/reports/certificates">
                    <i class="ti ti-certificate me-2"></i><?= gettext('Certificates') ?>
                  </a>
                  <?php endif; ?>
                  <a class="dropdown-item" href="<?= $sRootPath ?>/fundraiser/<?= $frId ?>/reports/statement">
                    <i class="ti ti-file-invoice me-2"></i><?= gettext('Buyer Statements') ?>
                  </a>
                  <div class="dropdown-divider"></div>
                  <form method="post" action="<?= $sRootPath ?>/fundraiser/<?= $frId ?>/delete" class="d-inline"
                        data-confirm="<?= InputUtils::escapeAttribute(gettext('Are you sure you want to delete this fundraiser?')) ?>">
                    <?= $csrfFundraiserDeleteField ?>
                    <button type="submit" class="dropdown-item text-danger border-0 bg-transparent">
                      <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                    </button>
                  </form>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (empty($activeFundraisers)): ?>
    <div class="empty py-4">
      <p class="empty-title text-body-secondary"><?= $filterStatus !== ''
          ? sprintf(gettext('No %s fundraisers found.'), gettext($filterStatus))
          : gettext('No active fundraisers found.') ?></p>
      <?php if (empty($filterStatus) && empty($filterType) && empty($dateStart) && empty($dateEnd)): ?>
      <a href="<?= $sRootPath ?>/fundraiser/editor" class="btn btn-primary btn-sm">
        <i class="ti ti-plus me-1"></i><?= gettext('Create Fundraiser') ?>
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ==================== ARCHIVED FUNDRAISERS ==================== -->
<?php $archivedCount = count($archivedFundraisers); ?>
<?php if ($filterStatus === ''): ?>
<div class="card mt-3">
  <div class="card-header p-0">
    <button class="btn btn-ghost-secondary w-100 text-start rounded-0 py-2 px-3"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#archiveCollapse"
            aria-expanded="false"
            aria-controls="archiveCollapse">
      <i class="ti ti-archive me-1 text-body-secondary"></i>
      <?= sprintf(ngettext('%d archived fundraiser', '%d archived fundraisers', $archivedCount), $archivedCount) ?>
      <i class="ti ti-chevron-right ms-auto"></i>
    </button>
  </div>
  <div id="archiveCollapse" class="collapse">
    <div class="card-body p-0">
      <div style="overflow: visible;">
        <table id="fundraisers-archive" class="table table-hover table-vcenter mb-0 w-100">
          <thead>
            <tr>
              <th><?= gettext('Title') ?></th>
              <th><?= gettext('Type') ?></th>
              <th><?= gettext('Date') ?></th>
              <th><?= gettext('Status') ?></th>
              <th class="text-end"><?= gettext('Items') ?></th>
              <th class="text-end"><?= gettext('Raised') ?></th>
              <th><?= gettext('Goal %') ?></th>
              <th class="text-end"><?= gettext('Buyers') ?></th>
              <th class="text-center w-1 no-export"><?= gettext('Actions') ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($archivedFundraisers as $fundraiser):
              $frId    = $fundraiser->getId();
              $summary = $summaries[$frId] ?? ['items' => 0, 'raised' => 0.0, 'est' => 0.0, 'buyers' => 0];
              $raised  = (float) $summary['raised'];
              $goal    = $fundraiser->getGoalAmount() !== null ? (float) $fundraiser->getGoalAmount() : null;
              $goalPct = ($goal !== null && $goal > 0) ? min(round(($raised / $goal) * 100), 100) : null;
              $frType  = $fundraiser->getType() ?: 'Auction';
              $status  = $fundraiser->getStatus() ?: 'Closed';
          ?>
            <tr>
              <td class="fw-medium">
                <a href="<?= $sRootPath ?>/fundraiser/view/<?= $frId ?>" class="text-reset text-decoration-none">
                  <?= InputUtils::escapeHTML($fundraiser->getTitle()) ?>
                </a>
              </td>
              <td><span class="badge bg-azure-lt"><?= InputUtils::escapeHTML(gettext($frType)) ?></span></td>
              <td><?= InputUtils::escapeHTML($frDateRange($fundraiser, $sDateFormat)) ?></td>
              <td><span class="<?= $frStatusBadgeClass($status) ?>"><?= InputUtils::escapeHTML(gettext($status)) ?></span></td>
              <td class="text-end"><?= (int) $summary['items'] ?></td>
              <td class="text-end"><?= CurrencyFormatter::formatHtml($raised) ?></td>
              <td style="min-width:80px">
                <?php if ($goalPct !== null): ?>
                <div class="progress" style="height:8px" title="<?= $goalPct ?>%">
                  <div class="progress-bar bg-success" role="progressbar" style="width:<?= $goalPct ?>%" aria-valuenow="<?= $goalPct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <small class="text-body-secondary"><?= $goalPct ?>%</small>
                <?php else: ?>
                <span class="text-body-secondary">—</span>
                <?php endif; ?>
              </td>
              <td class="text-end"><?= (int) $summary['buyers'] ?></td>
              <td class="text-center w-1">
                <div class="dropdown">
                  <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                    <i class="ti ti-dots-vertical"></i>
                  </button>
                  <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="<?= $sRootPath ?>/fundraiser/view/<?= $frId ?>">
                      <i class="ti ti-eye me-2"></i><?= gettext('View') ?>
                    </a>
                    <a class="dropdown-item" href="<?= $sRootPath ?>/fundraiser/editor/<?= $frId ?>">
                      <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                    </a>
                    <div class="dropdown-divider"></div>
                    <?php if ($frShowItemCatalog($frType)): ?>
                    <a class="dropdown-item" href="<?= $sRootPath ?>/fundraiser/<?= $frId ?>/reports/catalog">
                      <i class="ti ti-book me-2"></i><?= gettext('Catalog') ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($frShowBidSheets($frType)): ?>
                    <a class="dropdown-item" href="<?= $sRootPath ?>/fundraiser/<?= $frId ?>/reports/bid-sheets">
                      <i class="ti ti-list me-2"></i><?= gettext('Bid Sheets') ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($frShowItemCatalog($frType)): ?>
                    <a class="dropdown-item" href="<?= $sRootPath ?>/fundraiser/<?= $frId ?>/reports/certificates">
                      <i class="ti ti-certificate me-2"></i><?= gettext('Certificates') ?>
                    </a>
                    <?php endif; ?>
                    <a class="dropdown-item" href="<?= $sRootPath ?>/fundraiser/<?= $frId ?>/reports/statement">
                      <i class="ti ti-file-invoice me-2"></i><?= gettext('Buyer Statements') ?>
                    </a>
                    <div class="dropdown-divider"></div>
                    <form method="post" action="<?= $sRootPath ?>/fundraiser/<?= $frId ?>/delete" class="d-inline"
                          data-confirm="<?= InputUtils::escapeAttribute(gettext('Are you sure you want to delete this fundraiser?')) ?>">
                      <?= $csrfFundraiserDeleteField ?>
                      <button type="submit" class="dropdown-item text-danger border-0 bg-transparent">
                        <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                      </button>
                    </form>
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
</div>
<?php endif; ?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
document.addEventListener('DOMContentLoaded', function () {
    // Delegated confirm dialog for delete forms — CSP-safe (no inline onsubmit)
    document.addEventListener('submit', function (e) {
        var form = e.target.closest('form[data-confirm]');
        if (!form) return;
        if (!confirm(form.dataset.confirm)) {
            e.preventDefault();
        }
    });

    // Auto-submit filter form on select change (CSP-safe, no inline onchange)
    document.querySelectorAll('#fundraiserFilterForm select').forEach(function (sel) {
        sel.addEventListener('change', function () {
            document.getElementById('fundraiserFilterForm').submit();
        });
    });

    // Active fundraisers DataTable
    var dtCfg = {
        order: [[2, 'desc']],
        columnDefs: [
            { targets: -1, orderable: false, searchable: false },
            { targets: 6, orderable: false },
        ],
        pageLength: 25,
    };
    $.extend(dtCfg, window.CRM.plugin.dataTable);
    $('#fundraisers').DataTable(dtCfg);

    // Archive fundraisers DataTable — not rendered at all when a status filter is
    // active (see fundraiser-list.php), so guard on the element existing.
    // autoWidth:false prevents column-width miscomputation when the container is
    // hidden (Bootstrap collapse). columns.adjust() is called on the
    // shown.bs.collapse event to finalise widths when the user first expands it.
    if (document.getElementById('fundraisers-archive')) {
        var dtCfgArc = {
            order: [[2, 'desc']],
            autoWidth: false,
            columnDefs: [
                { targets: -1, orderable: false, searchable: false },
                { targets: 6, orderable: false },
            ],
            pageLength: 25,
        };
        $.extend(dtCfgArc, window.CRM.plugin.dataTable);
        var archiveTable = $('#fundraisers-archive').DataTable(dtCfgArc);

        // Adjust column widths when the collapse reveals the table for the first time.
        document.getElementById('archiveCollapse')?.addEventListener('shown.bs.collapse', function () {
            archiveTable.columns.adjust();
        }, { once: true });
    }
});
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
