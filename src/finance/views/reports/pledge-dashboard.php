<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\FinancialService;

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
                                <?= htmlspecialchars($year['label']) ?>
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
            <div class="card border-left-success shadow h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                <?= gettext('Total Pledges') ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                $<?= number_format($totalPledges, 2) ?>
                            </div>
                            <div class="text-xs text-muted mt-1">
                                <?= FinancialService::formatFiscalYear($selectedFyid) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fa-solid fa-handshake fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fund Summary Cards -->
        <?php if (!empty($fundTotals)): ?>
            <?php foreach ($fundTotals as $fundTotal): ?>
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card border-left-primary shadow h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        <?= htmlspecialchars($fundTotal['fund_name']) ?>
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        $<?= number_format($fundTotal['total_amount'], 2) ?>
                                    </div>
                                    <div class="text-xs text-muted mt-1">
                                        <?= $fundTotal['family_count'] ?> <?= $fundTotal['family_count'] == 1 ? gettext('Family') : gettext('Families') ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fa-solid fa-donate fa-2x text-gray-300"></i>
                                </div>
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
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <?= gettext('Family Pledges') ?> 
                            <span class="badge badge-primary ml-2"><?= count($familyPledges) ?></span>
                        </h6>
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
