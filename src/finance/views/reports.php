<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Get fiscal year info for display
$iFYMonth = SystemConfig::getIntValue('iFYMonth');
$currentYear = (int) date('Y');

if ($iFYMonth === 1) {
    $fyLabel = (string) $currentYear;
} else {
    $currentMonth = (int) date('n');
    if ($currentMonth >= $iFYMonth) {
        $fyLabel = $currentYear . '/' . substr((string) ($currentYear + 1), 2, 2);
    } else {
        $fyLabel = ($currentYear - 1) . '/' . substr((string) $currentYear, 2, 2);
    }
}

$monthNames = [
    1 => gettext('January'), 2 => gettext('February'), 3 => gettext('March'),
    4 => gettext('April'), 5 => gettext('May'), 6 => gettext('June'),
    7 => gettext('July'), 8 => gettext('August'), 9 => gettext('September'),
    10 => gettext('October'), 11 => gettext('November'), 12 => gettext('December'),
];
$fyMonthName = $monthNames[$iFYMonth] ?? '';

?>

<div class="card" id="financeReports">
    <div class="list-group list-group-flush">
        <?php
        $sections = [
            [
                'title' => gettext('Tax & Giving Reports'),
                'reports' => [
                    ['href' => '/FinancialReports.php?ReportType=Giving%20Report', 'title' => gettext('Giving Report (Tax Statements)'), 'description' => gettext('Generate annual tax-deductible giving statements for donors. Can be printed or emailed.')],
                    ['href' => '/FinancialReports.php?ReportType=Zero%20Givers', 'title' => gettext('Zero Givers'), 'description' => gettext('Identify members who have not made any donations within a date range.')],
                ],
            ],
            [
                'title' => gettext('Pledge Reports'),
                'reports' => [
                    ['href' => '/finance/pledge/dashboard', 'title' => gettext('Pledge Summary'), 'description' => gettext('Interactive pledges-vs-payments summary by fund with sort, search, and export.')],
                    ['href' => '/FinancialReports.php?ReportType=Pledge%20Family%20Summary', 'title' => gettext('Pledge Family Summary'), 'description' => gettext('Detailed breakdown of pledges and payments by family.')],
                    ['href' => '/FinancialReports.php?ReportType=Pledge%20Reminders', 'title' => gettext('Pledge Reminders'), 'description' => gettext('Generate reminder letters for families with outstanding pledges.')],
                ],
            ],
            [
                'title' => gettext('Deposit Reports'),
                'reports' => [
                    ['href' => '/FinancialReports.php?ReportType=Individual%20Deposit%20Report', 'title' => gettext('Individual Deposit Report'), 'description' => gettext('Detailed breakdown of a single deposit slip.')],
                    ['href' => '/FinancialReports.php?ReportType=Advanced%20Deposit%20Report', 'title' => gettext('Advanced Deposit Report'), 'description' => gettext('Customizable report with filtering by date, fund, family, and payment method.')],
                ],
            ],
            [
                'title' => gettext('Membership Reports'),
                'reports' => [
                    ['href' => '/FinancialReports.php?ReportType=Voting%20Members', 'title' => gettext('Voting Members'), 'description' => gettext('List members eligible to vote based on giving history and membership criteria.')],
                ],
            ],
        ];
        foreach ($sections as $section) :
            ?>
        <div class="list-group-item bg-light fw-bold"><?= InputUtils::escapeHTML($section['title']) ?></div>
            <?php foreach ($section['reports'] as $report) : ?>
        <div class="list-group-item">
            <div class="row align-items-center">
                <div class="col">
                    <a href="<?= $sRootPath . $report['href'] ?>" class="fw-bold text-body"><?= InputUtils::escapeHTML($report['title']) ?></a>
                    <div class="text-secondary"><?= InputUtils::escapeHTML($report['description']) ?></div>
                </div>
                <div class="col-auto">
                    <a href="<?= $sRootPath . $report['href'] ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-play me-1"></i><?= gettext('Run') ?>
                    </a>
                </div>
            </div>
        </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title mb-0"><?= gettext('Report Tips') ?></h3>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <h4 class="mb-1"><?= gettext('Fiscal Year') ?></h4>
                <p class="text-secondary mb-0">
                    <?= InputUtils::escapeHTML(sprintf(gettext('Your fiscal year starts in %s.'), $fyMonthName)) ?>
                    <?= InputUtils::escapeHTML(gettext('Current fiscal year')) ?>: <strong><?= InputUtils::escapeHTML($fyLabel) ?></strong>.
                    <?= InputUtils::escapeHTML(gettext('Change this in System Settings.')) ?>
                </p>
            </div>
            <div class="col-md-4">
                <h4 class="mb-1"><?= gettext('Export Options') ?></h4>
                <p class="text-secondary mb-0"><?= gettext('Most reports can be exported as PDF for printing or CSV for spreadsheet analysis.') ?></p>
            </div>
            <div class="col-md-4">
                <h4 class="mb-1"><?= gettext('Filtering') ?></h4>
                <p class="text-secondary mb-0"><?= gettext('Use classification and family filters to generate reports for specific groups of donors.') ?></p>
            </div>
        </div>
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
