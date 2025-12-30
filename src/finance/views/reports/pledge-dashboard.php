<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\FinancialService;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="container-fluid">
    <!-- Page Controls -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-end">
                <a href="<?= SystemURLs::getRootPath() ?>/PledgeEditor.php?PledgeOrPayment=Pledge" class="btn btn-primary">
                    <i class="fa-solid fa-plus mr-1"></i>
                    <?= gettext('Add New') . ' ' . gettext('Pledge') ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Fiscal Year Selector -->
    <div class="row mb-4">
        <div class="col-12 col-sm-6 col-md-4">
            <div class="card">
                <div class="card-body">
                    <form method="GET">
                        <div class="form-group">
                            <label for="fyid" class="font-weight-bold"><?= gettext('Fiscal Year') ?></label>
                            <select name="fyid" id="fyid" class="form-control" onchange="this.form.submit();">
                                <?php foreach ($availableYears as $year): ?>
                                    <option value="<?= $year['id'] ?>" <?= $year['id'] == $selectedFyid ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($year['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <small class="form-text text-muted">
                            <?= gettext('Current Fiscal Year') ?>: <strong><?= FinancialService::formatFiscalYear($currentFyid) ?></strong>
                        </small>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Fund Totals Summary -->
    <?php if (!empty($fundTotals) || !empty($totalPledges)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 font-weight-bold"><?= gettext('Pledges Overview') ?></h5>
                </div>
                <div class="card-body">
                    <!-- Total Pledges Card -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-left-success shadow-sm h-100 bg-light">
                                <div class="card-body py-3">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-2">
                                        <?= gettext('Total Pledges') . ' - ' . FinancialService::formatFiscalYear($selectedFyid) ?>
                                    </div>
                                    <div class="h3 mb-0 font-weight-bold text-gray-800">
                                        $<?= number_format($totalPledges, 2) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fund Summary Cards -->
                    <?php if (!empty($fundTotals)): ?>
                    <div class="row">
                        <div class="col-12 mb-2">
                            <h6 class="mb-3 text-muted font-weight-bold text-uppercase small"><?= gettext('By Fund') ?></h6>
                        </div>
                        <?php foreach ($fundTotals as $fundTotal): ?>
                            <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                <div class="card border-left-primary shadow-sm h-100">
                                    <div class="card-body py-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            <?= htmlspecialchars($fundTotal['fund_name']) ?>
                                        </div>
                                        <div class="h5 mb-1 font-weight-bold text-gray-800">
                                            $<?= number_format($fundTotal['total_amount'], 2) ?>
                                        </div>
                                        <div class="text-xs text-muted">
                                            <?= $fundTotal['family_count'] ?> <?= $fundTotal['family_count'] == 1 ? gettext('Family') : gettext('Families') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-bottom">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="mb-0 font-weight-bold"><?= gettext('Family Pledges') ?></h5>
                            </div>
                            <div class="col-auto">
                                <span class="badge badge-primary badge-lg"><?= count($familyPledges) ?> <?= gettext('Families') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th><?= gettext('Family Name') ?></th>
                                        <?php if (SystemConfig::getBooleanValue('bUseDonationEnvelopes')): ?>
                                        <th><?= gettext('Envelope') ?></th>
                                        <?php endif; ?>
                                        <th><?= gettext('Fund Name') ?></th>
                                        <th class="text-right"><?= gettext('Pledge Amount') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($familyPledges as $familyIdx => $family): ?>
                                        <?php 
                                        $pledgeCount = count($family['pledges']);
                                        $isMultiplePledges = $pledgeCount > 1;
                                        ?>
                                        <?php foreach ($family['pledges'] as $idx => $pledge): ?>
                                            <tr class="<?= $isMultiplePledges ? 'border-left border-primary border-3' : '' ?>">
                                                <td class="<?= $idx === 0 ? 'font-weight-bold' : 'text-muted small pl-4' ?>">
                                                    <?php if ($idx === 0): ?>
                                                        <a href="<?= SystemURLs::getRootPath() ?>/v2/family/<?= $family['family_id'] ?>">
                                                            <?= htmlspecialchars($family['family_name']) ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">↳</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if (SystemConfig::getBooleanValue('bUseDonationEnvelopes')): ?>
                                                <td class="text-muted small">
                                                    <?= $idx === 0 ? htmlspecialchars($family['envelope'] ?? '') : '' ?>
                                                </td>
                                                <?php endif; ?>
                                                <td><?= htmlspecialchars($pledge['fund_name']) ?></td>
                                                <td class="text-right font-weight-bold">
                                                    $<?= number_format($pledge['pledge_amount'], 2) ?>
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
