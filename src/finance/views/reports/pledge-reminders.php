<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$downloadUrl = SystemURLs::getRootPath() . '/finance/reports/pledge-reminders/download';
$hasCategories = array_keys($fundGroups) !== [''];
?>

<?php if ($message !== '') : ?>
    <div class="alert alert-warning" role="alert" id="pledgeRemindersMessage">
        <i class="fa-solid fa-triangle-exclamation me-1"></i>
        <strong><?= gettext('No Data Found') ?></strong>
        <?= InputUtils::escapeHTML($message) ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= InputUtils::escapeAttribute($downloadUrl) ?>" id="pledgeRemindersForm">
    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= gettext('Filters') ?></h3></div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label" for="FYID"><?= gettext('Fiscal Year') ?></label>
                <select class="form-select" name="FYID" id="FYID">
                    <?php foreach ($fiscalYears as $fiscalYear) : ?>
                        <option value="<?= (int) $fiscalYear['id'] ?>" <?= $criteria['fiscalYearId'] === $fiscalYear['id'] ? 'selected' : '' ?>><?= InputUtils::escapeHTML($fiscalYear['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label" for="classList"><?= gettext('Classification') ?></label>
                <select name="classList[]" id="classList" class="form-select" multiple data-report-select>
                    <?php foreach ($classifications as $classification) : ?>
                        <option value="<?= (int) $classification['id'] ?>" <?= in_array($classification['id'], $criteria['classificationIds'], true) ? 'selected' : '' ?>><?= InputUtils::escapeHTML($classification['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-select-all="classList"><?= gettext('Add All Classes') ?></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-clear-all="classList"><?= gettext('Clear All Classes') ?></button>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="family"><?= gettext('Filter by Family') ?></label>
                <select name="family[]" id="family" class="form-select" multiple data-report-select>
                    <?php foreach ($families as $family) : ?>
                        <option value="<?= (int) $family['id'] ?>" <?= in_array($family['id'], $criteria['familyIds'], true) ? 'selected' : '' ?>><?= InputUtils::escapeHTML($family['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-select-all="family"><?= gettext('Add All Families') ?></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-clear-all="family"><?= gettext('Clear All Families') ?></button>
                </div>
            </div>

            <div class="mb-0">
                <label class="form-label" for="fundsList"><?= gettext('Filter by Fund') ?></label>
                <select name="funds[]" id="fundsList" class="form-select" multiple data-report-select>
                    <?php foreach ($fundGroups as $category => $funds) : ?>
                        <?php if ($hasCategories) : ?><optgroup label="<?= InputUtils::escapeAttribute($category !== '' ? $category : gettext('Uncategorized')) ?>"><?php endif; ?>
                        <?php foreach ($funds as $fund) : ?>
                            <option value="<?= (int) $fund['id'] ?>" <?= in_array($fund['id'], $criteria['fundIds'], true) ? 'selected' : '' ?>><?= InputUtils::escapeHTML($fund['name']) ?><?= $fund['inactive'] ? ' — ' . gettext('INACTIVE') : '' ?></option>
                        <?php endforeach; ?>
                        <?php if ($hasCategories) : ?></optgroup><?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-select-all="fundsList"><?= gettext('Add All Funds') ?></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-clear-all="fundsList"><?= gettext('Clear All Funds') ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title"><?= gettext('Other Settings') ?></h3></div>
        <div class="card-body">
            <fieldset class="mb-3">
                <legend class="form-label"><?= gettext('Include') ?></legend>
                <?php foreach (['pledge' => gettext('Only Payments with Pledges'), 'all' => gettext('All Payments')] as $value => $label) : ?>
                    <label class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="pledge_filter" value="<?= $value ?>" id="pledgeFilter<?= ucfirst($value) ?>" <?= $criteria['onlyWithPledge'] === ($value === 'pledge') ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= InputUtils::escapeHTML($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <fieldset class="mb-0">
                <legend class="form-label"><?= gettext('Generate') ?></legend>
                <?php foreach (['yes' => gettext('Only Families with unpaid pledges'), 'no' => gettext('All Families')] as $value => $label) : ?>
                    <label class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="only_owe" value="<?= $value ?>" id="onlyOwe<?= ucfirst($value) ?>" <?= $criteria['onlyOwing'] === ($value === 'yes') ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= InputUtils::escapeHTML($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-secondary" href="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath() . '/finance/reports') ?>"><?= gettext('Back') ?></a>
        <button type="submit" class="btn btn-primary" id="downloadPledgeReminders"><i class="fa-solid fa-file-pdf me-1"></i><?= gettext('Download PDF') ?></button>
    </div>
</form>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/finance-report-options.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
