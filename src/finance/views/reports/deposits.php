<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$base = $sRootPath . '/finance/reports/deposits';
$advanced = $criteria['classIds'] !== [] || $criteria['familyIds'] !== [] || $criteria['fundIds'] !== []
    || $criteria['methods'] !== [] || $criteria['sort'] !== 'deposit' || $criteria['detail'] !== 'detail';
$value = static fn (string $key): string => is_scalar($query[$key] ?? null) ? (string) $query[$key] : '';
ob_start();
?>
<button type="submit" form="depositReportForm" formaction="<?= InputUtils::escapeAttribute($base . '/download') ?>" class="btn btn-primary" id="createReport">
    <i class="fa-solid fa-download me-1"></i><?= gettext('Download Report') ?>
</button>
<?php
$sPageHeaderButtons = ob_get_clean();
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<?php if ($error !== '') : ?>
<div class="alert alert-warning" role="alert" id="depositReportError"><?= InputUtils::escapeHTML($error) ?></div>
<?php endif; ?>

<?php if ($summary !== null && $summary['count'] > 0) : ?>
<div class="card card-sm mb-3" id="depositReportPreview" role="status">
    <div class="card-body d-flex flex-wrap gap-4">
        <div><span class="text-secondary"><?= gettext('Payments') ?></span> <strong><?= (int) $summary['count'] ?></strong></div>
        <div><span class="text-secondary"><?= gettext('Total') ?></span> <strong><?= InputUtils::escapeHTML($summary['total']) ?></strong></div>
    </div>
</div>
<?php endif; ?>

<form method="post" action="<?= InputUtils::escapeAttribute($base) ?>" id="depositReportForm">
    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= gettext('Review Payments') ?></h3></div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label" for="deposit"><?= gettext('Filter by Deposit') ?></label>
                <select class="form-select" name="deposit" id="deposit" aria-describedby="depositHelp">
                    <option value="0"><?= gettext('All deposits within date range') ?></option>
                    <?php foreach ($deposits as $deposit) : ?>
                    <option value="<?= (int) $deposit['id'] ?>" <?= $criteria['depositId'] === $deposit['id'] ? 'selected' : '' ?>><?= InputUtils::escapeHTML($deposit['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text" id="depositHelp"><?= gettext('Choose one deposit to review its payments or print its bank slip. A selected deposit overrides the date range.') ?></div>
            </div>
            <div class="row g-3 mb-3">
                <?php foreach (['DateStart' => gettext('Report Start Date'), 'DateEnd' => gettext('Report End Date')] as $key => $label) : ?>
                <div class="col-sm-6 col-lg-4">
                    <label class="form-label" for="<?= $key ?>"><?= InputUtils::escapeHTML($label) ?></label>
                    <input type="date" class="form-control" name="<?= $key ?>" id="<?= $key ?>" value="<?= InputUtils::escapeAttribute($value($key)) ?>">
                </div>
                <?php endforeach; ?>
                <div class="col-lg-4">
                    <fieldset>
                        <legend class="form-label"><?= gettext('Apply Report Dates To') ?></legend>
                        <?php foreach (['Deposit' => gettext('Deposit Date'), 'Payment' => gettext('Payment Date')] as $key => $label) : ?>
                        <label class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="datetype" value="<?= $key ?>" <?= $criteria['datetype'] === $key ? 'checked' : '' ?>>
                            <span class="form-check-label"><?= InputUtils::escapeHTML($label) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </fieldset>
                </div>
            </div>
            <fieldset class="mb-0">
                <legend class="form-label"><?= gettext('Output Method') ?></legend>
                <?php foreach (['pdf' => 'PDF', 'csv' => 'CSV'] as $key => $label) : ?>
                <label class="form-check form-check-inline">
                    <input type="radio" class="form-check-input" name="output" value="<?= $key ?>" <?= ($value('output') === 'csv' ? 'csv' : 'pdf') === $key ? 'checked' : '' ?>>
                    <span class="form-check-label"><?= $label ?></span>
                </label>
                <?php endforeach; ?>
            </fieldset>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-outline-primary" id="previewDepositReport"><?= gettext('Preview Totals') ?></button>
            <a class="btn btn-link" href="<?= InputUtils::escapeAttribute($base) ?>"><?= gettext('Reset Filters') ?></a>
        </div>
    </div>

    <details class="card mb-3" id="depositAdvancedSettings" <?= $advanced ? 'open' : '' ?>>
        <summary class="card-header fw-bold"><?= gettext('Advanced Settings') ?></summary>
        <div class="card-body">
            <p class="text-secondary"><?= gettext('Leave filters empty to include all payments. These settings apply to the report, not the bank slip.') ?></p>
            <div class="row g-3 mb-3">
                <?php foreach ([
                    'sort' => [gettext('Sort Data by'), ['deposit' => gettext('Deposit'), 'fund' => gettext('Fund'), 'family' => gettext('Family')], $criteria['sort']],
                    'detail_level' => [gettext('Report Type'), ['detail' => gettext('All Data'), 'medium' => gettext('Moderate Detail'), 'summary' => gettext('Summary Data')], $criteria['detail']],
                ] as $name => [$label, $options, $selected]) : ?>
                <fieldset class="col-md-6">
                    <legend class="form-label"><?= InputUtils::escapeHTML($label) ?></legend>
                    <?php foreach ($options as $key => $text) : ?>
                    <label class="form-check">
                        <input class="form-check-input" type="radio" name="<?= $name ?>" value="<?= $key ?>" <?= $selected === $key ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= InputUtils::escapeHTML($text) ?></span>
                    </label>
                    <?php endforeach; ?>
                </fieldset>
                <?php endforeach; ?>
            </div>
            <?php foreach ([
                'classList' => [gettext('Classification'), $classifications, $criteria['classIds'], 'name'],
                'family' => [gettext('Filter by Family'), $families, $criteria['familyIds'], 'label'],
            ] as $name => [$label, $options, $selected, $textKey]) : ?>
            <div class="mb-3">
                <label class="form-label" for="<?= $name ?>"><?= InputUtils::escapeHTML($label) ?></label>
                <select class="form-select" multiple name="<?= $name ?>[]" id="<?= $name ?>" data-report-select>
                    <?php foreach ($options as $option) : ?>
                    <option value="<?= (int) $option['id'] ?>" <?= in_array($option['id'], $selected, true) ? 'selected' : '' ?>><?= InputUtils::escapeHTML($option[$textKey]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endforeach; ?>
            <div class="mb-3">
                <label class="form-label" for="fundsList"><?= gettext('Filter by Fund') ?></label>
                <select class="form-select" multiple name="funds[]" id="fundsList" data-report-select>
                    <?php foreach ($fundGroups as $category => $funds) : ?>
                    <optgroup label="<?= InputUtils::escapeAttribute($category !== '' ? $category : gettext('Uncategorized')) ?>">
                        <?php foreach ($funds as $fund) : ?>
                        <option value="<?= (int) $fund['id'] ?>" <?= in_array($fund['id'], $criteria['fundIds'], true) ? 'selected' : '' ?>><?= InputUtils::escapeHTML($fund['name']) ?><?= $fund['inactive'] ? ' — ' . gettext('INACTIVE') : '' ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-0">
                <label class="form-label" for="method"><?= gettext('Filter by Payment Type') ?></label>
                <select class="form-select" multiple name="method[]" id="method" data-report-select>
                    <?php foreach (['CHECK' => gettext('Check'), 'CASH' => gettext('Cash'), 'CREDITCARD' => gettext('Credit Card'), 'BANKDRAFT' => gettext('Bank Draft')] as $key => $label) : ?>
                    <option value="<?= $key ?>" <?= in_array($key, $criteria['methods'], true) ? 'selected' : '' ?>><?= InputUtils::escapeHTML($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </details>

    <div class="card">
        <div class="card-body">
            <h3 class="card-title"><?= gettext('Prepare for the Bank') ?></h3>
            <p class="text-secondary" id="bankSlipHelp"><?= gettext('Print the selected deposit in full, with cash and check totals, count sheets and witness lines. Report filters are ignored. Electronic payments stay in the summary and are excluded from the physical bank ticket total.') ?></p>
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-outline-secondary" formaction="<?= InputUtils::escapeAttribute($base . '/download') ?>" name="mode" value="slip" id="printBankSlip" aria-describedby="bankSlipHelp">
                    <i class="fa-solid fa-print me-1"></i><?= gettext('Print Bank Slip') ?>
                </button>
            </div>
        </div>
    </div>
</form>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/finance-report-options.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
