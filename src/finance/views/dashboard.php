<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Use FinancialService for all dashboard data
$financialService = new FinancialService();
$dashboardData = $financialService->getDashboardData();

// Extract data for template use
$fiscalYear = $dashboardData['fiscalYear'];
$fyStartDate = $fiscalYear['startDate'];
$fyEndDate = $fiscalYear['endDate'];
$fyLabel = $fiscalYear['label'];
$iFYMonth = $fiscalYear['month'];

$depositStats = $dashboardData['depositStats'];
$totalDeposits = $depositStats['total'];
$openDeposits = $depositStats['open'];
$closedDeposits = $depositStats['closed'];

$recentDeposits = $dashboardData['recentDeposits'];
$activeFunds = $dashboardData['activeFunds'];
$activeFundCount = $dashboardData['activeFundCount'];
$totalFunds = $dashboardData['totalFundCount'];

$ytdPaymentTotal = $dashboardData['ytdPaymentTotal'];
$ytdPledgeTotal = $dashboardData['ytdPledgeTotal'];
$ytdPaymentCount = $dashboardData['ytdPaymentCount'];
$ytdDonorFamilies = $dashboardData['ytdDonorFamilies'];

$currentDeposit = $dashboardData['currentDeposit'];
$currentDepositId = $dashboardData['currentDepositId'];

$isAdmin = AuthenticationManager::getCurrentUser()->isAdmin();

$sRootPath = SystemURLs::getRootPath();
?>

<div class="container-fluid">

    <!-- Stat Cards Row -->
    <div class="row mb-3">
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
                            <div class="fw-medium">$<?= number_format($ytdPaymentTotal ?? 0, 2) ?></div>
                            <div class="text-muted"><?= gettext('YTD Payments') ?></div>
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
                            <span class="bg-primary text-white avatar rounded-circle">
                                <i class="fa-solid fa-file-signature icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium">$<?= number_format($ytdPledgeTotal ?? 0, 2) ?></div>
                            <div class="text-muted"><?= gettext('YTD Pledges') ?></div>
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
                            <span class="bg-secondary text-white avatar rounded-circle">
                                <i class="fa-solid fa-people-roof icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium"><?= number_format($ytdDonorFamilies ?? 0) ?></div>
                            <div class="text-muted"><?= gettext('Donor Families') ?></div>
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
                                <i class="fa-solid fa-receipt icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium"><?= number_format($ytdPaymentCount) ?></div>
                            <div class="text-muted"><?= gettext('Total Payments') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-bolt me-2"></i><?= gettext('Quick Actions') ?></h3>
            <div class="ms-auto text-muted small">
                <i class="fa-solid fa-calendar-days me-1"></i>
                <?= gettext('Fiscal Year') ?>: <strong><?= $fyLabel ?></strong>
            </div>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= $sRootPath ?>/FindDepositSlip.php" class="btn btn-success">
                    <i class="fa-solid fa-circle-plus me-1"></i><?= gettext('Create Deposit') ?>
                </a>
                <a href="<?= $sRootPath ?>/PledgeEditor.php?PledgeOrPayment=Pledge" class="btn btn-primary">
                    <i class="fa-solid fa-file-signature me-1"></i><?= gettext('Add Pledge') ?>
                </a>
                <?php if ($currentDeposit && !$currentDeposit->getClosed()): ?>
                <a href="<?= $sRootPath ?>/PledgeEditor.php?CurrentDeposit=<?= $currentDepositId ?>&PledgeOrPayment=Payment" class="btn btn-secondary">
                    <i class="fa-solid fa-hand-holding-dollar me-1"></i><?= gettext('Add Payment') ?>
                </a>
                <?php else: ?>
                <button type="button" class="btn btn-outline-secondary disabled" title="<?= gettext('Create or open a deposit first') ?>">
                    <i class="fa-solid fa-hand-holding-dollar me-1"></i><?= gettext('Add Payment') ?>
                </button>
                <?php endif; ?>
                <a href="<?= $sRootPath ?>/finance/reports" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-file-invoice-dollar me-1"></i><?= gettext('Reports') ?>
                </a>
                <a href="<?= $sRootPath ?>/finance/pledge/dashboard" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-handshake me-1"></i><?= gettext('Pledges') ?>
                </a>
                <?php if ($isAdmin): ?>
                <a href="<?= $sRootPath ?>/DonationFundEditor.php" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-piggy-bank me-1"></i><?= gettext('Manage Funds') ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Content Column -->
        <div class="col-lg-8">
            <!-- Tax Year Checklist -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-clipboard-check me-2"></i><?= gettext('Tax Year Reporting Checklist') ?></h3>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        <?= gettext('Complete these tasks to ensure accurate year-end tax reporting for your donors.') ?>
                    </p>

                    <div class="list-group list-group-flush">
                        <!-- Close All Deposits -->
                        <div class="list-group-item d-flex align-items-center px-0">
                            <div class="me-3">
                                <?php if ($openDeposits === 0): ?>
                                <span class="badge bg-green-lt text-green rounded-circle p-2"><i class="fa-solid fa-check"></i></span>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark rounded-circle p-2"><i class="fa-solid fa-exclamation"></i></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <strong><?= gettext('Close All Deposits') ?></strong>
                                <p class="mb-0 small text-muted"><?= gettext('Ensure all deposits for the tax year are closed before generating statements.') ?></p>
                            </div>
                            <div>
                                <?php if ($openDeposits > 0): ?>
                                <span class="badge bg-warning text-dark"><?= $openDeposits ?> <?= gettext('open') ?></span>
                                <?php endif; ?>
                                <a href="<?= $sRootPath ?>/FindDepositSlip.php" class="btn btn-sm btn-outline-secondary ms-2">
                                    <i class="fa-solid fa-eye"></i> <?= gettext('View') ?>
                                </a>
                            </div>
                        </div>

                        <!-- Review Donation Funds -->
                        <div class="list-group-item d-flex align-items-center px-0">
                            <div class="me-3">
                                <?php if ($activeFundCount > 0): ?>
                                <span class="badge bg-green-lt text-green rounded-circle p-2"><i class="fa-solid fa-check"></i></span>
                                <?php else: ?>
                                <span class="badge bg-danger rounded-circle p-2"><i class="fa-solid fa-times"></i></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <strong><?= gettext('Review Donation Funds') ?></strong>
                                <p class="mb-0 small text-muted"><?= gettext('Verify fund names and descriptions are accurate for statements.') ?></p>
                            </div>
                            <div>
                                <span class="badge bg-blue-lt text-blue"><?= $activeFundCount ?> <?= gettext('active') ?></span>
                                <a href="<?= $sRootPath ?>/DonationFundEditor.php" class="btn btn-sm btn-outline-secondary ms-2">
                                    <i class="fa-solid fa-cog"></i> <?= gettext('Edit') ?>
                                </a>
                            </div>
                        </div>

                        <!-- Update Church Info -->
                        <div class="list-group-item d-flex align-items-center px-0">
                            <div class="me-3">
                                <?php
                                $hasChurchInfo = !empty(SystemConfig::getValue('sChurchName')) && !empty(SystemConfig::getValue('sChurchAddress'));
                                ?>
                                <?php if ($hasChurchInfo): ?>
                                <span class="badge bg-green-lt text-green rounded-circle p-2"><i class="fa-solid fa-check"></i></span>
                                <?php else: ?>
                                <span class="badge bg-danger rounded-circle p-2"><i class="fa-solid fa-times"></i></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <strong><?= gettext('Church Information') ?></strong>
                                <p class="mb-0 small text-muted"><?= gettext('Verify church name, address, and contact info appears on tax statements.') ?></p>
                            </div>
                            <div>
                                <?php if ($isAdmin): ?>
                                <a href="<?= $sRootPath ?>/admin/system/church-info" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-cog"></i> <?= gettext('Settings') ?>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Review Tax Report Text -->
                        <div class="list-group-item d-flex align-items-center px-0">
                            <div class="me-3">
                                <span class="badge bg-info rounded-circle p-2"><i class="fa-solid fa-file-lines"></i></span>
                            </div>
                            <div class="flex-grow-1">
                                <strong><?= gettext('Tax Report Verbiage') ?></strong>
                                <p class="mb-0 small text-muted"><?= gettext('Customize the text that appears on tax statements (sTaxReport1, sTaxReport2, etc).') ?></p>
                            </div>
                            <div>
                                <a href="<?= $sRootPath ?>/SystemSettings.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-pen-to-square"></i> <?= gettext('Edit') ?>
                                </a>
                            </div>
                        </div>

                        <!-- Generate Tax Statements -->
                        <div class="list-group-item d-flex align-items-center px-0">
                            <div class="me-3">
                                <span class="badge bg-primary rounded-circle p-2"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                            </div>
                            <div class="flex-grow-1">
                                <strong><?= gettext('Generate Tax Statements') ?></strong>
                                <p class="mb-0 small text-muted"><?= gettext('Print or email annual giving statements to all donors.') ?></p>
                            </div>
                            <div>
                                <a href="<?= $sRootPath ?>/finance/reports" class="btn btn-sm btn-primary">
                                    <i class="fa-solid fa-play"></i> <?= gettext('Generate') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Deposits -->
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title"><i class="fa-solid fa-clock-rotate-left me-2"></i><?= gettext('Recent Deposits') ?></h3>
                    <a href="<?= $sRootPath ?>/FindDepositSlip.php" class="btn btn-sm btn-outline-secondary ms-auto">
                        <i class="fa-solid fa-list me-1"></i><?= gettext('View All') ?>
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if ($recentDeposits->count() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-vcenter mb-0">
                            <thead>
                                <tr>
                                    <th><?= gettext('ID') ?></th>
                                    <th><?= gettext('Date') ?></th>
                                    <th><?= gettext('Type') ?></th>
                                    <th><?= gettext('Comment') ?></th>
                                    <th class="text-end"><?= gettext('Total') ?></th>
                                    <th><?= gettext('Status') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentDeposits as $deposit): ?>
                                <tr class="<?= ($deposit->getId() === $currentDepositId) ? 'table-active' : '' ?>">
                                    <td>
                                        <a href="<?= $sRootPath ?>/DepositSlipEditor.php?DepositSlipID=<?= $deposit->getId() ?>">
                                            #<?= $deposit->getId() ?>
                                        </a>
                                    </td>
                                    <td><?= $deposit->getDate('M j, Y') ?></td>
                                    <td><span class="badge bg-blue-lt text-blue"><?= $deposit->getType() ?></span></td>
                                    <td class="text-truncate finance-truncate"><?= InputUtils::escapeHTML($deposit->getComment() ?? '') ?></td>
                                    <td class="text-end fw-bold">$<?= number_format($deposit->getVirtualColumn('totalAmount') ?? 0, 2) ?></td>
                                    <td>
                                        <?php if ($deposit->getClosed()): ?>
                                        <span class="badge bg-green-lt text-green"><?= gettext('Closed') ?></span>
                                        <?php else: ?>
                                        <span class="badge bg-warning text-dark"><?= gettext('Open') ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty">
                        <div class="empty-icon"><i class="fa-solid fa-inbox fa-3x text-muted"></i></div>
                        <p class="empty-title"><?= gettext('No deposits found.') ?></p>
                        <a href="<?= $sRootPath ?>/FindDepositSlip.php" class="btn btn-primary">
                            <i class="fa-solid fa-plus me-1"></i><?= gettext('Create First Deposit') ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Current Deposit Card -->
            <?php if ($currentDeposit): ?>
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title"><i class="fa-solid fa-file-invoice me-2"></i><?= gettext('Current Deposit') ?></h3>
                    <?php if (!$currentDeposit->getClosed()): ?>
                    <span class="badge bg-azure-lt text-azure ms-auto"><?= gettext('Open') ?></span>
                    <?php else: ?>
                    <span class="badge bg-green-lt text-green ms-auto"><?= gettext('Closed') ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted"><?= gettext('Deposit') ?> #:</span>
                        <strong><?= $currentDeposit->getId() ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted"><?= gettext('Date') ?>:</span>
                        <span><?= $currentDeposit->getDate('M j, Y') ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted"><?= gettext('Type') ?>:</span>
                        <span class="badge bg-blue-lt text-blue"><?= $currentDeposit->getType() ?></span>
                    </div>
                    <hr>
                    <div class="text-center mb-3">
                        <div class="h3 text-success mb-0">$<?= number_format($currentDeposit->getVirtualColumn('totalAmount') ?? 0, 2) ?></div>
                        <small class="text-muted"><?= gettext('Total Amount') ?></small>
                    </div>
                    <a href="<?= $sRootPath ?>/DepositSlipEditor.php?DepositSlipID=<?= $currentDeposit->getId() ?>" class="btn btn-primary w-100">
                        <i class="fa-solid fa-pen-to-square me-1"></i><?= gettext('Edit Deposit') ?>
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title text-muted"><i class="fa-solid fa-file-invoice me-2"></i><?= gettext('No Active Deposit') ?></h3>
                </div>
                <div class="card-body">
                    <div class="empty">
                        <div class="empty-icon"><i class="fa-solid fa-circle-plus fa-3x text-muted"></i></div>
                        <p class="empty-title"><?= gettext('Create or select a deposit to get started.') ?></p>
                        <a href="<?= $sRootPath ?>/FindDepositSlip.php" class="btn btn-success">
                            <i class="fa-solid fa-plus me-1"></i><?= gettext('Create Deposit') ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Deposit Statistics -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-chart-pie me-2"></i><?= gettext('Deposit Statistics') ?></h3>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span><?= gettext('Total Deposits:') ?></span>
                        <strong><?= $totalDeposits ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span><?= gettext('Open Deposits:') ?></span>
                        <span class="badge bg-warning text-dark"><?= $openDeposits ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span><?= gettext('Closed Deposits:') ?></span>
                        <span class="badge bg-green-lt text-green"><?= $closedDeposits ?></span>
                    </div>

                    <?php if ($totalDeposits > 0): ?>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= ($closedDeposits / $totalDeposits) * 100 ?>%">
                            <?= round(($closedDeposits / $totalDeposits) * 100) ?>%
                        </div>
                    </div>
                    <small class="text-muted"><?= gettext('Closed vs Total') ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Active Funds -->
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title"><i class="fa-solid fa-piggy-bank me-2"></i><?= gettext('Donation Funds') ?></h3>
                    <span class="badge bg-blue-lt text-blue ms-auto"><?= $activeFundCount ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if ($activeFunds->count() > 0): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($activeFunds as $fund): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <span><?= InputUtils::escapeHTML($fund->getName()) ?></span>
                            <span class="badge bg-green-lt text-green">
                                <i class="fa-solid fa-check"></i>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <div class="empty py-3">
                        <p class="empty-title"><?= gettext('No active funds configured.') ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- System Settings Panel Component -->
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
    window.CRM.settingsPanel.init({
        container: '#financialSettings',
        title: <?= json_encode(gettext('Financial Settings'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        icon: 'fa-solid fa-sliders',
        settings: [
            { name: 'iFYMonth',          type: 'choice', label: <?= json_encode(gettext('First month of the fiscal year'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, choices: <?= json_encode(SystemConfig::getChoices('iFYMonth'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> },
            { name: 'sDepositSlipType',  type: 'choice', label: <?= json_encode(gettext('Deposit ticket type'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, tooltip: <?= json_encode(SystemConfig::getTooltip('sDepositSlipType'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, choices: <?= json_encode(SystemConfig::getChoices('sDepositSlipType'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> },
            { name: 'iChecksPerDepositForm', type: 'number',  label: <?= json_encode(gettext('Number of checks for Deposit Slip Report'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, min: 1, max: 100 },
            { name: 'bDisplayBillCounts',    type: 'boolean', label: <?= json_encode(gettext('Display bill counts on deposit slip'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> },
            { name: 'bUseScannedChecks',     type: 'boolean', label: <?= json_encode(gettext('Enable use of scanned checks'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> },
            { name: 'bEnableNonDeductible',  type: 'boolean', label: <?= json_encode(gettext('Enable non-deductible payments'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> },
            { name: 'bUseDonationEnvelopes', type: 'boolean', label: <?= json_encode(gettext('Enable use of donation envelopes'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> },
            { name: 'aFinanceQueries',       type: 'text',    label: <?= json_encode(gettext('Finance permission query IDs'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, placeholder: '30,31,32' }
        ],
        onSave: function() {
            // Reload page after short delay to show updated fiscal year data
            setTimeout(function() {
                window.location.reload();
            }, 1500);
        }
    });
});
</script>
<?php endif; ?>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
