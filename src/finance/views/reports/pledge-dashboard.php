<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="container-fluid">
    <!-- Page Header with Controls -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="mb-3 mb-0">
                <label for="fyid" class="fw-bold"><?= gettext('Fiscal Year') ?></label>
                <form method="GET" class="d-inline">
                    <select name="fyid" id="fyid" class="form-select d-inline-block" style="width: auto;">
                        <option value="0" <?= $selectedFyid === 0 ? 'selected' : '' ?>><?= gettext('All Time') ?></option>
                        <?php foreach ($availableYears as $year): ?>
                            <option value="<?= $year['id'] ?>" <?= $year['id'] == $selectedFyid ? 'selected' : '' ?>>
                                <?= InputUtils::escapeHTML($year['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <small class="form-text text-body-secondary">
                    <?= gettext('Current Fiscal Year') ?>: <strong><?= FinancialService::formatFiscalYear($currentFyid) ?></strong>
                </small>
            </div>
        </div>
        <div class="col-md-6 text-end">
            <a href="<?= SystemURLs::getRootPath() ?>/finance/pledge/new?type=Pledge" class="btn btn-primary">
                <i class="fa-solid fa-plus me-1"></i>
                <?= gettext('Add New Pledge') ?>
            </a>
        </div>
    </div>

    <!-- Overview Stats -->
    <?php if (!empty($fundTotals) || !empty($totalPledges)): ?>
    <?php
        $paidPercent = static fn (float $paid, float $pledged): string => $pledged > 0
            ? ($paid / $pledged > 1 ? '>100%' : number_format(($paid / $pledged) * 100, 0) . '%')
            : '—';
        $stats = [
            ['label' => gettext('Total Pledged'), 'value' => $totalPledges, 'icon' => 'fa-file-signature', 'color' => 'primary',
             'note' => FiscalYearUtils::formatFiscalYearLabel($selectedFyid)],
            ['label' => gettext('Total Paid'), 'value' => $totalPayments, 'icon' => 'fa-hand-holding-dollar', 'color' => 'success',
             'note' => $paidPercent((float) $totalPayments, (float) $totalPledges) . ' ' . gettext('of pledges')],
            ['label' => gettext('Still Owed'), 'value' => $overallTotals['underpaid'] ?? 0, 'icon' => 'fa-hourglass-half', 'color' => 'warning',
             'note' => gettext('Pledged but not yet paid')],
            ['label' => gettext('Overpaid'), 'value' => $overallTotals['overpaid'] ?? 0, 'icon' => 'fa-circle-plus', 'color' => 'info',
             'note' => gettext('Paid beyond the pledge')],
        ];
    ?>
    <div class="row row-cards mb-3">
        <?php foreach ($stats as $stat): ?>
        <div class="col-6 col-lg-3">
            <div class="card card-sm h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-<?= $stat['color'] ?> text-white avatar rounded-circle">
                                <i class="fa-solid <?= $stat['icon'] ?> icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium"><?= CurrencyFormatter::formatHtml($stat['value']) ?></div>
                            <div class="text-body-secondary"><?= InputUtils::escapeHTML($stat['label']) ?></div>
                            <div class="text-body-secondary small"><?= InputUtils::escapeHTML($stat['note']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Category Summary (only when at least one fund has a category) -->
    <?php if (!empty($categoryTotals)): ?>
    <div class="card mb-3">
        <div class="card-status-top bg-info"></div>
        <div class="card-header py-2">
            <h3 class="card-title">
                <i class="fa-solid fa-tags me-1"></i>
                <?= gettext('Summary by Category') ?>
            </h3>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-vcenter mb-0 w-100">
                <thead>
                    <tr>
                        <th><?= gettext('Category') ?></th>
                        <th class="text-end"><?= gettext('Funds') ?></th>
                        <th class="text-end"><?= gettext('Pledges') ?></th>
                        <th class="text-end"><?= gettext('Payments') ?></th>
                        <th class="text-end"><?= gettext('Overpaid') ?></th>
                        <th class="text-end"><?= gettext('Underpaid') ?></th>
                        <th style="min-width: 8rem;"><?= gettext('Paid') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categoryTotals as $categoryTotal): ?>
                        <?php $categoryPercent = $categoryTotal['total_pledged'] > 0 ? ($categoryTotal['total_paid'] / $categoryTotal['total_pledged']) * 100 : 0; ?>
                        <tr>
                            <td>
                                <?= $categoryTotal['category'] !== ''
                                    ? InputUtils::escapeHTML($categoryTotal['category'])
                                    : '<span class="text-body-secondary">' . gettext('Uncategorized') . '</span>' ?>
                            </td>
                            <td class="text-end"><?= (int) $categoryTotal['fund_count'] ?></td>
                            <td class="text-end"><?= CurrencyFormatter::formatHtml($categoryTotal['total_pledged']) ?></td>
                            <td class="text-end"><?= CurrencyFormatter::formatHtml($categoryTotal['total_paid']) ?></td>
                            <td class="text-end"><?= CurrencyFormatter::formatHtml($categoryTotal['overpaid']) ?></td>
                            <td class="text-end"><?= CurrencyFormatter::formatHtml($categoryTotal['underpaid']) ?></td>
                            <td>
                                <div class="small text-body-secondary mb-1"><?= $paidPercent((float) $categoryTotal['total_paid'], (float) $categoryTotal['total_pledged']) ?></div>
                                <div class="progress" title="<?= number_format($categoryPercent, 0) ?>%">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: <?= min($categoryPercent, 100) ?>%" aria-valuenow="<?= number_format($categoryPercent, 0) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Fund Summary DataTable -->
    <?php if (!empty($fundTotals)): ?>
    <div class="card mb-3">
        <div class="card-status-top bg-info"></div>
        <div class="card-header py-2">
            <h3 class="card-title">
                <i class="fa-solid fa-chart-bar me-1"></i>
                <?= gettext('Fund Summary') ?>
            </h3>
        </div>
        <div class="table-responsive">
            <table id="pledgeFundSummary" class="table table-hover table-vcenter mb-0 w-100">
                <thead>
                    <tr>
                        <th><?= gettext('Fund') ?></th>
                        <th class="text-end"><?= gettext('Pledges') ?></th>
                        <th class="text-end"><?= gettext('Payments') ?></th>
                        <th class="text-end"><?= gettext('# Pledges') ?></th>
                        <th class="text-end"><?= gettext('# Payments') ?></th>
                        <th class="text-end"><?= gettext('Overpaid') ?></th>
                        <th class="text-end"><?= gettext('Underpaid') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fundTotals as $fundTotal): ?>
                        <tr>
                            <td>
                                <?php if ((int) $fundTotal['fund_id'] > 0): ?>
                                    <a class="fund-summary-link"
                                       data-fund-id="<?= (int) $fundTotal['fund_id'] ?>"
                                       href="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath()) ?>/finance/fund/<?= (int) $fundTotal['fund_id'] ?>/contributors?fyid=<?= (int) $selectedFyid ?>">
                                        <?= InputUtils::escapeHTML($fundTotal['fund_name']) ?>
                                    </a>
                                <?php else: ?>
                                    <?= InputUtils::escapeHTML($fundTotal['fund_name']) ?>
                                <?php endif; ?>
                                <?php if (($fundTotal['category'] ?? '') !== ''): ?>
                                    <div class="small text-body-secondary"><?= InputUtils::escapeHTML($fundTotal['category']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end" data-order="<?= InputUtils::escapeAttribute($fundTotal['total_pledged']) ?>">
                                <?= CurrencyFormatter::formatHtml($fundTotal['total_pledged']) ?>
                            </td>
                            <td class="text-end" data-order="<?= InputUtils::escapeAttribute($fundTotal['total_paid']) ?>">
                                <?= CurrencyFormatter::formatHtml($fundTotal['total_paid']) ?>
                            </td>
                            <td class="text-end"><?= (int) $fundTotal['pledge_count'] ?></td>
                            <td class="text-end"><?= (int) $fundTotal['payment_count'] ?></td>
                            <td class="text-end" data-order="<?= InputUtils::escapeAttribute($fundTotal['overpaid']) ?>">
                                <?= CurrencyFormatter::formatHtml($fundTotal['overpaid']) ?>
                            </td>
                            <td class="text-end" data-order="<?= InputUtils::escapeAttribute($fundTotal['underpaid']) ?>">
                                <?= CurrencyFormatter::formatHtml($fundTotal['underpaid']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if (!empty($overallTotals)): ?>
                <tfoot>
                    <tr class="fw-bold table-secondary">
                        <td><?= gettext('Total') ?></td>
                        <td class="text-end" data-order="<?= InputUtils::escapeAttribute($overallTotals['total_pledged']) ?>">
                            <?= CurrencyFormatter::formatHtml($overallTotals['total_pledged']) ?>
                        </td>
                        <td class="text-end" data-order="<?= InputUtils::escapeAttribute($overallTotals['total_paid']) ?>">
                            <?= CurrencyFormatter::formatHtml($overallTotals['total_paid']) ?>
                        </td>
                        <td class="text-end"><?= (int) $overallTotals['pledge_count'] ?></td>
                        <td class="text-end"><?= (int) $overallTotals['payment_count'] ?></td>
                        <td class="text-end" data-order="<?= InputUtils::escapeAttribute($overallTotals['overpaid']) ?>">
                            <?= CurrencyFormatter::formatHtml($overallTotals['overpaid']) ?>
                        </td>
                        <td class="text-end" data-order="<?= InputUtils::escapeAttribute($overallTotals['underpaid']) ?>">
                            <?= CurrencyFormatter::formatHtml($overallTotals['underpaid']) ?>
                        </td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Family Pledges DataTable -->
    <?php if (empty($familyPledges)): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-info me-2"></i>
            <?= gettext('No pledges found for the selected fiscal year') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-status-top bg-primary"></div>
            <div class="card-header py-2">
                <h3 class="card-title">
                    <i class="fa-solid fa-handshake me-1"></i>
                    <?= gettext('Family Pledges') ?>
                </h3>
            </div>
            <div class="table-responsive">
                <table id="familyPledges" class="table table-hover table-vcenter mb-0 w-100">
                    <thead>
                        <tr>
                            <th><?= gettext('Family Name') ?></th>
                            <?php if (SystemConfig::getBooleanValue('bUseDonationEnvelopes')): ?>
                            <th><?= gettext('Envelope') ?></th>
                            <?php endif; ?>
                            <th><?= gettext('Fund Name') ?></th>
                            <th class="text-end"><?= gettext('Pledge Amount') ?></th>
                            <th class="text-end"><?= gettext('Payments') ?></th>
                            <th class="text-end"><?= gettext('Remaining') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($familyPledges as $family): ?>
                            <?php foreach ($family['pledges'] as $pledge): ?>
                                <?php
                                if ($pledge['pledge_amount'] <= 0.0) {
                                    // Payment-only row (backfilled): no pledge to track against
                                    $remaining = null;
                                    $percentComplete = 100;
                                    $statusClass = 'text-success fw-bold';
                                } else {
                                    $remaining = $pledge['pledge_amount'] - $pledge['payment_amount'];
                                    $percentComplete = ($pledge['payment_amount'] / $pledge['pledge_amount']) * 100;
                                    if ($percentComplete >= 100) {
                                        $statusClass = 'text-success fw-bold';
                                    } elseif ($percentComplete >= 75) {
                                        $statusClass = 'text-info';
                                    } elseif ($percentComplete >= 50) {
                                        $statusClass = 'text-warning';
                                    } else {
                                        $statusClass = 'text-danger';
                                    }
                                }
                                ?>
                                <tr>
                                    <td class="fw-bold">
                                        <a href="<?= SystemURLs::getRootPath() ?>/people/family/<?= $family['family_id'] ?>">
                                            <?= InputUtils::escapeHTML($family['family_name']) ?>
                                        </a>
                                    </td>
                                    <?php if (SystemConfig::getBooleanValue('bUseDonationEnvelopes')): ?>
                                    <td class="text-body-secondary small">
                                        <?= InputUtils::escapeHTML($family['envelope'] ?? '') ?>
                                    </td>
                                    <?php endif; ?>
                                    <td><?= InputUtils::escapeHTML($pledge['fund_name']) ?></td>
                                    <td class="text-end fw-bold" data-order="<?= InputUtils::escapeAttribute($pledge['pledge_amount']) ?>">
                                        <?= CurrencyFormatter::formatHtml($pledge['pledge_amount']) ?>
                                    </td>
                                    <td class="text-end" data-order="<?= InputUtils::escapeAttribute($pledge['payment_amount']) ?>">
                                        <?= CurrencyFormatter::formatHtml($pledge['payment_amount']) ?>
                                    </td>
                                    <td class="text-end <?= $statusClass ?>" data-order="<?= InputUtils::escapeAttribute($remaining ?? 0) ?>">
                                        <?= $remaining !== null ? CurrencyFormatter::formatHtml($remaining) : '<span class="text-body-secondary">—</span>' ?>
                                        <small class="d-block text-body-secondary"><?= $remaining !== null ? number_format($percentComplete, 0) . '%' : gettext('No pledge') ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
document.addEventListener('DOMContentLoaded', function () {
    // Fiscal year selector: submit the form when the selection changes
    var fyidDashboard = document.getElementById('fyid');
    if (fyidDashboard) {
        fyidDashboard.addEventListener('change', function () {
            this.closest('form').submit();
        });
    }

    // Currency config for footerCallback — mirrors PHP CurrencyFormatter::format()
    var _crmCur = {
        sym: <?= InputUtils::jsonEncodeForScript(CurrencyFormatter::symbol()) ?>,
        pos: <?= InputUtils::jsonEncodeForScript(CurrencyFormatter::position()) ?>,
        th:  <?= InputUtils::jsonEncodeForScript(SystemConfig::getValue('sThousandsSeparator')) ?>,
        dec: <?= InputUtils::jsonEncodeForScript(SystemConfig::getValue('sDecimalSeparator')) ?>
    };
    function _fmtCur(n) {
        if (n < 0) { return '-' + _fmtCur(-n); }
        var parts = n.toFixed(2).split('.');
        if (_crmCur.th !== '') {
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, _crmCur.th);
        }
        var s = parts.join(_crmCur.dec != null ? _crmCur.dec : '.');
        return _crmCur.pos === 'after'
            ? s + '\u00A0' + _crmCur.sym
            : _crmCur.sym + '\u00A0' + s;
    }

    if (document.getElementById('pledgeFundSummary')) {
        var fundCfg = $.extend({}, window.CRM.plugin.dataTable, {
            order: [[0, 'asc']],
            pageLength: 25,
            footerCallback: function () {
                var api = this.api();
                // Currency columns: 1=Pledges, 2=Payments, 5=Overpaid, 6=Underpaid
                [1, 2, 5, 6].forEach(function (colIdx) {
                    var total = 0;
                    api.column(colIdx, {search: 'applied'}).nodes().each(function (cell) {
                        total += parseFloat(cell.dataset.order) || 0;
                    });
                    $(api.column(colIdx).footer()).text(_fmtCur(total));
                });
                // Integer count columns: 3=# Pledges, 4=# Payments
                [3, 4].forEach(function (colIdx) {
                    var total = 0;
                    api.column(colIdx, {search: 'applied'}).nodes().each(function (cell) {
                        total += parseInt(cell.textContent, 10) || 0;
                    });
                    $(api.column(colIdx).footer()).text(total);
                });
            }
        });
        $('#pledgeFundSummary').DataTable(fundCfg);
    }

    if (document.getElementById('familyPledges')) {
        var famCfg = $.extend({}, window.CRM.plugin.dataTable, { order: [[0, 'asc']], pageLength: 25 });
        $('#familyPledges').DataTable(famCfg);
    }
});
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
