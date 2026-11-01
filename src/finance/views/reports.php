<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\TaxYearUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Get fiscal year info for display
$iFYMonth = SystemConfig::getIntValue('iFYMonth');
$fyLabel = FinancialService::formatFiscalYear(FiscalYearUtils::getCurrentFiscalYearId());

$monthNames = [
    1 => gettext('January'), 2 => gettext('February'), 3 => gettext('March'),
    4 => gettext('April'), 5 => gettext('May'), 6 => gettext('June'),
    7 => gettext('July'), 8 => gettext('August'), 9 => gettext('September'),
    10 => gettext('October'), 11 => gettext('November'), 12 => gettext('December'),
];
$fyMonthName = $monthNames[$iFYMonth] ?? '';

$taxYear = TaxYearUtils::datesForEndYear(TaxYearUtils::currentEndYear());
$taxYearLabel = $taxYear['label'];
$taxYearPeriod = TaxYearUtils::describePeriod($taxYear['startDate'], $taxYear['endDate']);

?>

<div class="card mb-3">
    <div class="card-body py-3">
        <i class="fa-solid fa-calendar-days me-1"></i>
        <?= gettext('Fiscal Year') ?>: <strong><?= InputUtils::escapeHTML($fyLabel) ?></strong>
        <span class="text-secondary ms-2"><?= InputUtils::escapeHTML(sprintf(gettext('Your fiscal year starts in %s.'), $fyMonthName)) ?></span>
        <div class="mt-2">
            <i class="fa-solid fa-file-invoice-dollar me-1"></i>
            <?= gettext('Tax Year') ?>: <strong><?= InputUtils::escapeHTML($taxYearLabel) ?></strong>
            <span class="text-secondary ms-2"><?= InputUtils::escapeHTML(sprintf(gettext('Giving statements cover the tax year, %s.'), $taxYearPeriod)) ?></span>
        </div>
    </div>
</div>

<div class="card" id="financeReports">
    <div class="list-group list-group-flush">
        <?php
        $sections = [
            [
                'title' => gettext('Giving Reports'),
                'reports' => [
                    ['href' => '/finance/reports/giving-statements', 'title' => gettext('Giving Statements'), 'description' => gettext('Generate annual tax-deductible giving statements for donors. Can be printed or emailed.')],
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
                    ['href' => '/finance/reports/deposits', 'title' => gettext('Deposit Reports'), 'description' => gettext('Review payments, download PDF or CSV, or print a bank slip for one deposit.')],
                ],
            ],
            [
                'title' => gettext('Membership Reports'),
                'reports' => [
                    ['href' => '/finance/reports/voting-members', 'title' => gettext('Voting Members'), 'description' => gettext('Prepare a list for church review using classifications and optional age, membership, and family giving filters.')],
                ],
            ],
        ];
        foreach ($sections as $section) :
            ?>
        <div class="list-group-item bg-surface-secondary fw-bold"><?= InputUtils::escapeHTML($section['title']) ?></div>
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

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
