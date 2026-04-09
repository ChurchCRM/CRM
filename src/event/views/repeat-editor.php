<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();

require_once SystemURLs::getDocumentRoot() . '/Include/QuillEditorHelper.php';
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/repeat-event-editor.min.css') ?>">

<div class="card">
    <div class="card-status-top bg-primary"></div>
    <div class="card-header">
        <h3 class="card-title">
            <i class="ti ti-repeat me-2"></i><?= gettext('Create Repeat Events') ?>
        </h3>
    </div>
    <div class="card-body">
        <p class="text-secondary">
            <?= gettext('Use this form to bulk-create a series of recurring events. Each event is created individually and can be edited or deleted independently after creation.') ?>
        </p>

        <form method="POST" action="<?= $sRootPath ?>/event/repeat-editor" name="RepeatEventsForm">
            <!-- Event Template -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><?= gettext('Event Template') ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="typeId" class="form-label fw-bold">
                                    <span class="text-danger">*</span> <?= gettext('Event Type') ?>
                                </label>
                                <?php if ($typeId > 0 && $eventType !== null): ?>
                                    <input type="hidden" name="typeId" value="<?= (int) $typeId ?>">
                                    <div>
                                        <span class="badge bg-info-lt text-info"><?= InputUtils::escapeHTML($typeName) ?></span>
                                        <a href="<?= $sRootPath ?>/event/repeat-editor" class="btn btn-sm btn-outline-secondary ms-2">
                                            <i class="ti ti-switch-horizontal me-1"></i><?= gettext('Change') ?>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <select name="typeId" id="typeId" class="form-select" required>
                                        <option value=""><?= gettext('Select an event type…') ?></option>
                                        <?php foreach ($allEventTypes as $et): ?>
                                            <option value="<?= (int) $et->getId() ?>"
                                                    data-name="<?= InputUtils::escapeAttribute($et->getName()) ?>">
                                                <?= InputUtils::escapeHTML($et->getName()) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="EventTitle" class="form-label fw-bold">
                                    <span class="text-danger">*</span> <?= gettext('Event Title') ?>
                                </label>
                                <input type="text" name="EventTitle" id="EventTitle" class="form-control"
                                       value="<?= InputUtils::escapeAttribute($typeName) ?>"
                                       maxlength="100" required
                                       placeholder="<?= gettext('e.g., Sunday Worship Service') ?>">
                                <small class="form-text text-secondary"><?= gettext('This title will be used for all generated events.') ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold"><?= gettext('Event Description') ?></label>
                        <?= getQuillEditorContainer('EventDesc', 'EventDescInput', '', 'form-control', '80px') ?>
                    </div>
                </div>
            </div>

            <!-- Time Settings -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><?= gettext('Time') ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="StartTime" class="form-label fw-bold">
                                    <span class="text-danger">*</span> <?= gettext('Start Time') ?>
                                </label>
                                <input type="time" name="StartTime" id="StartTime" class="form-control"
                                       value="<?= InputUtils::escapeAttribute($defStartTime) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="EndTime" class="form-label fw-bold">
                                    <span class="text-danger">*</span> <?= gettext('End Time') ?>
                                </label>
                                <input type="time" name="EndTime" id="EndTime" class="form-control"
                                       value="<?= InputUtils::escapeAttribute($defEndTime) ?>" required>
                            </div>
                        </div>
                    </div>
                    <small class="form-text text-secondary"><?= gettext('The same start and end time will be used for all generated events.') ?></small>
                </div>
            </div>

            <!-- Recurrence Pattern -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><span class="text-danger">*</span> <?= gettext('Recurrence Pattern') ?></h5>
                </div>
                <div class="card-body">
                    <div class="event-recurrence-patterns">
                        <!-- Weekly -->
                        <div class="form-check mb-3 d-flex align-items-center flex-wrap">
                            <input class="form-check-input mt-0 me-2" type="radio" name="RecurType" id="recurWeekly"
                                   value="weekly" <?= ($defRecurType === 'weekly') ? 'checked' : '' ?>>
                            <label class="form-check-label me-3 mb-0" for="recurWeekly"><?= gettext('Every week on') ?></label>
                            <select name="RecurDOW" id="RecurDOW" class="form-select form-select-sm" style="width: auto;"
                                <?= ($defRecurType !== 'weekly') ? 'disabled' : '' ?>>
                                <?php
                                $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                foreach ($days as $day): ?>
                                    <option value="<?= $day ?>" <?= ($defRecurDOW === $day) ? 'selected' : '' ?>><?= gettext($day) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Monthly -->
                        <div class="form-check mb-3 d-flex align-items-center flex-wrap">
                            <input class="form-check-input mt-0 me-2" type="radio" name="RecurType" id="recurMonthly"
                                   value="monthly" <?= ($defRecurType === 'monthly') ? 'checked' : '' ?>>
                            <label class="form-check-label me-3 mb-0" for="recurMonthly"><?= gettext('Every month on the') ?></label>
                            <select name="RecurDOM" id="RecurDOM" class="form-select form-select-sm" style="width: auto;"
                                <?= ($defRecurType !== 'monthly') ? 'disabled' : '' ?>>
                                <?php for ($d = 1; $d <= 31; $d++): ?>
                                    <option value="<?= $d ?>" <?= ($defRecurDOM === $d) ? 'selected' : '' ?>>
                                        <?= date('jS', mktime(0, 0, 0, 1, $d, 2000)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <!-- Yearly -->
                        <div class="form-check mb-3 d-flex align-items-center flex-wrap">
                            <input class="form-check-input mt-0 me-2" type="radio" name="RecurType" id="recurYearly"
                                   value="yearly" <?= ($defRecurType === 'yearly') ? 'checked' : '' ?>>
                            <label class="form-check-label me-3 mb-0" for="recurYearly"><?= gettext('Every year on') ?></label>
                            <input type="text" name="RecurDOY" id="RecurDOY" class="form-control form-control-sm" style="width: 100px;"
                                   placeholder="MM-DD"
                                   value="<?= InputUtils::escapeAttribute($defRecurDOY) ?>"
                                   pattern="\d{2}-\d{2}" title="<?= gettext('Format: MM-DD (e.g. 04-12 for April 12)') ?>"
                                <?= ($defRecurType !== 'yearly') ? 'disabled' : '' ?>>
                            <small class="text-secondary ms-2"><?= gettext('Format: MM-DD (e.g. 04-12 for April 12)') ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Date Range -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><span class="text-danger">*</span> <?= gettext('Date Range') ?></h5>
                </div>
                <div class="card-body">
                    <p class="text-secondary mb-3">
                        <?= gettext('Events will be created for every matching occurrence within this date range.') ?>
                    </p>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="RangeStart" class="form-label fw-bold"><?= gettext('From') ?></label>
                                <input type="date" name="RangeStart" id="RangeStart" class="form-control"
                                       value="<?= InputUtils::escapeAttribute($rangeStart) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="RangeEnd" class="form-label fw-bold"><?= gettext('To') ?></label>
                                <input type="date" name="RangeEnd" id="RangeEnd" class="form-control"
                                       value="<?= InputUtils::escapeAttribute($rangeEnd) ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Optional Settings -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><?= gettext('Optional Settings') ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if ($allCalendars->count() > 0): ?>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?= gettext('Pin to Calendars') ?></label>
                                <?php foreach ($allCalendars as $calendar): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="PinnedCalendars[]"
                                               id="cal_<?= (int) $calendar->getId() ?>"
                                               value="<?= (int) $calendar->getId() ?>">
                                        <label class="form-check-label" for="cal_<?= (int) $calendar->getId() ?>">
                                            <?= InputUtils::escapeHTML($calendar->getName()) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="LinkedGroupId" class="form-label fw-bold"><?= gettext('Linked Group') ?></label>
                                <select name="LinkedGroupId" id="LinkedGroupId" class="form-select">
                                    <option value="0"><?= gettext('No Group (Select for Kiosk Check-in)') ?></option>
                                    <?php foreach ($allGroups as $group): ?>
                                        <option value="<?= (int) $group->getId() ?>">
                                            <?= InputUtils::escapeHTML($group->getName()) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-secondary">
                                    <?= gettext('Link events to a group for Kiosk check-in functionality.') ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold"><?= gettext('Event Status') ?></label>
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check" name="EventStatus" id="statusActive" value="0" checked autocomplete="off">
                            <label class="btn btn-outline-success" for="statusActive">
                                <i class="ti ti-check me-1"></i><?= gettext('Active') ?>
                            </label>
                            <input type="radio" class="btn-check" name="EventStatus" id="statusInactive" value="1" autocomplete="off">
                            <label class="btn btn-outline-secondary" for="statusInactive">
                                <i class="ti ti-ban me-1"></i><?= gettext('Inactive') ?>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="<?= $sRootPath ?>/event/dashboard" class="btn btn-outline-secondary">
                    <i class="ti ti-x me-1"></i><?= gettext('Cancel') ?>
                </a>
                <button type="submit" name="CreateRepeat" value="1" class="btn btn-primary btn-lg">
                    <i class="ti ti-repeat me-1"></i><?= gettext('Create Repeat Events') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/repeat-event-editor.min.js') ?>"></script>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    (function() {
        <?= getQuillEditorInitScript('EventDesc', 'EventDescInput', gettext("Enter event description..."), false) ?>
    })();
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
