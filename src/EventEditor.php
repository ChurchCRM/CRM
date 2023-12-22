<?php

/*******************************************************************************
 *
 *  filename    : EventEditor.php
 *  last change : 2005-09-10
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2005 Todd Pillars
 *                Copyright 2012 Michael Wilt
 *
 *  function    : Editor for Church Events
  *
 ******************************************************************************/

// table fields
//  event_id       int(11)
//  event_type     enum('CS', 'SS', 'VOL')
//  event_title    varchar(255)
//  event_desc     varchar(255)
//  event_text     text
//  event_start    datetime
//  event_end      datetime
//  inactive       int(1) default 0

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$sPageTitle = gettext('Church Event Editor');

if (!AuthenticationManager::getCurrentUser()->isAddEvent()) {
    header('Location: Menu.php');
}

$sAction = 'Create Event';
require 'Include/Header.php';

if (isset($_GET['calendarAction'])) {
    $sAction = 'Edit';
    $sOpp = $_GET['calendarAction'];
} else {
    if (array_key_exists('Action', $_POST)) {
        $sAction = $_POST['Action'];
    }

    if (array_key_exists('EID', $_POST)) {
        $sOpp = $_POST['EID'];
    } // from EDIT button on event listing

    if (array_key_exists('EN_tyid', $_POST)) {
        $tyid = $_POST['EN_tyid'];
    } else {  // from event type list page
        $tyid = 0;
    }
}

$iEventID = 0;
$iErrors = 0;

if (!$sAction) {
    $sAction = 'Create Event';
}

//
// process the action inputs
//
if ($sAction === 'Create Event' && !empty($tyid)) {
    //
    // user is coming from the event types screen and thus there
    // is no existing event in the event_event table
    //
    // will use the event type information to smart-prefill the
    // event fields...but still allow the user to edit everything
    // except event type since event type is tied to the attendance count fields
    //
    $EventExists = 0;
    $sSQL = "SELECT * FROM event_types WHERE type_id=$tyid";
    $rsOpps = RunQuery($sSQL);
    $numRows = mysqli_num_rows($rsOpps);
    $ceRow = mysqli_fetch_array($rsOpps, MYSQLI_BOTH);
    extract($ceRow);

    $iTypeID = $type_id;
    $sTypeName = $type_name;
    $sDefStartTime = $type_defstarttime;
    $iDefRecurDOW = $type_defrecurDOW;
    $iDefRecurDOM = $type_defrecurDOM;
    $sDefRecurDOY = $type_defrecurDOY;
    $sDefRecurType = $type_defrecurtype;

    $sSQL = "SELECT * FROM eventcounts_evtcnt WHERE evtcnt_eventid='$iEventID' ORDER BY evtcnt_countid ASC";
    $sSQL = "SELECT evctnm_countid, evctnm_countname FROM eventcountnames_evctnm WHERE evctnm_eventtypeid='$iTypeID' ORDER BY evctnm_countid ASC";
    $cOpps = RunQuery($sSQL);
    $iNumCounts = mysqli_num_rows($cOpps);

    $aCountID = [];
    $aCountName = [];
    $aCount = [];

    if ($iNumCounts) {
        for ($c = 0; $c < $iNumCounts; $c++) {
            $cRow = mysqli_fetch_array($cOpps, MYSQLI_BOTH);
            extract($cRow);
            $aCountID[$c] = $evctnm_countid;
            $aCountName[$c] = $evctnm_countname;
            $aCount[$c] = 0;
        }
    }
    $nCnts = $iNumCounts;
    $sCountNotes = '';
    //
    // this switch manages the smart-prefill of the form based on the event type
    // definitions, recurrence type, etc.
    //
    switch ($sDefRecurType) {
        case 'none':
            $sEventStartDate = date('Y-m-d');
            $sEventEndDate = $sEventStartDate;
            $aStartTimeTokens = explode(':', $sDefStartTime);
            $iEventStartHour = $aStartTimeTokens[0];
            $iEventStartMins = $aStartTimeTokens[1];
            $iEventEndHour = $aStartTimeTokens[0] + 1;
            $iEventEndMins = $aStartTimeTokens[1];
            break;

        case 'weekly':
        // check for the last occurrence of this type_id in the events table and
        // create a new event based on this date reference
        //
            $sSQL = "SELECT * FROM events_event WHERE event_type = '$iTypeID' ORDER BY event_start DESC LIMIT 1";
            $ecOpps = RunQuery($sSQL);
            $numRows = mysqli_num_rows($ecOpps);
            if ($numRows > 0) {
              // use the most recent event if it exists
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
            // use the event type definition
                $sEventStartDate = date('Y-m-d', strtotime("last $iDefRecurDOW"));
                $aStartTimeTokens = explode(':', $sDefStartTime);
                $iEventStartHour = $aStartTimeTokens[0];
                $iEventStartMins = $aStartTimeTokens[1];
                $sEventEndDate = $sEventStartDate;
                $iEventEndHour = $aStartTimeTokens[0] + 1;
                $iEventEndMins = $aStartTimeTokens[1];
            }
            break;

        case 'monthly':
        // check for the last occurrence of this type_id in the events table and
        // create a new event based on this date reference
        //
            $sSQL = "SELECT * FROM events_event WHERE event_type = '$iTypeID' ORDER BY event_start DESC LIMIT 1";
            $ecOpps = RunQuery($sSQL);
            $numRows = mysqli_num_rows($ecOpps);
            if ($numRows > 0) {
              // use the most recent event if it exists
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
                $iEventEndHour = $aEventStartTimeTokens[0] + 1;
                $iEventEndMins = $aEventStartTimeTokens[1];
            } else {
            // use the event type definition
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
                $iEventEndHour = $aStartTimeTokens[0] + 1;
                $iEventEndMins = $aStartTimeTokens[1];
            }
            break;

        case 'yearly':
            $sSQL = "SELECT * FROM events_event WHERE event_type = '$iTypeID' ORDER BY event_start DESC LIMIT 1";
            $ecOpps = RunQuery($sSQL);
            $numRows = mysqli_num_rows($ecOpps);
            if ($numRows > 0) {
              // use the most recent event if it exists
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
                $iEventEndHour = $aEventStartTimeTokens[0] + 1;
                $iEventEndMins = $aEventStartTimeTokens[1];
            } else {
            // use the event type definition
                $currentDOY = time();
                $defaultDOY = strtotime($sDefRecurDOY);
                if ($currentDOY < $defaultDOY) {  // event is future
                    $sEventStartDate = $sDefRecurDOY;
                } elseif ($currentDOY > $defaultDOY + (365 * 24 * 60 * 60)) {  // event is over 1 year past
                    $aDMY = explode('-', $sDefRecurDOY);
                    $sEventStartDate = date('Y-m-d', mktime(0, 0, 0, $aDMY[1], $aDMY[2], date('Y') - 1));
                } else { // event is past
                    $aDMY = explode('-', $sDefRecurDOY);
                    $sEventStartDate = date('Y-m-d', mktime(0, 0, 0, $aDMY[1], $aDMY[2], date('Y')));
                }

                $aStartTimeTokens = explode(':', $sDefStartTime);
                $iEventStartHour = $aStartTimeTokens[0];
                $iEventStartMins = $aStartTimeTokens[1];
                $sEventEndDate = $sEventStartDate;
                $iEventEndHour = $aStartTimeTokens[0] + 1;
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
    // Get data for the form as it now exists..
    $EventExists = 1;
    $sSQL = "SELECT * FROM events_event as t1, event_types as t2 WHERE t1.event_type = t2.type_id AND t1.event_id ='" . $sOpp . "' LIMIT 1";
    $rsOpps = RunQuery($sSQL);

    $aRow = mysqli_fetch_array($rsOpps, MYSQLI_BOTH);
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
    //        echo $cvSQL;
    $cvOpps = RunQuery($sSQL);
    $iNumCounts = mysqli_num_rows($cvOpps);
    $nCnts = $iNumCounts;
    //        echo "numcounts = {$aNumCounts}\n\l";
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
} elseif (isset($_POST['SaveChanges'])) {
    // Does the user want to save changes to text fields?
    $iEventID = $_POST['EventID'];
    $iTypeID = $_POST['EventTypeID'];
    $EventExists = $_POST['EventExists'];
    $sEventTitle = $_POST['EventTitle'];
    $sEventDesc = $_POST['EventDesc'];
    if (empty($_POST['EventTypeID'])) {
        $bEventTypeError = true;
        $iErrors++;
    } else {
        $sSQL = "SELECT type_name FROM event_types WHERE type_id = '" . InputUtils::legacyFilterInput($iTypeID) . "' LIMIT 1";
        $rsOpps = RunQuery($sSQL);
        $aRow = mysqli_fetch_array($rsOpps, MYSQLI_BOTH);
        extract($aRow);
        $sTypeName = $type_name;
    }
    $sEventText = $_POST['EventText'];
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

    // If no errors, then update.
    if ($iErrors == 0) {
        if ($EventExists == 0) {
            $sSQL = "INSERT events_event
                     SET `event_type` = '" . InputUtils::legacyFilterInput($iTypeID) . "',
                     `event_title` = '" . InputUtils::legacyFilterInput($sEventTitle) . "',
                     `event_desc` = '" . InputUtils::legacyFilterInput($sEventDesc) . "',
                     `event_text` = '" . InputUtils::filterHTML($sEventText) . "',
                     `event_start` = '" . InputUtils::legacyFilterInput($sEventStart) . "',
                     `event_end` = '" . InputUtils::legacyFilterInput($sEventEnd) . "',
                     `inactive` = '" . InputUtils::legacyFilterInput($iEventStatus) . "';";
            RunQuery($sSQL);
            $iEventID = mysqli_insert_id($cnInfoCentral);
            for ($c = 0; $c < $iNumCounts; $c++) {
                $cCnt = ltrim(rtrim($aCountName[$c]));
                $sSQL = "INSERT eventcounts_evtcnt
                       (evtcnt_eventid, evtcnt_countid, evtcnt_countname, evtcnt_countcount, evtcnt_notes)
                       VALUES
                       ('" . InputUtils::legacyFilterInput($iEventID) . "',
                        '" . InputUtils::legacyFilterInput($aCountID[$c]) . "',
                        '" . InputUtils::legacyFilterInput($aCountName[$c]) . "',
                        '" . InputUtils::legacyFilterInput($aCount[$c]) . "',
                        '" . InputUtils::legacyFilterInput($sCountNotes) . "') ON DUPLICATE KEY UPDATE evtcnt_countcount='$aCount[$c]', evtcnt_notes='$sCountNotes'";
                RunQuery($sSQL);
            }
        } else {
            $sSQL = "UPDATE events_event
                     SET `event_type` = '" . InputUtils::legacyFilterInput($iTypeID) . "',
                     `event_title` = '" . InputUtils::legacyFilterInput($sEventTitle) . "',
                     `event_desc` = '" . InputUtils::legacyFilterInput($sEventDesc) . "',
                     `event_text` = '" . InputUtils::filterHTML($sEventText) . "',
                     `event_start` = '" . InputUtils::legacyFilterInput($sEventStart) . "',
                     `event_end` = '" . InputUtils::legacyFilterInput($sEventEnd) . "',
                     `inactive` = '" . InputUtils::legacyFilterInput($iEventStatus) . "'
                      WHERE `event_id` = '" . InputUtils::legacyFilterInput($iEventID) . "';";
            echo $sSQL;
            RunQuery($sSQL);
            for ($c = 0; $c < $iNumCounts; $c++) {
                $cCnt = ltrim(rtrim($aCountName[$c]));
                $sSQL = "INSERT eventcounts_evtcnt
                       (evtcnt_eventid, evtcnt_countid, evtcnt_countname, evtcnt_countcount, evtcnt_notes)
                       VALUES
                       ('" . InputUtils::legacyFilterInput($iEventID) . "',
                        '" . InputUtils::legacyFilterInput($aCountID[$c]) . "',
                        '" . InputUtils::legacyFilterInput($aCountName[$c]) . "',
                        '" . InputUtils::legacyFilterInput($aCount[$c]) . "',
                        '" . InputUtils::legacyFilterInput($sCountNotes) . "') ON DUPLICATE KEY UPDATE evtcnt_countcount='$aCount[$c]', evtcnt_notes='$sCountNotes'";
                RunQuery($sSQL);
            }
        }
        $EventExists = 1;
        header('Location: ListEvents.php');
    }
}

// Construct the form
?>

<div class='box'>
  <div class='box-header'>
    <h3 class='box-title'>
      <?= ($EventExists == 0) ? gettext('Create a new Event') : gettext('Editing Event ID: ') . $iEventID ?>
    </h3>
  </div>
  <div class='box-header'>
    <?php
    if ($iErrors != 0) {
        echo "<div class='alert alert-danger'>" . gettext('There were ') . $iErrors . gettext(' errors. Please see below') . '</div>';
    } else {
        echo '<div>' . gettext('Items with a ') . '<span style="color: red">*</span>' . gettext(' are required') . '</div>';
    }
    ?>
  </div>

<form method="post" action="EventEditor.php" name="EventsEditor">
<input type="hidden" name="EventID" value="<?= ($iEventID) ?>">
<input type="hidden" name="EventExists" value="<?= $EventExists ?>">

<table class='table'>
<?php if (empty($iTypeID)) {
    ?>

  <tr>
    <td class="LabelColumn"><span style="color: red">*</span><?= gettext('Event Type') ?>:</td>
    <td colspan="3" class="TextColumn">
      <select name='EN_tyid' class='form-control' id='event_type_id' width='100%' style='width: 100%'>
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
                echo '<div><span style="color: red;">' . gettext('You must pick an event type.') . '</span></div>';
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
} else { // if (empty($iTypeID))?>
  <tr>
    <td class="LabelColumn"><span style="color: red">*</span><?= gettext('Event Type') ?>:</td>
    <td colspan="3" class="TextColumn">
    <input type="hidden" name="EventTypeName" value="<?= ($sTypeName) ?>">
    <input type="hidden" name="EventTypeID" value="<?= ($iTypeID) ?>">
    <?= ($iTypeID . '-' . $sTypeName) ?>
    </td>
  </tr>

  <tr>
    <td class="LabelColumn"><span style="color: red">*</span><?= gettext('Event Title') ?>:</td>
    <td colspan="1" class="TextColumn">
      <input type="text" name="EventTitle" value="<?= ($sEventTitle) ?>" size="30" maxlength="100" class='form-control' width="100%" style="width: 100%" required>
    </td>
  </tr>
  <tr>
    <td class="LabelColumn"><span style="color: red">*</span><?= gettext('Event Desc') ?>:</td>
    <td colspan="3" class="TextColumn">
      <textarea name="EventDesc" rows="4" maxlength="100" class='form-control' required width="100%" style="width: 100%"><?= ($sEventDesc) ?></textarea>
    </td>
  </tr>
  <tr>
    <td class="LabelColumn"><span style="color: red">*</span>
      <?= gettext('Date Range') ?>:
    </td>
    <td class="TextColumn">
      <input type="text" name="EventDateRange" value=""
             maxlength="10" id="EventDateRange" size="50" class='form-control' width="100%" style="width: 100%" required>
    </td>

  </tr>

  <tr>
    <td class="LabelColumn"><?= gettext('Attendance Counts') ?></td>
    <td class="TextColumn" colspan="3">
      <input type="hidden" name="NumAttendCounts" value="<?= $nCnts ?>">
      <?php
        if ($nCnts == 0) {
            echo gettext('No Attendance counts recorded');
        } else {
            ?>
    <table>
            <?php
            for ($c = 0; $c < $nCnts; $c++) {
                ?><tr>
          <td><strong><?= (gettext($aCountName[$c]) . ':') ?>&nbsp;</strong></td>
        <td>
        <input type="text" name="EventCount[]" value="<?= ($aCount[$c]) ?>" size="8" class='form-control'>
        <input type="hidden" name="EventCountID[]" value="<?= ($aCountID[$c]) ?>">
        <input type="hidden" name="EventCountName[]" value="<?= ($aCountName[$c]) ?>">
        </td>
        </tr>
                <?php
            } //end for loop
            ?>
      <tr>
      <td><strong><?= gettext('Attendance Notes: ') ?>&nbsp;</strong></td>
        <td><input type="text" name="EventCountNotes" value="<?= $sCountNotes ?>" class='form-control'>
        </td>
        </tr>
        </table>
            <?php
        } //endif
        ?>
    </td>
  </tr>

  <tr>
    <td colspan="4" class="TextColumn"><?= gettext('Event Sermon') ?>:<br>
        <textarea id="#EventText" name="EventText" rows="5" cols="70" class='form-control'><?= ($sEventText) ?></textarea>
    </td>
  </tr>

  <tr>
    <td class="LabelColumn"><span style="color: red">*</span><?= gettext('Event Status') ?>:</td>
    <td colspan="3" class="TextColumn">
      <input type="radio" name="EventStatus" value="0" <?php if ($iEventStatus == 0) {
            echo 'checked';
                                                       } ?>/> <?= _('Active')?>
      <input type="radio" name="EventStatus" value="1" <?php if ($iEventStatus == 1) {
            echo 'checked';
                                                       } ?>/> <?= _('Inactive')?>
    </td>
  </tr>

  <tr>
    <td></td>
    <td><input type="submit" name="SaveChanges" value="<?= gettext('Save Changes') ?>" class="btn btn-primary"></td>
  </tr>
    <?php
} // if (empty($iTypeID))?>
</table>
</form>
</div>

<div>
  <a href="ListEvents.php" class='btn btn-default'>
    <i class='fa fa-chevron-left'></i>
    <?= gettext('Return to Events') ?>
  </a>
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
    });
</script>

<?php require 'Include/Footer.php' ?>

<script src="<?= SystemURLs::getRootPath() ?>/skin/external/ckeditor/ckeditor.js"></script>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  CKEDITOR.replace('EventText',{
    customConfig: '<?= SystemURLs::getRootPath() ?>/skin/js/ckeditor/event_editor_config.js',
    language : window.CRM.lang,
    width : '100%'
  });
</script>
