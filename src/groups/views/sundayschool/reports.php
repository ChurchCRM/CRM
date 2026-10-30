<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$error = $_GET['error'] ?? '';
?>

<?php if ($error === 'nogroup') { ?>
<div class="alert alert-danger">
    <i class="fa-solid fa-triangle-exclamation me-1"></i>
    <?= gettext('At least one group must be selected to make an attendance sheet.') ?>
</div>
<?php } ?>

<form method="post" action="<?= $sRootPath ?>/groups/sundayschool/reports">
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-people-group me-2"></i><?= gettext('Select Group') ?></h3>
        </div>
        <div class="card-body">
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-2">
                <?php foreach ($groups as $group) { ?>
                    <div class="col">
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="GroupID[]" value="<?= $group->getId() ?>"<?= $selectedGroupId === 0 || $selectedGroupId === (int) $group->getId() ? ' checked' : '' ?>>
                            <span class="form-check-label"><?= InputUtils::escapeHTML($group->getName()) ?></span>
                        </label>
                    </div>
                <?php } ?>
            </div>
            <div class="form-text mt-2"><?= gettext('Multiple groups will have a Page Break between Groups') ?></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-calendar-days me-2"></i><?= gettext('Report Details') ?></h3>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="FirstSunday"><?= gettext('First Sunday') ?></label>
                    <input type="text" id="FirstSunday" name="FirstSunday" value="<?= InputUtils::escapeHTML($calDates['firstSunday']) ?>"
                           class="form-control date-picker" maxlength="10" placeholder="<?= $sDatePickerFormat ?>">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="LastSunday"><?= gettext('Last Sunday') ?></label>
                    <input type="text" id="LastSunday" name="LastSunday" value="<?= InputUtils::escapeHTML($calDates['lastSunday']) ?>"
                           class="form-control date-picker" maxlength="10" placeholder="<?= $sDatePickerFormat ?>">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="ExtraStudents"><?= gettext('Extra Students') ?></label>
                    <input type="number" min="0" id="ExtraStudents" name="ExtraStudents" value="0" class="form-control">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="ExtraTeachers"><?= gettext('Extra Teachers') ?></label>
                    <input type="number" min="0" id="ExtraTeachers" name="ExtraTeachers" value="0" class="form-control">
                </div>
            </div>

            <label class="form-label"><?= gettext('No Sunday School') ?></label>
            <div class="form-text mt-0 mb-2"><?= gettext('Sundays with no class are left off the sheet.') ?></div>
            <div class="row g-3">
                <?php for ($i = 1; $i <= 8; $i++) { ?>
                    <div class="col-6 col-md-3">
                        <input type="text" name="NoSchool<?= $i ?>" value="<?= InputUtils::escapeHTML($calDates['noSchool' . $i]) ?>"
                               class="form-control date-picker" maxlength="10" placeholder="<?= $sDatePickerFormat ?>"
                               aria-label="<?= InputUtils::escapeAttribute(gettext('No Sunday School') . ' ' . $i) ?>">
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
            <button type="submit" name="SubmitClassAttendance" class="btn btn-primary">
                <i class="fa-solid fa-clipboard-check me-1"></i><?= gettext('Create Attendance Sheet') ?>
            </button>
            <a href="<?= $sRootPath ?>/groups/sundayschool/dashboard" class="btn btn-secondary"><?= gettext('Cancel') ?></a>
        </div>
    </div>
</form>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
