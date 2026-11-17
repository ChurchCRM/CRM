<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div id="volunteerConversionPage">
    <?php if ($conversion['opportunityCount'] === 0): ?>
    <div class="card">
        <div class="card-body text-center text-secondary py-5" id="volunteerConversionNothing">
            <i class="fa-solid fa-handshake-angle fa-2x mb-2 d-block"></i>
            <?= gettext('There are no Volunteer Opportunities to convert.') ?>
        </div>
    </div>
    <?php else: ?>
    <div class="row row-cards">
        <div class="col-12 col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-people-group me-2"></i><?= gettext('Where they go') ?></h3>
                </div>
                <div class="card-body">
                    <p class="form-text mt-0"><?= gettext('Each opportunity becomes a position on the first team of one ministry, and everyone linked to it becomes qualified for that position. Self sign-up starts off on these positions.') ?></p>
                    <?php $intoPrevious = $conversion['previousMinistryId'] > 0; ?>
                    <label class="form-check">
                        <input class="form-check-input" type="radio" name="target" value="new" id="volunteerConversionTargetNew" <?= $intoPrevious ? '' : 'checked' ?>>
                        <span class="form-check-label"><?= gettext('A new ministry') ?></span>
                    </label>
                    <input class="form-control mb-3" type="text" id="volunteerConversionMinistryName" maxlength="100" <?= $intoPrevious ? 'disabled' : '' ?>
                           value="<?= InputUtils::escapeAttribute($conversion['defaultMinistryName']) ?>" aria-label="<?= InputUtils::escapeAttribute(gettext('New ministry name')) ?>">
                    <label class="form-check">
                        <input class="form-check-input" type="radio" name="target" value="existing" id="volunteerConversionTargetExisting" <?= $conversion['ministries'] === [] ? 'disabled' : '' ?> <?= $intoPrevious ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= gettext('An existing ministry') ?></span>
                    </label>
                    <select class="form-select" id="volunteerConversionMinistry" aria-label="<?= InputUtils::escapeAttribute(gettext('Existing ministry')) ?>" <?= $intoPrevious ? '' : 'disabled' ?>>
                        <?php foreach ($conversion['ministries'] as $ministry): ?>
                        <option value="<?= (int) $ministry['id'] ?>" <?= (int) $ministry['id'] === $conversion['previousMinistryId'] ? 'selected' : '' ?>><?= InputUtils::escapeHTML($ministry['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-7">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-list-check me-2"></i><?= gettext('What will happen') ?></h3>
                </div>
                <div class="card-body" id="volunteerConversionSummary" aria-live="polite"></div>
                <div class="card-footer d-flex flex-wrap align-items-center gap-2">
                    <button type="button" class="btn btn-primary" id="volunteerConversionRun" disabled>
                        <i class="fa-solid fa-right-left me-2"></i><?= gettext('Convert') ?>
                    </button>
                    <span class="text-secondary" id="volunteerConversionNote"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-handshake-angle me-2"></i><?= gettext('Opportunities') ?></h3>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table" id="volunteerConversionTable">
                <thead>
                    <tr>
                        <th><?= gettext('Opportunity') ?></th>
                        <th><?= gettext('Becomes') ?></th>
                        <th><?= gettext('Volunteers') ?></th>
                        <th><?= gettext('Notes') ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM = window.CRM || {};
    window.CRM.volunteerConversion = <?= InputUtils::jsonEncodeForScript($conversion) ?>;
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/admin-volunteer-conversion.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
