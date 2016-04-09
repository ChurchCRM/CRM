<?php
/*******************************************************************************
 *
 *  filename    : EventEditor.php
 *  last change : 2005-09-10
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2005 Todd Pillars
 *                Copyright 2012 Michael Wilt
 *
 *  function    : Editor for Church Events
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
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

require "Include/Config.php";
require "Include/Functions.php";
require "Include/RenderFunctions.php";

$sPageTitle = gettext("Church Event Editor");

$sAction = 'Create Event';
require "Include/Header.php";

if (array_key_exists ('Action', $_POST))
	$sAction = $_POST['Action'];

if (array_key_exists ('EID', $_POST))
	$sOpp = $_POST['EID']; // from EDIT button on event listing

if (array_key_exists ("EN_tyid", $_POST))
	$tyid = $_POST["EN_tyid"]; // from event type list page
else
	$tyid = 0;

$iEventID = 0;
$iErrors = 0;

$bTitleError = false;
$bDescError = false;
$bESDError = false;
$bESTError = false;
$bStatusError = false;
if (!$sAction) { $sAction = gettext('Create Event'); }

//
// process the action inputs
//
if ($sAction==gettext('Create Event') && !empty($tyid)){
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
  $numRows = mysql_num_rows($rsOpps);
  $ceRow = mysql_fetch_array($rsOpps, MYSQL_BOTH);
  extract($ceRow);

  $iTypeID = $type_id;
  $sTypeName = $type_name;
  $sDefStartTime = $type_defstarttime;
  $iDefRecurDOW = $type_defrecurDOW;
  $iDefRecurDOM = $type_defrecurDOM;
  $sDefRecurDOY = $type_defrecurDOY;
  $sDefRecurType = $type_defrecurtype;

	$sSQL= "SELECT * FROM eventcounts_evtcnt WHERE evtcnt_eventid='$iEventID' ORDER BY evtcnt_countid ASC";
  $sSQL = "SELECT evctnm_countid, evctnm_countname FROM eventcountnames_evctnm WHERE evctnm_eventtypeid='$iTypeID' ORDER BY evctnm_countid ASC";
  $cOpps = RunQuery($sSQL);
  $iNumCounts = mysql_num_rows($cOpps);

  $aCountID = array();
  $aCountName = array();
  $aCount = array();

  if($iNumCounts) {
    for($c = 0; $c <$iNumCounts; $c++){
        $cRow = mysql_fetch_array($cOpps, MYSQL_BOTH);
        extract($cRow);
        $aCountID[$c] = $evctnm_countid;
        $aCountName[$c] = $evctnm_countname;
        $aCount[$c]=0;
    }
  }
  $nCnts = $iNumCounts;
  $sCountNotes="";
//
// this switch manages the smart-prefill of the form based on the event type
// definitions, recurrance type, etc.
//
  switch ($sDefRecurType){
    case "none":
      $sEventStartDate = date('Y-m-d');
      $sEventEndDate = $sEventStartDate;
      $aStartTimeTokens = explode(":",$sDefStartTime);
      $iEventStartHour = $aStartTimeTokens[0];
      $iEventStartMins = $aStartTimeTokens[1];
      $iEventEndHour = $aStartTimeTokens[0]+1;
      $iEventEndMins = $aStartTimeTokens[1];
      break;

    case "weekly":
    // check for the last occurance of this type_id in the events table and
    // create a new event based on this date reference
    //
      $sSQL = "SELECT * FROM events_event WHERE event_type = '$iTypeID' ORDER BY event_start DESC LIMIT 1";
      $ecOpps = RunQuery($sSQL);
      $numRows = mysql_num_rows($ecOpps);
      if($numRows >0){
        // use the most recent event if it exists
        $ecRow = mysql_fetch_array($ecOpps, MYSQL_BOTH);
        extract($ecRow);
        $aStartTokens = explode(" ", $event_start);
        $ceEventStartDate = $aStartTokens[0];
        $sEventStartDate = date('Y-m-d',strtotime("$ceEventStartDate +1 week"));

        $aEventStartTimeTokens = explode(":",$sDefStartTime);
        $iEventStartHour = $aEventStartTimeTokens[0];
        $iEventStartMins = $aEventStartTimeTokens[1];

        $sEventEndDate = $sEventStartDate;
        $iEventEndHour = $iEventStartHour+1;
        $iEventEndMins = $iEventStartMins;
      } else {
        // use the event type definition
        $sEventStartDate = date('Y-m-d',strtotime("last $iDefRecurDOW"));
        $aStartTimeTokens = explode(":",$sDefStartTime);
        $iEventStartHour = $aStartTimeTokens[0];
        $iEventStartMins = $aStartTimeTokens[1];
        $sEventEndDate = $sEventStartDate;
        $iEventEndHour = $aStartTimeTokens[0]+1;
        $iEventEndMins = $aStartTimeTokens[1];
      }
      break;

    case "monthly":
    // check for the last occurance of this type_id in the events table and
    // create a new event based on this date reference
    //
      $sSQL = "SELECT * FROM events_event WHERE event_type = '$iTypeID' ORDER BY event_start DESC LIMIT 1";
      $ecOpps = RunQuery($sSQL);
      $numRows = mysql_num_rows($ecOpps);
      if($numRows >0){
        // use the most recent event if it exists
        $ecRow = mysql_fetch_array($ecOpps, MYSQL_BOTH);
        extract($ecRow);
        $aStartTokens = explode(" ", $event_start);
        $ceEventStartDate = $aStartTokens[0];
        $ceDMY = explode("-",$aStartTokens[0]);
        $aEventStartTimeTokens = explode(":",$ceStartTokens[1]);

        $sEventStartDate = date('Y-m-d',mktime(0,0,0,$ceDMY[1]+1,$ceDMY[2],$ceDMY[0]));
        $iEventStartHour = $aEventStartTimeTokens[0];
        $iEventStartMins = $aEventStartTimeTokens[1];
        $sEventEndDate = $sEventStartDate;
        $iEventEndHour = $aEventStartTimeTokens[0]+1;
        $iEventEndMins = $aEventStartTimeTokens[1];
      } else {
        // use the event type definition
        $currentDOM = date('d');
        if($currentDOM < $iDefRecurDOM){
          $sEventStartDate = date('Y-m-d',mktime(0,0,0,date('m')-1,$iDefRecurDOM,date('Y')));
        } else {
          $sEventStartDate = date('Y-m-d',mktime(0,0,0,date('m'),$iDefRecurDOM,date('Y')));
        }

        $aStartTimeTokens = explode(":",$ceDefStartTime);
        $iEventStartHour = $aStartTimeTokens[0];
        $iEventStartMins = $aStartTimeTokens[1];
        $sEventEndDate = $sEventStartDate;
        $iEventEndHour = $aStartTimeTokens[0]+1;
        $iEventEndMins = $aStartTimeTokens[1];
      }
      break;

    case "yearly":
      $sSQL = "SELECT * FROM events_event WHERE event_type = '$iTypeID' ORDER BY event_start DESC LIMIT 1";
      $ecOpps = RunQuery($sSQL);
      $numRows = mysql_num_rows($ecOpps);
      if($numRows >0){
        // use the most recent event if it exists
        $ecRow = mysql_fetch_array($ecOpps, MYSQL_BOTH);
        extract($ecRow);
        $aStartTokens = explode(" ", $event_start);
        $sEventStartDate = $aStartTokens[0];
        $aDMY = explode("-",$aStartTokens[0]);
        $aEventStartTimeTokens = explode(":",$aStartTokens[1]);

        $sEventStartDate = date('Y-m-d',mktime(0,0,0,$aDMY[1],$aDMY[2],$aDMY[0]+1));
        $iEventStartHour = $aEventStartTimeTokens[0];
        $iEventStartMins = $aEventStartTimeTokens[1];
        $sEventEndDate = $sEventStartDate;
        $iEventEndHour = $aEventStartTimeTokens[0]+1;
        $iEventEndMins = $aEventStartTimeTokens[1];
      } else {
        // use the event type definition
        $currentDOY = time();
        $defaultDOY = strtotime($sDefRecurDOY);
        if($currentDOY < $defaultDOY){  // event is future
          $sEventStartDate = $sDefRecurDOY;
        } else if ($currentDOY > $defaultDOY+(365 * 24 * 60 * 60)){  // event is over 1 year past
          $aDMY = explode("-",$sDefRecurDOY);
          $sEventStartDate = date('Y-m-d',mktime(0,0,0,$aDMY[1],$aDMY[2],date('Y')-1));
        } else { // event is past
          $aDMY = explode("-",$sDefRecurDOY);
          $sEventStartDate = date('Y-m-d',mktime(0,0,0,$aDMY[1],$aDMY[2],date('Y')));
        }

        $aStartTimeTokens = explode(":",$sDefStartTime);
        $iEventStartHour = $aStartTimeTokens[0];
        $iEventStartMins = $aStartTimeTokens[1];
        $sEventEndDate = $sEventStartDate;
        $iEventEndHour = $aStartTimeTokens[0]+1;
        $iEventEndMins = $aStartTimeTokens[1];
      }
      break;
  }
  $sEventTitle = $sEventStartDate."-".$sTypeName;
  $sEventDesc="";
  $sEventText="";
  $iEventStatus=0;
  $iTypeID = $type_id;
}
else if ($sAction = gettext('Edit') && !empty($sOpp))
{
        // Get data for the form as it now exists..
        $EventExists = 1;
        $sSQL = "SELECT * FROM events_event as t1, event_types as t2 WHERE t1.event_type = t2.type_id AND t1.event_id ='".$sOpp."' LIMIT 1";
        $rsOpps = RunQuery($sSQL);

        $aRow = mysql_fetch_array($rsOpps, MYSQL_BOTH);
        extract($aRow);

        $iEventID = $event_id;
        $iTypeID = $type_id;
        $sTypeName = $type_name;
        $sEventTitle = $event_title;
        $sEventDesc = $event_desc;
        $sEventText = $event_text;
        $aStartTokens = explode(" ", $event_start);
        $sEventStartDate = $aStartTokens[0];
        $aStartTimeTokens = explode(":", $aStartTokens[1]);
        $iEventStartHour = $aStartTimeTokens[0];
        $iEventStartMins = $aStartTimeTokens[1];
        $aEndTokens = explode(" ", $event_end);
        $sEventEndDate = $aEndTokens[0];
        $aEndTimeTokens = explode(":", $aEndTokens[1]);
        $iEventEndHour = $aEndTimeTokens[0];
        $iEventEndMins = $aEndTimeTokens[1];
        $iEventStatus = $inactive;

        $sSQL= "SELECT * FROM eventcounts_evtcnt WHERE evtcnt_eventid='$iEventID' ORDER BY evtcnt_countid ASC";
//        echo $cvSQL;
        $cvOpps = RunQuery($sSQL);
        $iNumCounts = mysql_num_rows($cvOpps);
        $nCnts = $iNumCounts;
//        echo "numcounts = {$aNumCounts}\n\l";
        if($iNumCounts) {
          for($c = 0; $c <$iNumCounts; $c++){
            $aRow = mysql_fetch_array($cvOpps, MYSQL_BOTH);
            extract($aRow);
            $aCountID[$c] = $evtcnt_countid;
            $aCountName[$c] = $evtcnt_countname;
            $aCount[$c]= $evtcnt_countcount;
            $sCountNotes = $evtcnt_notes;
          }
        }
//        for($v=0; $v<$aNumCounts; $v++)echo "count {$cCountName[$v]} = {$cCount[$v]}\n\r";

} elseif (isset($_POST["SaveChanges"])) {
// Does the user want to save changes to text fields?
        $iEventID = $_POST['EventID'];
        $iTypeID = $_POST['EventTypeID'];
        $EventExists = $_POST['EventExists'];
        $sEventTitle = $_POST['EventTitle'];
        $sEventDesc = $_POST['EventDesc'];
        $sEventStartDate = $_POST['EventStartDate'];
        $sEventStartTime = $_POST['EventStartTime'];
        if (empty($_POST['EventTitle'])) {
                $bTitleError = true;
                $iErrors++;
        }
				if (empty($_POST['EventDesc'])) {
                $bDescError = true;
                $iErrors++;
        }
				if (empty($_POST['EventTypeID'])) {
                $bEventTypeError = true;
                $iErrors++;
        } else {
					$sSQL = "SELECT type_name FROM event_types WHERE type_id = '" . FilterInput($iTypeID) . "' LIMIT 1";
	        $rsOpps = RunQuery($sSQL);
	        $aRow = mysql_fetch_array($rsOpps, MYSQL_BOTH);
	        extract($aRow);
					$sTypeName = $type_name;
				}
        $sEventText = $_POST['EventText'];
        if (empty($_POST['EventStartDate'])) {
                $bESDError = true;
                $iErrors++;
        }
        if (empty($_POST['EventStartTime'])) {
                $bESTError = true;
                $iErrors++;
        } else {
                $aESTokens = explode(":", $_POST['EventStartTime']);
                $iEventStartHour = $aESTokens[0];
                $iEventStartMins = $aESTokens[1];
        }
				if ($_POST['EventStatus'] === NULL) {
                $bStatusError = true;
                $iErrors++;
        }
        $sEventStart = $sEventStartDate." ".$sEventStartTime;
        $sEventEndDate = $_POST['EventEndDate'];
        $sEventEndTime = $_POST['EventEndTime'];
        $aEETokens = explode(":", $_POST['EventEndTime']);
        $iEventEndHour = $aEETokens[0];
        $iEventEndMins = $aEETokens[1];
        $sEventEnd = $sEventEndDate." ".$sEventEndTime;
        $iEventStatus = $_POST['EventStatus'];

        $iNumCounts = $_POST["NumAttendCounts"];
        $nCnts = $iNumCounts;
        $aEventCountArry = $_POST["EventCount"];
        $aEventCountIDArry = $_POST["EventCountID"];
        $aEventCountNameArry = $_POST["EventCountName"];

        foreach($aEventCountArry as $CCC) $aCount[] = $CCC;
        foreach($aEventCountIDArry as $CID) $aCountID[] = $CID;
        foreach($aEventCountNameArry as $CNM) $aCountName[] = $CNM;

        $sCountNotes = $_POST["EventCountNotes"];

        // If no errors, then update.
        if ($iErrors == 0)
        {
          if($EventExists==0){
            $sSQL = "INSERT events_event
                     SET `event_type` = '". FilterInput($iTypeID)."',
                     `event_title` = '".FilterInput($sEventTitle)."',
                     `event_desc` = '".FilterInput($sEventDesc)."',
                     `event_text` = '".FilterInput($sEventText)."',
                     `event_start` = '".FilterInput($sEventStart)."',
                     `event_end` = '".FilterInput($sEventEnd)."',
                     `inactive` = '".FilterInput($iEventStatus)."',
                     `event_typename` = '".FilterInput($sTypeName)."'";
            RunQuery($sSQL);
            $iEventID = mysql_insert_id();
            for($c=0; $c<$iNumCounts; $c++)
            {
              $cCnt = ltrim(rtrim($aCountName[$c]));
              $sSQL = "INSERT eventcounts_evtcnt
											 (evtcnt_eventid, evtcnt_countid, evtcnt_countname, evtcnt_countcount, evtcnt_notes)
											 VALUES
											 ('".FilterInput($iEventID)."',
											  '".FilterInput($aCountID[$c])."',
												'".FilterInput($aCountName[$c])."',
												'".FilterInput($aCount[$c])."',
												'".FilterInput($sCountNotes)."') ON DUPLICATE KEY UPDATE evtcnt_countcount='$aCount[$c]', evtcnt_notes='$sCountNotes'";
              RunQuery($sSQL);
            }

          } else {
            $sSQL = "UPDATE events_event
                     SET `event_type` = '".FilterInput($iTypeID)."',
                     `event_title` = '".FilterInput($sEventTitle)."',
                     `event_desc` = '".FilterInput($sEventDesc)."',
                     `event_text` = '".FilterInput($sEventText)."',
                     `event_start` = '".FilterInput($sEventStart)."',
                     `event_end` = '".FilterInput($sEventEnd)."',
                     `inactive` = '".FilterInput($iEventStatus)."',
                     `event_typename` = '".FilterInput($sTypeName)."'".
                    " WHERE `event_id` = '" . FilterInput($iEventID)."';";
//            echo $sSQL;
            RunQuery($sSQL);
            for($c=0; $c<$iNumCounts; $c++)
            {
              $cCnt = ltrim(rtrim($aCountName[$c]));
              $sSQL = "INSERT eventcounts_evtcnt
											 (evtcnt_eventid, evtcnt_countid, evtcnt_countname, evtcnt_countcount, evtcnt_notes)
											 VALUES
											 ('".FilterInput($iEventID)."',
											  '".FilterInput($aCountID[$c])."',
												'".FilterInput($aCountName[$c])."',
												'".FilterInput($aCount[$c])."',
												'".FilterInput($sCountNotes)."') ON DUPLICATE KEY UPDATE evtcnt_countcount='$aCount[$c]', evtcnt_notes='$sCountNotes'";
              RunQuery($sSQL);
            }
          }
          $EventExists=1;
          header ("Location: ListEvents.php");
        }
}

// Construct the form
?>

<div class='box'>
	<div class='box-header'>
		<h3 class='box-title'>
			<?= ($EventExists==0) ? gettext("Create a new Event") : gettext("Editing Event ID: ").$iEventID ?>
		</h3>
	</div>
	<div class='box-header'>
		<?php
		if ($iErrors != 0) {
				echo "<div class='alert alert-danger'>" . gettext('There were ') . $iErrors . gettext(' errors. Please see below') . "</div>";
		}
		else
		{
				echo "<div>" . gettext('Items with a ') . '<span style="color: red">*</span>' . gettext(' are required') . "</div>";
		}
		?>
	</div>

<form method="post" action="EventEditor.php" name="EventsEditor">
<input type="hidden" name="EventID" value="<?= ($iEventID) ?>">
<input type="hidden" name="EventExists" value="<?= $EventExists ?>">

<table class='table'>
<?php if (empty($iTypeID)) { ?>

  <tr>
    <td class="LabelColumn"><span style="color: red">*</span><?= gettext("Event Type:") ?></td>
    <td colspan="3" class="TextColumn">
			<select name='EN_tyid' class='form-control' id='event_type_id'>
				<option><?= gettext("Select your event type"); ?></option>
				<?php
					$sSQL = "SELECT * FROM event_types";
					$rsEventTypes = RunQuery($sSQL);
					while($aRow = mysql_fetch_array($rsEventTypes)) {
						extract($aRow);
						echo "<option value='" . $type_id . "' >" . $type_name . "</option>";
					}
				?>
			</select>
			<?php if ( $bEventTypeError ) echo "<div><span style=\"color: red;\">" . gettext("You must pick an event type.") . "</span></div>"; ?>
			<script type="text/javascript">
				$('#event_type_id').on('change', function(e) {
					e.preventDefault();
					document.forms.EventsEditor.submit();
				});
			</script>
    </td>
  </tr>

<?php } else { // if (empty($iTypeID)) ?>

  <tr>
    <td class="LabelColumn"><span style="color: red">*</span><?= gettext("Event Type:") ?></td>
    <td colspan="3" class="TextColumn">
    <input type="hidden" name="EventTypeName" value="<?= ($sTypeName) ?>">
    <input type="hidden" name="EventTypeID" value="<?= ($iTypeID) ?>">
    <?= ($iTypeID."-".$sTypeName) ?>
    </td>
  </tr>

  <tr>
    <td class="LabelColumn"><span style="color: red">*</span><?= gettext("Event Title:") ?></td>
    <td colspan="1" class="TextColumn">
      <input type="text" name="EventTitle" value="<?= ($sEventTitle) ?>" size="30" maxlength="100" class='form-control'>
      <?php if ( $bTitleError ) echo "<div><span style=\"color: red;\">" . gettext("You must enter a title.") . "</span></div>"; ?>
    </td>
    <td class="LabelColumn"><span style="color: red">*</span><?= gettext("Event Desc:") ?></td>
    <td colspan="1" class="TextColumn">
      <input type="text" name="EventDesc" value="<?= ($sEventDesc) ?>" size="30" maxlength="100" class='form-control'>
      <?php if ( $bDescError ) echo "<div><span style=\"color: red;\">" . gettext("You must enter a description.") . "</span></div>"; ?>
    </td>
  </tr>

  <tr>
    <td class="LabelColumn"><span style="color: red">*</span>
      <?= gettext("Start Date:") ?>
    </td>
    <td class="TextColumn">
      <input type="text" name="EventStartDate" value="<?= ($sEventStartDate) ?>" maxlength="10" id="EventStartDate" size="11" class='form-control'>
      <?php if ( $bESDError ) echo "<div><span style=\"color: red;\">" . gettext("You must enter a start date.") . "</span></div>"; ?>
    </td>
    <td class="LabelColumn"><span style="color: red">*</span>
      <?= gettext("Start Time:") ?>
    </td>
    <td class="TextColumn">
     <div class="input-group bootstrap-timepicker timepicker">
      <input name="EventStartTime" id="EventStartTime" type="text" class='form-control' placeholder='HH:MM' />
     </div>
      <?php if ( $bESTError ) echo "<div><span style=\"color: red;\">" . gettext("You must enter a start time.") . "</span></div>"; ?>
    </td>
  </tr>
  <tr>
    <td class="LabelColumn">
      <?= gettext("End Date:") ?>
    </td>
    <td class="TextColumn">
      <input type="text" name="EventEndDate" value="<?= ($sEventEndDate) ?>" maxlength="10" id="EventEndDate" size="11" class='form-control'>
    </td>
    <td class="LabelColumn">
      <?= gettext("End Time:") ?>
    </td>
    <td class="TextColumn">
     <div class="input-group bootstrap-timepicker timepicker">
      <input name="EventEndTime" id="EventEndTime" type="text" class='form-control' placeholder='HH:MM' />
     </div>
    </td>
  </tr>

  <tr>
    <td class="LabelColumn"><?= gettext("Attendance Counts") ?></td>
    <td class="TextColumn" colspan="3">
      <input type="hidden" name="NumAttendCounts" value="<?= $nCnts ?>">
      <?php
      if($nCnts==0){
      echo gettext("No Attendance counts recorded");
      } else {
      ?>
    <table>
      <?php
      for ($c=0; $c<$nCnts; $c++){
        ?><tr>
          <td><strong><?= ($aCountName[$c].": ") ?>&nbsp;</strong></td>
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
      <td><strong><?= gettext("Attendance Notes: ") ?>&nbsp;</strong></td>
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
    <td class="LabelColumn"><?= gettext("Event Sermon:") ?></td>
    <td colspan="3" class="TextColumn"><textarea name="EventText" rows="5" cols="80" class='form-control'><?= ($sEventText) ?></textarea></td>
  </tr>

  <tr>
    <td class="LabelColumn"><span style="color: red">*</span><?= gettext("Event Status:") ?></td>
    <td colspan="3" class="TextColumn">
      <?php $render->Radio("Inactive", "EventStatus", 0, ($iEventStatus == 0)); ?>
      <?php $render->Radio("Active",   "EventStatus", 1, ($iEventStatus == 1)); ?>
      <?php if ( $bStatusError ) echo "<div><span style=\"color: red;\">" . gettext("Is this Active or Inactive?") . "</span></div>"; ?>
    </td>
  </tr>

  <tr>
		<td></td>
    <td><input type="submit" name="SaveChanges" value="<?= gettext("Save Changes") ?>" class="btn btn-primary"></td>
  </tr>
<?php } // if (empty($iTypeID)) ?>
</table>
</form>
</div>

<div>
  <a href="ListEvents.php" class='btn btn-default'>
    <i class='glyphicon glyphicon-chevron-left'></i>
    <?= gettext("Return to Events") ?>
  </a>
</div>

<script>
$("#EventStartDate").datepicker({format:'yyyy-mm-dd'});
$("#EventEndDate").datepicker({format:'yyyy-mm-dd'});
$("#EventStartTime").timepicker({showMeridian: false});
$("#EventEndTime").timepicker({showMeridian: false});
</script>
<?php require "Include/Footer.php" ?>
