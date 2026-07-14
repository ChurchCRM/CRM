<?php
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\Utils\InputUtils;

$sRootPath    = $sRootPath ?? SystemURLs::getRootPath();
$fundraiserId = (int) $fundraiserId;
$status       = $fundraiser->getStatus() ?: 'Active';
$frType       = $fundraiser->getType() ?: 'Auction';
$goalAmount   = $fundraiser->getGoalAmount() !== null ? (float) $fundraiser->getGoalAmount() : null;

// Status top-card accent colour
$statusColor = match ($status) {
    'Active'   => 'bg-green',
    'Planning' => 'bg-azure',
    default    => 'bg-secondary',
};

// Status badge
$statusBadge = match ($status) {
    'Active'   => 'badge bg-green-lt text-green',
    'Planning' => 'badge bg-azure-lt text-azure',
    default    => 'badge bg-secondary-lt',
};

// Finance report jump URLs — only active when the linked fund record was successfully resolved.
// FundId may be set but point to a hard-deleted fund; in that case $fundName stays null
// and we correctly treat the fundraiser as "unlinked".
$hasFund      = $fundName !== null;
$dateFrom     = $fundraiser->getDate()?->format('Y-m-d') ?? '';
$dateTo       = ($fundraiser->getEndDate() ?? $fundraiser->getDate())?->format('Y-m-d') ?? $dateFrom;
$financeBase  = $sRootPath . '/FinancialReports.php';
$depositUrl   = $financeBase . '?' . http_build_query([
    'ReportType' => 'Advanced Deposit Report',
    'DateStart'  => $dateFrom,
    'DateEnd'    => $dateTo,
    'datetype'   => 'deposit',
]);
$givingUrl    = $financeBase . '?' . http_build_query([
    'ReportType' => 'Giving Report',
    'DateStart'  => $dateFrom,
    'DateEnd'    => $dateTo,
]);
$depositSlipUrl = $sRootPath . '/FindDepositSlip.php';

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="row">
  <!-- ==================== MAIN COLUMN ==================== -->
  <div class="col-lg-8">

    <!-- Summary card -->
    <div class="card mb-3">
      <div class="card-status-top <?= $statusColor ?>"></div>
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><?= InputUtils::escapeHTML($fundraiser->getTitle()) ?></h3>
        <span class="ms-auto">
          <span class="<?= $statusBadge ?>"><?= InputUtils::escapeHTML(gettext($status)) ?></span>
        </span>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-3"><?= gettext('Type') ?></dt>
          <dd class="col-sm-9">
            <span class="badge bg-azure-lt"><?= InputUtils::escapeHTML(gettext($frType)) ?></span>
          </dd>

          <dt class="col-sm-3"><?= gettext('Date') ?></dt>
          <dd class="col-sm-9">
            <?php
            $startStr = $fundraiser->getDate()?->format($sDateFormat) ?? '';
            $endDate  = $fundraiser->getEndDate();
            if ($endDate && $endDate != $fundraiser->getDate()) {
                echo InputUtils::escapeHTML($startStr . ' – ' . $endDate->format($sDateFormat));
            } else {
                echo InputUtils::escapeHTML($startStr);
            }
            ?>
          </dd>

          <dt class="col-sm-3"><?= gettext('Associated Fund') ?></dt>
          <dd class="col-sm-9">
            <?php if ($fundName !== null): ?>
              <?= InputUtils::escapeHTML($fundName) ?>
            <?php else: ?>
              <span class="text-body-secondary">—</span>
            <?php endif; ?>
          </dd>

          <?php if ($fundraiser->getDescription()): ?>
          <dt class="col-sm-3"><?= gettext('Description') ?></dt>
          <dd class="col-sm-9"><?= InputUtils::escapeHTML($fundraiser->getDescription()) ?></dd>
          <?php endif; ?>
        </dl>
      </div>
    </div>

    <!-- Goal progress card -->
    <?php $totalRaised = (float) ($viewModel['totalRaised'] ?? 0); ?>
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-hand-holding-dollar me-2"></i><?= gettext('Fundraising') ?></h3>
      </div>
      <div class="card-body">
        <?php if ($goalAmount !== null && $goalAmount > 0):
            $pct = min(round(($totalRaised / $goalAmount) * 100, 1), 100);
        ?>
        <div class="d-flex justify-content-between mb-1">
          <span><?= gettext('Raised') ?></span>
          <strong><?= CurrencyFormatter::formatHtml($totalRaised) ?> / <?= CurrencyFormatter::formatHtml($goalAmount) ?></strong>
        </div>
        <div class="progress mb-1" style="height:20px;">
          <div class="progress-bar bg-success" role="progressbar"
               style="width:<?= $pct ?>%"
               aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
            <?= $pct ?>%
          </div>
        </div>
        <small class="text-body-secondary"><?= htmlspecialchars(sprintf(gettext('%s of %s goal raised'), CurrencyFormatter::format($totalRaised), CurrencyFormatter::format($goalAmount)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small>
        <?php else: ?>
        <div class="h3 text-success mb-0"><?= CurrencyFormatter::formatHtml($totalRaised) ?></div>
        <small class="text-body-secondary"><?= gettext('Total raised (no goal set)') ?></small>
        <?php endif; ?>
      </div>
    </div>

    <!-- Donated items table -->
    <?php $itemList = iterator_to_array($donatedItems, false); ?>
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fa-solid fa-gavel me-2"></i><?= gettext('Donated Items') ?></h3>
        <span class="badge bg-primary ms-2"><?= count($itemList) ?></span>
      </div>
      <div class="card-body p-0">
        <?php if (!empty($itemList)): ?>
        <div class="table-responsive">
          <table class="table table-vcenter table-hover mb-0">
            <thead>
              <tr>
                <th><?= gettext('Code') ?></th>
                <th><?= gettext('Title') ?></th>
                <th><?= gettext('Donor') ?></th>
                <th class="text-end"><?= gettext('Est. Value') ?></th>
                <th class="text-end"><?= gettext('Sale Price') ?></th>
                <th><?= gettext('Buyer') ?></th>
                <th><?= gettext('Status') ?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($itemList as $item):
                $donor = $item->getDonorId() ? ($personMap[(int) $item->getDonorId()] ?? null) : null;
                $buyer = $item->getBuyerId() ? ($personMap[(int) $item->getBuyerId()] ?? null) : null;
                $isSold = (int) $item->getBuyerId() > 0 && (float) $item->getSellprice() > 0;
            ?>
              <tr>
                <td class="font-monospace"><?= InputUtils::escapeHTML($item->getItem() ?: '~') ?></td>
                <td><?= InputUtils::escapeHTML($item->getTitle()) ?></td>
                <td>
                  <?php if ($donor): ?>
                    <?= InputUtils::escapeHTML($donor->getFirstName() . ' ' . $donor->getLastName()) ?>
                  <?php else: ?>
                    <span class="text-body-secondary">—</span>
                  <?php endif; ?>
                </td>
                <td class="text-end"><?= CurrencyFormatter::formatHtml($item->getEstprice() !== null ? (float) $item->getEstprice() : null) ?></td>
                <td class="text-end">
                  <?= $isSold ? CurrencyFormatter::formatHtml((float) $item->getSellprice()) : '<span class="text-body-secondary">—</span>' ?>
                </td>
                <td>
                  <?php if ($buyer): ?>
                    <?= InputUtils::escapeHTML($buyer->getFirstName() . ' ' . $buyer->getLastName()) ?>
                  <?php else: ?>
                    <span class="text-body-secondary">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($isSold): ?>
                    <span class="badge bg-green-lt text-green"><?= gettext('Sold') ?></span>
                  <?php else: ?>
                    <span class="badge bg-secondary-lt"><?= gettext('Unsold') ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="empty py-3">
          <p class="empty-title text-body-secondary"><?= gettext('No donated items yet.') ?></p>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Footer action bar -->
    <div class="d-flex justify-content-between mb-3 py-2">
      <a href="<?= $sRootPath ?>/fundraiser/" class="btn btn-outline-secondary">
        <i class="ti ti-chevron-left me-1"></i><?= gettext('Back to Fundraisers') ?>
      </a>
      <?php if ($canEdit): ?>
      <div class="d-flex gap-2">
        <a href="<?= $sRootPath ?>/fundraiser/<?= $fundraiserId ?>/paddle-numbers" class="btn btn-outline-primary">
          <i class="ti ti-users me-1"></i><?= gettext('View Buyers') ?>
        </a>
        <a href="<?= $sRootPath ?>/fundraiser/editor/<?= $fundraiserId ?>" class="btn btn-primary">
          <i class="ti ti-pencil me-1"></i><?= gettext('Edit') ?>
        </a>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /.col-lg-8 -->

  <!-- ==================== SIDEBAR ==================== -->
  <div class="col-lg-4">

    <!-- At a Glance -->
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-chart-bar me-2"></i><?= gettext('At a Glance') ?></h3>
      </div>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <span class="text-body-secondary"><?= gettext('Items Donated') ?></span>
          <strong><?= (int) ($viewModel['items'] ?? 0) ?></strong>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-body-secondary"><?= gettext('Items Sold') ?></span>
          <strong><?= (int) ($viewModel['itemsSold'] ?? 0) ?></strong>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-body-secondary"><?= gettext('Sell-through') ?></span>
          <strong><?= number_format((float) ($viewModel['sellThroughPct'] ?? 0), 1) ?>%</strong>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-body-secondary"><?= gettext('Est. Value Total') ?></span>
          <strong><?= InputUtils::escapeHTML($viewModel['totalEstValue_formatted'] ?? CurrencyFormatter::format(0)) ?></strong>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-body-secondary"><?= gettext('Material Value') ?></span>
          <strong><?= InputUtils::escapeHTML($viewModel['totalMaterialValue_formatted'] ?? CurrencyFormatter::format(0)) ?></strong>
        </div>
        <div class="d-flex justify-content-between mb-0">
          <span class="text-body-secondary"><?= gettext('Buyers Registered') ?></span>
          <strong><?= (int) ($viewModel['buyers'] ?? 0) ?></strong>
        </div>
      </div>
    </div>

    <!-- Financials + Report Jumps -->
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-file-invoice-dollar me-2"></i><?= gettext('Financials') ?></h3>
      </div>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <span class="text-body-secondary"><?= gettext('Total Raised') ?></span>
          <strong class="text-success"><?= InputUtils::escapeHTML($viewModel['totalRaised_formatted'] ?? CurrencyFormatter::format(0)) ?></strong>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-body-secondary"><?= gettext('Avg Sale Price') ?></span>
          <strong><?= InputUtils::escapeHTML($viewModel['avgSalePrice_formatted'] ?? CurrencyFormatter::format(0)) ?></strong>
        </div>
        <div class="d-flex justify-content-between mb-3">
          <span class="text-body-secondary"><?= gettext('Highest Sale') ?></span>
          <strong><?= InputUtils::escapeHTML($viewModel['highestSale_formatted'] ?? CurrencyFormatter::format(0)) ?></strong>
        </div>

        <h4 class="text-body-secondary mb-2 small"><?= gettext('Auction Reports') ?></h4>
        <div class="list-group list-group-flush mb-3">
          <a href="<?= $sRootPath ?>/fundraiser/<?= $fundraiserId ?>/reports/catalog" class="list-group-item list-group-item-action">
            <i class="ti ti-book me-2 text-body-secondary"></i><?= gettext('Catalog') ?>
          </a>
          <a href="<?= $sRootPath ?>/fundraiser/<?= $fundraiserId ?>/reports/bid-sheets" class="list-group-item list-group-item-action">
            <i class="ti ti-list me-2 text-body-secondary"></i><?= gettext('Bid Sheets') ?>
          </a>
          <a href="<?= $sRootPath ?>/fundraiser/<?= $fundraiserId ?>/reports/certificates" class="list-group-item list-group-item-action">
            <i class="ti ti-certificate me-2 text-body-secondary"></i><?= gettext('Certificates') ?>
          </a>
          <a href="<?= $sRootPath ?>/fundraiser/<?= $fundraiserId ?>/reports/statement" class="list-group-item list-group-item-action">
            <i class="ti ti-file-invoice me-2 text-body-secondary"></i><?= gettext('Buyer Statements') ?>
          </a>
        </div>

        <h4 class="text-body-secondary mb-2 small"><?= gettext('Finance Reports') ?></h4>
        <?php if (!$hasFund): ?>
        <div class="alert alert-secondary py-2 small mb-2">
          <i class="ti ti-info-circle me-1"></i>
          <?= gettext('Link this fundraiser to a donation fund to enable Finance report jumps.') ?>
        </div>
        <?php endif; ?>
        <div class="list-group list-group-flush">
          <?php if ($hasFund): ?>
          <a href="<?= InputUtils::escapeAttribute($depositUrl) ?>"
             class="list-group-item list-group-item-action"
             title="<?= InputUtils::escapeAttribute(gettext('Date-filtered across all funds — use the fund filter inside the report to narrow to this fund')) ?>">
            <i class="ti ti-report-money me-2 text-body-secondary"></i><?= gettext('Deposit Report (date-filtered)') ?>
          </a>
          <a href="<?= InputUtils::escapeAttribute($givingUrl) ?>"
             class="list-group-item list-group-item-action"
             title="<?= InputUtils::escapeAttribute(gettext('Date-filtered across all funds — use the fund filter inside the report to narrow to this fund')) ?>">
            <i class="ti ti-heart me-2 text-body-secondary"></i><?= gettext('Giving Report (date-filtered)') ?>
          </a>
          <a href="<?= InputUtils::escapeAttribute($depositSlipUrl) ?>" class="list-group-item list-group-item-action">
            <i class="ti ti-receipt me-2 text-body-secondary"></i><?= gettext('Deposit Slips') ?>
          </a>
          <?php else: ?>
          <span class="list-group-item disabled text-body-secondary" tabindex="-1"
                title="<?= InputUtils::escapeAttribute(gettext('Link this fundraiser to a donation fund to enable financial reports.')) ?>">
            <i class="ti ti-report-money me-2"></i><?= gettext('Deposit Report') ?>
          </span>
          <span class="list-group-item disabled text-body-secondary" tabindex="-1"
                title="<?= InputUtils::escapeAttribute(gettext('Link this fundraiser to a donation fund to enable financial reports.')) ?>">
            <i class="ti ti-heart me-2"></i><?= gettext('Giving Report') ?>
          </span>
          <span class="list-group-item disabled text-body-secondary" tabindex="-1"
                title="<?= InputUtils::escapeAttribute(gettext('Link this fundraiser to a donation fund to enable financial reports.')) ?>">
            <i class="ti ti-receipt me-2"></i><?= gettext('Deposit Slips') ?>
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div><!-- /.col-lg-4 -->
</div><!-- /.row -->

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
