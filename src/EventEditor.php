<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';
require_once __DIR__ . '/Include/QuillEditorHelper.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\EventCountNameQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
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
$iErrors = 0;

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
        $sDefStartTime = $eventType->getDefStartTime();
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
        ->filterByEventTypeId((int)$iTypeID)
        ->orderByCountId()
        ->find();
    
    $iNumCounts = count($eventCountNames);

    $aCountID = [];
    $aCountName = [];
    $aCount = [];

    if ($iNumCounts > 0) {
        $c = 0;
        foreach ($eventCountNames as $countName) {
            $aCountID[$c] = $countName->getCountId();
            $aCountName[$c] = $countName->getCountName();
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
            $sEventStartDate = date('Y-m-d');
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
                $sEventStartDate = date('Y-m-d', strtotime("$ceEventStartDate +1 week"));

                $aEventStartTimeTokens = explode(':', $sDefStartTime);
                $iEventStartHour = $aEventStartTimeTokens[0];
                $iEventStartMins = $aEventStartTimeTokens[1];

                $sEventEndDate = $sEventStartDate;
                $iEventEndHour = $iEventStartHour + 1;
                $iEventEndMins = $iEventStartMins;
            } else {
                // Use the event type definition
                $sEventStartDate = date('Y-m-d', strtotime("last $iDefRecurDOW"));
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

                $sEventStartDate = date('Y-m-d', mktime(0, 0, 0, $ceDMY[1] + 1, $ceDMY[2], $ceDMY[0]));
                $iEventStartHour = $aEventStartTimeTokens[0];
                $iEventStartMins = $aEventStartTimeTokens[1];
                $sEventEndDate = $sEventStartDate;
                $iEventEndHour = intval($aEventStartTimeTokens[0]) + 1;
                $iEventEndMins = $aEventStartTimeTokens[1];
            } else {
                // Use the event type definition
                $currentDOM = date('d');
                if ($currentDOM < $iDefRecurDOM) {
                    $sEventStartDate = date('Y-m-d', mktime(0, 0, 0, date('m') - 1, $iDefRecurDOM, date('Y')));
                } else {
                    $sEventStartDate = date('Y-m-d', mktime(0, 0, 0, date('m'), $iDefRecurDOM, date('Y')));
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

                $sEventStartDate = date('Y-m-d', mktime(0, 0, 0, $aDMY[1], $aDMY[2], $aDMY[0] + 1));
                $iEventStartHour = $aEventStartTimeTokens[0];
                $iEventStartMins = $aEventStartTimeTokens[1];
                $sEventEndDate = $sEventStartDate;
                $iEventEndHour = intval($aEventStartTimeTokens[0]) + 1;
                $iEventEndMins = $aEventStartTimeTokens[1];
            } else {
                // Use the event type definition
                $currentDOY = time();
                $defaultDOY = strtotime($sDefRecurDOY);
                if ($currentDOY < $defaultDOY) {
                    // Event is in the future
                    $sEventStartDate = $sDefRecurDOY;
                } elseif ($currentDOY > $defaultDOY + (365 * 24 * 60 * 60)) {
                    // Event is over 1 year in the past
                    $aDMY = explode('-', $sDefRecurDOY);
                    $sEventStartDate = date('Y-m-d', mktime(0, 0, 0, $aDMY[1], $aDMY[2], date('Y') - 1));
                } else {
                    // Event is past
                    $aDMY = explode('-', $sDefRecurDOY);
                    $sEventStartDate = date('Y-m-d', mktime(0, 0, 0, $aDMY[1], $aDMY[2], date('Y')));
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
    $iTypeID = $type_id;
} elseif ($sAction = 'Edit' && !empty($sOpp)) {
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
        $aRow = array_merge($event->toArray(), $event->getEventType()->toArray());
        extract($aRow);

        $iEventID = $event_id;
        $iTypeID = $type_id;
        $sTypeName = $type_name;
        $sEventTitle = $event_title;
        $sEventDesc = $event_desc;
        $sEventText = $event_text;
        $aStartTokens = explode(' ', $event_start);
        $sEventStartDate = $aStartTokens[0];
        $aStartTimeTokens = explode(':', $aStartTokens[1]);
        $iEventStartHour = $aStartTimeTokens[0];
        $iEventStartMins = $aStartTimeTokens[1];
        $aEndTokens = explode(' ', $event_end);
        $sEventEndDate = $aEndTokens[0];
        $aEndTimeTokens = explode(':', $aEndTokens[1]);
        $iEventEndHour = $aEndTimeTokens[0];
        $iEventEndMins = $aEndTimeTokens[1];
        $iEventStatus = $inactive;

        $sSQL = "SELECT * FROM eventcounts_evtcnt WHERE evtcnt_eventid='$iEventID' ORDER BY evtcnt_countid ASC";

        $cvOpps = RunQuery($sSQL);
        $iNumCounts = mysqli_num_rows($cvOpps);
        $nCnts = $iNumCounts;

        if ($iNumCounts) {
            for ($c = 0; $c < $iNumCounts; $c++) {
                $aRow = mysqli_fetch_array($cvOpps, MYSQLI_BOTH);
                extract($aRow);
                $aCountID[$c] = $evtcnt_countid;
                $aCountName[$c] = $evtcnt_countname;
                $aCount[$c] = $evtcnt_countcount;
                $sCountNotes = $evtcnt_notes;
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
        header('Location: ListEvents.php');
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
                    <td class="LabelColumn"><?= gettext('Attendance Counts') ?></td>
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
                                    $isTotal = (strtolower($countName) === 'total');
                                    $inputId = 'EventCount_' . $c;
                                    ?>
                                    <div class="col-md-3 col-sm-6 mb-2">
                                        <label for="<?= $inputId ?>" class="font-weight-bold"><?= gettext($countName) ?></label>
                                        <input type="number" 
                                               id="<?= $inputId ?>" 
                                               name="EventCount[]" 
                                               value="<?= (int) $aCount[$c] ?>" 
                                               class="form-control attendance-count <?= $isTotal ? 'total-count' : 'addend-count' ?>"
                                               <?= $isTotal ? 'readonly' : '' ?>
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

        // Auto-calculate Total from other attendance counts
        function updateTotalCount() {
            var total = 0;
            $('.addend-count').each(function() {
                var val = parseInt($(this).val()) || 0;
                total += val;
            });
            $('.total-count').val(total);
        }

        // Bind change event to all non-total count fields
        $('.addend-count').on('input change', updateTotalCount);

        // Calculate initial total on page load
        updateTotalCount();
    });
</script>

<?php require_once __DIR__ . '/Include/Footer.php'; ?>

<?= getQuillEditorInitScript('EventText', 'EventTextInput', gettext("Enter event description...")) ?>

