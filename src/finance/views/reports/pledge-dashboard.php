<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="container-fluid">
    <!-- Page Header with Controls -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="form-group mb-0">
                <label for="fyid" class="font-weight-bold"><?= gettext('Fiscal Year') ?></label>
                <form method="GET" class="d-inline">
                    <select name="fyid" id="fyid" class="form-control d-inline-block" style="width: auto;" onchange="this.form.submit();">
                        <?php foreach ($availableYears as $year): ?>
                            <option value="<?= $year['id'] ?>" <?= $year['id'] == $selectedFyid ? 'selected' : '' ?>>
                                <?= InputUtils::escapeHTML($year['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <small class="form-text text-muted">
                    <?= gettext('Current Fiscal Year') ?>: <strong><?= FinancialService::formatFiscalYear($currentFyid) ?></strong>
                </small>
            </div>
        </div>
        <div class="col-md-6 text-right">
            <a href="<?= SystemURLs::getRootPath() ?>/PledgeEditor.php?PledgeOrPayment=Pledge" class="btn btn-primary">
                <i class="fa-solid fa-plus mr-1"></i>
                <?= gettext('Add New') . ' ' . gettext('Pledge') ?>
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
                        $<?= number_format($totalPledges, 2) ?>
                    </div>
                    <div class="text-white-50 text-uppercase small font-weight-bold mt-2 finance-metric-label">
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
                        $<?= number_format($totalPayments, 2) ?>
                    </div>
                    <div class="text-white-50 text-uppercase small font-weight-bold mt-2 finance-metric-label">
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
                        <div class="card-header bg-info text-white py-2">
                            <h5 class="mb-0">
                                <i class="fa-solid fa-donate mr-1"></i>
                                <?= InputUtils::escapeHTML($fundTotal['fund_name']) ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="h5 mb-1 font-weight-bold text-dark">
                                $<?= number_format($fundTotal['total_paid'], 2) ?>
                            </div>
                            <div class="text-muted small mb-2">
                                <?= gettext('of') ?> $<?= number_format($fundTotal['total_pledged'], 2) ?>
                                (<?= number_format($fundPercent, 0) ?>%)
                            </div>
                            <div class="text-muted small mb-2">
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

    <!-- Results Table -->
    <div class="row">
        <div class="col-12">
            <?php if (empty($familyPledges)): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-info-circle mr-2"></i>
                    <?= gettext('No pledges found for the selected fiscal year') ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php else: ?>
                <div class="card finance-card shadow-sm border-0">
                    <div class="card-header bg-primary text-white py-2">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-handshake mr-1"></i>
                            <?= gettext('Family Pledges') ?>
                            <span class="badge badge-light ml-2"><?= count($familyPledges) ?></span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th><?= gettext('Family Name') ?></th>
                                        <?php if (SystemConfig::getBooleanValue('bUseDonationEnvelopes')): ?>
                                        <th><?= gettext('Envelope') ?></th>
                                        <?php endif; ?>
                                        <th><?= gettext('Fund Name') ?></th>
                                        <th class="text-right"><?= gettext('Pledge Amount') ?></th>
                                        <th class="text-right"><?= gettext('Payments') ?></th>
                                        <th class="text-right"><?= gettext('Remaining') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($familyPledges as $familyIdx => $family): ?>
                                        <?php
                                        $pledgeCount = count($family['pledges']);
                                        $isMultiplePledges = $pledgeCount > 1;
                                        ?>
                                        <?php foreach ($family['pledges'] as $idx => $pledge): ?>
                                            <tr <?= $isMultiplePledges ? 'style="border-left: 3px solid #007bff;"' : '' ?>>
                                                <td class="<?= $idx === 0 ? 'font-weight-bold' : 'text-muted small pl-4' ?>">
                                                    <?php if ($idx === 0): ?>
                                                        <a href="<?= SystemURLs::getRootPath() ?>/v2/family/<?= $family['family_id'] ?>">
                                                            <?= InputUtils::escapeHTML($family['family_name']) ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">↳</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if (SystemConfig::getBooleanValue('bUseDonationEnvelopes')): ?>
                                                <td class="text-muted small">
                                                    <?= $idx === 0 ? InputUtils::escapeHTML($family['envelope'] ?? '') : '' ?>
                                                </td>
                                                <?php endif; ?>
                                                <td><?= InputUtils::escapeHTML($pledge['fund_name']) ?></td>
                                                <td class="text-right font-weight-bold">
                                                    $<?= number_format($pledge['pledge_amount'], 2) ?>
                                                </td>
                                                <td class="text-right">
                                                    $<?= number_format($pledge['payment_amount'], 2) ?>
                                                </td>
                                                <?php
                                                $remaining = $pledge['pledge_amount'] - $pledge['payment_amount'];
                                                $percentComplete = $pledge['pledge_amount'] > 0 ? ($pledge['payment_amount'] / $pledge['pledge_amount']) * 100 : 0;
                                                if ($percentComplete >= 100) {
                                                    $statusClass = 'text-success font-weight-bold';
                                                } elseif ($percentComplete >= 75) {
                                                    $statusClass = 'text-info';
                                                } elseif ($percentComplete >= 50) {
                                                    $statusClass = 'text-warning';
                                                } else {
                                                    $statusClass = 'text-danger';
                                                }
                                                ?>
                                                <td class="text-right <?= $statusClass ?>">
                                                    $<?= number_format($remaining, 2) ?>
                                                    <small class="d-block text-muted"><?= number_format($percentComplete, 0) ?>%</small>
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

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
