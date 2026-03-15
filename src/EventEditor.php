<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';
require_once __DIR__ . '/Include/QuillEditorHelper.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\EventAudience;
use ChurchCRM\model\ChurchCRM\EventAudienceQuery;
use ChurchCRM\model\ChurchCRM\EventCountNameQuery;
use ChurchCRM\model\ChurchCRM\EventCountsQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;

if (AuthenticationManager::getCurrentUser()->isAddEvent() === false) {
    RedirectUtils::securityRedirect('AddEvent');
}

$sPageTitle = gettext('Church Event Editor');

require_once __DIR__ . '/Include/Header.php';

$sAction = 'Create Event';

if (isset($_GET['calendarAction'])) {
    $sAction = 'Edit';
    $sOpp = $_GET['calendarAction'];
} else {
    if (array_key_exists('Action', $_POST)) {
        $sAction = $_POST['Action'];
    }

    // Check for EID from GET (from ListEvents link) or POST (from form submission)
    if (array_key_exists('EID', $_GET)) {
        $sOpp = InputUtils::filterInt($_GET['EID']);
        $sAction = 'Edit';
    } elseif (array_key_exists('EID', $_POST)) {
        $sOpp = InputUtils::filterInt($_POST['EID']);
    } // from EDIT button on event listing

    $tyid = 0;

    if (array_key_exists('EN_tyid', $_POST)) {
        $tyid = InputUtils::filterInt($_POST['EN_tyid']);
    }
}

$EventExists = 0;
$iEventID = 0;
$iTypeID = 0;
$iErrors = 0;
$iLinkedGroupId = 0;

if ($sAction === 'Create Event' && !empty($tyid)) {
    // User is coming from the event types screen and thus there
    // is no existing event in the event_event table
    //
    // will use the event type information to smart-prefill the
    // event fields...but still allow the user to edit everything
    // except event type since event type is tied to the attendance count fields

    // Use Propel ORM instead of raw SQL for type safety and SQL injection prevention (GHSA-wxcc-gvfv-56fg)
    $eventType = EventTypeQuery::create()->findOneById((int)$tyid);
    
    if ($eventType !== null) {
        $iTypeID = $eventType->getId();
        $sTypeName = $eventType->getName();
        $sDefStartTime = $eventType->getDefStartTime() ? $eventType->getDefStartTime()->format('H:i:s') : '00:00:00';
        $iDefRecurDOW = $eventType->getDefRecurDow();
        $iDefRecurDOM = $eventType->getDefRecurDom();
        $sDefRecurDOY = $eventType->getDefRecurDoy();
        $sDefRecurType = $eventType->getDefRecurType();
    } else {
        // Handle case where event type is not found
        $iTypeID = 0;
        $sTypeName = '';
        $sDefStartTime = '00:00';
        $iDefRecurDOW = 0;
        $iDefRecurDOM = 0;
        $sDefRecurDOY = '';
        $sDefRecurType = 'none';
    }

    // Use Propel ORM to fetch event count names
    $eventCountNames = EventCountNameQuery::create()
        ->filterByTypeId((int)$iTypeID)
        ->orderById()
        ->find();
    
    $iNumCounts = count($eventCountNames);

    $aCountID = [];
    $aCountName = [];
    $aCount = [];

    if ($iNumCounts > 0) {
        $c = 0;
        foreach ($eventCountNames as $countName) {
            $aCountID[$c] = $countName->getId();
            $aCountName[$c] = $countName->getName();
            $aCount[$c] = 0;
            $c++;
        }
    }
    $nCnts = $iNumCounts;
    $sCountNotes = '';

    // This switch manages the smart-prefill of the form based on the event type
    // definitions, recurrence type, etc.
    switch ($sDefRecurType) {
        case 'none':
            $sEventStartDate = DateTimeUtils::getTodayDate();
            $sEventEndDate = $sEventStartDate;
            $aStartTimeTokens = explode(':', $sDefStartTime);
            $iEventStartHour = $aStartTimeTokens[0];
            $iEventStartMins = $aStartTimeTokens[1];
            $iEventEndHour = intval($aStartTimeTokens[0]) + 1;
            $iEventEndMins = $aStartTimeTokens[1];
            break;

        case 'weekly':
            // Check for the last occurrence of this type_id in the events table and
            // create a new event based on this date reference
            $sSQL = "SELECT * FROM events_event WHERE event_type = '$iTypeID' ORDER BY event_start DESC LIMIT 1";
            $ecOpps = RunQuery($sSQL);
            $numRows = mysqli_num_rows($ecOpps);
            if ($numRows > 0) {
                // Use the most recent event if it exists
                $ecRow = mysqli_fetch_array($ecOpps, MYSQLI_BOTH);
                extract($ecRow);
                $aStartTokens = explode(' ', $event_start);
                $ceEventStartDate = $aStartTokens[0];
                $sEventStartDate = DateTimeUtils::getDateRelativeTo($ceEventStartDate, '+1 week');

                $aEventStartTimeTokens = explode(':', $sDefStartTime);
                $iEventStartHour = $aEventStartTimeTokens[0];
                $iEventStartMins = $aEventStartTimeTokens[1];

                $sEventEndDate = $sEventStartDate;
                $iEventEndHour = $iEventStartHour + 1;
                $iEventEndMins = $iEventStartMins;
            } else {
                // Use the event type definition
                $sEventStartDate = DateTimeUtils::getRelativeDate("last $iDefRecurDOW");
                $aStartTimeTokens = explode(':', $sDefStartTime);
                $iEventStartHour = $aStartTimeTokens[0];
                $iEventStartMins = $aStartTimeTokens[1];
                $sEventEndDate = $sEventStartDate;
                $iEventEndHour = intval($aStartTimeTokens[0]) + 1;
                $iEventEndMins = $aStartTimeTokens[1];
            }
            break;

        case 'monthly':
            // Check for the last occurrence of this type_id in the events table and
            // create a new event based on this date reference
            $sSQL = "SELECT * FROM events_event WHERE event_type = '$iTypeID' ORDER BY event_start DESC LIMIT 1";
            $ecOpps = RunQuery($sSQL);
            $numRows = mysqli_num_rows($ecOpps);
            if ($numRows > 0) {
                // Use the most recent event if it exists
                $ecRow = mysqli_fetch_array($ecOpps, MYSQLI_BOTH);
                extract($ecRow);
                $aStartTokens = explode(' ', $event_start);
                $ceEventStartDate = $aStartTokens[0];
                $ceDMY = explode('-', $aStartTokens[0]);
                $aEventStartTimeTokens = explode(':', $ceStartTokens[1]);

                $sEventStartDate = DateTimeUtils::formatDateFromComponents((int) $ceDMY[0], (int) $ceDMY[1] + 1, (int) $ceDMY[2]);
                $iEventStartHour = $aEventStartTimeTokens[0];
                $iEventStartMins = $aEventStartTimeTokens[1];
                $sEventEndDate = $sEventStartDate;
                $iEventEndHour = intval($aEventStartTimeTokens[0]) + 1;
                $iEventEndMins = $aEventStartTimeTokens[1];
            } else {
                // Use the event type definition
                $currentDOM = DateTimeUtils::getCurrentDay();
                $currentMonth = DateTimeUtils::getCurrentMonth();
                $currentYear = DateTimeUtils::getCurrentYear();
                if ($currentDOM < $iDefRecurDOM) {
                    $sEventStartDate = DateTimeUtils::formatDateFromComponents($currentYear, $currentMonth - 1, $iDefRecurDOM);
                } else {
                    $sEventStartDate = DateTimeUtils::formatDateFromComponents($currentYear, $currentMonth, $iDefRecurDOM);
                }

                $aStartTimeTokens = explode(':', $ceDefStartTime);
                $iEventStartHour = $aStartTimeTokens[0];
                $iEventStartMins = $aStartTimeTokens[1];
                $sEventEndDate = $sEventStartDate;
                $iEventEndHour = intval($aStartTimeTokens[0]) + 1;
                $iEventEndMins = $aStartTimeTokens[1];
            }
            break;

        case 'yearly':
            $sSQL = "SELECT * FROM events_event WHERE event_type = '$iTypeID' ORDER BY event_start DESC LIMIT 1";
            $ecOpps = RunQuery($sSQL);
            $numRows = mysqli_num_rows($ecOpps);
            if ($numRows > 0) {
                // Use the most recent event, if it exists
                $ecRow = mysqli_fetch_array($ecOpps, MYSQLI_BOTH);
                extract($ecRow);
                $aStartTokens = explode(' ', $event_start);
                $sEventStartDate = $aStartTokens[0];
                $aDMY = explode('-', $aStartTokens[0]);
                $aEventStartTimeTokens = explode(':', $aStartTokens[1]);

                $sEventStartDate = DateTimeUtils::formatDateFromComponents((int) $aDMY[0] + 1, (int) $aDMY[1], (int) $aDMY[2]);
                $iEventStartHour = $aEventStartTimeTokens[0];
                $iEventStartMins = $aEventStartTimeTokens[1];
                $sEventEndDate = $sEventStartDate;
                $iEventEndHour = intval($aEventStartTimeTokens[0]) + 1;
                $iEventEndMins = $aEventStartTimeTokens[1];
            } else {
                // Use the event type definition
                $currentDOY = time();
                $defaultDOY = strtotime($sDefRecurDOY);
                $currentYear = DateTimeUtils::getCurrentYear();
                if ($currentDOY < $defaultDOY) {
                    // Event is in the future
                    $sEventStartDate = $sDefRecurDOY;
                } elseif ($currentDOY > $defaultDOY + (365 * 24 * 60 * 60)) {
                    // Event is over 1 year in the past
                    $aDMY = explode('-', $sDefRecurDOY);
                    $sEventStartDate = DateTimeUtils::formatDateFromComponents($currentYear - 1, (int) $aDMY[1], (int) $aDMY[2]);
                } else {
                    // Event is past
                    $aDMY = explode('-', $sDefRecurDOY);
                    $sEventStartDate = DateTimeUtils::formatDateFromComponents($currentYear, (int) $aDMY[1], (int) $aDMY[2]);
                }

                $aStartTimeTokens = explode(':', $sDefStartTime);
                $iEventStartHour = $aStartTimeTokens[0];
                $iEventStartMins = $aStartTimeTokens[1];
                $sEventEndDate = $sEventStartDate;
                $iEventEndHour = intval($aStartTimeTokens[0]) + 1;
                $iEventEndMins = $aStartTimeTokens[1];
            }
            break;
    }
    $sEventTitle = $sEventStartDate . '-' . $sTypeName;
    $sEventDesc = '';
    $sEventText = '';
    $iEventStatus = 0;
} elseif ($sAction === 'Edit' && !empty($sOpp)) {
    $EventExists = 1;
    // Use Propel ORM instead of raw SQL for SQL injection prevention
    $iEventID = (int) $sOpp;
    $event = EventQuery::create()
        ->joinWithEventType()
        ->findOneById($iEventID);
    
    if ($event === null) {
        $iErrors++;
        LoggerUtils::getAppLogger()->warning('Event not found: ' . $iEventID);
    } else {
        // Use Propel getters instead of extract() to avoid field name mismatches
        $iEventID = $event->getId();
        $iTypeID = $event->getType();
        $sTypeName = $event->getEventType()->getName();
        $sEventTitle = $event->getTitle();
        $sEventDesc = $event->getDesc();
        $sEventText = $event->getText();
        
        // Parse start date/time
        $eventStart = $event->getStart();
        if ($eventStart instanceof \DateTime) {
            $sEventStartDate = $eventStart->format('Y-m-d');
            $iEventStartHour = $eventStart->format('H');
            $iEventStartMins = $eventStart->format('i');
        } else {
            $aStartTokens = explode(' ', (string)$eventStart);
            $sEventStartDate = $aStartTokens[0];
            $aStartTimeTokens = explode(':', $aStartTokens[1] ?? '00:00');
            $iEventStartHour = $aStartTimeTokens[0];
            $iEventStartMins = $aStartTimeTokens[1];
        }
        
        // Parse end date/time
        $eventEnd = $event->getEnd();
        if ($eventEnd instanceof \DateTime) {
            $sEventEndDate = $eventEnd->format('Y-m-d');
            $iEventEndHour = $eventEnd->format('H');
            $iEventEndMins = $eventEnd->format('i');
        } else {
            $aEndTokens = explode(' ', (string)$eventEnd);
            $sEventEndDate = $aEndTokens[0];
            $aEndTimeTokens = explode(':', $aEndTokens[1] ?? '00:00');
            $iEventEndHour = $aEndTimeTokens[0];
            $iEventEndMins = $aEndTimeTokens[1];
        }
        
        $iEventStatus = $event->getInActive();

        // Get linked group (via EventAudience)
        $linkedGroups = $event->getGroups();
        $iLinkedGroupId = 0;
        if ($linkedGroups->count() > 0) {
            // Get the first linked group (typically only one for kiosk/check-in purposes)
            $iLinkedGroupId = $linkedGroups->getFirst()->getId();
        }

        // Get event attendance counts using Propel ORM
        $eventCounts = EventCountsQuery::create()
            ->filterByEvtcntEventid($iEventID)
            ->orderByEvtcntCountid()
            ->find();
        
        $iNumCounts = $eventCounts->count();
        $nCnts = $iNumCounts;

        if ($iNumCounts) {
            $c = 0;
            foreach ($eventCounts as $countRow) {
                $aCountID[$c] = $countRow->getEvtcntCountid();
                $aCountName[$c] = $countRow->getEvtcntCountname();
                $aCount[$c] = $countRow->getEvtcntCountcount();
                $sCountNotes = $countRow->getEvtcntNotes();
                $c++;
            }
        }
    }
} elseif (isset($_POST['SaveChanges'])) {
    $iEventID = InputUtils::legacyFilterInput($_POST['EventID'], 'int');
    $iTypeID = InputUtils::legacyFilterInput($_POST['EventTypeID'], 'int');
    $EventExists = InputUtils::legacyFilterInput($_POST['EventExists'], 'int');
    $sEventTitle = InputUtils::legacyFilterInput($_POST['EventTitle']);
    $sEventDesc = $_POST['EventDescInput'];
    if (empty($_POST['EventTypeID'])) {
        $bEventTypeError = true;
        $iErrors++;
    } else {
        $sSQL = "SELECT type_name FROM event_types WHERE type_id = '" . $iTypeID . "' LIMIT 1";
        $rsOpps = RunQuery($sSQL);
        $aRow = mysqli_fetch_array($rsOpps, MYSQLI_BOTH);
        extract($aRow);
        $sTypeName = $type_name;
    }
    $sEventText = $_POST['EventTextInput'];
    if ($_POST['EventStatus'] === null) {
        $bStatusError = true;
        $iErrors++;
    }
    $sEventRange = $_POST['EventDateRange'];
    $sEventStartDateTime = DateTime::createFromFormat('Y-m-d H:i a', explode(' - ', $sEventRange)[0]);
    $sEventEndDateTime = DateTime::createFromFormat('Y-m-d H:i a', explode(' - ', $sEventRange)[1]);
    $sEventStart = $sEventStartDateTime->format('Y-m-d H:i');
    $sEventStartDate = $sEventStartDateTime->format('Y-m-d');
    $iEventStartHour = $sEventStartDateTime->format('H');
    $iEventStartMins = $sEventStartDateTime->format('i');
    $sEventEnd = $sEventEndDateTime->format('Y-m-d H:i');
    $sEventEndDate = $sEventEndDateTime->format('Y-m-d');
    $iEventEndHour = $sEventEndDateTime->format('H');
    $iEventEndMins = $sEventEndDateTime->format('i');
    $iEventStatus = $_POST['EventStatus'];

    $iNumCounts = $_POST['NumAttendCounts'];
    $nCnts = $iNumCounts;
    $aEventCountArry = $_POST['EventCount'];
    $aEventCountIDArry = $_POST['EventCountID'];
    $aEventCountNameArry = $_POST['EventCountName'];

    foreach ($aEventCountArry as $CCC) {
        $aCount[] = $CCC;
    }
    foreach ($aEventCountIDArry as $CID) {
        $aCountID[] = $CID;
    }
    foreach ($aEventCountNameArry as $CNM) {
        $aCountName[] = $CNM;
    }

    $sCountNotes = $_POST['EventCountNotes'];
    
    // Get selected linked group (for kiosk/check-in functionality)
    $iLinkedGroupId = isset($_POST['LinkedGroupId']) ? InputUtils::filterInt($_POST['LinkedGroupId']) : 0;

    if ($iErrors === 0) {
        if ($EventExists === 0) {
            $event = new Event();
            $event
                ->setType(InputUtils::legacyFilterInput($iTypeID))
                ->setTitle(InputUtils::legacyFilterInput($sEventTitle))
                ->setDesc(InputUtils::sanitizeHTML($sEventDesc))
                ->setText(InputUtils::sanitizeHTML($sEventText))
                ->setStart(InputUtils::legacyFilterInput($sEventStart))
                ->setEnd(InputUtils::legacyFilterInput($sEventEnd))
                ->setInActive(InputUtils::legacyFilterInput($iEventStatus));
            $event->save();
            $event->reload();

            $iEventID = $event->getId();
            
            // Link group to event (for kiosk/check-in functionality)
            if ($iLinkedGroupId > 0) {
                $eventAudience = new EventAudience();
                $eventAudience->setEventId($iEventID);
                $eventAudience->setGroupId($iLinkedGroupId);
                $eventAudience->save();
            }
            
            for ($c = 0; $c < $iNumCounts; $c++) {
                $cCnt = ltrim(rtrim($aCountName[$c]));
                $filteredCount = InputUtils::legacyFilterInput($aCount[$c]);
                $filteredCountNotes = InputUtils::legacyFilterInput($sCountNotes);
                $sSQL = "INSERT eventcounts_evtcnt
                       (evtcnt_eventid, evtcnt_countid, evtcnt_countname, evtcnt_countcount, evtcnt_notes)
                       VALUES
                       ('" . InputUtils::legacyFilterInput($iEventID) . "',
                        '" . InputUtils::legacyFilterInput($aCountID[$c]) . "',
                        '" . InputUtils::legacyFilterInput($aCountName[$c]) . "',
                        '" . $filteredCount . "',
                        '" . $filteredCountNotes . "') ON DUPLICATE KEY UPDATE evtcnt_countcount='" . $filteredCount . "', evtcnt_notes='" . $filteredCountNotes . "'";
                RunQuery($sSQL);
            }
        } else {
            $event = EventQuery::create()->findOneById(InputUtils::legacyFilterInput($iEventID));
            $event
                ->setType(InputUtils::legacyFilterInput($iTypeID))
                ->setTitle(InputUtils::legacyFilterInput($sEventTitle))
                ->setDesc(InputUtils::sanitizeHTML($sEventDesc))
                ->setText(InputUtils::sanitizeHTML($sEventText))
                ->setStart(InputUtils::legacyFilterInput($sEventStart))
                ->setEnd(InputUtils::legacyFilterInput($sEventEnd))
                ->setInActive(InputUtils::legacyFilterInput($iEventStatus));
            $event->save();
            
            // Update linked group (for kiosk/check-in functionality)
            // First, remove existing group links for this event
            EventAudienceQuery::create()
                ->filterByEventId($iEventID)
                ->delete();
            
            // Then add the new group link if selected
            if ($iLinkedGroupId > 0) {
                $eventAudience = new EventAudience();
                $eventAudience->setEventId($iEventID);
                $eventAudience->setGroupId($iLinkedGroupId);
                $eventAudience->save();
            }
            
            for ($c = 0; $c < $iNumCounts; $c++) {
                $cCnt = ltrim(rtrim($aCountName[$c]));
                $filteredCount = InputUtils::legacyFilterInput($aCount[$c]);
                $filteredCountNotes = InputUtils::legacyFilterInput($sCountNotes);
                $sSQL = "INSERT eventcounts_evtcnt
                       (evtcnt_eventid, evtcnt_countid, evtcnt_countname, evtcnt_countcount, evtcnt_notes)
                       VALUES
                       ('" . InputUtils::legacyFilterInput($iEventID) . "',
                        '" . InputUtils::legacyFilterInput($aCountID[$c]) . "',
                        '" . InputUtils::legacyFilterInput($aCountName[$c]) . "',
                        '" . $filteredCount . "',
                        '" . $filteredCountNotes . "') ON DUPLICATE KEY UPDATE evtcnt_countcount='" . $filteredCount . "', evtcnt_notes='" . $filteredCountNotes . "'";
                RunQuery($sSQL);
            }
        }
        $EventExists = 1;
        RedirectUtils::redirect('ListEvents.php');
    }
}
?>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <a href="ListEvents.php" class="btn btn-outline-secondary">
        <i class="fas fa-chevron-left mr-1"></i>
        <?= gettext('Return to Events') ?>
    </a>
    <?php if ($EventExists && $iEventID > 0): ?>
    <div>
        <a href="Checkin.php?EventID=<?= $iEventID ?>" class="btn btn-info mr-2">
            <i class="fas fa-clipboard-check mr-1"></i>
            <?= gettext('Manage Check-ins') ?>
        </a>
        <form method="POST" action="ListEvents.php" class="d-inline" onsubmit="return confirm('<?= gettext('Deleting this event will also delete all attendance records. Are you sure?') ?>');">
            <input type="hidden" name="EID" value="<?= $iEventID ?>">
            <button type="submit" name="Action" value="Delete" class="btn btn-outline-danger">
                <i class="fas fa-trash mr-1"></i>
                <?= gettext('Delete Event') ?>
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<div class='card'>
    <div class='card-header'>
        <h3 class="mb-0"><?= ($EventExists === 0) ? gettext('Create a new Event') : gettext('Editing Event') . ': ' . InputUtils::escapeHTML($sEventTitle ?: 'ID ' . $iEventID) ?></h3>
        <?php if ($iErrors > 0): ?>
            <div class="alert alert-danger mt-2 mb-0"><?= gettext('There were ') . $iErrors . gettext(' errors. Please see below') ?></div>
        <?php endif; ?>
    </div>
    <div class='card-body'>
        <p class="text-muted mb-3"><span class="text-danger">*</span> <?= gettext('Required fields') ?></p>

    <form method="post" action="EventEditor.php" name="EventsEditor">
        <input type="hidden" name="EventID" value="<?= ($iEventID) ?>">
        <input type="hidden" name="EventExists" value="<?= $EventExists ?>">

        <table class='table'>
            <?php if (empty($iTypeID)) {
                ?>

                <tr>
                    <td class="LabelColumn"><span class="text-danger">*</span><?= gettext('Event Type') ?>:</td>
                    <td colspan="3" class="TextColumn">
                        <select name='EN_tyid' class='form-control w-100' id='event_type_id'>
                            <option><?= gettext('Select your event type'); ?></option>
                            <?php
                            $sSQL = 'SELECT * FROM event_types';
                            $rsEventTypes = RunQuery($sSQL);
                            while ($aRow = mysqli_fetch_array($rsEventTypes)) {
                                extract($aRow);
                                echo "<option value='" . $type_id . "' >" . $type_name . '</option>';
                            } ?>
                        </select>
                        <?php if ($bEventTypeError) {
                            echo '<div><span class="text-danger">' . gettext('You must pick an event type.') . '</span></div>';
                        } ?>
                        <script nonce="<?= SystemURLs::getCSPNonce() ?>" >
                            $('#event_type_id').on('change', function(e) {
                                e.preventDefault();
                                document.forms.EventsEditor.submit();
                            });
                        </script>
                    </td>
                </tr>

                <?php
            } else { ?>
                <tr>
                    <td class="LabelColumn"><span class="text-danger">*</span><?= gettext('Event Type') ?></td>
                    <td colspan="3" class="TextColumn">
                        <input type="hidden" name="EventTypeName" value="<?= ($sTypeName) ?>">
                        <input type="hidden" name="EventTypeID" value="<?= ($iTypeID) ?>">
                        <span class="badge badge-info font-weight-normal" style="font-size: 1rem;"><?= InputUtils::escapeHTML($sTypeName) ?></span>
                    </td>
                </tr>
                <tr>
                    <td class="LabelColumn"><span class="text-danger">*</span><?= gettext('Event Title') ?></td>
                    <td colspan="3" class="TextColumn">
                        <input type="text" name="EventTitle" value="<?= InputUtils::escapeHTML($sEventTitle) ?>" maxlength="100" class="form-control" placeholder="<?= gettext('Enter event title...') ?>" required>
                    </td>
                </tr>
                <tr>
                    <td class="LabelColumn"><?= gettext('Event Description') ?></td>
                    <td colspan="3" class="TextColumn">
                        <?= getQuillEditorContainer('EventDesc', 'EventDescInput', $sEventDesc, 'form-control', '100px') ?>
                    </td>
                </tr>
                <tr>
                    <td class="LabelColumn"><span class="text-danger">*</span><?= gettext('Date & Time') ?></td>
                    <td class="TextColumn" colspan="3">
                        <input type="text" name="EventDateRange" value=""
                               maxlength="10" id="EventDateRange" class="form-control" style="max-width: 400px;" required>
                        <small class="form-text text-muted"><?= gettext('Select start and end date/time') ?></small>
                    </td>
                </tr>
                <tr>
                    <td class="LabelColumn"><?= gettext('Linked Group') ?></td>
                    <td class="TextColumn" colspan="3">
                        <select name="LinkedGroupId" id="LinkedGroupId" class="form-control" style="max-width: 400px;">
                            <option value="0"><?= gettext('No Group (Select for Kiosk Check-in)') ?></option>
                            <?php
                            $groups = GroupQuery::create()
                                ->orderByName()
                                ->find();
                            foreach ($groups as $group) {
                                $selected = (isset($iLinkedGroupId) && $iLinkedGroupId === $group->getId()) ? 'selected' : '';
                                echo '<option value="' . $group->getId() . '" ' . $selected . '>' . InputUtils::escapeHTML($group->getName()) . '</option>';
                            }
                            ?>
                        </select>
                        <small class="form-text text-muted"><?= gettext('Link this event to a group for Kiosk check-in functionality. The group members will appear on the kiosk.') ?></small>
                    </td>
                </tr>
                <tr>
                    <td class="LabelColumn">
                        <div><?= gettext('Attendance Counts') ?></div>
                        <?php if ($nCnts > 0) { ?>
                        <div class="mt-2">
                            <input type="number" 
                                   id="RealTotal" 
                                   class="form-control" 
                                   readonly 
                                   value="0" 
                                   style="background-color: #e9ecef; font-weight: bold; max-width: 200px;">
                            <small class="form-text text-muted"><?= gettext('Auto-calculated from counts above') ?></small>
                        </div>
                        <?php } ?>
                    </td>
                    <td class="TextColumn" colspan="3">
                        <input type="hidden" name="NumAttendCounts" value="<?= $nCnts ?>">
                        <?php
                        if ($nCnts === 0) {
                            echo gettext('No Attendance counts recorded');
                        } else {
                            ?>
                            <div class="row">
                                <?php
                                for ($c = 0; $c < $nCnts; $c++) {
                                    $countName = $aCountName[$c];
                                    $inputId = 'EventCount_' . $c;
                                    ?>
                                    <div class="col-md-4 col-sm-6 mb-2">
                                        <label for="<?= $inputId ?>" class="font-weight-bold"><?= gettext($countName) ?></label>
                                        <input type="number" 
                                               id="<?= $inputId ?>" 
                                               name="EventCount[]" 
                                               value="<?= (int) $aCount[$c] ?>" 
                                               class="form-control attendance-count"
                                               min="0"
                                               data-count-name="<?= InputUtils::escapeHTML($countName) ?>">
                                        <input type="hidden" name="EventCountID[]" value="<?= $aCountID[$c] ?>">
                                        <input type="hidden" name="EventCountName[]" value="<?= $countName ?>">
                                    </div>
                                    <?php
                                } ?>
                            </div>
                            <div class="form-group mt-3">
                                <label for="EventCountNotes" class="font-weight-bold"><?= gettext('Attendance Notes') ?></label>
                                <input type="text" id="EventCountNotes" name="EventCountNotes" value="<?= InputUtils::escapeHTML($sCountNotes) ?>" class="form-control" placeholder="<?= gettext('Optional notes about attendance...') ?>">
                            </div>
                            <?php
                        } ?>
                    </td>
                </tr>

                <tr>
                    <td class="LabelColumn"><?= gettext('Sermon / Event Text') ?></td>
                    <td colspan="3" class="TextColumn">
                        <small class="form-text text-muted mb-2"><?= gettext('Optional - Add sermon notes or additional event details') ?></small>
                        <?= getQuillEditorContainer('EventText', 'EventTextInput', $sEventText, 'form-control', '200px') ?>
                    </td>
                </tr>

                <tr>
                    <td class="LabelColumn"><span class="text-danger">*</span><?= gettext('Event Status') ?></td>
                    <td colspan="3" class="TextColumn">
                        <div class="btn-group btn-group-toggle" data-toggle="buttons">
                            <label class="btn btn-outline-success <?= ($iEventStatus == 0) ? 'active' : '' ?>">
                                <input type="radio" name="EventStatus" value="0" <?= ($iEventStatus == 0) ? 'checked' : '' ?>>
                                <i class="fas fa-check mr-1"></i><?= gettext('Active') ?>
                            </label>
                            <label class="btn btn-outline-secondary <?= ($iEventStatus == 1) ? 'active' : '' ?>">
                                <input type="radio" name="EventStatus" value="1" <?= ($iEventStatus == 1) ? 'checked' : '' ?>>
                                <i class="fas fa-ban mr-1"></i><?= gettext('Inactive') ?>
                            </label>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td></td>
                    <td>
                        <button type="submit" name="SaveChanges" value="<?= gettext('Save Changes') ?>" class="btn btn-primary btn-lg">
                            <i class="fas fa-save mr-1"></i><?= gettext('Save Changes') ?>
                        </button>
                    </td>
                </tr>
                <?php
            } ?>
        </table>
    </form>
    </div>
</div>
<?php
$eventStart = $sEventStartDate . ' ' . $iEventStartHour . ':' . $iEventStartMins;
$eventEnd = $sEventEndDate . ' ' . $iEventEndHour . ':' . $iEventEndMins;
?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $( document ).ready(function() {
        var startDate = moment("<?= $eventStart?>", "YYYY-MM-DD h:mm").format("YYYY-MM-DD h:mm A");
        var endDate = moment("<?= $eventEnd?>", "YYYY-MM-DD h:mm").format("YYYY-MM-DD h:mm A");
        $('#EventDateRange').val(startDate + " - " + endDate);
        $('#EventDateRange').daterangepicker({
            timePicker: true,
            timePickerIncrement: 30,
            linkedCalendars: true,
            showDropdowns: true,
            locale: {
                format: 'YYYY-MM-DD h:mm A'
            },
            minDate: 1 / 1 / 1900,
            startDate: startDate,
            endDate: endDate
        });

        // Auto-calculate Real Total from all attendance counts
        function updateRealTotal() {
            var total = 0;
            $('.attendance-count').each(function() {
                var val = parseInt($(this).val()) || 0;
                total += val;
            });
            $('#RealTotal').val(total);
        }

        // Bind change event to all attendance count fields
        $('.attendance-count').on('input change', updateRealTotal);

        // Calculate initial total on page load
        updateRealTotal();
    });

    (function() {
        <?= getQuillEditorInitScript('EventDesc', 'EventDescInput', gettext("Enter event description..."), false) ?>
    })();

    (function() {
        <?= getQuillEditorInitScript('EventText', 'EventTextInput', gettext("Enter sermon notes or event text..."), false) ?>
    })();
</script>

<?php require_once __DIR__ . '/Include/Footer.php'; ?>

