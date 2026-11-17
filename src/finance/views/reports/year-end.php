<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
require __DIR__ . '/../_charity-details-banner.php';

$year = $overview['year'];
$root = SystemURLs::getRootPath();
$period = http_build_query([
    'DateStart' => $overview['startDate'],
    'DateEnd' => $overview['endDate'],
    'datetype' => 'Payment',
]);
$statementsUrl = $root . '/finance/reports/giving-statements?' . $period;
$hasStatements = $overview['statements']['gifts'] > 0;

$wording = [
    'sGivingStatementOpening' => gettext('Opening line'),
    'sGivingStatementClosing' => gettext('Thank-you line'),
    'sGivingStatementQuestions' => gettext('Closing line'),
    'sTaxSigner' => gettext('Signed by'),
];
$panelSettings = array_map(
    static fn (string $name, string $label): array => ['name' => $name, 'type' => $name === 'sTaxSigner' ? 'text' : 'textarea', 'label' => $label],
    array_keys($wording),
    array_values($wording)
);

// Each step: state is ok / warn / info; the link takes the team to where it is fixed.
$steps = [
    [
        'state' => $overview['openDeposits'] === 0 ? 'ok' : 'warn',
        'title' => gettext('Close All Deposits'),
        'text' => $overview['openDeposits'] === 0
            ? gettext('Every deposit dated in this year is closed.')
            : sprintf(gettext('%d open deposits dated in this year. Close them so every gift is final before statements go out.'), $overview['openDeposits']),
        'url' => $root . '/finance/deposit/search',
        'label' => gettext('View'),
    ],
    [
        'state' => $overview['undeposited'] === 0 ? 'ok' : 'warn',
        'title' => gettext('Payments'),
        'text' => $overview['undeposited'] === 0
            ? gettext('Every payment in this year is on a deposit.')
            : sprintf(gettext('%d payments are not on any deposit yet.'), $overview['undeposited']),
        'url' => $root . '/finance/deposit/search',
        'label' => gettext('Review'),
    ],
    [
        'state' => $overview['unassigned'] === 0 ? 'ok' : 'info',
        'title' => gettext('Anonymous'),
        'text' => $overview['unassigned'] === 0
            ? gettext('Every gift belongs to a family.')
            : sprintf(gettext('%d gifts have no family. They cannot be on a statement.'), $overview['unassigned']),
        'url' => $root . '/finance/reports',
        'label' => gettext('Financial Reports'),
    ],
];
$badge = ['ok' => 'bg-green-lt text-green', 'warn' => 'bg-warning text-dark', 'info' => 'bg-blue-lt text-blue'];
$icon = ['ok' => 'fa-check', 'warn' => 'fa-exclamation', 'info' => 'fa-info'];
?>

<div class="container-fluid">
    <form method="GET" class="card mb-3" id="yearForm">
        <div class="card-body d-flex flex-wrap align-items-end gap-3">
            <div>
                <label for="year" class="form-label fw-bold mb-1"><?= gettext('Church Fiscal Year') ?></label>
                <select name="year" id="year" class="form-select">
                    <?php foreach ($years as $option) : ?>
                        <option value="<?= (int) $option ?>" <?= $option === $year ? 'selected' : '' ?>><?= InputUtils::escapeHTML(FiscalYearUtils::getFiscalYearDatesForCalendarYear($option)['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" id="showYear"><i class="fa-solid fa-calendar-check me-1"></i><?= gettext('Show') ?></button>
            <span class="text-body-secondary small mb-2"><?= InputUtils::escapeHTML($overview['label']) ?> · <?= InputUtils::escapeHTML($overview['startDate']) ?> – <?= InputUtils::escapeHTML($overview['endDate']) ?></span>
            <?php if (substr($overview['startDate'], 5) !== '01-01') : ?>
                <div class="w-100 text-body-secondary small" id="fiscalYearNote">
                    <i class="fa-solid fa-circle-info me-1"></i><?= gettext('Your church fiscal year does not start on January 1. Tax authorities often want receipts for the calendar year: choose Review and Filter and set the dates to January 1 – December 31.') ?>
                </div>
            <?php endif; ?>
        </div>
    </form>

    <div class="row row-cards mb-3" id="yearSummary">
        <?php foreach ([
            [gettext('Families'), (string) $overview['statements']['families'], 'yearFamilies'],
            [gettext('Payments'), (string) $overview['statements']['gifts'], 'yearGifts'],
            [gettext('Total'), $overview['statements']['total'], 'yearTotal'],
            [gettext('Have an email'), (string) $overview['emailable'], 'yearEmailable'],
            [gettext('Mail only'), (string) $overview['mailOnly'], 'yearMailOnly'],
        ] as [$label, $value, $id]) : ?>
            <div class="col-6 col-lg">
                <div class="card card-sm"><div class="card-body">
                    <div class="text-secondary"><?= InputUtils::escapeHTML($label) ?></div>
                    <div class="h2 mb-0" id="<?= $id ?>"><?= InputUtils::escapeHTML($value) ?></div>
                </div></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row row-cards">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-clipboard-check me-2"></i><?= gettext('Before You Send Statements') ?></h3></div>
                <div class="list-group list-group-flush" id="yearChecklist">
                    <?php foreach ($steps as $step) : ?>
                        <div class="list-group-item d-flex align-items-center">
                            <span class="badge <?= $badge[$step['state']] ?> rounded-circle p-2 me-3"><i class="fa-solid <?= $icon[$step['state']] ?>"></i></span>
                            <div class="flex-grow-1">
                                <strong><?= InputUtils::escapeHTML($step['title']) ?></strong>
                                <p class="mb-0 small text-body-secondary"><?= InputUtils::escapeHTML($step['text']) ?></p>
                            </div>
                            <?php if ($step['url'] !== '') : ?>
                                <a href="<?= InputUtils::escapeAttribute($step['url']) ?>" class="btn btn-sm btn-outline-secondary"><?= InputUtils::escapeHTML($step['label']) ?></a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="statementWording" class="mb-3"
                 data-title="<?= InputUtils::escapeAttribute(gettext('Giving Report (Tax Statements)')) ?>"
                 data-settings="<?= InputUtils::escapeAttribute(json_encode($panelSettings, JSON_THROW_ON_ERROR)) ?>"></div>
            <a href="<?= $root ?>/finance/reports/giving-statements/sample" class="btn btn-outline-secondary mb-3" id="sampleLetter" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf me-1"></i><?= gettext('Preview Sample Letter') ?></a>
            <a href="<?= $root ?>/finance/statements/settings" class="btn btn-ghost-secondary mb-3" id="statementSettingsLink"><?= gettext('Statement Settings') ?></a>
            <p class="form-text"><?= gettext('The letter text above is the ordinary giving statement. Charity name, registration number, and the Canadian receipt switch are on Statement Settings.') ?></p>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-file-invoice-dollar me-2"></i><?= gettext('Generate Tax Statements') ?> <?= InputUtils::escapeHTML($overview['label']) ?></h3></div>
                <div class="card-body d-grid gap-2">
                    <?php if ($hasStatements) : ?>
                        <a class="btn btn-primary" id="yearPdf" href="<?= InputUtils::escapeAttribute($root . '/finance/reports/giving-statements/download?output=pdf&' . $period) ?>"><i class="fa-solid fa-file-pdf me-1"></i><?= gettext('Download PDF') ?></a>
                        <a class="btn btn-outline-primary" id="yearCsv" href="<?= InputUtils::escapeAttribute($root . '/finance/reports/giving-statements/download?output=csv&' . $period) ?>"><i class="fa-solid fa-file-csv me-1"></i><?= gettext('Download CSV') ?></a>
                    <?php else : ?>
                        <div class="alert alert-warning mb-0" id="yearEmpty"><strong><?= gettext('No Data Found') ?></strong></div>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary" id="customStatements" href="<?= InputUtils::escapeAttribute($statementsUrl . ($hasStatements ? '&preview=1' : '')) ?>"><i class="fa-solid fa-sliders me-1"></i><?= gettext('Review and Filter') ?></a>
                    <div class="form-text"><?= gettext('Download sends every donor in the year. Review and Filter shows the totals first and lets you narrow by family, fund, class or date.') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/finance-year-end.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
