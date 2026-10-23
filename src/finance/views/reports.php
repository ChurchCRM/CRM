<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

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

$jobs = [
    [
        'title' => gettext('Send year-end giving statements'),
        'description' => gettext('Generate tax-deductible giving statements for this fiscal year. Ready to print or email.'),
        'href' => '/finance/reports/year-end',
        'icon' => 'fa-file-invoice-dollar',
        'primary' => true,
    ],
    [
        'title' => gettext('See who has not given'),
        'description' => gettext('Find members with no gifts in a date range so you can follow up.'),
        'href' => '/finance/reports/Zero%20Givers',
        'icon' => 'fa-user-slash',
        'primary' => true,
    ],
    [
        'title' => gettext('Review pledges vs payments'),
        'description' => gettext('See which families are on track and which need a reminder.'),
        'href' => '/finance/pledge/dashboard',
        'icon' => 'fa-chart-pie',
        'primary' => true,
    ],
    [
        'title' => gettext('Send pledge reminders'),
        'description' => gettext('Generate reminder letters for families with unpaid pledges.'),
        'href' => '/finance/reports/Pledge%20Reminders',
        'icon' => 'fa-envelope',
        'primary' => false,
    ],
    [
        'title' => gettext('Print a deposit slip'),
        'description' => gettext('Detailed breakdown of a single deposit.'),
        'href' => '/finance/reports/Individual%20Deposit%20Report',
        'icon' => 'fa-receipt',
        'primary' => false,
    ],
    [
        'title' => gettext('Custom deposit report'),
        'description' => gettext('Filter deposits by date, fund, family, or payment method.'),
        'href' => '/finance/reports/Advanced%20Deposit%20Report',
        'icon' => 'fa-filter',
        'primary' => false,
    ],
    [
        'title' => gettext('Pledge family summary'),
        'description' => gettext('Detailed breakdown of pledges and payments by family.'),
        'href' => '/finance/reports/Pledge%20Family%20Summary',
        'icon' => 'fa-users',
        'primary' => false,
    ],
    [
        'title' => gettext('Voting members'),
        'description' => gettext('List members eligible to vote based on giving and membership.'),
        'href' => '/finance/reports/Voting%20Members',
        'icon' => 'fa-check-to-slot',
        'primary' => false,
    ],
];
?>

<div class="card mb-3">
    <div class="card-body py-3">
        <i class="fa-solid fa-calendar-days me-1"></i>
        <?= gettext('Fiscal Year') ?>: <strong><?= InputUtils::escapeHTML($fyLabel) ?></strong>
        <span class="text-secondary ms-2"><?= InputUtils::escapeHTML(sprintf(gettext('Your fiscal year starts in %s.'), $fyMonthName)) ?></span>
    </div>
</div>

<div class="row g-3 mb-4">
<?php foreach (array_filter($jobs, fn($j) => $j['primary']) as $job) : ?>
    <div class="col-md-4">
        <a href="<?= $sRootPath . $job['href'] ?>" class="card card-link text-reset h-100 text-decoration-none">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <span class="avatar bg-primary-lt me-2"><i class="fa-solid <?= $job['icon'] ?>"></i></span>
                    <div class="fw-bold"><?= InputUtils::escapeHTML($job['title']) ?></div>
                </div>
                <div class="text-secondary small"><?= InputUtils::escapeHTML($job['description']) ?></div>
            </div>
        </a>
    </div>
<?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('More reports') ?></h3>
    </div>
    <div class="list-group list-group-flush">
<?php foreach (array_filter($jobs, fn($j) => !$j['primary']) as $job) : ?>
        <a href="<?= $sRootPath . $job['href'] ?>" class="list-group-item list-group-item-action">
            <div class="d-flex align-items-center">
                <i class="fa-solid <?= $job['icon'] ?> me-3 text-secondary"></i>
                <div>
                    <div class="fw-bold"><?= InputUtils::escapeHTML($job['title']) ?></div>
                    <div class="text-secondary small"><?= InputUtils::escapeHTML($job['description']) ?></div>
                </div>
            </div>
        </a>
<?php endforeach; ?>
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
