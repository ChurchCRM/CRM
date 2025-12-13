<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Get fiscal year info for display
$iFYMonth = (int) SystemConfig::getValue('iFYMonth');
$currentYear = (int) date('Y');

if ($iFYMonth === 1) {
    $fyLabel = (string) $currentYear;
} else {
    $currentMonth = (int) date('n');
    if ($currentMonth >= $iFYMonth) {
        $fyLabel = $currentYear . '/' . substr($currentYear + 1, 2, 2);
    } else {
        $fyLabel = ($currentYear - 1) . '/' . substr($currentYear, 2, 2);
    }
}

$bCSVAdminOnly = SystemConfig::getBooleanValue('bCSVAdminOnly');
$isAdmin = AuthenticationManager::getCurrentUser()->isAdmin();

?>

<div class="container-fluid">
    <!-- Page Description -->
    <div class="row mb-3">
        <div class="col-12">
            <p class="text-muted mb-0">
                <?= gettext('Generate reports for tax statements, pledge tracking, and financial analysis.') ?>
            </p>
        </div>
    </div>

    <div class="row">
        <!-- Tax & Giving Reports -->
        <div class="col-lg-6 mb-4">
            <div class="card finance-card shadow-sm border-0 h-100">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-receipt"></i> <?= gettext('Tax & Giving Reports') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        <?= gettext('Generate annual giving statements for donors and identify giving patterns.') ?>
                    </p>
                    
                    <div class="list-group list-group-flush">
                        <!-- Giving Report (Tax Statements) -->
                        <a href="<?= SystemURLs::getRootPath() ?>/FinancialReports.php?ReportType=Giving%20Report" class="list-group-item list-group-item-action finance-list-group-item py-3">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="fa-solid fa-file-invoice text-success mr-2"></i>
                                        <?= gettext('Giving Report (Tax Statements)') ?>
                                    </h6>
                                    <small class="text-muted"><?= gettext('Generate annual tax-deductible giving statements for donors. Can be printed or emailed.') ?></small>
                                </div>
                                <span class="badge badge-success badge-pill"><?= gettext('PDF') ?></span>
                            </div>
                        </a>
                        
                        <!-- Zero Givers -->
                        <a href="<?= SystemURLs::getRootPath() ?>/FinancialReports.php?ReportType=Zero%20Givers" class="list-group-item list-group-item-action finance-list-group-item py-3">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="fa-solid fa-user-slash text-warning mr-2"></i>
                                        <?= gettext('Zero Givers') ?>
                                    </h6>
                                    <small class="text-muted"><?= gettext('Identify members who have not made any donations within a date range.') ?></small>
                                </div>
                                <span class="badge badge-warning badge-pill"><?= gettext('PDF') ?></span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pledge Reports -->
        <div class="col-lg-6 mb-4">
            <div class="card finance-card shadow-sm border-0 h-100">
                <div class="card-header bg-info text-white py-3">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-hand-holding-dollar"></i> <?= gettext('Pledge Reports') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        <?= gettext('Track pledges and payment progress for campaigns and fiscal year budgeting.') ?>
                    </p>
                    
                    <div class="list-group list-group-flush">
                        <!-- Pledge Summary -->
                        <a href="<?= SystemURLs::getRootPath() ?>/FinancialReports.php?ReportType=Pledge%20Summary" class="list-group-item list-group-item-action finance-list-group-item py-3">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="fa-solid fa-chart-bar text-info mr-2"></i>
                                        <?= gettext('Pledge Summary') ?>
                                    </h6>
                                    <small class="text-muted"><?= gettext('Summary of pledges vs payments by fund for the fiscal year.') ?></small>
                                </div>
                                <span class="badge badge-info badge-pill"><?= gettext('PDF/CSV') ?></span>
                            </div>
                        </a>
                        
                        <!-- Pledge Family Summary -->
                        <a href="<?= SystemURLs::getRootPath() ?>/FinancialReports.php?ReportType=Pledge%20Family%20Summary" class="list-group-item list-group-item-action finance-list-group-item py-3">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="fa-solid fa-users text-info mr-2"></i>
                                        <?= gettext('Pledge Family Summary') ?>
                                    </h6>
                                    <small class="text-muted"><?= gettext('Detailed breakdown of pledges and payments by family.') ?></small>
                                </div>
                                <span class="badge badge-info badge-pill"><?= gettext('PDF') ?></span>
                            </div>
                        </a>
                        
                        <!-- Pledge Reminders -->
                        <a href="<?= SystemURLs::getRootPath() ?>/FinancialReports.php?ReportType=Pledge%20Reminders" class="list-group-item list-group-item-action finance-list-group-item py-3">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="fa-solid fa-bell text-warning mr-2"></i>
                                        <?= gettext('Pledge Reminders') ?>
                                    </h6>
                                    <small class="text-muted"><?= gettext('Generate reminder letters for families with outstanding pledges.') ?></small>
                                </div>
                                <span class="badge badge-warning badge-pill"><?= gettext('PDF') ?></span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deposit Reports -->
        <div class="col-lg-6 mb-4">
            <div class="card finance-card shadow-sm border-0 h-100">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-cash-register"></i> <?= gettext('Deposit Reports') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        <?= gettext('Generate detailed reports for individual deposits or date ranges.') ?>
                    </p>
                    
                    <div class="list-group list-group-flush">
                        <!-- Individual Deposit Report -->
                        <a href="<?= SystemURLs::getRootPath() ?>/FinancialReports.php?ReportType=Individual%20Deposit%20Report" class="list-group-item list-group-item-action finance-list-group-item py-3">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="fa-solid fa-file-invoice text-primary mr-2"></i>
                                        <?= gettext('Individual Deposit Report') ?>
                                    </h6>
                                    <small class="text-muted"><?= gettext('Detailed breakdown of a single deposit slip.') ?></small>
                                </div>
                                <span class="badge badge-primary badge-pill"><?= gettext('PDF/CSV') ?></span>
                            </div>
                        </a>
                        
                        <!-- Advanced Deposit Report -->
                        <a href="<?= SystemURLs::getRootPath() ?>/FinancialReports.php?ReportType=Advanced%20Deposit%20Report" class="list-group-item list-group-item-action finance-list-group-item py-3">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="fa-solid fa-database text-secondary mr-2"></i>
                                        <?= gettext('Advanced Deposit Report') ?>
                                    </h6>
                                    <small class="text-muted"><?= gettext('Customizable report with filtering by date, fund, family, and payment method.') ?></small>
                                </div>
                                <span class="badge badge-secondary badge-pill"><?= gettext('PDF/CSV') ?></span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Membership & Other Reports -->
        <div class="col-lg-6 mb-4">
            <div class="card finance-card shadow-sm border-0 h-100">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-clipboard-list"></i> <?= gettext('Membership Reports') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        <?= gettext('Reports related to member voting eligibility and organization governance.') ?>
                    </p>
                    
                    <div class="list-group list-group-flush">
                        <!-- Voting Members -->
                        <a href="<?= SystemURLs::getRootPath() ?>/FinancialReports.php?ReportType=Voting%20Members" class="list-group-item list-group-item-action finance-list-group-item py-3">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="fa-solid fa-vote-yea text-dark mr-2"></i>
                                        <?= gettext('Voting Members') ?>
                                    </h6>
                                    <small class="text-muted"><?= gettext('List members eligible to vote based on giving history and membership criteria.') ?></small>
                                </div>
                                <span class="badge badge-dark badge-pill"><?= gettext('PDF') ?></span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Section -->
    <div class="row">
        <div class="col-12">
            <div class="card finance-card shadow-sm border-0">
                <div class="card-header bg-light py-2">
                    <h5 class="mb-0 text-muted">
                        <i class="fa-solid fa-circle-info"></i> <?= gettext('Report Tips') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6><i class="fa-solid fa-calendar-check text-primary"></i> <?= gettext('Fiscal Year') ?></h6>
                            <p class="small text-muted">
                                <?= gettext('Your fiscal year starts in month') ?> <strong><?= $iFYMonth ?></strong>. 
                                <?= gettext('Current fiscal year:') ?> <strong><?= $fyLabel ?></strong>.
                                <?= gettext('Change this in System Settings.') ?>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <h6><i class="fa-solid fa-download text-success"></i> <?= gettext('Export Options') ?></h6>
                            <p class="small text-muted">
                                <?= gettext('Most reports can be exported as PDF for printing or CSV for spreadsheet analysis.') ?>
                                <?php if ($bCSVAdminOnly && !$isAdmin): ?>
                                <span class="text-warning"><?= gettext('CSV export is restricted to administrators.') ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <h6><i class="fa-solid fa-filter text-info"></i> <?= gettext('Filtering') ?></h6>
                            <p class="small text-muted">
                                <?= gettext('Use classification and family filters to generate reports for specific groups of donors.') ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
