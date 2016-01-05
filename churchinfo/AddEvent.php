<?php
/*******************************************************************************
 *
 *  filename    : AddEvent.php
 *  last change : 2005-09-08
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2005 Todd Pillars
 *
 *  function    : Church Event Additions
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
//  event_txt      text
//  event_date     datetime

require "Include/Config.php";
require "Include/Functions.php";

$sAction = $_GET["Action"];
$sOpp = FilterInput($_GET["Opp"],'int');

$sDeleteError = "";

if ($_POST['Action']== "Deactivate" && !empty($_POST['EID']))
{
    $sSQL = "UPDATE events_event SET inactive = 1 WHERE event_id = ".$_POST['EID']." LIMIT 1";
    RunQuery($sSQL);
}
elseif ($_POST['Action']== "Activate" && !empty($_POST['EID']))
{
    $sSQL = "UPDATE events_event SET inactive = 0 WHERE event_id = ".$_POST['EID']." LIMIT 1";
    RunQuery($sSQL);
}

$sPageTitle = gettext("Add Church Event(s)");

require "Include/Header.php";
?>
<link rel="stylesheet" type="text/css" href="<?= $sURLPath; ?>/vendor/AdminLTE/plugins/datatables/dataTables.bootstrap.css">
<script type="text/javascript" language="javascript" src="<?= $sURLPath; ?>/vendor/AdminLTE/plugins/datatables/jquery.dataTables.min.js"></script>
<script type="text/javascript" language="javascript" src="<?= $sURLPath; ?>/vendor/AdminLTE/plugins/datatables/dataTables.bootstrap.js"></script>

<?php

// Check if we're adding an event
if (isset($_POST["AddEvent"]))
{
    $newEventType = $_POST['newEventType'];
    $newEventTitle = FilterInput($_POST['newEventTitle']);
    $newEventDesc = FilterInput($_POST['newEventDesc']);
    $newEventText = FilterInput($_POST['newEventText']);
    $newEventStart = $_POST['newEventStartDate']." ".$_POST['newEventStartTime'];
    $newEventEnd = $_POST['newEventEndDate']." ".$_POST['newEventEndTime'];
    $newEventStatus = $_POST['newEventStatus'];

    // Insert into the funds table
    $sSQL = "INSERT INTO `events_event`
             (`event_id` , `event_type` , `event_title`, `event_desc`, `event_text`, `event_start`, `event_end`, `inactive`)
             VALUES
             ('', '".$newEventType."', '".$newEventTitle."', '".$newEventDesc."', '".$newEventText."', '".$newEventStart."', '".$newEventEnd."', '".$newEventStatus."');";
    RunQuery($sSQL);

    // $bNewNameError = false;
}
//
        // Get data for the form as it now exists..
        // for this month
        $currMonth = date("m");
        $sSQL = "SELECT * FROM events_event as t1, event_types as t2";
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
                //$sSQL .= " WHERE (TO_DAYS(event_start) - TO_DAYS(now()) < 30)";
                $sSQL .= " WHERE t1.event_type = t2.type_id AND MONTH(event_start) = ".$currMonth;
        }
        $sSQL .= " ORDER BY event_start";

        $rsOpps = RunQuery($sSQL);
        $numRows = mysql_num_rows($rsOpps);

        // Create arrays of the fundss.
        for ($row = 1; $row <= $numRows; $row++)
        {
                $aRow = mysql_fetch_array($rsOpps, MYSQL_BOTH);
                extract($aRow);

                $aEventID[$row] = $event_id;
                $aEventType[$row] = $type_name;
                $aEventTitle[$row] = $event_title;
                $aEventDesc[$row] = $event_desc;
                $aEventText[$row] = $event_text;
                $aEventStart[$row] = $event_start;
                $aEventEnd[$row] = $event_end;
                $aEventStatus[$row] = $inactive;
        }

// Construct the form
?>
<div class="box">
	<div class="box-header">
		<h3 class="box-title">
       <?php if ($numRows == 0)  { 
            echo gettext("No church events for ".date("F")); 
        } else {
            echo gettext("There ".($numRows == 1 ? "is ".$numRows." event":"are ".$numRows." events")." for ".date("F")); 
        ?></h3>
	</div><!-- /.box-header -->
	<div class="box-body table-responsive">
		<table  class="table table-striped table-bordered dataTable no-footer" id="eventsTable">

         <thead>
         <tr>
           <th><?php echo gettext("Event Type"); ?></td>
           <th><?php echo gettext("Event Title"); ?></td>
           <th><?php echo gettext("Description"); ?></td>
           <th><?php echo gettext("Start Date/Time"); ?></td>
           <th><?php echo gettext("Active"); ?></td>
           <th><?php echo gettext("Action"); ?></td>
          </tr>
        </thead>
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
           <td><?php echo $aEventType[$row]; ?></td>
           <td><?php echo htmlentities(stripslashes($aEventTitle[$row]),ENT_NOQUOTES, "UTF-8"); ?></td>
           <td><?php echo ($aEventDesc[$row] == '' ? "&nbsp;":$aEventDesc[$row]); ?>
             <?php echo ($aEventText[$row] != '' ? "&nbsp;&nbsp;&nbsp;<a href=\"javascript:popUp('GetText.php?EID=".$aEventID[$row]."')\"><strong>text</strong></a>":""); ?></td>
           <td><?php echo FormatDate($aEventStart[$row],1); ?></td>
           <td align="center"><?php echo ($aEventStatus[$row] != 0 ? "No":"Yes"); ?></td>
           <td align="center">
             <form name="EditEvent" action="EventEditor.php" method="POST">
               <input type="hidden" name="EID" value="<?php echo $aEventID[$row]; ?>">
               <input type="submit" name="Action" <?php echo 'value="' . gettext("Edit") . '"'; ?> class="icButton">
             </form>
           </td>
           <td>
             <form name="DeactivateEvent" action="AddEvent.php" method="POST">
               <input type="hidden" name="EID" value="<?php echo $aEventID[$row]; ?>">
               <?php if ($aEventStatus[$row] == 0 ) { ?>
               <input type="submit" name="Action" value="<?php echo gettext("Deactivate"); ?>" class="icButton" onClick="return confirm('Are you sure you want to DEACTIVATE Event ID: <?php echo  $aEventID[$row]; ?>')">
                <?php } else { ?>
               <input type="submit" name="Action" value="<?php echo gettext("Activate"); ?>" class="icButton">
                <?php } ?>
             </form>
           </td>
         </tr>
         <?php } ?>
         
          </table>
        </div>
    </div>
   <div class="box">
	<div class="box-header">
		<h3 class="box-title">Create a New Event</h3>
    </div>
    <div class="box-body table-responsive">
    <table id="createEventTable">
<?php } ?>
                <tr><td colspan="5"><hr></td></tr>
                <tr>
                  <td colspan="5">
                      <form method="post" action="AddEvent.php" name="AddEvent">
                        <table width="70%" align="center">
                          <tr>
                            <td class="LabelColumn"><?php echo gettext("Event Type:"); ?></td>
                            <td colspan="3">
                              <select name="newEventType">
<?php
// Get Event Names
$sSQL = "SELECT * FROM `event_types`";

        $rsOpps = RunQuery($sSQL);
        $numRows = mysql_num_rows($rsOpps);

        // Create arrays of the events.
        for ($row = 1; $row <= $numRows; $row++)
        {
                $aRow = mysql_fetch_array($rsOpps, MYSQL_BOTH);
                extract($aRow);

                echo '<option value="'.$type_id.'">'.$type_name.'</option>';
        }
?>
                              </select>
                            </td>
                          </tr>
                          <tr>
                            <td class="LabelColumn"><?php echo gettext("Event Title:"); ?></td>
                            <td colspan="3">
                              <input type="text" name="newEventTitle" size="40" maxlength="100">
                              <?php if ( $bNewTitleError ) echo "<div><span style=\"color: red;\"><BR>" . gettext("You must enter a name.") . "</span></div>"; ?>
                            </td>
                          </tr>
                          <tr>
                            <td class="LabelColumn"><?php echo gettext("Event Desc:"); ?></td>
                            <td colspan="3">
                              <input type="text" name="newEventDesc" size="40" maxlength="100">
                              <?php if ( $bNewDescError ) echo "<div><span style=\"color: red;\"><BR>" . gettext("You must enter a name.") . "</span></div>"; ?>
                            </td>
                          </tr>
                          <tr>
                            <td class="LabelColumn"><?php echo gettext("Event Sermon:"); ?></td>
                            <td colspan="3"><textarea name="newEventText" rows="10" cols="80"></textarea></td>
                          </tr>
                          <tr>
                            <td class="LabelColumn" <?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?>>
                              <?php echo gettext("Start Date:"); ?>
                            </td>
                            <td>
                             <div class="form-group">
                                <label>Start Date</label>

                                <div class="input-group">
                                  <div class="input-group-addon">
                                    <i class="fa fa-calendar"></i>
                                  </div>
                                  <input type="text" class="form-control pull-right active" id="newEventStartDate" name="newEventStartDate">
                                </div>
                            </div>
                            </td>
                            <td class="LabelColumn">
                              <?php echo gettext("Start Time:"); ?>
                            </td>
                            <td>
                            <div class="form-group">
                                <label>Start Time</label>

                                <div class="input-group bootstrap-timepicker timepicker">
                                    <input name="newEventStartTime" id="newEventStartTime" type="text" class="form-control input-small">
                                    <span class="input-group-addon"><i class="glyphicon glyphicon-time"></i></span>
                                </div>
                            </div>
                            </td>
                          </tr>
                          <tr>
                            <td class="LabelColumn" <?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?>>
                              <?php echo gettext("End Date:"); ?>
                            </td>
                            <td>
                                 <div class="form-group">
                                <label>Start Date</label>

                                <div class="input-group">
                                  <div class="input-group-addon">
                                    <i class="fa fa-calendar"></i>
                                  </div>
                                  <input type="text" class="form-control pull-right active" id="newEventEndDate" name="newEventEndDate">
                                </div>
                            </div>
                            </td>
                            <td class="LabelColumn">
                              <?php echo gettext("End Time:"); ?>
                            </td>
                            <td>
                                <div class="form-group">
                                <label>Start Time</label>

                                <div class="input-group bootstrap-timepicker timepicker">
                                    <input name="newEventEndTime" id="newEventEndTime" type="text" class="form-control input-small">
                                    <span class="input-group-addon"><i class="glyphicon glyphicon-time"></i></span>
                                </div>
                                </div>
                            </td>
                          </tr>
                          <tr>
                            <td class="LabelColumn"><?php echo gettext("Event Status:"); ?></td>
                            <td colspan="3">
                              <input type="radio" name="newEventStatus" value="0" checked> Active <input type="radio" name="newEventStatus" value="1"> Inactive
                              <?php if ( $bNewStatusError ) echo "<div><span style=\"color: red;\"><BR>" . gettext("Is this Active or Inactive?") . "</span></div>"; ?>
                            </td>
                          </tr>
                          <tr>
                            <td colspan="4" align="center"><input type="submit" Name="AddEvent" <?php echo 'value="' . gettext("Add Event") . '"'; ?> class="icButton"></td>
                          </tr>
                        </table>
                        </form>
                      </td>
                </tr>
            </table>
    </div>
 </div>

       
       

<script>
$("#newEventStartDate").datepicker({format:'yyyy-mm-dd'});
$("#newEventEndDate").datepicker({format:'yyyy-mm-dd'});
$('#newEventStartTime').timepicker({showMeridian: false});
$("#newEventEndTime").timepicker({showMeridian: false});
$("#eventsTable").dataTable();
</script>
<?php require "Include/Footer.php"; ?>
