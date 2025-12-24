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

?>

<div class="container-fluid">
    <!-- Fiscal Year Info -->
    <div class="row mb-3">
        <div class="col-12 d-flex align-items-center">
            <p class="text-muted mb-0 flex-grow-1">
                <i class="fa-solid fa-calendar-alt mr-1"></i>
                <?= gettext('Fiscal Year') ?>: <strong><?= $fyLabel ?></strong> 
                (<?= date('M j, Y', strtotime($fyStartDate)) ?> - <?= date('M j, Y', strtotime($fyEndDate)) ?>)
            </p>
            <?php if ($isAdmin): ?>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#financialSettings" aria-expanded="false" aria-controls="financialSettings">
                <i class="fa-solid fa-cog"></i> <?= gettext('Financial Settings') ?>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <!-- Financial Settings (Admin Only) - Uses reusable settings panel component -->
    <div class="collapse mb-3" id="financialSettings"></div>
    <?php endif; ?>

    <!-- Key Metrics Row -->
    <div class="row">
        <!-- YTD Payments -->
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card finance-card shadow-sm border-0 h-100">
                <div class="card-body text-center py-4 finance-metric-card metric-payments">
                    <div class="finance-metric-value">
                        $<?= number_format($ytdPaymentTotal ?? 0, 2) ?>
                    </div>
                    <div class="text-white-50 text-uppercase small font-weight-bold mt-2 finance-metric-label">
                        <?= gettext('YTD Payments') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- YTD Pledges -->
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card finance-card shadow-sm border-0 h-100">
                <div class="card-body text-center py-4 finance-metric-card metric-pledges">
                    <div class="finance-metric-value">
                        $<?= number_format($ytdPledgeTotal ?? 0, 2) ?>
                    </div>
                    <div class="text-white-50 text-uppercase small font-weight-bold mt-2 finance-metric-label">
                        <?= gettext('YTD Pledges') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Donor Families -->
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card finance-card shadow-sm border-0 h-100">
                <div class="card-body text-center py-4 finance-metric-card metric-donors">
                    <div class="finance-metric-value">
                        <?= number_format($ytdDonorFamilies ?? 0) ?>
                    </div>
                    <div class="text-white-50 text-uppercase small font-weight-bold mt-2 finance-metric-label">
                        <?= gettext('Donor Families') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Count -->
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card finance-card shadow-sm border-0 h-100">
                <div class="card-body text-center py-4 finance-metric-card metric-count">
                    <div class="finance-metric-value">
                        <?= number_format($ytdPaymentCount) ?>
                    </div>
                    <div class="text-white-50 text-uppercase small font-weight-bold mt-2 finance-metric-label">
                        <?= gettext('Total Payments') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Content Column -->
        <div class="col-lg-8">
            <!-- Quick Actions -->
            <div class="card finance-card shadow-sm border-0 mb-4">
                <div class="card-header bg-primary text-white py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-bolt"></i> <?= gettext('Quick Actions') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Create Deposit -->
                        <div class="col-md-3 mb-3">
                            <a href="<?= SystemURLs::getRootPath() ?>/FindDepositSlip.php" class="btn btn-lg btn-outline-success btn-block py-3">
                                <i class="fa-solid fa-plus-circle fa-2x mb-2 d-block"></i>
                                <?= gettext('Create Deposit') ?>
                            </a>
                        </div>
                        
                        <!-- Add Pledge -->
                        <div class="col-md-3 mb-3">
                            <a href="<?= SystemURLs::getRootPath() ?>/PledgeEditor.php?PledgeOrPayment=Pledge" class="btn btn-lg btn-outline-warning btn-block py-3">
                                <i class="fa-solid fa-file-signature fa-2x mb-2 d-block"></i>
                                <?= gettext('Add Pledge') ?>
                            </a>
                        </div>
                        
                        <!-- Add Payment -->
                        <div class="col-md-3 mb-3">
                            <?php if ($currentDeposit && !$currentDeposit->getClosed()): ?>
                            <a href="<?= SystemURLs::getRootPath() ?>/PledgeEditor.php?CurrentDeposit=<?= $currentDepositId ?>&PledgeOrPayment=Payment" class="btn btn-lg btn-outline-primary btn-block py-3">
                                <i class="fa-solid fa-hand-holding-dollar fa-2x mb-2 d-block"></i>
                                <?= gettext('Add Payment') ?>
                            </a>
                            <?php else: ?>
                            <button type="button" class="btn btn-lg btn-outline-secondary btn-block py-3" disabled title="<?= gettext('Create or open a deposit first') ?>">
                                <i class="fa-solid fa-hand-holding-dollar fa-2x mb-2 d-block"></i>
                                <?= gettext('Add Payment') ?>
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Generate Reports -->
                        <div class="col-md-3 mb-3">
                            <a href="<?= SystemURLs::getRootPath() ?>/finance/reports" class="btn btn-lg btn-outline-info btn-block py-3">
                                <i class="fa-solid fa-file-invoice-dollar fa-2x mb-2 d-block"></i>
                                <?= gettext('Generate Reports') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tax Year Checklist -->
            <div class="card finance-card shadow-sm border-0 mb-4">
                <div class="card-header bg-warning py-2">
                    <h5 class="mb-0 text-dark">
                        <i class="fa-solid fa-clipboard-check"></i> <?= gettext('Tax Year Reporting Checklist') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        <?= gettext('Complete these tasks to ensure accurate year-end tax reporting for your donors.') ?>
                    </p>
                    
                    <div class="list-group list-group-flush">
                        <!-- Close All Deposits -->
                        <div class="list-group-item d-flex align-items-center px-0">
                            <div class="mr-3">
                                <?php if ($openDeposits === 0): ?>
                                <span class="badge badge-success rounded-circle p-2"><i class="fa-solid fa-check"></i></span>
                                <?php else: ?>
                                <span class="badge badge-warning rounded-circle p-2"><i class="fa-solid fa-exclamation"></i></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <strong><?= gettext('Close All Deposits') ?></strong>
                                <p class="mb-0 small text-muted"><?= gettext('Ensure all deposits for the tax year are closed before generating statements.') ?></p>
                            </div>
                            <div>
                                <?php if ($openDeposits > 0): ?>
                                <span class="badge badge-pill badge-warning"><?= $openDeposits ?> <?= gettext('open') ?></span>
                                <?php endif; ?>
                                <a href="<?= SystemURLs::getRootPath() ?>/FindDepositSlip.php" class="btn btn-sm btn-outline-secondary ml-2">
                                    <i class="fa-solid fa-eye"></i> <?= gettext('View') ?>
                                </a>
                            </div>
                        </div>

                        <!-- Review Donation Funds -->
                        <div class="list-group-item d-flex align-items-center px-0">
                            <div class="mr-3">
                                <?php if ($activeFundCount > 0): ?>
                                <span class="badge badge-success rounded-circle p-2"><i class="fa-solid fa-check"></i></span>
                                <?php else: ?>
                                <span class="badge badge-danger rounded-circle p-2"><i class="fa-solid fa-times"></i></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <strong><?= gettext('Review Donation Funds') ?></strong>
                                <p class="mb-0 small text-muted"><?= gettext('Verify fund names and descriptions are accurate for statements.') ?></p>
                            </div>
                            <div>
                                <span class="badge badge-pill badge-info"><?= $activeFundCount ?> <?= gettext('active') ?></span>
                                <a href="<?= SystemURLs::getRootPath() ?>/DonationFundEditor.php" class="btn btn-sm btn-outline-secondary ml-2">
                                    <i class="fa-solid fa-cog"></i> <?= gettext('Edit') ?>
                                </a>
                            </div>
                        </div>

                        <!-- Update Church Info -->
                        <div class="list-group-item d-flex align-items-center px-0">
                            <div class="mr-3">
                                <?php 
                                $hasChurchInfo = !empty(SystemConfig::getValue('sChurchName')) && !empty(SystemConfig::getValue('sChurchAddress'));
                                ?>
                                <?php if ($hasChurchInfo): ?>
                                <span class="badge badge-success rounded-circle p-2"><i class="fa-solid fa-check"></i></span>
                                <?php else: ?>
                                <span class="badge badge-danger rounded-circle p-2"><i class="fa-solid fa-times"></i></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <strong><?= gettext('Church Information') ?></strong>
                                <p class="mb-0 small text-muted"><?= gettext('Verify church name, address, and contact info appears on tax statements.') ?></p>
                            </div>
                            <div>
                                <a href="<?= SystemURLs::getRootPath() ?>/SystemSettings.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-cog"></i> <?= gettext('Settings') ?>
                                </a>
                            </div>
                        </div>

                        <!-- Review Tax Report Text -->
                        <div class="list-group-item d-flex align-items-center px-0">
                            <div class="mr-3">
                                <span class="badge badge-info rounded-circle p-2"><i class="fa-solid fa-file-alt"></i></span>
                            </div>
                            <div class="flex-grow-1">
                                <strong><?= gettext('Tax Report Verbiage') ?></strong>
                                <p class="mb-0 small text-muted"><?= gettext('Customize the text that appears on tax statements (sTaxReport1, sTaxReport2, etc).') ?></p>
                            </div>
                            <div>
                                <a href="<?= SystemURLs::getRootPath() ?>/SystemSettings.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-edit"></i> <?= gettext('Edit') ?>
                                </a>
                            </div>
                        </div>

                        <!-- Generate Tax Statements -->
                        <div class="list-group-item d-flex align-items-center px-0 bg-light">
                            <div class="mr-3">
                                <span class="badge badge-primary rounded-circle p-2"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                            </div>
                            <div class="flex-grow-1">
                                <strong><?= gettext('Generate Tax Statements') ?></strong>
                                <p class="mb-0 small text-muted"><?= gettext('Print or email annual giving statements to all donors.') ?></p>
                            </div>
                            <div>
                                <a href="<?= SystemURLs::getRootPath() ?>/finance/reports" class="btn btn-sm btn-primary">
                                    <i class="fa-solid fa-play"></i> <?= gettext('Generate') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Deposits -->
            <div class="card finance-card shadow-sm border-0 mb-4">
                <div class="card-header bg-secondary text-white py-2 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-clock-rotate-left"></i> <?= gettext('Recent Deposits') ?>
                    </h5>
                    <a href="<?= SystemURLs::getRootPath() ?>/FindDepositSlip.php" class="btn btn-sm btn-outline-light">
                        <i class="fa-solid fa-list mr-1"></i><?= gettext('View All Deposits') ?>
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if ($recentDeposits->count() > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th><?= gettext('ID') ?></th>
                                    <th><?= gettext('Date') ?></th>
                                    <th><?= gettext('Type') ?></th>
                                    <th><?= gettext('Comment') ?></th>
                                    <th class="text-right"><?= gettext('Total') ?></th>
                                    <th><?= gettext('Status') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentDeposits as $deposit): ?>
                                <tr class="<?= ($deposit->getId() === $currentDepositId) ? 'table-active' : '' ?>">
                                    <td>
                                        <a href="<?= SystemURLs::getRootPath() ?>/DepositSlipEditor.php?DepositSlipID=<?= $deposit->getId() ?>">
                                            #<?= $deposit->getId() ?>
                                        </a>
                                    </td>
                                    <td><?= $deposit->getDate('M j, Y') ?></td>
                                    <td><span class="badge badge-info"><?= $deposit->getType() ?></span></td>
                                    <td class="text-truncate finance-truncate"><?= InputUtils::escapeHTML($deposit->getComment() ?? '') ?></td>
                                    <td class="text-right font-weight-bold">$<?= number_format($deposit->getVirtualColumn('totalAmount') ?? 0, 2) ?></td>
                                    <td>
                                        <?php if ($deposit->getClosed()): ?>
                                        <span class="badge badge-success"><?= gettext('Closed') ?></span>
                                        <?php else: ?>
                                        <span class="badge badge-warning"><?= gettext('Open') ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fa-solid fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted"><?= gettext('No deposits found.') ?></p>
                        <a href="<?= SystemURLs::getRootPath() ?>/FindDepositSlip.php" class="btn btn-primary">
                            <i class="fa-solid fa-plus"></i> <?= gettext('Create First Deposit') ?>
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
            <div class="card finance-card shadow-sm border-0 mb-4">
                <div class="card-header <?= $currentDeposit->getClosed() ? 'bg-secondary' : 'bg-success' ?> text-white py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-file-invoice"></i> <?= gettext('Current Deposit') ?>
                    </h5>
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
                        <span class="text-muted"><?= gettext('Type:') ?></span>
                        <span class="badge badge-info"><?= $currentDeposit->getType() ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted"><?= gettext('Status:') ?></span>
                        <?php if ($currentDeposit->getClosed()): ?>
                        <span class="badge badge-secondary"><?= gettext('Closed') ?></span>
                        <?php else: ?>
                        <span class="badge badge-success"><?= gettext('Open') ?></span>
                        <?php endif; ?>
                    </div>
                    <hr>
                    <div class="text-center mb-3">
                        <div class="h3 text-success mb-0">$<?= number_format($currentDeposit->getVirtualColumn('totalAmount') ?? 0, 2) ?></div>
                        <small class="text-muted"><?= gettext('Total Amount') ?></small>
                    </div>
                    <a href="<?= SystemURLs::getRootPath() ?>/DepositSlipEditor.php?DepositSlipID=<?= $currentDeposit->getId() ?>" class="btn btn-primary btn-block">
                        <i class="fa-solid fa-edit"></i> <?= gettext('Edit Deposit') ?>
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="card finance-card shadow-sm border-0 mb-4">
                <div class="card-header bg-light py-2">
                    <h5 class="mb-0 text-muted">
                        <i class="fa-solid fa-file-invoice"></i> <?= gettext('No Active Deposit') ?>
                    </h5>
                </div>
                <div class="card-body text-center py-4">
                    <i class="fa-solid fa-plus-circle fa-3x text-muted mb-3"></i>
                    <p class="text-muted"><?= gettext('Create or select a deposit to get started.') ?></p>
                    <a href="<?= SystemURLs::getRootPath() ?>/FindDepositSlip.php" class="btn btn-success">
                        <i class="fa-solid fa-plus"></i> <?= gettext('Create Deposit') ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Deposit Statistics -->
            <div class="card finance-card shadow-sm border-0 mb-4">
                <div class="card-header bg-info text-white py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-chart-pie"></i> <?= gettext('Deposit Statistics') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span><?= gettext('Total Deposits:') ?></span>
                        <strong><?= $totalDeposits ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span><?= gettext('Open Deposits:') ?></span>
                        <span class="badge badge-warning"><?= $openDeposits ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span><?= gettext('Closed Deposits:') ?></span>
                        <span class="badge badge-success"><?= $closedDeposits ?></span>
                    </div>
                    
                    <?php if ($totalDeposits > 0): ?>
                    <div class="progress finance-progress">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= ($closedDeposits / $totalDeposits) * 100 ?>%">
                            <?= round(($closedDeposits / $totalDeposits) * 100) ?>%
                        </div>
                    </div>
                    <small class="text-muted"><?= gettext('Closed vs Total') ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Active Funds -->
            <div class="card finance-card shadow-sm border-0 mb-4">
                <div class="card-header bg-dark text-white py-2 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-piggy-bank"></i> <?= gettext('Donation Funds') ?>
                    </h5>
                    <span class="badge badge-light"><?= $activeFundCount ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if ($activeFunds->count() > 0): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($activeFunds as $fund): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <span><?= InputUtils::escapeHTML($fund->getName()) ?></span>
                            <span class="badge badge-success badge-pill">
                                <i class="fa-solid fa-check"></i>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <div class="text-center py-3">
                        <p class="text-muted mb-0"><?= gettext('No active funds configured.') ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (AuthenticationManager::getCurrentUser()->isAdmin()): ?>
                    <div class="card-footer bg-light">
                        <a href="<?= SystemURLs::getRootPath() ?>/DonationFundEditor.php" class="btn btn-sm btn-outline-secondary btn-block">
                            <i class="fa-solid fa-cog"></i> <?= gettext('Manage Funds') ?>
                        </a>
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
    // Initialize the settings panel with financial settings
    window.CRM.settingsPanel.init({
        container: '#financialSettings',
        title: i18next.t('Financial Settings'),
        icon: 'fa-solid fa-sliders',
        settings: [
            'iFYMonth',
            'sDepositSlipType',
            'iChecksPerDepositForm',
            'bDisplayBillCounts',
            'bUseScannedChecks',
            'bEnableNonDeductible',
            'bUseDonationEnvelopes',
            'aFinanceQueries'
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
