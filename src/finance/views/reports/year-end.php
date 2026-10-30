<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$year = $overview['year'];
$root = SystemURLs::getRootPath();
$period = http_build_query([
    'DateStart' => $overview['startDate'],
    'DateEnd' => $overview['endDate'],
    'datetype' => 'Payment',
]);
$statementsUrl = $root . '/finance/reports/giving-statements?' . $period;
$isAdmin = AuthenticationManager::getCurrentUser()->isAdmin();
$hasStatements = $overview['statements']['gifts'] > 0;

$wording = [
    'sTaxReport1' => gettext('Opening line'),
    'sTaxReport2' => gettext('Thank-you line'),
    'sTaxReport3' => gettext('Closing line'),
    'sTaxSigner' => gettext('Signed by'),
];
$panelSettings = array_map(
    static fn (string $name, string $label): array => ['name' => $name, 'type' => 'text', 'label' => $label],
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
    [
        'state' => !empty(SystemConfig::getValue('sChurchName')) && !empty(SystemConfig::getValue('sChurchAddress')) ? 'ok' : 'warn',
        'title' => gettext('Church Information'),
        'text' => gettext('Verify church name, address, and contact info appears on tax statements.'),
        'url' => $isAdmin ? $root . '/admin/system/church-info' : '',
        'label' => gettext('Settings'),
    ],
];
$badge = ['ok' => 'bg-green-lt text-green', 'warn' => 'bg-warning text-dark', 'info' => 'bg-blue-lt text-blue'];
$icon = ['ok' => 'fa-check', 'warn' => 'fa-exclamation', 'info' => 'fa-info'];
?>

<div class="container-fluid">
    <form method="GET" class="card mb-3" id="yearForm">
        <div class="card-body d-flex flex-wrap align-items-end gap-3">
            <div>
                <label for="year" class="form-label fw-bold mb-1"><?= gettext('Year') ?></label>
                <select name="year" id="year" class="form-select">
                    <?php foreach ($years as $option) : ?>
                        <option value="<?= (int) $option ?>" <?= $option === $year ? 'selected' : '' ?>><?= (int) $option ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" id="showYear"><i class="fa-solid fa-calendar-check me-1"></i><?= gettext('Show') ?></button>
            <span class="text-body-secondary small mb-2"><?= InputUtils::escapeHTML($overview['label']) ?> · <?= InputUtils::escapeHTML($overview['startDate']) ?> – <?= InputUtils::escapeHTML($overview['endDate']) ?></span>
        </div>
    </form>

    <div class="row row-cards mb-3" id="yearSummary">
        <?php foreach ([
            [gettext('Families'), (string) $overview['statements']['families'], 'yearFamilies'],
            [gettext('Payments'), (string) $overview['statements']['gifts'], 'yearGifts'],
            [gettext('Total'), $overview['statements']['total'], 'yearTotal'],
            [gettext('Can be emailed'), (string) $overview['emailable'], 'yearEmailable'],
            [gettext('Print and mail'), (string) $overview['mailOnly'], 'yearMailOnly'],
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
                <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-clipboard-check me-2"></i><?= gettext('Tax Year Reporting Checklist') ?></h3></div>
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

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-pen-to-square me-2"></i><?= gettext('Giving Report (Tax Statements)') ?></h3></div>
                <dl class="card-body mb-0 row" id="statementWordingView">
                    <?php foreach ($wording as $name => $label) : ?>
                        <dt class="col-sm-3"><?= InputUtils::escapeHTML($label) ?></dt>
                        <dd class="col-sm-9"><?= InputUtils::escapeHTML((string) SystemConfig::getValue($name)) ?></dd>
                    <?php endforeach; ?>
                </dl>
                <?php if ($isAdmin) : ?>
                    <div class="card-footer">
                        <div id="statementWording"
                             data-title="<?= InputUtils::escapeAttribute(gettext('Edit')) ?>"
                             data-settings="<?= InputUtils::escapeAttribute(json_encode($panelSettings, JSON_THROW_ON_ERROR)) ?>"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-file-invoice-dollar me-2"></i><?= gettext('Generate Tax Statements') ?> <?= (int) $year ?></h3></div>
                <div class="card-body d-grid gap-2">
                    <?php if ($hasStatements) : ?>
                        <a class="btn btn-primary" id="previewStatements" href="<?= InputUtils::escapeAttribute($statementsUrl . '&preview=1') ?>"><i class="fa-solid fa-eye me-1"></i><?= gettext('Preview') ?></a>
                        <a class="btn btn-outline-primary" id="yearPdf" href="<?= InputUtils::escapeAttribute($root . '/finance/reports/giving-statements/download?output=pdf&' . $period) ?>"><i class="fa-solid fa-file-pdf me-1"></i><?= gettext('Download PDF') ?></a>
                        <a class="btn btn-outline-primary" id="yearCsv" href="<?= InputUtils::escapeAttribute($root . '/finance/reports/giving-statements/download?output=csv&' . $period) ?>"><i class="fa-solid fa-file-csv me-1"></i><?= gettext('Download CSV') ?></a>
                    <?php else : ?>
                        <div class="alert alert-warning mb-0" id="yearEmpty"><strong><?= gettext('No Data Found') ?></strong></div>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary" id="customStatements" href="<?= InputUtils::escapeAttribute($statementsUrl) ?>"><i class="fa-solid fa-sliders me-1"></i><?= gettext('Report Settings') ?></a>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-chart-column me-2"></i><?= gettext('Financial Reports') ?></h3></div>
                <div class="list-group list-group-flush">
                    <a class="list-group-item list-group-item-action" href="<?= $root ?>/finance/reports"><?= gettext('Financial Reports') ?></a>
                    <a class="list-group-item list-group-item-action" href="<?= $root ?>/finance/pledge/dashboard"><?= gettext('Pledge Dashboard') ?></a>
                    <a class="list-group-item list-group-item-action" href="<?= $root ?>/finance/deposit/search"><?= gettext('View All Deposits') ?></a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($isAdmin) : ?>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/finance-year-end.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<?php endif; ?>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
