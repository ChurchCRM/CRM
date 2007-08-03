<?php
/*******************************************************************************
 *
 *  filename    : EventNames.php
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
 *
 *  Modified by Stephen Shaffer, Oct 2006
 *  feature changes - added recurring defaults and customizable attendance count 
 *  fields
 *       
 ******************************************************************************/

require "Include/Config.php";
require "Include/Functions.php";

if (!$_SESSION['bAdmin'] && !$_SESSION['bAddEvent'])
{
    header ("Location: Menu.php");
}

$sPageTitle = gettext("Edit Event Types");

require "Include/Header.php";

?>
<script language="javascript">

function confirmDeleteOpp( Opp ) {
var answer = confirm (<?php echo '"' . gettext("Are you sure you want to delete this event?") . '"'; ?>)
if ( answer )
        window.location="EventEditor.php?Opp=" + Opp + "&Action=delete"
}
</script>

<table width="100%" align="center" cellpadding="4" cellspacing="0">
  <tr>
    <td align="center"><input type="button" class="icButton" <?php echo 'value="' . gettext("Back to Menu") . '"'; ?> Name="Exit" onclick="javascript:document.location='Menu.php';"></td>
  </tr>
</table>
<?php
//
//  process the ACTION button inputs from the form page
//
$editing='FALSE';

switch ($_POST['Action']){
case gettext("Add Event Type"):
    // Insert into the event_name table
    $sSQL = "INSERT INTO event_types () VALUES()";
    RunQuery($sSQL);
    $theID = mysql_insert_id();
    $editID=$theID;  
    $editing='TRUE';
    $_POST['Action']='';
    break;

case gettext("Save Changes"):

  $editing='FALSE';
  $eName = $_POST["newEvtName"];
  $eTime = $_POST["newEvtStartTime"];
  $eDOM = $_POST["newEvtRecurDOM"];
  $eDOW = $_POST["newEvtRecurDOW"];
  $eDOY = $_POST["newEvtRecurDOY"];
  $eRecur=$_POST["newEvtTypeRecur"];
  $eCntLst = $_POST["newEvtTypeCntLst"];
  $eCntArray = explode(",",$eCntLst);
  $eCntArray[] = "Total";
  $eCntNum = count($eCntArray);
  $theID=$_POST["theID"];

  $sSQL = "UPDATE event_types SET type_name='$eName', type_defstarttime='$eTime',type_defrecurtype='$eRecur', type_defrecurDOW='$eDOW',type_defrecurDOM='$eDOM',type_defrecurDOY='$eDOY' WHERE type_id='$theID'";

  RunQuery($sSQL);
  
  for($j=0; $j<$eCntNum; $j++)
  {
    $cCnt = ltrim(rtrim($eCntArray[$j]));
    $sSQL = "INSERT eventcountnames_evctnm (evctnm_eventtypeid, evctnm_countname) VALUES ('$theID','$cCnt') ON DUPLICATE KEY UPDATE evctnm_countname='$cCnt'";
    RunQuery($sSQL);
  }
  $editID='';
  $theID='';
  $_POST['Action']='';
  break;
//  
//case "Edit": 
//  $theID = $_POST["theID"];
//  break;
//
case gettext("Delete"):
  $theID = $_POST["theID"];
  $sSQL = "DELETE FROM event_types WHERE type_id='$theID' LIMIT 1";
//  echo "$sSQL";
  RunQuery($sSQL);
  $sSQL = "DELETE FROM eventcountnames_evctnm WHERE evctnm_eventtypeid='$theID'";
//  echo "$sSQL";
  RunQuery($sSQL);
  $theID='';
  $editID='';
  $editing='FALSE';
  $_POST['Action']='';
  break;
}


// Get data for the form as it now exists.

$sSQL = "SELECT * FROM event_types ORDER BY type_id";
$rsOpps = RunQuery($sSQL);
$numRows = mysql_num_rows($rsOpps);

        // Create arrays of the event types
        for ($row = 1; $row <= $numRows; $row++)
        {
                $aRow = mysql_fetch_array($rsOpps, MYSQL_BOTH);
                extract($aRow);

                $aTypeID[$row] = $type_id;
                $aTypeName[$row] = $type_name;             
                $aDefStartTime[$row] = $type_defstarttime;
                $aDefRecurDOW[$row] = $type_defrecurDOW;
                $aDefRecurDOM[$row] = $type_defrecurDOM;
                $aDefRecurDOY[$row] = $type_defrecurDOY;
                $aDefRecurType[$row] = $type_defrecurtype;
//                echo "$row:::DOW = $aDefRecurDOW[$row], DOM=$aDefRecurDOM[$row], DOY=$adefRecurDOY[$row] type=$aDefRecurType[$row]\n\r\n<br>";
                
                switch ($aDefRecurType[$row]){
                  case "none": 
                    $recur[$row]="None";
                    break;
                  case "weekly": 
                    $recur[$row]="Weekly on ".$aDefRecurDOW[$row];
                    break;
                  case "monthly": 
                    $recur[$row]="Monthly on ".date('dS',mktime(0,0,0,1,$aDefRecurDOM[$row],2000));
                    break;
                  case "yearly": 
                    $recur[$row]="Yearly on ".substr($aDefRecurDOY[$row],5);
                    break;
                  default: 
                    $recur[$row]="None";
                }
                // recur types = 1-DOW for weekly, 2-DOM for monthly, 3-DOY for yearly.  
                // repeats on DOW, DOM or DOY 
                //
                // new - check the count definintions table for a list of count fields
                $cSQL = "SELECT evctnm_countid, evctnm_countname FROM eventcountnames_evctnm WHERE evctnm_eventtypeid='$aTypeID[$row]' ORDER BY evctnm_countid";
                $cOpps = RunQuery($cSQL);
                $numCounts = mysql_num_rows($cOpps);
                $cCountName="";
                if($numCounts)
                {
                  $cCountName="";
                  for($c = 1; $c <=$numCounts; $c++){
                    $cRow = mysql_fetch_array($cOpps, MYSQL_BOTH);
                    extract($cRow);
                    $cCountID[$c] = $evctnm_countid;
                    $cCountName[$c] = $evctnm_countname;                
                  }
                  $cCountList[$row] = implode(", ",$cCountName);
                 }
                 else
                 {
                  $cCountList[$row]="";
                 }                 
        }

// Construct the form
?>
<table width="100%" align="center" cellpadding="2" cellspacing="0">
<?php
if ($numRows > 0)
{
?>
  <caption>
    <h3><?php echo gettext("There currently ".($numRows == 1 ? "is ".$numRows." event":"are ".$numRows." custom event types")); ?></h3>
  </caption>
     <tr class="TableHeader">
        <td align="center" width="5%"><strong><?php echo gettext("Event Type"); ?></strong></td>
        <td align="left" width="23%"><strong><?php echo gettext("Name"); ?></strong></td>
        <td align="left" width="23%"><strong><?php echo gettext("Recurrance Pattern"); ?></strong></td>
        <td align="left" width="12%"><strong><?php echo gettext("Start Time"); ?></strong></td>
        <td align="left" width="20%"><strong><?php echo gettext("Attendance Counts"); ?></strong></td>
        <td align="center" colspan=3 width="17%"><strong><?php echo gettext("Action"); ?></strong></td>
      </tr>
<?php
      $sRowClass = "RowColorA";
      for ($row=1; $row <= $numRows; $row++)
      {
        $sRowClass = AlternateRowStyle($sRowClass);
?>
        <tr class="<?php echo $sRowClass; ?>">
          <td class="TextColumn" align="center"><?php echo $aTypeID[$row]; ?></td>
          
          <?php
          $t=$_POST["theID"];
          if($aTypeID[$row]==$editID)
          {
          ?>
              <form name="UpdateEventNames" action="EventNames.php" method="POST">
              <input type="hidden" name="theID" value="<?php echo $aTypeID[$row]; ?>">
              <td class="TextColumn">
              <strong><?php echo gettext("EVENT TYPE NAME"); ?></strong><br>
              <input class="SmallText" type="text" name="newEvtName" value="<?php echo $aTypeName[$row]; ?>" size="30" maxlength="35"></td> 
              <script language="javascript"> 
                document.UpdateEventNames.newEvtName.focus() 
              </script>
    
              <td class="TextColumn">
              <strong><?php echo gettext("RECURRANCE PATTERN"); ?></strong><br>
              <input class="SmallText" type="radio" name="newEvtTypeRecur" value=none>None</input><br><hr>
              <input class="SmallText" type="radio" name="newEvtTypeRecur" value=weekly>Weekly on <select name="newEvtRecurDOW" size="1">
                  <option class="SmallText" value=1><?php echo gettext("Sundays"); ?></option>
                  <option class="SmallText" value=2><?php echo gettext("Mondays"); ?></option>    
                  <option class="SmallText" value=3><?php echo gettext("Tuesdays"); ?></option>
                  <option class="SmallText" value=4><?php echo gettext("Wednesdays"); ?></option>    
                  <option class="SmallText" value=5><?php echo gettext("Thursdays"); ?></option>    
                  <option class="SmallText" value=6><?php echo gettext("Fridays"); ?></option> 
                  <option class="SmallText" value=7><?php echo gettext("Saturdays"); ?></option>  
                 </select>                                                                                                   
              </input><br><hr>
              <input class="SmallText" type="radio" name="newEvtTypeRecur" value=monthly>Monthly on 
                <select name="newEvtRecurDOM" size="1">
                  <option class="SmallText" value=0 selected>None</option>
                  <?php  
                    for($kk=1; $kk<=31; $kk++)
                    {
                      $DOM = date('dS',mktime(0,0,0,1,$kk,2000));
                      ?>
                      <option class="SmallText" value=<?php echo $kk; ?>><?php echo $DOM; ?></option>
                      <?php
                    }
                  ?>
                 </select>                                                                                                   
              </input><br><hr>
              <input class="SmallText" type="radio" name="newEvtTypeRecur" value=yearly>Yearly on 
              <input class="SmallText" type="text" name="newEvtRecurDOY" value="<?php echo $aDefRecurDOY[$row]; ?>" maxlength="10" id="nSD" size="11">&nbsp;
              <input class="SmallText" type="image" onclick="return showCalendar('nSD', 'y-mm-dd');" src="Images/calendar.gif">
              <span class="SmallText"><br><?php echo gettext("[format: YYYY-MM-DD]"); ?></span></td>                                                           
              </input>
              </td>
              <td class="TextColumn"> 
              <strong><?php echo gettext("DEFAULT"); ?><br><?php echo gettext("START TIME"); ?></strong><br>                             
              <select class="SmallText" name="newEvtStartTime" size="1">
                <?php createTimeDropdown(7,22,15,'',''); ?>
              </select>&nbsp;
              <span class="SmallText"><?php echo gettext("[format: HH:MM]"); ?></span>
              </td>              
              <td class="TextColumn">
              <strong><?php echo gettext("ATTENDANCE COUNTS"); ?></strong><br>
              <?php echo gettext("Total,"); ?><span class="SmallText"><?php echo gettext("[Every event type includes a Total count]"); ?></span>
              <input class="SmallText" type="Text" name="newEvtTypeCntLst" value="<?php echo $cCountList[$row]; ?>" Maxlength="50" id="nETCL" size="30"><br><span class="SmallText"><?php echo gettext("[enter a list of the attendance counts you want to include with this event. <br> Separate each count_name with a comma. e.g. Members, Visitors, Campus, Children]"); ?></span</td>
              <td colspan="2" align="center" valign="bottom">
              <input type="submit" Name="Action" <?php echo 'value="' . gettext("Save Changes") . '"'; ?> class="icButton">
              </td>
              </form>
              </tr>
              <?php 
            } else {
              ?>
              <td class="TextColumn"><?php echo $aTypeName[$row]; ?></td>
              <td class="TextColumn"><?php echo $recur[$row] ?></td>
              <td class="TextColumn"><?php echo $aDefStartTime[$row]; ?></td>
              <td class="TextColumn"><?php echo $cCountList[$row] ?></td>
              <td class="TextColumn" align="center">
                  <form name="ProcessEventType" action="EventEditor.php" method="POST">
                  <input type="hidden" name="EN_tyid" value="<?php echo $aTypeID[$row]; ?>">
                  <input type="submit" name="Action" value="<?php echo gettext("Create=>Event"); ?>" class="icButton")">
                </form> 
              </td>
              <td class="TextColumn" align="center">
                  <form name="ProcessEventType" action="EditEventTypes.php" method="POST">
                  <input type="hidden" name="EN_tyid" value="<?php echo $aTypeID[$row]; ?>">
                  <input type="submit" class="SmallText" name="Action" value="<?php echo gettext("Edit"); ?>" class="icButton")">
                </form> 
              </td>
              <td class="TextColumn" align="center">
                <form name="ProcessEventType" action="EventNames.php" method="POST">
                  <input type="hidden" name="theID" value="<?php echo $aTypeID[$row]; ?>">
                  <input type="submit" class="SmallText" name="Action" value="<?php echo gettext("Delete"); ?>" class="icButton" onClick="return confirm('Deleting this event TYPE will NOT delete any existing Events or Attendance Counts.  Are you sure you want to DELETE Event Type ID: <?php echo  $aTypeID[$row]; ?>')">
                </form>
              </td>
              </tr>
              <?php
            }
            
        }
}
if($editing=='FALSE'){
?>
<tr align="center">
    <td colspan=8 class="TextColumn" align="center">
    <form name="AddEventNames" action="EventNames.php" method="POST">
    <span class="SmallText"><?php echo gettext("New Event Type"); ?></span>
    <input type="submit" Name="Action" <?php echo 'value="' . gettext("Add Event Type") . '"'; ?> class="icButton">
    </form>
    </td>
</tr>
</table>
<?php
} else {
?>
</table>
<?php
}
require "Include/Footer.php";
?>
