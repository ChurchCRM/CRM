<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2><?= gettext('Tax Statements (Giving Report)') ?></h2>
            <p class="text-muted">
                <?= gettext('Generate annual tax-deductible giving statements for donors.') ?>
            </p>
        </div>
    </div>

    <?php if (!empty($_GET['NoRows'])): ?>
    <div class="alert alert-warning" role="alert">
        <i class="fas fa-exclamation-triangle"></i>
        <strong><?= gettext('No Data Found') ?></strong><br>
        <?= gettext('No records were returned. Please adjust your filters or date range and try again.') ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= gettext('Report Filters') ?></h3>
        </div>
        <div class="card-body">
            <form method="post" id="taxReportForm" action="<?= $sRootPath ?>/finance/reports/tax-report">

                <div class="row">
                    <!-- Date Range -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="DateStart"><?= gettext('Report Start Date') ?></label>
                            <input type="text" name="DateStart" id="DateStart"
                                   class="form-control date-picker"
                                   value="<?= InputUtils::escapeHTML($today) ?>"
                                   maxlength="10" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="DateEnd"><?= gettext('Report End Date') ?></label>
                            <input type="text" name="DateEnd" id="DateEnd"
                                   class="form-control date-picker"
                                   value="<?= InputUtils::escapeHTML($today) ?>"
                                   maxlength="10" required>
                        </div>
                    </div>
                </div><!-- /.row -->

                <!-- Minimum Amount -->
                <div class="form-group">
                    <label for="minimum"><?= gettext('Minimum Giving Amount') ?></label>
                    <div class="input-group" style="max-width:200px;">
                        <div class="input-group-prepend">
                            <span class="input-group-text">$</span>
                        </div>
                        <input type="number" name="minimum" id="minimum" class="form-control" value="0" min="0" step="0.01">
                    </div>
                    <small class="form-text text-muted"><?= gettext('Enter 0 to include all donors regardless of gift amount.') ?></small>
                </div>

                <!-- Filter by Classification -->
                <div class="form-group">
                    <label for="classList"><?= gettext('Filter by Classification') ?></label>
                    <select name="classList[]" id="classList" class="form-control" multiple>
                        <?php foreach ($classifications as $cls): ?>
                            <option value="<?= (int) $cls->getOptionId() ?>">
                                <?= InputUtils::escapeHTML($cls->getOptionName()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted"><?= gettext('Leave empty to include all classifications.') ?></small>
                    <div class="mt-1">
                        <button type="button" id="addAllClasses" class="btn btn-sm btn-secondary">
                            <?= gettext('Add All') ?>
                        </button>
                        <button type="button" id="clearAllClasses" class="btn btn-sm btn-secondary">
                            <?= gettext('Clear All') ?>
                        </button>
                    </div>
                </div>

                <!-- Filter by Fund -->
                <div class="form-group">
                    <label for="fundsList"><?= gettext('Filter by Fund') ?></label>
                    <select name="funds[]" id="fundsList" class="form-control" multiple>
                        <?php foreach ($funds as $fund): ?>
                            <option value="<?= (int) $fund->getId() ?>">
                                <?= InputUtils::escapeHTML($fund->getName()) ?>
                                <?php if ($fund->getActive() === 'false'): ?>
                                    &nbsp;(<?= gettext('Inactive') ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted"><?= gettext('Leave empty to include all funds.') ?></small>
                    <div class="mt-1">
                        <button type="button" id="addAllFunds" class="btn btn-sm btn-secondary">
                            <?= gettext('Add All') ?>
                        </button>
                        <button type="button" id="clearAllFunds" class="btn btn-sm btn-secondary">
                            <?= gettext('Clear All') ?>
                        </button>
                    </div>
                </div>

                <!-- Filter by Family -->
                <div class="form-group">
                    <label for="family"><?= gettext('Filter by Family') ?></label>
                    <select name="family[]" id="family" class="form-control" multiple>
                        <?php foreach ($families as $fam): ?>
                            <option value="<?= (int) $fam->getId() ?>">
                                <?= InputUtils::escapeHTML($fam->getName()) ?>
                                <?php if ($fam->getCity()): ?>
                                    &ndash; <?= InputUtils::escapeHTML($fam->getCity()) ?>, <?= InputUtils::escapeHTML($fam->getState()) ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted"><?= gettext('Leave empty to include all families.') ?></small>
                    <div class="mt-1">
                        <button type="button" id="addAllFamilies" class="btn btn-sm btn-secondary">
                            <?= gettext('Add All') ?>
                        </button>
                        <button type="button" id="clearAllFamilies" class="btn btn-sm btn-secondary">
                            <?= gettext('Clear All') ?>
                        </button>
                    </div>
                </div>

                <!-- Filter by Deposit -->
                <div class="form-group">
                    <label for="deposit"><?= gettext('Filter by Deposit') ?></label>
                    <small class="form-text text-muted"><?= gettext('If a deposit is selected, the date range above will be ignored.') ?></small>
                    <select name="deposit" id="deposit" class="form-control">
                        <option value="0" selected><?= gettext('All deposits within date range') ?></option>
                        <?php foreach ($deposits as $dep): ?>
                            <option value="<?= (int) $dep->getId() ?>">
                                <?= (int) $dep->getId() ?>
                                &nbsp;<?= InputUtils::escapeHTML($dep->getDate('Y-m-d') ?? '') ?>
                                &nbsp;<?= InputUtils::escapeHTML($dep->getType() ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Letterhead -->
                <div class="form-group">
                    <label><?= gettext('Letterhead') ?></label><br>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="letterhead" id="letterhead_graphic" value="graphic">
                        <label class="form-check-label" for="letterhead_graphic"><?= gettext('Graphic') ?></label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="letterhead" id="letterhead_address" value="address" checked>
                        <label class="form-check-label" for="letterhead_address"><?= gettext('Church Address') ?></label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="letterhead" id="letterhead_none" value="none">
                        <label class="form-check-label" for="letterhead_none"><?= gettext('Blank (pre-printed letterhead)') ?></label>
                    </div>
                </div>

                <!-- Remittance Slip -->
                <div class="form-group">
                    <label><?= gettext('Include Remittance Slip') ?></label><br>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="remittance" id="remittance_no" value="no" checked>
                        <label class="form-check-label" for="remittance_no"><?= gettext('No') ?></label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="remittance" id="remittance_yes" value="yes">
                        <label class="form-check-label" for="remittance_yes"><?= gettext('Yes') ?></label>
                    </div>
                </div>

                <div class="form-group mt-4">
                    <button type="submit" id="createReport" class="btn btn-primary">
                        <i class="fa-solid fa-file-pdf mr-1"></i>
                        <?= gettext('Generate PDF') ?>
                    </button>
                    <a href="<?= $sRootPath ?>/finance/reports" class="btn btn-secondary ml-2">
                        <?= gettext('Cancel') ?>
                    </a>
                </div>

            </form>
        </div><!-- /.card-body -->
    </div><!-- /.card -->
</div><!-- /.container-fluid -->

<script>
    // Add/Clear all select helpers
    document.getElementById('addAllClasses').addEventListener('click', function () {
        document.querySelectorAll('#classList option').forEach(o => o.selected = true);
    });
    document.getElementById('clearAllClasses').addEventListener('click', function () {
        document.querySelectorAll('#classList option').forEach(o => o.selected = false);
    });
    document.getElementById('addAllFunds').addEventListener('click', function () {
        document.querySelectorAll('#fundsList option').forEach(o => o.selected = true);
    });
    document.getElementById('clearAllFunds').addEventListener('click', function () {
        document.querySelectorAll('#fundsList option').forEach(o => o.selected = false);
    });
    document.getElementById('addAllFamilies').addEventListener('click', function () {
        document.querySelectorAll('#family option').forEach(o => o.selected = true);
    });
    document.getElementById('clearAllFamilies').addEventListener('click', function () {
        document.querySelectorAll('#family option').forEach(o => o.selected = false);
    });
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
