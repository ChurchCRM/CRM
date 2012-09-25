<?php
/*******************************************************************************
 *
 *  filename    : EventEditor.php
 *  last change : 2005-09-10
 *  website     : http://www.terralabs.com
 *  copyright   : Copyright 2005 Todd Pillars
 *                Copyright 2012 Michael Wilt
 *
 *  function    : Editor for Church Events
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
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
require "Include/Header.php";

$sPageTitle = gettext("Church Event Editor");

$sAction = 'Create=>Event';

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

//
// process the action inputs
//
if ($sAction==gettext('Create=>Event') && !empty($tyid)){
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
        echo "weekly time = $iEventStartHour\r\n";
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
//        echo "eventID = $iEventID";
        $sTypeName = $_POST['EventTypeName'];
        $iTypeID = $_POST['EventTypeID'];
        $EventExists = $_POST['EventExists'];
        $sEventTitle = FilterInput($_POST['EventTitle']);
        $sEventDesc = FilterInput($_POST['EventDesc']);
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
        $sEventText = FilterInput($_POST['EventText']);
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
                     SET `event_type` = '".$iTypeID."',
                     `event_title` = '".$sEventTitle."',
                     `event_desc` = '".$sEventDesc."',
                     `event_text` = '".$sEventText."',
                     `event_start` = '".$sEventStart."',
                     `event_end` = '".$sEventEnd."',
                     `inactive` = '".$iEventStatus."',  
                     `event_typename` = '".$sTypeName."'";  
            RunQuery($sSQL);
            $iEventID = mysql_insert_id();
            for($c=0; $c<$iNumCounts; $c++)
            {
              $cCnt = ltrim(rtrim($aCountName[$c]));
              $sSQL = "INSERT eventcounts_evtcnt (evtcnt_eventid, evtcnt_countid, evtcnt_countname, evtcnt_countcount, evtcnt_notes) VALUES ('$iEventID','$aCountID[$c]','$aCountName[$c]','$aCount[$c]','$sCountNotes') ON DUPLICATE KEY UPDATE evtcnt_countcount='$aCount[$c]', evtcnt_notes='$sCountNotes'";
//              echo $sSQL;
              RunQuery($sSQL);
            }          
            
          } else {
            $sSQL = "UPDATE events_event
                     SET `event_type` = '".$iTypeID."',
                     `event_title` = '".$sEventTitle."',
                     `event_desc` = '".$sEventDesc."',
                     `event_text` = '".$sEventText."',
                     `event_start` = '".$sEventStart."',
                     `event_end` = '".$sEventEnd."',
                     `inactive` = '".$iEventStatus."',
                     `event_typename` = '".$sTypeName."'".
                    " WHERE `event_id` = '" . $iEventID."';";
//            echo $sSQL;
            RunQuery($sSQL);
            for($c=0; $c<$iNumCounts; $c++)
            {
              $cCnt = ltrim(rtrim($aCountName[$c]));
              $sSQL = "INSERT eventcounts_evtcnt (evtcnt_eventid, evtcnt_countid, evtcnt_countname, evtcnt_countcount, evtcnt_notes) VALUES ('$iEventID','$aCountID[$c]','$aCountName[$c]','$aCount[$c]','$sCountNotes') ON DUPLICATE KEY UPDATE evtcnt_countcount='$aCount[$c]', evtcnt_notes='$sCountNotes'";
              RunQuery($sSQL);
            }          
          }
          $EventExists=1;
          header ("Location: ListEvents.php");
        }
}

// Construct the form
?>

<form method="post" action="EventEditor.php" name="EventsEditor">
<input type="hidden" name="EventID" value="<?php echo ($iEventID); ?>">
<input type="hidden" name="EventExists" value="<?php echo $EventExists ;?>">
<?php // used to be ($iEventID ? $iEventID : $aEventID_POST['EID']) ?>
<table cellpadding="3" width="75%" align="center">
  <caption>
    <h3><?php 
        if($EventExists==0){
          echo gettext("Editing Event ID: (ID will be created once saved)");
        } else {
          echo gettext("Editing Event ID: ").$iEventID; 
        }
        ?></h3>
  </caption>
  <tr>
    <td colspan="4" align="center"><input type="button" class="icButton" <?php echo 'value="' . gettext("Back to Menu") . '"'; ?> Name="Exit" onclick="javascript:document.location='Menu.php';"></td>
  </tr>
  <tr>
    <td colspan="4" align="center">
    <?php
    if ($iErrors != 0) {
        echo gettext ('There were ').$iErrors. gettext(' errors. Please see below');
    }
    else
    {
        echo gettext('Items with a ').'<font color="#ff0000">*</font>'.gettext(' are required');
    }
    ?>
    </td>
  </tr>
  <tr>
    <td class="LabelColumn"><font color="#ff0000">*</font><?php echo gettext("Event Type:"); ?></td>
    <td colspan="3" class="TextColumn">
    <input type="hidden" name="EventTypeName" value="<?php echo ($sTypeName); ?>">
    <input type="hidden" name="EventTypeID" value="<?php echo ($iTypeID); ?>">
    <?php echo ($iTypeID."-".$sTypeName); ?>
    </td>
  </tr>
  
  <tr>
    <td class="LabelColumn"><font color="#ff0000">*</font><?php echo gettext("Event Title:"); ?></td>
    <td colspan="1" class="TextColumn">
      <input type="text" name="EventTitle" value="<?php echo ($sEventTitle); ?>" size="30" maxlength="100">
      <?php if ( $bTitleError ) echo "<div><span style=\"color: red;\">" . gettext("You must enter a title.") . "</span></div>"; ?>
    </td>
    <td class="LabelColumn"><font color="#ff0000">*</font><?php echo gettext("Event Desc:"); ?></td>
    <td colspan="1" class="TextColumn">
      <input type="text" name="EventDesc" value="<?php echo ($sEventDesc); ?>" size="30" maxlength="100">
      <?php if ( $bDescError ) echo "<div><span style=\"color: red;\">" . gettext("You must enter a description.") . "</span></div>"; ?>
    </td>
  </tr>
  
  <tr>
    <td class="LabelColumn"><font color="#ff0000">*</font>
      <?php echo gettext("Start Date:"); ?><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?>
    </td>
    <td class="TextColumn">
      <input type="text" name="EventStartDate" value="<?php echo ($sEventStartDate); ?>" maxlength="10" id="SD" size="11">&nbsp;
      <input type="image" onclick="return showCalendar('SD', 'y-mm-dd');" src="Images/calendar.gif">
      <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span>
      <?php if ( $bESDError ) echo "<div><span style=\"color: red;\">" . gettext("You must enter a start date.") . "</span></div>"; ?>
    </td>
    <td class="LabelColumn"><font color="#ff0000">*</font>
      <?php echo gettext("Start Time:"); ?>
    </td>
    <td class="TextColumn">
      <select name="EventStartTime" size="1">
      <?php createTimeDropdown($iEventPeriodStartHr,$iEventPeriodEndHr,$iEventPeriodIntervalMin,$iEventStartHour,$iEventStartMins); ?>
      </select>
      &nbsp;<span class="SmallText"><?php echo gettext("[format: HH:MM]"); ?></span>
      <?php if ( $bESTError ) echo "<div><span style=\"color: red;\">" . gettext("You must enter a start time.") . "</span></div>"; ?>
    </td>
  </tr> 
  <tr>
    <td class="LabelColumn">
      <?php echo gettext("End Date:"); ?>
    </td>
    <td class="TextColumn">
      <input type="text" name="EventEndDate" value="<?php echo ($sEventEndDate); ?>" maxlength="10" id="ED" size="11">&nbsp;
      <input type="image" onclick="return showCalendar('ED', 'y-mm-dd');" src="Images/calendar.gif">
      <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span>
    </td>
    <td class="LabelColumn">
      <?php echo gettext("End Time:"); ?>
    </td>
    <td class="TextColumn">
      <select name="EventEndTime" size="1">
      <?php createTimeDropdown($iEventPeriodStartHr,$iEventPeriodEndHr,$iEventPeriodIntervalMin,$iEventEndHour,$iEventEndMins); ?>
      </select>
      &nbsp;<span class="SmallText"><?php echo gettext("[format: HH:MM]"); ?></span>
    </td>
  </tr>  
 
  <tr>
    <td class="LabelColumn"><?php echo gettext("Attendance Counts"); ?></td>
    <td class="TextColumn" colspan="3">
      <input type="hidden" name="NumAttendCounts" value="<?php echo $nCnts; ?>"> 
      <?php
      if($nCnts==0){
      echo gettext("No Attendance counts recorded");
      } else {
      ?>   
    <table>
      <?php
      for ($c=0; $c<$nCnts; $c++){
        ?><tr>
          <td><strong><?php echo ($aCountName[$c].": "); ?></strong></td>
        <td>
        <input type="text" name="EventCount[]" value="<?php echo ($aCount[$c]);?>" size="8">
        <input type="hidden" name="EventCountID[]" value="<?php echo ($aCountID[$c]);?>">
        <input type="hidden" name="EventCountName[]" value="<?php echo ($aCountName[$c]);?>">
        </td>
        </tr>
      <?php
      } //end for loop
      ?>
      <tr>
      <td><strong><?php echo gettext("Attendance Notes: "); ?></strong></td>
        <td><input type="text" name="EventCountNotes" value="<?php echo $sCountNotes; ?>">
        </td>
        </tr>
        </table>
        <?php
        } //endif 
        ?>
    </td>
  </tr>  
  
  <tr>
    <td class="LabelColumn"><?php echo gettext("Event Sermon:"); ?></td>
    <td colspan="3" class="TextColumn"><textarea name="EventText" rows="5" cols="80"><?php echo ($sEventText); ?></textarea></td>
  </tr>

  <tr>
    <td class="LabelColumn"><font color="#ff0000">*</font><?php echo gettext("Event Status:"); ?></td>
    <td colspan="3" class="TextColumn">
      <input type="radio" name="EventStatus" value="0"<?php if ($iEventStatus == 0) echo " checked";?>> Active <input type="radio" name="EventStatus" value="1"<?php if ($iEventStatus == 1) echo " checked"; ?>> Inactive
      <?php if ( $bStatusError ) echo "<div><span style=\"color: red;\">" . gettext("Is this Active or Inactive?") . "</span></div>"; ?>
    </td>
  </tr>
  
  <tr>
    <td colspan="2" align="center"><input type="submit" name="SaveChanges" <?php echo 'value="' . gettext("Save Changes") . '"'; ?> class="icButton"></td>
  </tr>
</table>
</form>
<?php require "Include/Footer.php"; ?>
