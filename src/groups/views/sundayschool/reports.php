<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\FiscalYearUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$error = $_GET['error'] ?? '';
?>

<?php if ($error === 'nogroup') { ?>
<div class="alert alert-danger">
    <i class="fa-solid fa-triangle-exclamation me-1"></i>
    <?= gettext('At least one group must be selected to make class lists or attendance sheets.') ?>
</div>
<?php } ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-file-pdf me-2"></i><?= gettext('Report Details') ?></h3>
    </div>
    <div class="card-body">
        <form method="post" action="<?= $sRootPath ?>/groups/sundayschool/reports">
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label"><?= gettext('Select Group') ?>:</label>
                <div class="col-sm-9">
                    <select id="GroupID" name="GroupID[]" multiple size="8" class="form-select">
                        <option value="0"><?= gettext('None') ?></option>
                        <?php foreach ($groups as $group) { ?>
                            <option value="<?= $group->getId() ?>"><?= InputUtils::escapeHTML($group->getName()) ?></option>
                        <?php } ?>
                    </select>
                    <div class="form-text">
                        <?= gettext('Multiple groups will have a Page Break between Groups') ?>
                    </div>
                    <div class="mt-2">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="allroles" value="1" id="allroles" checked>
                            <label class="form-check-label" for="allroles"><?= gettext('List all Roles (unchecked will list Teacher/Student roles only)') ?></label>
                        </div>
                    </div>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="withPictures" value="1" id="withPictures" checked>
                            <label class="form-check-label" for="withPictures"><?= gettext('With Photos') ?></label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-3 col-form-label"><?= gettext('Fiscal Year') ?>:</label>
                <div class="col-sm-4">
                    <?php
                    // Inline FY select using ORM data
                    $currentFY = FiscalYearUtils::getCurrentFiscalYearId();
                    ?>
                    <select class="form-select" name="FYID">
                        <option disabled value="0"<?= $iFYID === 0 ? ' selected' : '' ?>><?= gettext('Select Fiscal Year') ?></option>
                        <?php for ($fy = 1; $fy < $currentFY + 2; $fy++) { ?>
                            <option value="<?= $fy ?>"<?= $iFYID === $fy ? ' selected' : '' ?>><?= MakeFYString($fy) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-3 col-form-label"><?= gettext('First Sunday') ?>:</label>
                <div class="col-sm-4">
                    <input type="text" name="FirstSunday" value="<?= InputUtils::escapeHTML($calDates['firstSunday']) ?>"
                           class="form-control date-picker" maxlength="10"
                           placeholder="<?= $sDatePickerFormat ?>">
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-3 col-form-label"><?= gettext('Last Sunday') ?>:</label>
                <div class="col-sm-4">
                    <input type="text" name="LastSunday" value="<?= InputUtils::escapeHTML($calDates['lastSunday']) ?>"
                           class="form-control date-picker" maxlength="10"
                           placeholder="<?= $sDatePickerFormat ?>">
                </div>
            </div>

            <?php for ($i = 1; $i <= 8; $i++) { ?>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label"><?= gettext('No Sunday School') ?>:</label>
                <div class="col-sm-4">
                    <input type="text" name="NoSchool<?= $i ?>" value="<?= InputUtils::escapeHTML($calDates['noSchool' . $i]) ?>"
                           class="form-control date-picker" maxlength="10"
                           placeholder="<?= $sDatePickerFormat ?>">
                </div>
            </div>
            <?php } ?>

            <div class="row mb-3">
                <label class="col-sm-3 col-form-label"><?= gettext('Extra Students') ?>:</label>
                <div class="col-sm-2">
                    <input type="text" name="ExtraStudents" value="0" class="form-control">
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-3 col-form-label"><?= gettext('Extra Teachers') ?>:</label>
                <div class="col-sm-2">
                    <input type="text" name="ExtraTeachers" value="0" class="form-control">
                </div>
            </div>

            <div class="d-flex flex-wrap" style="gap:.5rem;">
                <button type="submit" name="SubmitClassList" class="btn btn-primary">
                    <i class="fa-solid fa-list me-1"></i><?= gettext('Create Class List') ?>
                </button>
                <button type="submit" name="SubmitClassAttendance" class="btn btn-info">
                    <i class="fa-solid fa-clipboard-check me-1"></i><?= gettext('Create Attendance Sheet') ?>
                </button>
                <button type="submit" name="SubmitPhotoBook" class="btn btn-danger">
                    <i class="fa-solid fa-book me-1"></i><?= gettext('Create PhotoBook') ?>
                </button>
                <a href="<?= $sRootPath ?>/groups/sundayschool/dashboard" class="btn btn-secondary">
                    <?= gettext('Cancel') ?>
                </a>
            </div>
        </form>
    </div>
</div>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
