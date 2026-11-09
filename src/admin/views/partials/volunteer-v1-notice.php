<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/**
 * The Volunteer Opportunities retirement notice (#10606), shown wherever V1 still is.
 *
 * Expects `$volunteerV1Notice` (VolunteerV1Service::noticeFacts()). With
 * `$volunteerV1NoticeCsrf` set it can be dismissed until the next upgrade.
 */
$volunteerV1NoticeCsrf ??= null;
$rootPath = SystemURLs::getRootPath();
?>
<div class="alert alert-warning" role="alert" id="volunteer-v1-notice"<?= $volunteerV1NoticeCsrf !== null ? ' data-csrf-token="' . InputUtils::escapeAttribute($volunteerV1NoticeCsrf) . '"' : '' ?>>
    <div class="d-flex align-items-start">
        <div class="me-3">
            <i class="fa-solid fa-handshake-angle fa-2x"></i>
        </div>
        <div class="flex-grow-1">
            <h4 class="alert-heading mb-2"><?= gettext('Volunteer Opportunities are being retired') ?></h4>
            <?php if ($volunteerV1Notice['unconverted'] > 0): ?>
            <p class="mb-2">
                <?= sprintf(
                    gettext('Volunteer Opportunities will be retired in early 2027. Convert your %1$d opportunities and %2$d volunteers to Ministries now. You can preview the result before anything changes.'),
                    (int) $volunteerV1Notice['unconverted'],
                    (int) $volunteerV1Notice['people']
                ) ?>
            </p>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= InputUtils::escapeAttribute($rootPath) ?>/admin/volunteer-conversion" class="btn btn-primary" id="volunteer-v1-notice-convert">
                    <?= gettext('Review the conversion') ?>
                </a>
            <?php elseif ($volunteerV1Notice['pending'] > 0): ?>
            <p class="mb-2">
                <?= sprintf(
                    gettext('People linked to converted Volunteer Opportunities since the conversion: %d. Add them to their positions before Volunteer Opportunities are retired in early 2027.'),
                    (int) $volunteerV1Notice['pending']
                ) ?>
            </p>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= InputUtils::escapeAttribute($rootPath) ?>/admin/volunteer-conversion" class="btn btn-primary" id="volunteer-v1-notice-convert">
                    <?= gettext('Review the conversion') ?>
                </a>
            <?php else: ?>
            <p class="mb-2">
                <?= gettext('Your Volunteer Opportunities are converted to Ministries. Switch Volunteer Management to V2 before Volunteer Opportunities are retired in early 2027.') ?>
            </p>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= InputUtils::escapeAttribute($rootPath) ?>/admin/system/feature-toggles" class="btn btn-primary" id="volunteer-v1-notice-switch">
                    <?= gettext('Open Feature Toggles') ?>
                </a>
            <?php endif; ?>
                <?php if ($volunteerV1NoticeCsrf !== null): ?>
                <button type="button" class="btn btn-ghost-secondary js-dismiss-volunteer-v1-notice">
                    <?= gettext('Dismiss') ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
