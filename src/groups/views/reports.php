<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>
<div class="container-fluid">

<?php if ($step === 1): ?>

    <!-- Step 1: Select Group and (optional) Role -->
    <div class="row">
        <div class="col-lg-8 col-xl-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= gettext('Select Group') ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= $sRootPath ?>/groups/reports">

                        <div class="mb-3">
                            <label for="GroupID" class="form-label">
                                <?= gettext('Group') ?> <span class="text-danger">*</span>
                            </label>
                            <select id="GroupID" name="GroupID" class="form-select" onchange="UpdateRoles();">
                                <option value="0"><?= gettext('— Select a group —') ?></option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?= (int) $group->getId() ?>">
                                        <?= InputUtils::escapeHTML($group->getName()) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="GroupRole" class="form-label">
                                <?= gettext('Role') ?>
                                <span class="text-body-secondary fw-normal ms-1 small"><?= gettext('(optional — leave blank to include all roles)') ?></span>
                            </label>
                            <select name="GroupRole" id="GroupRole" class="form-select">
                                <option value=""><?= gettext('All roles') ?></option>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <?= gettext('Next') ?> <i class="fa-solid fa-arrow-right ms-1"></i>
                            </button>
                            <a href="<?= $sRootPath ?>/groups/dashboard" class="btn btn-secondary">
                                <?= gettext('Cancel') ?>
                            </a>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($step === 2): ?>

    <!-- Step 2: Select Fields to Include -->
    <div class="row">
        <div class="col-lg-8 col-xl-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= gettext('Select Fields to Include') ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= $sRootPath ?>/Reports/GroupReport.php">

                        <input type="hidden" name="GroupID"     value="<?= InputUtils::escapeAttribute((int) $iGroupID) ?>">
                        <input type="hidden" name="GroupRole"   value="<?= InputUtils::escapeAttribute($groupRole) ?>">
                        <input type="hidden" name="OnlyCart"    value="0">
                        <input type="hidden" name="ReportModel" value="<?= InputUtils::escapeAttribute((int) $reportModel) ?>">

                        <div class="mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="form-label fw-medium mb-0"><?= gettext('Fields to Include') ?></label>
                                <div class="d-flex gap-2">
                                    <a href="#" id="selectAllFields" class="small"><?= gettext('Select all') ?></a>
                                    <span class="text-body-secondary small">·</span>
                                    <a href="#" id="clearAllFields" class="small"><?= gettext('Clear all') ?></a>
                                </div>
                            </div>
                            <div class="form-selectgroup form-selectgroup-pills" id="fieldPills">
                                <?php
                                $standardFields = [
                                    'AddressEnable'    => ['label' => gettext('Address'),    'icon' => 'fa-location-dot'],
                                    'HomePhoneEnable'  => ['label' => gettext('Home Phone'), 'icon' => 'fa-phone'],
                                    'WorkPhoneEnable'  => ['label' => gettext('Work Phone'), 'icon' => 'fa-briefcase'],
                                    'CellPhoneEnable'  => ['label' => gettext('Cell Phone'), 'icon' => 'fa-mobile-screen'],
                                    'EmailEnable'      => ['label' => gettext('Email'),      'icon' => 'fa-envelope'],
                                    'OtherEmailEnable' => ['label' => gettext('Other Email'),'icon' => 'fa-envelope-open'],
                                    'BirthdayEnable'   => ['label' => gettext('Birthday'),   'icon' => 'fa-cake-candles'],
                                    'GenderEnable'     => ['label' => gettext('Gender'),     'icon' => 'fa-venus-mars'],
                                    'GroupRoleEnable'  => ['label' => gettext('Group Role'), 'icon' => 'fa-tag'],
                                ];
                                foreach ($standardFields as $fieldName => $field): ?>
                                    <label class="form-selectgroup-item">
                                        <input type="checkbox" class="form-selectgroup-input"
                                               name="<?= InputUtils::escapeAttribute($fieldName) ?>"
                                               value="1">
                                        <span class="form-selectgroup-label">
                                            <i class="fa-solid <?= $field['icon'] ?> me-1"></i>
                                            <?= $field['label'] ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php if ($propFields->count() > 0): ?>
                            <div class="mb-4">
                                <label class="form-label fw-medium"><?= gettext('Group-Specific Properties') ?></label>
                                <div class="form-selectgroup form-selectgroup-pills" id="propPills">
                                    <?php foreach ($propFields as $prop): ?>
                                        <label class="form-selectgroup-item">
                                            <input type="checkbox" class="form-selectgroup-input"
                                                   name="<?= InputUtils::escapeAttribute($prop->getField()) ?>enable"
                                                   value="1">
                                            <span class="form-selectgroup-label">
                                                <?= InputUtils::escapeHTML($prop->getName()) ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary" id="CreateReportBtn">
                                <i class="fa-solid fa-file-pdf me-1"></i> <?= gettext('Create Report') ?>
                            </button>
                            <a href="<?= $sRootPath ?>/groups/reports" class="btn btn-secondary">
                                <?= gettext('Back') ?>
                            </a>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
document.getElementById('selectAllFields')?.addEventListener('click', function (e) {
    e.preventDefault();
    document.querySelectorAll('#fieldPills .form-selectgroup-input, #propPills .form-selectgroup-input').forEach(function (input) {
        input.checked = true;
    });
});
document.getElementById('clearAllFields')?.addEventListener('click', function (e) {
    e.preventDefault();
    document.querySelectorAll('#fieldPills .form-selectgroup-input, #propPills .form-selectgroup-input').forEach(function (input) {
        input.checked = false;
    });
});
</script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/GroupRoles.js"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
