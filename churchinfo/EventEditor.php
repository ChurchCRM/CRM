<?php
/*******************************************************************************
 *
 *  filename    : EventEditor.php
 *  last change : 2005-09-10
 *  website     : http://www.terralabs.com
 *  copyright   : Copyright 2005 Todd Pillars
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

$sAction = $_POST['Action'];
$sOpp = $_POST['EID']; // from EDIT button on event listing
$tyid = $_POST["EN_tyid"]; // from event type list page
//
// process the action inputs
//
if ($sAction=='Create=>Event' && !empty($tyid)){
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

  $aTypeID = $type_id;
  $aTypeName = $type_name;             
  $ceDefStartTime = $type_defstarttime;
  $ceDefRecurDOW = $type_defrecurDOW;
  $ceDefRecurDOM = $type_defrecurDOM;
  $ceDefRecurDOY = $type_defrecurDOY;
  $ceDefRecurType = $type_defrecurtype;
  
  $cSQL = "SELECT evctnm_countid, evctnm_countname FROM eventcountnames_evctnm WHERE evctnm_eventtypeid='$aTypeID' ORDER BY evctnm_countid ASC";
  $cOpps = RunQuery($cSQL);
  $aNumCounts = mysql_num_rows($cOpps);
  if($aNumCounts) {
    for($c = 0; $c <$aNumCounts; $c++){
        $cRow = mysql_fetch_array($cOpps, MYSQL_BOTH);
        extract($cRow);
        $cCountID[$c] = $evctnm_countid;
        $cCountName[$c] = $evctnm_countname;    
        $cCount[$c]=0;        
    }
  }    
  $nCnts = $aNumCounts;
  $cCountNotes="";               
//
// this switch manages the smart-prefill of the form based on the event type
// definitions, recurrance type, etc.
//           
  switch ($ceDefRecurType){
    case "none": 
      $aEventStartDate = date('Y-m-d');
      $aEventEndDate = $aEventStartDate;
      $ceStartTimeTokens = explode(":",$ceDefStartTime);
      $aEventStartHour = $ceStartTimeTokens[0];
      $aEventStartMins = $ceStartTimeTokens[1];
      $aEventEndHour = $ceStartTimeTokens[0]+1;
      $aEventEndMins = $ceStartTimeTokens[1];
      break;
      
    case "weekly": 
    // check for the last occurance of this type_id in the events table and
    // create a new event based on this date reference
    //
      $ecSQL = "SELECT * FROM events_event WHERE event_type = '$aTypeID' ORDER BY event_start DESC LIMIT 1";
      $ecOpps = RunQuery($ecSQL);
      $numRows = mysql_num_rows($ecOpps);
      if($numRows >0){
        // use the most recent event if it exists
        $ecRow = mysql_fetch_array($ecOpps, MYSQL_BOTH);
        extract($ecRow);
        $ceStartTokens = explode(" ", $event_start);
        $ceEventStartDate = $ceStartTokens[0];          
        $aEventStartDate = date('Y-m-d',strtotime("$ceEventStartDate +1 week"));
        
        $ceEventStartTimeTokens = explode(":",$ceDefStartTime);  
        $aEventStartHour = $ceEventStartTimeTokens[0];
        $aEventStartMins = $ceEventStartTimeTokens[1];

        $aEventEndDate = $aEventStartDate;
        $aEventEndHour = $aEventStartHour+1;
        $aEventEndMins = $aEventStartMins;     
      } else {
        // use the event type definition
        $aEventStartDate = date('Y-m-d',strtotime("last $ceDefRecurDOW"));
        $ceStartTimeTokens = explode(":",$ceDefStartTime);
        $aEventStartHour = $ceStartTimeTokens[0];
        $aEventStartMins = $ceStartTimeTokens[1];
        echo "weekly time = $aEventStartHour\r\n";
        $aEventEndDate = $aEventStartDate;
        $aEventEndHour = $ceStartTimeTokens[0]+1;
        $aEventEndMins = $ceStartTimeTokens[1];
      }
      break;
      
    case "monthly": 
    // check for the last occurance of this type_id in the events table and
    // create a new event based on this date reference
    //
      $ecSQL = "SELECT * FROM events_event WHERE event_type = '$aTypeID' ORDER BY event_start DESC LIMIT 1";
      $ecOpps = RunQuery($ecSQL);
      $numRows = mysql_num_rows($ecOpps);
      if($numRows >0){
        // use the most recent event if it exists
        $ecRow = mysql_fetch_array($ecOpps, MYSQL_BOTH);
        extract($ecRow);
        $ceStartTokens = explode(" ", $event_start);
        $ceEventStartDate = $ceStartTokens[0];
        $ceDMY = explode("-",$ceStartTokens[0]);
        $ceEventStartTimeTokens = explode(":",$ceStartTokens[1]);  
          
        $aEventStartDate = date('Y-m-d',mktime(0,0,0,$ceDMY[1]+1,$ceDMY[2],$ceDMY[0]));
        $aEventStartHour = $ceEventStartTimeTokens[0];
        $aEventStartMins = $ceEventStartTimeTokens[1];
        $aEventEndDate = $aEventStartDate;
        $aEventEndHour = $ceEventStartTimeTokens[0]+1;
        $aEventEndMins = $ceEventStartTimeTokens[1];
      } else {
        // use the event type definition
        $currentDOM = date('d');
        if($currentDOM < $ceDefRecurDOM){
          $aEventStartDate = date('Y-m-d',mktime(0,0,0,date('m')-1,$ceDefRecurDOM,date('Y')));
        } else {
          $aEventStartDate = date('Y-m-d',mktime(0,0,0,date('m'),$ceDefRecurDOM,date('Y')));         
        }

        $ceStartTimeTokens = explode(":",$ceDefStartTime);
        $aEventStartHour = $ceStartTimeTokens[0];
        $aEventStartMins = $ceStartTimeTokens[1];
        $aEventEndDate = $aEventStartDate;        
        $aEventEndHour = $ceStartTimeTokens[0]+1;
        $aEventEndMins = $ceStartTimeTokens[1];
      }
      break;
      
    case "yearly": 
      $ecSQL = "SELECT * FROM events_event WHERE event_type = '$aTypeID' ORDER BY event_start DESC LIMIT 1";
      $ecOpps = RunQuery($ecSQL);
      $numRows = mysql_num_rows($ecOpps);
      if($numRows >0){
        // use the most recent event if it exists
        $ecRow = mysql_fetch_array($ecOpps, MYSQL_BOTH);
        extract($ecRow);
        $ceStartTokens = explode(" ", $event_start);
        $ceEventStartDate = $ceStartTokens[0];
        $ceDMY = explode("-",$ceStartTokens[0]);
        $ceEventStartTimeTokens = explode(":",$ceStartTokens[1]);  
          
        $aEventStartDate = date('Y-m-d',mktime(0,0,0,$ceDMY[1],$ceDMY[2],$ceDMY[0]+1));
        $aEventStartHour = $ceEventStartTimeTokens[0];
        $aEventStartMins = $ceEventStartTimeTokens[1];
        $aEventEndDate = $aEventStartDate;        
        $aEventEndHour = $ceEventStartTimeTokens[0]+1;
        $aEventEndMins = $ceEventStartTimeTokens[1];
      } else {
        // use the event type definition
        $currentDOY = time();
        $defaultDOY = strtotime($ceDefRecurDOY);
        if($currentDOY < $defaultDOY){  // event is future
          $aEventStartDate = $ceDefRecurDOY;
        } else if ($currentDOY > $defaultDOY+(365 * 24 * 60 * 60)){  // event is over 1 year past
          $ceDMY = explode("-",$ceDefRecurDOY);
          $aEventStartDate = date('Y-m-d',mktime(0,0,0,$ceDMY[1],$ceDMY[2],date('Y')-1));         
        } else { // event is past
          $ceDMY = explode("-",$ceDefRecurDOY);
          $aEventStartDate = date('Y-m-d',mktime(0,0,0,$ceDMY[1],$ceDMY[2],date('Y')));         
        
        }

        $ceStartTimeTokens = explode(":",$ceDefStartTime);
        $aEventStartHour = $ceStartTimeTokens[0];
        $aEventStartMins = $ceStartTimeTokens[1];
        $aEventEndDate = $aEventStartDate;        
        $aEventEndHour = $ceStartTimeTokens[0]+1;
        $aEventEndMins = $ceStartTimeTokens[1];
      }
      break;
  }
  $aEventTitle = $aEventStartDate."-".$aTypeName;
  $aEventDesc="";
  $aEventText="";
  $aEventStatus=0;
  $aTypeID = $type_id;

}
else if ($sAction = 'Edit' && !empty($sOpp))
{
        // Get data for the form as it now exists..
        $EventExists = 1;
        $sSQL = "SELECT * FROM events_event as t1, event_types as t2 WHERE t1.event_type = t2.type_id AND t1.event_id ='".$sOpp."' LIMIT 1";

        $rsOpps = RunQuery($sSQL);

        $aRow = mysql_fetch_array($rsOpps, MYSQL_BOTH);
        extract($aRow);

        $aEventID = $event_id;
        $aTypeID = $type_id;
        $aEventName = $event_name;
        $aTypeName = $type_name;
        $aEventTitle = $event_title;
        $aEventDesc = $event_desc;
        $aEventText = $event_text;
        $aStartTokens = explode(" ", $event_start);
        $aEventStartDate = $aStartTokens[0];
        $aStartTimeTokens = explode(":", $aStartTokens[1]);
        $aEventStartHour = $aStartTimeTokens[0];
        $aEventStartMins = $aStartTimeTokens[1];
        $aEndTokens = explode(" ", $event_end);
        $aEventEndDate = $aEndTokens[0];
        $aEndTimeTokens = explode(":", $aEndTokens[1]);
        $aEventEndHour = $aEndTimeTokens[0];
        $aEventEndMins = $aEndTimeTokens[1];
        $aEventStatus = $inactive;
        
        $cvSQL= "SELECT * FROM eventcounts_evtcnt WHERE evtcnt_eventid='$aEventID' ORDER BY evtcnt_countid ASC"; 
//        echo $cvSQL;
        $cvOpps = RunQuery($cvSQL);
        $aNumCounts = mysql_num_rows($cvOpps);
        $nCnts = $aNumCounts;
//        echo "numcounts = {$aNumCounts}\n\l";
        if($aNumCounts) {
          for($c = 0; $c <$aNumCounts; $c++){
            $cRow = mysql_fetch_array($cvOpps, MYSQL_BOTH);
            extract($cRow);
            $cCountID[$c] = $evtcnt_countid;
            $cCountName[$c] = $evtcnt_countname;     
            $cCount[$c]= $evtcnt_countcount;
            $cCountNotes = $evtcnt_notes;            
          }
        }        
//        for($v=0; $v<$aNumCounts; $v++)echo "count {$cCountName[$v]} = {$cCount[$v]}\n\r";           

} elseif (isset($_POST["SaveChanges"])) {
// Does the user want to save changes to text fields?
        $bErrors = 0;
        $uEventID = $_POST['EventID'];
//        echo "eventID = $uEventID";
        $uTypeName = $_POST['EventTypeName'];
        $uTypeID = $_POST['EventTypeID'];
        $EventExists = $_POST['EventExists'];
        if (empty($_POST['EventTitle']))
        {
                $bTitleError = true;
                $bErrors++;
        }
        else
        {
                $uEventTitle = FilterInput($_POST['EventTitle']);
        }
        if (empty($_POST['EventDesc']))
        {
                $bDescError = true;
                $bErrors++;
        }
        else
        {
                $uEventDesc = FilterInput($_POST['EventDesc']);
        }
        $uEventText = FilterInput($_POST['EventText']);
        if (empty($_POST['EventStartDate']))
        {
                $bESDError = true;
                $bErrors++;
        }
        else
        {
                $uEventStartDate = $_POST['EventStartDate'];
        }
        if (empty($_POST['EventStartTime']))
        {
                $bESTError = true;
                $bErrors++;
        }
        else
        {
                $uEventStartTime = $_POST['EventStartTime'];
                $uESTokens = explode(":", $_POST['EventStartTime']);
                $uEventStartHour = $uESTokens[0];
                $uEventStartMins = $uESTokens[1];
        }
        $uEventStart = $uEventStartDate." ".$uEventStartTime;
        $uEventEndDate = $_POST['EventEndDate'];
        $uEventEndTime = $_POST['EventEndTime'];
        $uEETokens = explode(":", $_POST['EventEndTime']);
        $uEventEndHour = $uEETokens[0];
        $uEventEndMins = $uEETokens[1];
        $uEventEnd = $uEventEndDate." ".$uEventEndTime;
        $uEventStatus = $_POST['EventStatus'];
        
        $uNumCounts = $_POST["NumAttendCounts"];
        $nCnts = $uNumCounts;
        $uEventCountArry = $_POST["EventCount"];
        $uEventCountIDArry = $_POST["EventCountID"];
        $uEventCountNameArry = $_POST["EventCountName"];
        
        unset($uCount, $uCountID, $uCountName);
        foreach($uEventCountArry as $CCC) $uCount[] = $CCC; 
        foreach($uEventCountIDArry as $CID) $uCountID[] = $CID;
        foreach($uEventCountNameArry as $CNM) $uCountName[] = $CNM;

        $uCountNotes = $_POST["EventCountNotes"];

        // If no errors, then update.
        if ($bErrors == 0)
        {
          if($EventExists==0){
            $sSQL = "INSERT events_event
                     SET `event_type` = '".$uTypeID."',
                     `event_title` = '".$uEventTitle."',
                     `event_desc` = '".$uEventDesc."',
                     `event_text` = '".$uEventText."',
                     `event_start` = '".$uEventStart."',
                     `event_end` = '".$uEventEnd."',
                     `inactive` = '".$uEventStatus."',  
                     `event_typename` = '".$uTypeName."'";  
            RunQuery($sSQL);
            $uEventID = mysql_insert_id();
            for($c=0; $c<$uNumCounts; $c++)
            {
              $cCnt = ltrim(rtrim($uCountName[$c]));
              $sSQL = "INSERT eventcounts_evtcnt (evtcnt_eventid, evtcnt_countid, evtcnt_countname, evtcnt_countcount, evtcnt_notes) VALUES ('$uEventID','$uCountID[$c]','$uCountName[$c]','$uCount[$c]','$uCountNotes') ON DUPLICATE KEY UPDATE evtcnt_countcount='$uCount[$c]', evtcnt_notes='$uCountNotes'";
//              echo $sSQL;
              RunQuery($sSQL);
            }          
            
          } else {
            $sSQL = "UPDATE events_event
                     SET `event_type` = '".$uTypeID."',
                     `event_title` = '".$uEventTitle."',
                     `event_desc` = '".$uEventDesc."',
                     `event_text` = '".$uEventText."',
                     `event_start` = '".$uEventStart."',
                     `event_end` = '".$uEventEnd."',
                     `inactive` = '".$uEventStatus."',
                     `event_typename` = '".$uTypeName."'".
                    " WHERE `event_id` = '" . $uEventID."';";
//            echo $sSQL;
            RunQuery($sSQL);
            for($c=0; $c<$uNumCounts; $c++)
            {
              $cCnt = ltrim(rtrim($uCountName[$c]));
              $sSQL = "INSERT eventcounts_evtcnt (evtcnt_eventid, evtcnt_countid, evtcnt_countname, evtcnt_countcount, evtcnt_notes) VALUES ('$uEventID','$uCountID[$c]','$uCountName[$c]','$uCount[$c]','$uCountNotes') ON DUPLICATE KEY UPDATE evtcnt_countcount='$uCount[$c]', evtcnt_notes='$uCountNotes'";
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
<input type="hidden" name="EventID" value="<?php echo ($uEventID ? $uEventID:$aEventID); ?>">
<input type="hidden" name="EventExists" value="<?php echo $EventExists ;?>">
<?php // used to be ($uEventID ? $uEventID:$aEventID_POST['EID']) ?>
<table cellpadding="3" width="75%" align="center">
  <caption>
    <h3><?php 
        if($EventExists==0){
          echo gettext("Editing Event ID: (ID will be created once saved)");
        } else {
          echo gettext("Editing Event ID: ").($uEventID ? $uEventID:$aEventID); 
        }
        ?></h3>
  </caption>
  <tr>
    <td colspan="4" align="center"><input type="button" class="icButton" <?php echo 'value="' . gettext("Back to Menu") . '"'; ?> Name="Exit" onclick="javascript:document.location='Menu.php';"></td>
  </tr>
  <tr>
    <td colspan="4" align="center">
    <?php
    if ($bErrors != 0) {
        echo 'There were '.$bErrors.' errors. Please see below';
    }
    else
    {
        echo 'Items with a <font color="#ff0000">*</font> are required';
    }
    ?>
    </td>
  </tr>
  <tr>
    <td class="LabelColumn"><font color="#ff0000">*</font><?php echo gettext("Event Type:"); ?></td>
    <td colspan="3" class="TextColumn">
    <input type="hidden" name="EventTypeName" value="<?php echo ($uTypeName ? $uTypeName:$aTypeName); ?>">
    <input type="hidden" name="EventTypeID" value="<?php echo ($uTypeID ? $uTypeID:$aTypeID); ?>">
    <?php echo ($uTypeID ? $uTypeID."-".$uTypeName:$aTypeID."-".$aTypeName); ?>
    </td>
  </tr>
  
  <tr>
    <td class="LabelColumn"><font color="#ff0000">*</font><?php echo gettext("Event Title:"); ?></td>
    <td colspan="1" class="TextColumn">
      <input type="text" name="EventTitle" value="<?php echo ($uEventTitle ? $uEventTitle:$aEventTitle); ?>" echo " size="30" maxlength="100">
      <?php if ( $bTitleError ) echo "<div><span style=\"color: red;\">" . gettext("You must enter a title.") . "</span></div>"; ?>
    </td>
    <td class="LabelColumn"><font color="#ff0000">*</font><?php echo gettext("Event Desc:"); ?></td>
    <td colspan="1" class="TextColumn">
      <input type="text" name="EventDesc" value="<?php echo ($uEventDesc ? $uEventDesc:$aEventDesc); ?>" size="30" maxlength="100">
      <?php if ( $bDescError ) echo "<div><span style=\"color: red;\">" . gettext("You must enter a description.") . "</span></div>"; ?>
    </td>
  </tr>
  
  <tr>
    <td class="LabelColumn"><font color="#ff0000">*</font>
      <?php echo gettext("Start Date:"); ?><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?>
    </td>
    <td class="TextColumn">
      <input type="text" name="EventStartDate" value="<?php echo ($uEventStartDate ? $uEventStartDate:$aEventStartDate); ?>" maxlength="10" id="SD" size="11">&nbsp;
      <input type="image" onclick="return showCalendar('SD', 'y-mm-dd');" src="Images/calendar.gif">
      <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span>
      <?php if ( $bESDError ) echo "<div><span style=\"color: red;\">" . gettext("You must enter a start date.") . "</span></div>"; ?>
    </td>
    <td class="LabelColumn"><font color="#ff0000">*</font>
      <?php echo gettext("Start Time:"); ?>
    </td>
    <td class="TextColumn">
      <select name="EventStartTime" size="1">
      <?php
      if ($uEventStartHour) $aEventStartHour = $uEventStartHour;
      if ($uEventStartMins) $aEventStartMins = $uEventStartMins;
      ?>
      <?php createTimeDropdown(7,18,15,$aEventStartHour,$aEventStartMins); ?>
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
      <input type="text" name="EventEndDate" value="<?php echo ($uEventEndDate ? $uEventEndDate:$aEventEndDate); ?>" maxlength="10" id="ED" size="11">&nbsp;
      <input type="image" onclick="return showCalendar('ED', 'y-mm-dd');" src="Images/calendar.gif">
      <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span>
    </td>
    <td class="LabelColumn">
      <?php echo gettext("End Time:"); ?>
    </td>
    <td class="TextColumn">
      <select name="EventEndTime" size="1">
      <?php
      if ($uEventEndHour) $aEventEndHour = $uEventEndHour;
      if ($uEventEndMins) $aEventEndMins = $uEventEndMins;
      ?>
      <?php createTimeDropdown(7,18,15,$aEventEndHour,$aEventEndMins); ?>
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
          <td><strong><?php echo ($cCountName[$c] ? $cCountName[$c].": " : $uCountName[$c].": "); ?></strong></td>
        <td>
        <input type="text" name="EventCount[]" value="<?php echo ($cCount[$c] ? $cCount[$c] : $uCount[$c]);?>" size="8">
        <input type="hidden" name="EventCountID[]" value="<?php echo ($cCountID[$c] ? $cCountID[$c] : $uCountID[$c]);?>">
        <input type="hidden" name="EventCountName[]" value="<?php echo ($cCountName[$c] ? $cCountName[$c] : $uCountName[$c]);?>">
        </td>
        </tr>
      <?php
      } //end for loop
      ?>
      <tr>
      <td><strong><?php echo gettext("Attendance Notes: "); ?></strong></td>
        <td><input type="text" name="EventCountNotes" value="<?php echo $cCountNotes; ?>">
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
    <td colspan="3" class="TextColumn"><textarea name="EventText" rows="5" cols="80"><?php echo ($uEventText ? $uEventText:$aEventText); ?></textarea></td>
  </tr>

  <tr>
    <td class="LabelColumn"><font color="#ff0000">*</font><?php echo gettext("Event Status:"); ?></td>
    <td colspan="3" class="TextColumn">
      <input type="radio" name="EventStatus" value="0"<?php if ($aEventStatus == 0 || $uEventStatus == 0) echo " checked";?>> Active <input type="radio" name="EventStatus" value="1"<?php if ($aEventStatus == 1 || $uEventStatus == 1) echo " checked"; ?>> Inactive
      <?php if ( $bStatusError ) echo "<div><span style=\"color: red;\">" . gettext("Is this Active or Inactive?") . "</span></div>"; ?>
    </td>
  </tr>
  
  <tr>
    <td colspan="2" align="center"><input type="submit" name="SaveChanges" <?php echo 'value="' . gettext("Save Changes") . '"'; ?> class="icButton"></td>
  </tr>
</table>
</form>
<?php require "Include/Footer.php"; ?>
