<?php

/*******************************************************************************
*
*  filename    : ListEvents.php
*  website     : https://churchcrm.io
*  function    : List all Church Events
*
*  copyright   : Copyright 2005 Todd Pillars
*
*
*  Additional Contributors:
*  2007 Ed Davis
*  update 2017 Philippe Logel
*
*
*
*
******************************************************************************/

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\Utils\InputUtils;

$eType = 'All';
$ThisYear = date('Y');

if (isset($_POST['WhichType'])) {
    $eType = InputUtils::legacyFilterInput($_POST['WhichType']);
} else {
    $eType = 'All';
}

if ($eType != 'All') {
    $sSQL = "SELECT * FROM event_types WHERE type_id=$eType";
    $rsOpps = RunQuery($sSQL);
    $aRow = mysqli_fetch_array($rsOpps, MYSQLI_BOTH);
    extract($aRow);
    $sPageTitle = gettext('Listing Events of Type = ') . $type_name;
} else {
    $sPageTitle = gettext('Listing All Church Events');
}

// retrieve the year selector

if (isset($_POST['WhichYear'])) {
    $EventYear = InputUtils::legacyFilterInput($_POST['WhichYear'], 'int');
} else {
    $EventYear = date('Y');
}

///////////////////////
require 'Include/Header.php';

if (isset($_POST['Action']) && isset($_POST['EID']) && AuthenticationManager::getCurrentUser()->isAddEvent()) {
    $eID = InputUtils::legacyFilterInput($_POST['EID'], 'int');
    $action = InputUtils::legacyFilterInput($_POST['Action']);
    if ($action == 'Delete' && $eID) {
        $sSQL = 'DELETE FROM events_event WHERE event_id = ' . $eID . ' LIMIT 1';
        RunQuery($sSQL);

        $sSQL = 'DELETE FROM eventcounts_evtcnt WHERE evtcnt_eventid = ' . $eID;
        RunQuery($sSQL);
    } elseif ($action == 'Activate' && $eID) {
        $event = EventQuery::create()->findOneById($eID);
        $event->setInActive(0);
        $event->save();
    }
}

/// top of main form
//
$sSQL = 'SELECT DISTINCT event_types.*
         FROM event_types
         RIGHT JOIN events_event ON event_types.type_id=events_event.event_type
         ORDER BY type_id ';
$rsOpps = RunQuery($sSQL);
$numRows = mysqli_num_rows($rsOpps);

?>
<table cellpadding="1" align="center" cellspacing="0" class='table'>
<tr>
<td align="center" width="50%"><p><strong><?= gettext('Select Event Types To Display') ?></strong></p>
    <form name="EventTypeSelector" method="POST" action="ListEvents.php">
       <select name="WhichType" onchange="javascript:this.form.submit()" class='form-control'>
        <option value="All"><?= gettext('All') ?></option>
        <?php
        for ($r = 1; $r <= $numRows; $r++) {
            $aRow = mysqli_fetch_array($rsOpps, MYSQLI_BOTH);
            extract($aRow);
            //          foreach($aRow as $t)echo "$t\n\r";?>
          <option value="<?php echo $type_id ?>" <?php if ($type_id == $eType) {
                echo 'selected';
                         } ?>><?= $type_name ?></option>
            <?php
        }
        ?>
         </select>
</td>

<?php
// year selector
if ($eType === 'All') {
    $sSQL = 'SELECT DISTINCT YEAR(events_event.event_start)
           FROM events_event
           WHERE YEAR(events_event.event_start)';
} else {
    $sSQL = "SELECT DISTINCT YEAR(events_event.event_start)
           FROM events_event
           WHERE events_event.event_type = '$eType' AND YEAR(events_event.event_start)";
}
$rsOpps = RunQuery($sSQL);
$aRow = mysqli_fetch_array($rsOpps, MYSQLI_BOTH);
@extract($aRow); // @ needed to suppress error messages when no church events
$rsOpps = RunQuery($sSQL);
$numRows = mysqli_num_rows($rsOpps);
for ($r = 1; $r <= $numRows; $r++) {
    $aRow = mysqli_fetch_array($rsOpps, MYSQLI_BOTH);
    extract($aRow);
    $Yr[$r] = $aRow[0];
}

?>

<td align="center" width="50%"><p><strong><?= gettext('Display Events in Year') ?></strong></p>
       <select name="WhichYear" onchange="javascript:this.form.submit()" class='form-control'>
        <?php
        for ($r = 1; $r <= $numRows; $r++) {
            ?>
          <option value="<?php echo $Yr[$r] ?>" <?php if ($Yr[$r] == $EventYear) {
                echo 'selected';
                         } ?>><?= $Yr[$r] ?></option>
            <?php
        }
        ?>
         </select>
    </form>
</td>
</tr>
</table>
<?php

// Get data for the form as it now exists..
// for this year
$currYear = date('Y');
$currMonth = date('m');
$allMonths = ['12', '11', '10', '9', '8', '7', '6', '5', '4', '3', '2', '1'];
if ($eType === 'All') {
    $eTypeSQL = ' ';
} else {
    $eTypeSQL = " AND t1.event_type=$eType";
}

foreach ($allMonths as $mKey => $mVal) {
    unset($cCountSum);
    $sSQL = 'SELECT * FROM events_event as t1, event_types as t2 ';
    if (isset($previousMonth)) {
        // $sSQL .= " WHERE previous month stuff";
    } elseif (isset($nextMonth)) {
        // $sSQL .= " WHERE next month stuff";
    } elseif (isset($showAll)) {
        $sSQL .= '';
    } else {
        //$sSQL .= " WHERE (TO_DAYS(event_start_date) - TO_DAYS(now()) < 30)";
        $sSQL .= ' WHERE t1.event_type = t2.type_id' . $eTypeSQL . ' AND MONTH(t1.event_start) = ' . $mVal . " AND YEAR(t1.event_start)=$EventYear";
    }
    $sSQL .= ' ORDER BY t1.event_start ';

    $rsOpps = RunQuery($sSQL);
    $numRows = mysqli_num_rows($rsOpps);
    $aAvgRows = $numRows;
    // Create arrays of the fundss.
    for ($row = 1; $row <= $numRows; $row++) {
        $aRow = mysqli_fetch_array($rsOpps, MYSQLI_BOTH);
        extract($aRow);

        $aEventID[$row] = $event_id;
        $aEventType[$row] = $type_name;
        $aEventTitle[$row] = htmlentities(stripslashes($event_title), ENT_NOQUOTES, 'UTF-8');
        $aEventDesc[$row] = htmlentities(stripslashes($event_desc), ENT_NOQUOTES, 'UTF-8');
        $aEventText[$row] = htmlentities(stripslashes($event_text), ENT_NOQUOTES, 'UTF-8');
        $aEventStartDateTime[$row] = $event_start;
        $aEventEndDateTime[$row] = $event_end;
        $aEventStatus[$row] = $inactive;
        // get the list of attend-counts that exists in event_attend for this
        $attendSQL = "SELECT * FROM event_attend WHERE event_id=$event_id";
        $attOpps = RunQuery($attendSQL);
        if ($attOpps) {
            $attNumRows[$row] = mysqli_num_rows($attOpps);
        } else {
            $attNumRows[$row] = 0;
        }
    }

    if ($numRows > 0) {
        ?>
  <div class='box'>
    <div class='box-header'>
      <h3 class='box-title'><?= ($numRows == 1 ? gettext('There is') : gettext('There are')) . ' ' . $numRows . ' ' . ($numRows == 1 ? gettext('event') : gettext('events')) . ' ' . 'for' . '  ' . gettext(date('F', mktime(0, 0, 0, $mVal, 1, $currYear))) ?></h3>
    </div>
    <div class='box-body'>
  <table id="listEvents" class='table data-table table-striped table-bordered table-responsive'>
    <thead>
      <tr class="TableHeader">
        <?php if (AuthenticationManager::getCurrentUser()->isAddEvent()) {
            ?>
        <th><?= gettext('Action') ?></th>
            <?php
        } ?>
        <th><?= gettext('Description') ?></th>
        <th><?= gettext('Event Type') ?></th>
        <th><?= gettext('Attendance Counts') ?></th>
        <th><?= gettext('Start Date/Time') ?></th>
        <th><?= gettext('Active') ?></th>
      </tr>
    </thead>
    <tbody>
        <?php
        for ($row = 1; $row <= $numRows; $row++) {
            ?>
          <tr>
            <?php if (AuthenticationManager::getCurrentUser()->isAddEvent()) {
                ?>
              <td>
              <table class='table-responsive'>
                <tr>
                  <td>
                    <form name="EditEvent" action="EventEditor.php" method="POST">
                      <input type="hidden" name="EID" value="<?= $aEventID[$row] ?>">
                      <button type="submit" name="Action" title="<?= gettext('Edit') ?>" value="Edit" class="btn btn-default btn-sm">
                        <i class='fas fa-pen'></i>
                      </button>
                    </form>
                  </td>
                  <td>
                    <form name="EditAttendees" action="EditEventAttendees.php" method="POST">
                      <input type="hidden" name="EID" value="<?= $aEventID[$row] ?>">
                      <input type="hidden" name="EName" value="<?= $aEventTitle[$row] ?>">
                      <input type="hidden" name="EDesc" value="<?= $aEventDesc[$row] ?>">
                      <input type="hidden" name="EDate" value="<?= FormatDate($aEventStartDateTime[$row], 1) ?>">
                      <input type="submit" name="Action" value="<?= gettext('Attendees') . '(' . $attNumRows[$row] . ')' ?>" class="btn btn-info btn-sm btn-block" >
                    </form>
                  </td>
                  <td>
                    <form name="DeleteEvent" action="ListEvents.php" method="POST">
                      <input type="hidden" name="EID" value="<?= $aEventID[$row] ?>">
                      <button type="submit" name="Action" title="<?=gettext('Delete') ?>" value="Delete" class="btn btn-danger btn-sm"
                      onClick="return confirm('Deleting an event will also delete all attendance counts for that event.  Are you sure you want to DELETE Event ID: <?=  $aEventID[$row] ?>')">
                        <i class='fa fa-trash'></i>
                      </button>
                    </form>
                  </td>
                </tr>
              </table>
            </td>
                <?php
            } ?>
            <td>
              <?= $aEventTitle[$row] ?>
              <?= ($aEventDesc[$row] === '' ? '&nbsp;' : $aEventDesc[$row]) ?>
              <?php if ($aEventText[$row] != '') {
                    ?>
                <div class='text-bold'><a href="javascript:popUp('GetText.php?EID=<?=$aEventID[$row]?>')"><?= gettext("Sermon Text") ?></a></div>
                    <?php
              } ?>
            </td>
            <td><?= $aEventType[$row] ?></td>
            <td>
              <table width='100%' class='table-simple-padding'>
                <tr>
                  <?php
                    // RETRIEVE THE list of counts associated with the current event
                    $cvSQL = "SELECT * FROM eventcounts_evtcnt WHERE evtcnt_eventid='$aEventID[$row]' ORDER BY evtcnt_countid ASC";
                    $cvOpps = RunQuery($cvSQL);
                    $aNumCounts = mysqli_num_rows($cvOpps);

                    if ($aNumCounts) {
                        for ($c = 0; $c < $aNumCounts; $c++) {
                            $cRow = mysqli_fetch_array($cvOpps, MYSQLI_BOTH);
                            extract($cRow);
                            $cCountID[$c] = $evtcnt_countid;
                            $cCountName[$c] = $evtcnt_countname;
                            $cCount[$c] = $evtcnt_countcount;
                            $cCountNotes = $evtcnt_notes; ?>
                        <td>
                          <div class='text-bold'><?= $evtcnt_countname ?></div>
                          <div><?= $evtcnt_countcount ?></div>
                        </td>
                                <?php
                        }
                    } else {
                        ?>
                      <td>
                        <?= gettext('No Attendance Recorded') ?>
                      </td>
                        <?php
                    } ?>
                </tr>
              </table>
            </td>
            <td>
              <?= FormatDate($aEventStartDateTime[$row], 1) ?>
            </td>
            <td>
              <?= ($aEventStatus[$row] != 0 ? _('No') : _('Yes')) ?>
            </td>

          </tr>
            <?php
        } // end of for loop for # rows for this month

        // calculate averages if this is a single type list
        if ($eType != 'All' && $aNumCounts > 0) {
            $avgSQL = "SELECT evtcnt_countid, evtcnt_countname, AVG(evtcnt_countcount) from eventcounts_evtcnt, events_event WHERE eventcounts_evtcnt.evtcnt_eventid=events_event.event_id AND events_event.event_type='$eType' AND MONTH(events_event.event_start)='$mVal' GROUP BY eventcounts_evtcnt.evtcnt_countid ASC ";
            $avgOpps = RunQuery($avgSQL);
            $aAvgRows = mysqli_num_rows($avgOpps); ?>
          <tr>
            <td class="LabelColumn" colspan="2"><?= gettext(' Monthly Averages') ?></td>
            <td>
              <div class='row'>
                <?php
                // calculate and report averages
                for ($c = 0; $c < $aAvgRows; $c++) {
                    $avgRow = mysqli_fetch_array($avgOpps, MYSQLI_BOTH);
                    extract($avgRow);
                    $avgName = $avgRow['evtcnt_countname'];
                    $avgAvg = $avgRow[2]; ?>
                  <td align="center">
                    <span class="SmallText">
                    <strong>AVG<br><?= $avgName ?></strong>
                    <br><?= sprintf('%01.2f', $avgAvg) ?></span>
                  </td>
                    <?php
                } ?>
              </div>
            </td>
            <td class="TextColumn" colspan="3"></td>
          </tr>
            <?php
        } ?>
      </tbody>
    </table>
  </div>
  </div>
        <?php
    }
} // end for-each month loop
?>

<div class='text-center'>
  <a href="EventEditor.php" class='btn btn-primary'>
    <i class='fa fa-ticket'></i>
    <?= gettext('Add New Event') ?>
  </a>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>" >
//Added by @saulowulhynek to translation of datatable nav terms
  $(document).ready(function () {
    $('#listEvents').DataTable(window.CRM.plugin.dataTable);
  });
</script>

<?php
require 'Include/Footer.php';
?>
