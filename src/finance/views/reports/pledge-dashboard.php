<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Utils\CurrencyFormatter;
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
                    <select name="fyid" id="fyid" class="form-select d-inline-block" style="width: auto;" onchange="this.form.submit();">
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
    <div class="row mb-4">
        <!-- Total Pledges -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card finance-card shadow-sm border-0 h-100">
                <div class="card-body text-center py-4 finance-metric-card metric-pledges">
                    <div class="finance-metric-value">
                        <?= CurrencyFormatter::formatHtml($totalPledges) ?>
                    </div>
                    <div class="text-white-50 text-uppercase small fw-bold mt-2 finance-metric-label">
                        <?= gettext('Total Pledges') ?>
                    </div>
                    <div class="text-white-50 small mt-1">
                        <?= FinancialService::formatFiscalYear($selectedFyid) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Payments -->
        <div class="col-xl-3 col-md-6 mb-3">
            <?php $overallPercent = $totalPledges > 0 ? ($totalPayments / $totalPledges) * 100 : 0; ?>
            <div class="card finance-card shadow-sm border-0 h-100">
                <div class="card-body text-center py-4 finance-metric-card metric-payments">
                    <div class="finance-metric-value">
                        <?= CurrencyFormatter::formatHtml($totalPayments) ?>
                    </div>
                    <div class="text-white-50 text-uppercase small fw-bold mt-2 finance-metric-label">
                        <?= gettext('Total Payments') ?>
                    </div>
                    <div class="text-white-50 small mt-1">
                        <?= number_format($overallPercent, 1) ?>% <?= gettext('of pledges') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fund Summary Cards -->
        <?php if (!empty($fundTotals)): ?>
            <?php foreach ($fundTotals as $fundTotal): ?>
                <?php $fundPercent = $fundTotal['total_pledged'] > 0 ? ($fundTotal['total_paid'] / $fundTotal['total_pledged']) * 100 : 0; ?>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card finance-card shadow-sm border-0 h-100">
                        <div class="card-status-top bg-info"></div>
                        <div class="card-header py-2">
                            <h5 class="mb-0">
                                <i class="fa-solid fa-donate me-1"></i>
                                <?= InputUtils::escapeHTML($fundTotal['fund_name']) ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="h5 mb-1 fw-bold text-dark">
                                <?= CurrencyFormatter::formatHtml($fundTotal['total_paid']) ?>
                            </div>
                            <div class="text-body-secondary small mb-2">
                                <?= gettext('of') ?> <?= CurrencyFormatter::formatHtml($fundTotal['total_pledged']) ?>
                                (<?= number_format($fundPercent, 0) ?>%)
                            </div>
                            <div class="text-body-secondary small mb-2">
                                <?= $fundTotal['family_count'] ?> <?= $fundTotal['family_count'] == 1 ? gettext('Family') : gettext('Families') ?>
                            </div>
                            <div class="progress finance-progress">
                                <div class="progress-bar bg-info" role="progressbar" style="width: <?= min($fundPercent, 100) ?>%" aria-valuenow="<?= number_format($fundPercent, 0) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Fund Summary DataTable -->
    <?php if (!empty($fundTotals)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card finance-card shadow-sm border-0">
                <div class="card-status-top bg-info"></div>
                <div class="card-header py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-chart-bar me-1"></i>
                        <?= gettext('Fund Summary') ?>
                    </h5>
                </div>
                <div class="card-body p-0">
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
                                        <td><?= InputUtils::escapeHTML($fundTotal['fund_name']) ?></td>
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
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Family Pledges DataTable -->
    <div class="row">
        <div class="col-12">
            <?php if (empty($familyPledges)): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-circle-info me-2"></i>
                    <?= gettext('No pledges found for the selected fiscal year') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php else: ?>
                <div class="card finance-card shadow-sm border-0">
                    <div class="card-status-top bg-primary"></div>
                    <div class="card-header py-2">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-handshake me-1"></i>
                            <?= gettext('Family Pledges') ?>
                        </h5>
                    </div>
                    <div class="card-body p-0">
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
                                                <td class="text-end <?= $statusClass ?>" data-order="<?= InputUtils::escapeAttribute($remaining ?? $pledge['payment_amount']) ?>">
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
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('pledgeFundSummary')) {
        var fundCfg = $.extend({}, window.CRM.plugin.dataTable, { order: [[0, 'asc']], pageLength: 25 });
        $('#pledgeFundSummary').DataTable(fundCfg);
    }

    if (document.getElementById('familyPledges')) {
        var famCfg = $.extend({}, window.CRM.plugin.dataTable, { order: [[0, 'asc']], pageLength: 25 });
        $('#familyPledges').DataTable(famCfg);
    }
});
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
