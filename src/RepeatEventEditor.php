<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';
require_once __DIR__ . '/Include/QuillEditorHelper.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\CalendarQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\Service\EventService;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

if (AuthenticationManager::getCurrentUser()->isAddEvent() === false) {
    RedirectUtils::securityRedirect('AddEvent');
}

$sPageTitle = gettext('Create Repeat Events');

// Pre-select an event type if passed via GET
$tyid = 0;
if (array_key_exists('EN_tyid', $_GET)) {
    $tyid = InputUtils::filterInt($_GET['EN_tyid']);
} elseif (array_key_exists('EN_tyid', $_POST)) {
    $tyid = InputUtils::filterInt($_POST['EN_tyid']);
}

$successCount = 0;
$iErrors = 0;
$errorMsg = '';

// --- Process form submission ---
if (isset($_POST['CreateRepeat'])) {
    $iTypeID      = InputUtils::filterInt($_POST['EventTypeID'] ?? 0);
    $sTitle       = InputUtils::legacyFilterInput($_POST['EventTitle'] ?? '');
    $sDesc        = InputUtils::sanitizeHTML($_POST['EventDescInput'] ?? '');
    $sText        = '';
    $sStartTime   = InputUtils::legacyFilterInput($_POST['StartTime'] ?? '09:00');
    $sEndTime     = InputUtils::legacyFilterInput($_POST['EndTime'] ?? '10:00');
    $sRecurType   = InputUtils::legacyFilterInput($_POST['RecurType'] ?? '');
    $sRecurDOW    = InputUtils::legacyFilterInput($_POST['RecurDOW'] ?? 'Sunday');
    $iRecurDOM    = InputUtils::filterInt($_POST['RecurDOM'] ?? 1);
    $sRecurDOY    = InputUtils::legacyFilterInput($_POST['RecurDOY'] ?? '01-01');
    $sRangeStart  = InputUtils::legacyFilterInput($_POST['RangeStart'] ?? '');
    $sRangeEnd    = InputUtils::legacyFilterInput($_POST['RangeEnd'] ?? '');
    $iStatus      = InputUtils::filterInt($_POST['EventStatus'] ?? 0);
    $iLinkedGroup = InputUtils::filterInt($_POST['LinkedGroupId'] ?? 0);
    $pinnedCalendars = [];
    if (!empty($_POST['PinnedCalendars'])) {
        foreach ((array) $_POST['PinnedCalendars'] as $calId) {
            $pinnedCalendars[] = (int) $calId;
        }
    }

    $validRecurTypes = ['weekly', 'monthly', 'yearly'];

    if (empty($iTypeID)) {
        $iErrors++;
        $errorMsg = gettext('You must select an event type.');
    } elseif (empty($sTitle)) {
        $iErrors++;
        $errorMsg = gettext('Event title is required.');
    } elseif (!in_array($sRecurType, $validRecurTypes, true)) {
        $iErrors++;
        $errorMsg = gettext('You must select a valid recurrence pattern.');
    } elseif (empty($sRangeStart) || empty($sRangeEnd)) {
        $iErrors++;
        $errorMsg = gettext('You must specify a date range.');
    } elseif ($sRangeStart > $sRangeEnd) {
        $iErrors++;
        $errorMsg = gettext('Range start must be before range end.');
    }

    if ($iErrors === 0) {
        try {
            $service = new EventService();
            $createdIds = $service->createRepeatEvents([
                'title'           => $sTitle,
                'typeId'          => $iTypeID,
                'desc'            => $sDesc,
                'text'            => $sText,
                'startTime'       => $sStartTime,
                'endTime'         => $sEndTime,
                'recurType'       => $sRecurType,
                'recurDOW'        => $sRecurDOW,
                'recurDOM'        => $iRecurDOM,
                'recurDOY'        => $sRecurDOY,
                'rangeStart'      => $sRangeStart,
                'rangeEnd'        => $sRangeEnd,
                'pinnedCalendars' => $pinnedCalendars,
                'linkedGroupId'   => $iLinkedGroup,
                'inactive'        => $iStatus,
            ]);
            $successCount = count($createdIds);
        } catch (\InvalidArgumentException $e) {
            $iErrors++;
            $errorMsg = $e->getMessage();
        }

        if ($iErrors === 0) {
            // Store success message in session and redirect
            $_SESSION['repeat_event_success'] = sprintf(
                ngettext('%d repeat event created successfully.', '%d repeat events created successfully.', $successCount),
                $successCount
            );
            RedirectUtils::redirect('ListEvents.php');
        }
    }
}

// --- Pre-fill defaults from event type if provided ---
$eventType = null;
$sTypeName = '';
$iTypeID = $tyid;
$sDefStartTime = '09:00';
$sDefEndTime = '10:00';
$sDefRecurType = 'weekly';
$sDefRecurDOW = 'Sunday';
$iDefRecurDOM = 1;
$sDefRecurDOY = '01-01';

if ($iTypeID > 0) {
    $eventType = EventTypeQuery::create()->findOneById($iTypeID);
    if ($eventType !== null) {
        $sTypeName = $eventType->getName();
        $defStart = $eventType->getDefStartTime();
        if ($defStart instanceof \DateTime) {
            $sDefStartTime = $defStart->format('H:i');
            $endHour = ((int) $defStart->format('H') + 1) % 24;
            $sDefEndTime = sprintf('%02d:%s', $endHour, $defStart->format('i'));
        } elseif (!empty($defStart)) {
            $dt = \DateTime::createFromFormat('H:i:s', (string) $defStart) ?: \DateTime::createFromFormat('H:i', (string) $defStart);
            if ($dt) {
                $sDefStartTime = $dt->format('H:i');
                $endHour = ((int) $dt->format('H') + 1) % 24;
                $sDefEndTime = sprintf('%02d:%s', $endHour, $dt->format('i'));
            }
        }
        $sDefRecurType = $eventType->getDefRecurType() ?: 'weekly';
        $sDefRecurDOW = $eventType->getDefRecurDow() ?: 'Sunday';
        $sDefRecurDOM = $eventType->getDefRecurDom();
        $iDefRecurDOM = !empty($sDefRecurDOM) ? (int) $sDefRecurDOM : 1;
        $defDOY = $eventType->getDefRecurDoy();
        if ($defDOY instanceof \DateTime) {
            $sDefRecurDOY = $defDOY->format('m-d');
        } elseif (!empty($defDOY)) {
            // Extract MM-DD from stored date string
            $parts = explode('-', (string) $defDOY);
            if (count($parts) >= 3) {
                $sDefRecurDOY = $parts[1] . '-' . $parts[2];
            }
        }
    }
}

// Fetch all event types for the selector
$allEventTypes = EventTypeQuery::create()->orderByName()->find();

// Fetch all calendars for pinning
$allCalendars = CalendarQuery::create()->orderByName()->find();

// Fetch all groups for linking
$allGroups = GroupQuery::create()->orderByName()->find();

$sRangeStart = DateTimeUtils::getTodayDate();
$sRangeEnd = (new \DateTime('+1 year'))->format('Y-m-d');

require_once __DIR__ . '/Include/Header.php';
?>

<div class="mb-3">
    <a href="ListEvents.php" class="btn btn-outline-secondary">
        <i class="fas fa-chevron-left mr-1"></i>
        <?= gettext('Return to Events') ?>
    </a>
</div>

<div class="card card-primary">
    <div class="card-header">
        <h3 class="card-title mb-0">
            <i class="fas fa-redo mr-2"></i><?= gettext('Create Repeat Events') ?>
        </h3>
    </div>
    <div class="card-body">
        <?php if ($iErrors > 0): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <?= InputUtils::escapeHTML($errorMsg) ?>
            </div>
        <?php endif; ?>

        <p class="text-muted">
            <?= gettext('Use this form to bulk-create a series of recurring events. Each event is created individually and can be edited or deleted independently after creation.') ?>
        </p>

        <form method="POST" action="RepeatEventEditor.php" name="RepeatEventsForm">
            <!-- Event Type -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><?= gettext('Event Template') ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="EventTypeID" class="font-weight-bold">
                                    <span class="text-danger">*</span> <?= gettext('Event Type') ?>
                                </label>
                                <?php if ($iTypeID > 0 && $eventType !== null): ?>
                                    <input type="hidden" name="EventTypeID" value="<?= InputUtils::escapeAttribute($iTypeID) ?>">
                                    <div class="form-control-plaintext">
                                        <span class="badge badge-info" style="font-size: 1rem;"><?= InputUtils::escapeHTML($sTypeName) ?></span>
                                        <a href="RepeatEventEditor.php" class="btn btn-sm btn-outline-secondary ml-2">
                                            <i class="fas fa-exchange-alt mr-1"></i><?= gettext('Change') ?>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <select name="EventTypeID" id="EventTypeID" class="form-control" required>
                                        <option value=""><?= gettext('Select an event type…') ?></option>
                                        <?php foreach ($allEventTypes as $et): ?>
                                            <option value="<?= InputUtils::escapeAttribute($et->getId()) ?>">
                                                <?= InputUtils::escapeHTML($et->getName()) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="EventTitle" class="font-weight-bold">
                                    <span class="text-danger">*</span> <?= gettext('Event Title') ?>
                                </label>
                                <input type="text" name="EventTitle" id="EventTitle" class="form-control"
                                       value="<?= InputUtils::escapeAttribute($sTypeName) ?>"
                                       maxlength="100" required
                                       placeholder="<?= gettext('e.g., Sunday Worship Service') ?>">
                                <small class="form-text text-muted"><?= gettext('This title will be used for all generated events.') ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold"><?= gettext('Event Description') ?></label>
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
                            <div class="form-group">
                                <label for="StartTime" class="font-weight-bold">
                                    <span class="text-danger">*</span> <?= gettext('Start Time') ?>
                                </label>
                                <input type="time" name="StartTime" id="StartTime" class="form-control"
                                       value="<?= InputUtils::escapeAttribute($sDefStartTime) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="EndTime" class="font-weight-bold">
                                    <span class="text-danger">*</span> <?= gettext('End Time') ?>
                                </label>
                                <input type="time" name="EndTime" id="EndTime" class="form-control"
                                       value="<?= InputUtils::escapeAttribute($sDefEndTime) ?>" required>
                            </div>
                        </div>
                    </div>
                    <small class="form-text text-muted"><?= gettext('The same start and end time will be used for all generated events.') ?></small>
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
                            <input class="form-check-input mt-0 mr-2" type="radio" name="RecurType" id="recurWeekly"
                                   value="weekly" <?= ($sDefRecurType === 'weekly') ? 'checked' : '' ?>>
                            <label class="form-check-label mr-3 mb-0" for="recurWeekly" style="min-width: 120px;"><?= gettext('Every week on') ?></label>
                            <select name="RecurDOW" id="RecurDOW" class="form-control form-control-sm" style="width: 160px;"
                                <?= ($sDefRecurType !== 'weekly') ? 'disabled' : '' ?>>
                                <?php
                                $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                foreach ($days as $day):
                                    $sel = ($sDefRecurDOW === $day) ? 'selected' : '';
                                    ?>
                                    <option value="<?= $day ?>" <?= $sel ?>><?= gettext($day) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Monthly -->
                        <div class="form-check mb-3 d-flex align-items-center flex-wrap">
                            <input class="form-check-input mt-0 mr-2" type="radio" name="RecurType" id="recurMonthly"
                                   value="monthly" <?= ($sDefRecurType === 'monthly') ? 'checked' : '' ?>>
                            <label class="form-check-label mr-3 mb-0" for="recurMonthly" style="min-width: 120px;"><?= gettext('Every month on the') ?></label>
                            <select name="RecurDOM" id="RecurDOM" class="form-control form-control-sm" style="width: 100px;"
                                <?= ($sDefRecurType !== 'monthly') ? 'disabled' : '' ?>>
                                <?php for ($d = 1; $d <= 31; $d++): ?>
                                    <option value="<?= $d ?>" <?= ($iDefRecurDOM === $d) ? 'selected' : '' ?>>
                                        <?= date('jS', mktime(0, 0, 0, 1, $d, 2000)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <!-- Yearly -->
                        <div class="form-check mb-3 d-flex align-items-center flex-wrap">
                            <input class="form-check-input mt-0 mr-2" type="radio" name="RecurType" id="recurYearly"
                                   value="yearly" <?= ($sDefRecurType === 'yearly') ? 'checked' : '' ?>>
                            <label class="form-check-label mr-3 mb-0" for="recurYearly" style="min-width: 120px;"><?= gettext('Every year on') ?></label>
                            <input type="text" name="RecurDOY" id="RecurDOY" class="form-control form-control-sm"
                                   style="width: 120px;" placeholder="MM-DD"
                                   value="<?= InputUtils::escapeAttribute($sDefRecurDOY) ?>"
                                   pattern="\d{2}-\d{2}" title="<?= gettext('Format: MM-DD (e.g. 04-12 for April 12)') ?>"
                                <?= ($sDefRecurType !== 'yearly') ? 'disabled' : '' ?>>
                            <small class="text-muted ml-2"><?= gettext('Format: MM-DD (e.g. 04-12 for April 12)') ?></small>
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
                    <p class="text-muted mb-3">
                        <?= gettext('Events will be created for every matching occurrence within this date range.') ?>
                    </p>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="RangeStart" class="font-weight-bold"><?= gettext('From') ?></label>
                                <input type="date" name="RangeStart" id="RangeStart" class="form-control"
                                       value="<?= InputUtils::escapeAttribute($sRangeStart) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="RangeEnd" class="font-weight-bold"><?= gettext('To') ?></label>
                                <input type="date" name="RangeEnd" id="RangeEnd" class="form-control"
                                       value="<?= InputUtils::escapeAttribute($sRangeEnd) ?>" required>
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
                        <!-- Calendars -->
                        <?php if ($allCalendars->count() > 0): ?>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold"><?= gettext('Pin to Calendars') ?></label>
                                <?php foreach ($allCalendars as $calendar): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="PinnedCalendars[]"
                                               id="cal_<?= InputUtils::escapeAttribute($calendar->getId()) ?>"
                                               value="<?= InputUtils::escapeAttribute($calendar->getId()) ?>">
                                        <label class="form-check-label" for="cal_<?= InputUtils::escapeAttribute($calendar->getId()) ?>">
                                            <?= InputUtils::escapeHTML($calendar->getName()) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Linked Group -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="LinkedGroupId" class="font-weight-bold"><?= gettext('Linked Group') ?></label>
                                <select name="LinkedGroupId" id="LinkedGroupId" class="form-control">
                                    <option value="0"><?= gettext('No Group (Select for Kiosk Check-in)') ?></option>
                                    <?php foreach ($allGroups as $group): ?>
                                        <option value="<?= InputUtils::escapeAttribute($group->getId()) ?>">
                                            <?= InputUtils::escapeHTML($group->getName()) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">
                                    <?= gettext('Link events to a group for Kiosk check-in functionality.') ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label class="font-weight-bold"><?= gettext('Event Status') ?></label>
                        <div class="btn-group btn-group-toggle" data-toggle="buttons">
                            <label class="btn btn-outline-success active">
                                <input type="radio" name="EventStatus" value="0" checked>
                                <i class="fas fa-check mr-1"></i><?= gettext('Active') ?>
                            </label>
                            <label class="btn btn-outline-secondary">
                                <input type="radio" name="EventStatus" value="1">
                                <i class="fas fa-ban mr-1"></i><?= gettext('Inactive') ?>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="ListEvents.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times mr-1"></i><?= gettext('Cancel') ?>
                </a>
                <button type="submit" name="CreateRepeat" value="1" class="btn btn-primary btn-lg">
                    <i class="fas fa-redo mr-1"></i><?= gettext('Create Repeat Events') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function () {
    // Recurrence type: enable/disable associated inputs
    function updateRecurrenceInputs() {
        var selected = $('input[name="RecurType"]:checked').val();
        $('#RecurDOW').prop('disabled', selected !== 'weekly');
        $('#RecurDOM').prop('disabled', selected !== 'monthly');
        $('#RecurDOY').prop('disabled', selected !== 'yearly');
    }

    $('input[name="RecurType"]').on('change', updateRecurrenceInputs);
    updateRecurrenceInputs();

    // Validate end time >= start time
    $('form[name="RepeatEventsForm"]').on('submit', function (e) {
        var startTime = $('#StartTime').val();
        var endTime = $('#EndTime').val();
        if (startTime && endTime && endTime <= startTime) {
            e.preventDefault();
            alert('<?= addslashes(gettext('End time must be after start time.')) ?>');
            return false;
        }

        var rangeStart = $('#RangeStart').val();
        var rangeEnd = $('#RangeEnd').val();
        if (rangeStart && rangeEnd && rangeEnd < rangeStart) {
            e.preventDefault();
            alert('<?= addslashes(gettext('Range end date must be on or after range start date.')) ?>');
            return false;
        }
    });
});
</script>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    (function() {
        <?= getQuillEditorInitScript('EventDesc', 'EventDescInput', gettext("Enter event description..."), false) ?>
    })();
</script>

<?php require_once __DIR__ . '/Include/Footer.php'; ?>
