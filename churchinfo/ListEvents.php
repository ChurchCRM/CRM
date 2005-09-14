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

$sPageTitle = gettext("All Church Events");

require "Include/Header.php";

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

?>
<table cellpadding="4" align="center" cellspacing="0" width="100%">
  <tr>
    <td align="center"><input type="button" class="icButton" <?php echo 'value="' . gettext("Back to Menu") . '"'; ?> Name="Exit" onclick="javascript:document.location='Menu.php';"></td>
  </tr>
</table>
<?
// Get data for the form as it now exists..
// for this year
$currYear = date("Y");
$currMonth = date("m");
$allMonths = array("1","2","3","4","5","6","7","8","9","10","11","12");
foreach ($allMonths as $mKey => $mVal) {
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
                //$sSQL .= " WHERE (TO_DAYS(event_start_date) - TO_DAYS(now()) < 30)";
                $sSQL .= " WHERE t1.event_type = t2.type_id AND MONTH(t1.event_start) = ".$mVal;
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
                $aEventName[$row] = htmlentities(stripslashes($event_name));
                $aEventTitle[$row] = htmlentities(stripslashes($event_title));
                $aEventDesc[$row] = htmlentities(stripslashes($event_desc));
                $aEventText[$row] = htmlentities(stripslashes($event_text));
                $aEventStartDateTime[$row] = $event_start;
                $aEventEndDateTime[$row] = $event_end;
                $aEventStatus[$row] = $inactive;
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
           <td width="10%"><strong><?php echo gettext("Event Type"); ?></strong></td>
           <td width="25%"><strong><?php echo gettext("Event Title"); ?></strong></td>
           <td width="*"><strong><?php echo gettext("Description"); ?></strong></td>
           <td width="10%" align="center"><strong><?php echo gettext("Start Date/Time"); ?></strong></td>
           <td width="5%" align="center"><strong><?php echo gettext("Active"); ?></strong></td>
           <td colspan="2" width="15%" align="center"><strong><?php echo gettext("Action"); ?></strong></td>
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
           <td class="TextColumn"><? echo $aEventType[$row]; ?></td>
           <td class="TextColumn"><?php echo $aEventTitle[$row]; ?></td>
           <td class="TextColumn"><?php echo ($aEventDesc[$row] == '' ? "&nbsp;":$aEventDesc[$row]); ?>
             <?php echo ($aEventText[$row] != '' ? "&nbsp;&nbsp;&nbsp;<a href=\"javascript:popUp('GetText.php?EID=".$aEventID[$row]."')\"><strong>text</strong></a>":""); ?>
           </td>
           <td class="TextColumn"><?php echo FormatDate($aEventStartDateTime[$row],1); ?></td>
           <td class="TextColumn" align="center"><?php echo ($aEventStatus[$row] != 0 ? "No":"Yes"); ?></td>
           <td class="TextColumn" align="center">
             <form name="EditEvent" action="EventEditor.php" method="POST">
               <input type="hidden" name="EID" value="<?php echo $aEventID[$row]; ?>">
               <input type="submit" name="Action" <?php echo 'value="' . gettext("Edit") . '"'; ?> class="icButton">
             </form>
           </td>
           <td class="TextColumn">
             <form name="DeactivateEvent" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
               <input type="hidden" name="EID" value="<?php echo $aEventID[$row]; ?>">
               <?php if ($aEventStatus[$row] == 0 ) { ?>
               <input type="submit" name="Action" value="<?php echo gettext("Deactivate"); ?>" class="icButton" onClick="return confirm('Are you sure you want to DEACTIVATE Event ID: <?php echo  $aEventID[$row]; ?>')">
                <?php } else { ?>
               <input type="submit" name="Action" value="<?php echo gettext("Activate"); ?>" class="icButton">
                <?php } ?>
             </form>
           </td>
         </tr>
<?php
         }
?>
         <tr><td colspan="5">&nbsp;</td></tr>
<?
    }
?>
      </table>
<?php
}
?>
             <table width="100%">
               <tr>
                 <td align="center" valign="bottom">
                   <input type="button" Name="Action" <?php echo 'value="' . gettext("Add New Event") . '"'; ?> class="icButton" onclick="javascript:document.location='AddEvent.php';">
                 </td>
               </tr>
             </table>
<?php
require "Include/Footer.php";
?>
