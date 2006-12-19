<?php
/*******************************************************************************
 *
 *  filename    : ListEvents.php
 *  last change : 2005-09-10
 *  website     : http://www.terralabs.com
 *  copyright   : Copyright 2005 Todd Pillars
 *
 *  function    : List all Church Events
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

require "Include/Config.php";
require "Include/Functions.php";
$eType="All";
$ThisYear=date("Y");

if ($_POST['WhichType']){
  $eType = $_POST['WhichType'];
} else {
  $eType ="All";
}

if($eType!="All"){
  $sSQL = "SELECT * FROM event_types WHERE type_id=$eType";
  $rsOpps = RunQuery($sSQL);
  $aRow = mysql_fetch_array($rsOpps, MYSQL_BOTH);
  extract($aRow);
  $sPageTitle = "Listing Events of Type = ".$type_name;
} else {
  $sPageTitle = gettext("Listing All Church Events");
}

// retrieve the year selector

if($_POST['WhichYear'])
{
    $EventYear=$_POST['WhichYear'];
} else {
    $EventYear=date("Y");
}


///////////////////////
require "Include/Header.php";

if ($_POST['Action']== "Delete" && !empty($_POST['EID']))
{
    $sSQL = "DELETE FROM events_event WHERE event_id = ".$_POST['EID']." LIMIT 1";
    RunQuery($sSQL);
    
    $sSQL = "DELETE FROM eventcounts_evtcnt WHERE evtcnt_eventid = ".$_POST['EID'];
    RunQuery($sSQL);

}
elseif ($_POST['Action']== "Activate" && !empty($_POST['EID']))
{
    $sSQL = "UPDATE events_event SET inactive = 0 WHERE event_id = ".$_POST['EID']." LIMIT 1";
    RunQuery($sSQL);
}

/// top of main form
//
$sSQL = "SELECT DISTINCT event_types.* FROM event_types RIGHT JOIN events_event ON event_types.type_id=events_event.event_type ORDER BY type_id ";
$rsOpps = RunQuery($sSQL);
$numRows = mysql_num_rows($rsOpps);

?>
<table cellpadding="1" align="center" cellspacing="0" width="100%">
<tr>
<td align="center" width="50%"><strong><?php echo gettext("Select Event Types To Display") ?></strong><br>
    <form name="EventTypeSelector" method="POST" action="ListEvents.php">
       <select name="WhichType" onchange="javascript:this.form.submit()">
        <option value="All">All</option>
        <?php
        for ($r = 1; $r <= $numRows; $r++)
        {
          $aRow = mysql_fetch_array($rsOpps, MYSQL_BOTH);
          extract($aRow);
//          foreach($aRow as $t)echo "$t\n\r";
          ?>
          <option value="<?php echo $type_id ?>" <?php if($type_id==$eType) echo "selected" ?>><?php echo $type_name; ?></option>
          <?php
         }
         ?>
         </select>
</td>

<?php
// year selector
if($eType=="All"){
  $sSQL = "SELECT DISTINCT YEAR(events_event.event_start) FROM events_event WHERE YEAR(events_event.event_start)";
} else {
  $sSQL = "SELECT DISTINCT YEAR(events_event.event_start) FROM events_event WHERE events_event.event_type = '$eType' AND YEAR(events_event.event_start)";
}  
$rsOpps = RunQuery($sSQL);
$aRow = mysql_fetch_array($rsOpps, MYSQL_BOTH);
extract($aRow);
$rsOpps = RunQuery($sSQL);
$numRows = mysql_num_rows($rsOpps);
for($r=1; $r<=$numRows; $r++){
    $aRow = mysql_fetch_array($rsOpps, MYSQL_BOTH);
    extract($aRow);
    $Yr[$r]=$aRow[0];
}

?>

<td align="center" width="50%"><strong><?php echo gettext("Display Events in Year") ?></strong><br>
       <select name="WhichYear" onchange="javascript:this.form.submit()" >
        <?php
        for ($r = 1; $r <= $numRows; $r++)
        {
          ?>
          <option value="<?php echo $Yr[$r] ?>" <?php if($Yr[$r]==$EventYear) echo "selected" ?>><?php echo $Yr[$r]; ?></option>
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
$currYear = date("Y");
$currMonth = date("m");
$allMonths = array("1","2","3","4","5","6","7","8","9","10","11","12");
if ($eType=="All") {
  $eTypeSQL=" ";
} else {
  $eTypeSQL = " AND t1.event_type=$eType";

}

foreach ($allMonths as $mKey => $mVal) {
        unset($cCountSum);
        $sSQL = "SELECT * FROM events_event as t1, event_types as t2 ";
        if (isset($previousMonth))
        {
                // $sSQL .= " WHERE previous month stuff";
        }
        elseif (isset($nextMonth))
        {
                 // $sSQL .= " WHERE next month stuff";
        }
        elseif (isset($showAll))
        {
                $sSQL .="";
        }
        else
        {
                //$sSQL .= " WHERE (TO_DAYS(event_start_date) - TO_DAYS(now()) < 30)";
                $sSQL .= " WHERE t1.event_type = t2.type_id".$eTypeSQL." AND MONTH(t1.event_start) = ".$mVal." AND YEAR(t1.event_start)=$EventYear";
        }
        $sSQL .= " ORDER BY t1.event_start ";

        $rsOpps = RunQuery($sSQL);
        $numRows = mysql_num_rows($rsOpps);
        $aAvgRows = $numRows;
        // Create arrays of the fundss.
        for ($row = 1; $row <= $numRows; $row++)
        {
                $aRow = mysql_fetch_array($rsOpps, MYSQL_BOTH);
                extract($aRow);

                $aEventID[$row] = $event_id;
                $aEventType[$row] = $event_typename;
                $aEventName[$row] = htmlentities(stripslashes($event_name));
                $aEventTitle[$row] = htmlentities(stripslashes($event_title));
                $aEventDesc[$row] = htmlentities(stripslashes($event_desc));
                $aEventText[$row] = htmlentities(stripslashes($event_text));
                $aEventStartDateTime[$row] = $event_start;
                $aEventEndDateTime[$row] = $event_end;
                $aEventStatus[$row] = $inactive;
                // get the list of attend-counts that exists in event_attend for this 
                $attendSQL="SELECT * FROM event_attend WHERE event_id=$event_id";
                $attOpps = RunQuery($attendSQL);
                if($attOpps) 
                  $attNumRows[$row] = mysql_num_rows($attOpps);
                else
                  $attNumRows[$row]=0;

        }

// Construct the form
?>
<table cellpadding="4" align="center" cellspacing="0" width="100%">

<?php
if ($numRows > 0)
{
?>
       <caption>
         <h3><?php echo gettext("There ".($numRows == 1 ? "is ".$numRows." event":"are ".$numRows." events")." for ".date("F", mktime(0, 0, 0, $mVal, 1, $currYear))); ?></h3>
       </caption>
         <tr class="TableHeader">
           <td width="15%"><strong><?php echo gettext("Event Type"); ?></strong></td>
           <td width="20%"><strong><?php echo gettext("Event Title"); ?><br></strong>
           <strong><?php echo gettext("Description"); ?></strong></td>
           <td width="35%" align="center"><strong><?php echo gettext("Attendance Counts"); ?></strong></td>
           <td width="10%" align="center"><strong><?php echo gettext("Start Date/Time"); ?></strong></td>
           <td width="5%" align="center"><strong><?php echo gettext("Active"); ?></strong></td>
           <td colspan="3" width="15%" align="center"><strong><?php echo gettext("Action"); ?></strong></td>
        </tr>
         <?php
         //Set the initial row color
         $sRowClass = "RowColorA";

         for ($row=1; $row <= $numRows; $row++)
         {

         //Alternate the row color
         $sRowClass = AlternateRowStyle($sRowClass);

         //Display the row
         ?>
         <tr class="<?php echo $sRowClass; ?>">
           <td><span class="SmallText"><?php echo $aEventType[$row]; ?></span></td>
           <td><span class="SmallText"><?php echo $aEventTitle[$row]; ?><br>
           <?php echo ($aEventDesc[$row] == '' ? "&nbsp;":$aEventDesc[$row]); ?>
             <?php echo ($aEventText[$row] != '' ? "&nbsp;&nbsp;&nbsp;<a href=\"javascript:popUp('GetText.php?EID=".$aEventID[$row]."')\"><strong>Sermon Text</strong></a>":""); ?></span>
           </td>
           <td>
              <table width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
           <?php 
// RETRIEVE THE list of counts associated with the current event 
//
                $cvSQL= "SELECT * FROM eventcounts_evtcnt WHERE evtcnt_eventid='$aEventID[$row]' ORDER BY evtcnt_countid ASC"; 
//        echo $cvSQL;
                $cvOpps = RunQuery($cvSQL);
                $aNumCounts = mysql_num_rows($cvOpps);
//        echo "numcounts = {$aNumCounts}\n\l";
                if($aNumCounts) {
                
                for($c = 0; $c <$aNumCounts; $c++){
                  $cRow = mysql_fetch_array($cvOpps, MYSQL_BOTH);
                  extract($cRow);
                  $cCountID[$c] = $evtcnt_countid;
                  $cCountName[$c] = $evtcnt_countname;     
                  $cCount[$c]= $evtcnt_countcount;
                  $cCountNotes = $evtcnt_notes; 
//                  $cCountSum[$c]+= $evtcnt_countcount;
                  ?>
                  <td align="center">
                  <span class="SmallText">
                    <strong><?php echo $evtcnt_countname; ?></strong>
                    <br><?php echo $evtcnt_countcount; ?></span>
                  </td> 
                  <?php         
                }
                } else {
                  ?>
                  <td align="center">
                    <span class="SmallText">
                    <strong><?php echo gettext("No Attendance Recorded"); ?></strong>
                    </span>
                  </td> 
                  <?php     
//                  $aAvgRows -=1;                                   
                }       
           ?>
           </tr>
           </table>
           </td>
           <td><span class="SmallText"><?php echo FormatDate($aEventStartDateTime[$row],1); ?></span></td>
           
           <td class="SmallText" align="center"><?php echo ($aEventStatus[$row] != 0 ? "No":"Yes"); ?></span></td>
           
          <td><span class="SmallText">
          <form name="EditAttendees" action="EditEventAttendees.php" method="POST">
          <input type="hidden" name="EID" value="<?php echo $aEventID[$row]; ?>">
          <input type="hidden" name="EName" value="<?php echo $aEventTitle[$row]; ?>">
          <input type="hidden" name="EDesc" value="<?php echo $aEventDesc[$row]; ?>">
          <input type="hidden" name="EDate" value="<?php echo FormatDate($aEventStartDateTime[$row],1); ?>">
          <input type="submit" name="Action" value="<?php echo gettext("Attendees(".$attNumRows[$row].")"); ?>" class="icButton" >
             </form></span>
           </td>           
           
           <td align="center"><span class="SmallText">
             <form name="EditEvent" action="EventEditor.php" method="POST">
               <input type="hidden" name="EID" value="<?php echo $aEventID[$row]; ?>">
               <input class="SmallText" type="submit" name="Action" <?php echo 'value="' . gettext("Edit") . '"'; ?> class="icButton">
             </form></span>
           </td>
           <td><span class="SmallText">
             <form name="DeleteEvent" action="ListEvents.php" method="POST">
               <input type="hidden" name="EID" value="<?php echo $aEventID[$row]; ?>">
               <input class="SmallText" type="submit" name="Action" value="<?php echo gettext("Delete"); ?>" class="icButton" onClick="return confirm('Deleting an event will also delete all attendance counts for that event.  Are you sure you want to DELETE Event ID: <?php echo  $aEventID[$row]; ?>')">
             </form></span>
          </td>

         </tr>
<?php
         } // end of for loop for # rows for this month
         
// calculate averages if this is a single type list
if ($eType != "All" && $aNumCounts >0){
    $avgSQL="SELECT evtcnt_countid, evtcnt_countname, AVG(evtcnt_countcount) from eventcounts_evtcnt, events_event WHERE eventcounts_evtcnt.evtcnt_eventid=events_event.event_id AND events_event.event_type='$eType' AND MONTH(events_event.event_start)='$mVal' GROUP BY eventcounts_evtcnt.evtcnt_countid ASC ";
    
    $avgOpps = RunQuery($avgSQL);
    $aAvgRows = mysql_num_rows($avgOpps);

    ?>
    <tr>
    <td class="LabelColumn" colspan="2"><?php echo gettext(" Monthly Averages"); ?></td>
    <td>
       <table width="100%" cellpadding="0" cellspacing="0" border="0">
       <tr>
      <?php 
      // calculate and report averages
      for($c = 0; $c <$aAvgRows; $c++){
        $avgRow = mysql_fetch_array($avgOpps, MYSQL_BOTH);
        extract($avgRow);
        $avgName = $avgRow['evtcnt_countname'];
        $avgAvg = $avgRow[2];
        ?>
        <td align="center">
          <span class="SmallText">
          <strong>AVG<br><?php echo $avgName;?></strong>
          <br><?php echo sprintf("%01.2f",$avgAvg); ?></span>
        </td> 
        <?php         
      }
      ?>
      </tr>
      </table>
      </td>
      <td class="TextColumn" colspan="3"></td>
      </tr>
<?php } 
?>
         <tr><td class="TextColumn" colspan="6">&nbsp;</td></tr>
<?php
    }
?>
      </table>
<?php
} // end for-each month loop
?>
             <table width="100%">
                <tr class="<?php echo $sRowClass; ?>">
                 <td align="center" valign="bottom">
                   <input type="button" Name="Action" <?php echo 'value="' . gettext("Add New Event") . '"'; ?> class="icButton" onclick="javascript:document.location='EventNames.php';">
                 </td>
               </tr>
             </table>
<?php
require "Include/Footer.php";
?>
