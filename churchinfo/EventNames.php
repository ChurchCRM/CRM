<?php
/*******************************************************************************
 *
 *  filename    : EventNames.php
 *  last change : 2005-09-10
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2005 Todd Pillars
 *
 *  function    : List all Church Events
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
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
require "Include/RenderFunctions.php";

if (!$_SESSION['bAdmin'] && !$_SESSION['bAddEvent'])
{
    header ("Location: Menu.php");
}

$sPageTitle = gettext("Edit Event Types");

require "Include/Header.php";

?>
<script language="javascript">

function confirmDeleteOpp( Opp ) {
var answer = confirm("<?= gettext("Are you sure you want to delete this event?") ?>");
if ( answer )
    window.location="EventEditor.php?Opp=" + Opp + "&Action=delete"
}
</script>

<?php
//
//  process the ACTION button inputs from the form page
//
$editing='FALSE';
$editID = 0;

if (isset ($_POST['Action'])) {
	switch (FilterInput($_POST['Action'])){
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
<div class="box">
  <div class="box-header">
    <?php if ($numRows > 0) { ?>
      <h3 class="box-title"><?= gettext("There currently ".($numRows == 1 ? "is ".$numRows." event":"are ".$numRows." custom event types")) ?></h3>
    <?php } ?>
  </div>

<table class="table">
<?php
if ($numRows > 0)
{
?>
     <tr class="TableHeader">
        <td align="center" width="70px"><strong><?= gettext("Event Type") ?></strong></td>
        <td align="left"><strong><?= gettext("Name") ?></strong></td>
        <td align="left" width="250px"><strong><?= gettext("Recurrance Pattern") ?></strong></td>
        <td align="left" width="100px"><strong><?= gettext("Start Time") ?></strong></td>
        <td align="left"><strong><?= gettext("Attendance Counts") ?></strong></td>
        <td align="center" colspan="3"><strong><?= gettext("Action") ?></strong></td>
      </tr>
<?php
      $sRowClass = "RowColorA";
      for ($row=1; $row <= $numRows; $row++)
      {
        $sRowClass = AlternateRowStyle($sRowClass);
?>
        <tr class="<?= $sRowClass ?>">
          <td class="TextColumn" align="center"><?= $aTypeID[$row] ?></td>

          <?php
          $t = 0;
          if (isset ($_POST["theID"]))
          	$t=FilterInput ($_POST["theID"], 'int');
          if($aTypeID[$row]==$editID)
          {
          ?>
              <form name="UpdateEventNames" action="EventNames.php" method="POST">
              <input type="hidden" name="theID" value="<?= $aTypeID[$row] ?>">
              <td class="TextColumn">
              <strong><?= gettext("EVENT TYPE NAME") ?></strong>
              <input class="SmallText form-control" type="text" name="newEvtName" value="<?= $aTypeName[$row] ?>" size="30" maxlength="35" autofocus></td>

              <td class="TextColumn">
              <strong><?= gettext("RECURRANCE PATTERN") ?></strong>
              <div class='row'>
                <div class='col-xs-12'>
                  <?php $render->Radio('None', 'newEvtTypeRecur', 'none'); ?>
                </div>
              </div>
              <div class='row'>
                <div class='col-xs-5'>
                  <?php $render->Radio('Weekly', 'newEvtTypeRecur', 'weekly'); ?>
                </div>
                <div class='col-xs-7'>
                  <select name="newEvtRecurDOW" size="1" class='form-control pull-left'>
                    <option class="SmallText" value=1><?= gettext("Sundays") ?></option>
                    <option class="SmallText" value=2><?= gettext("Mondays") ?></option>
                    <option class="SmallText" value=3><?= gettext("Tuesdays") ?></option>
                    <option class="SmallText" value=4><?= gettext("Wednesdays") ?></option>
                    <option class="SmallText" value=5><?= gettext("Thursdays") ?></option>
                    <option class="SmallText" value=6><?= gettext("Fridays") ?></option>
                    <option class="SmallText" value=7><?= gettext("Saturdays") ?></option>
                  </select>
                </div>
              </div>
              <div class='row'>
                <div class='col-xs-5'>
                  <?php $render->Radio('Monthly', 'newEvtTypeRecur', 'monthly'); ?>
                </div>
                <div class='col-xs-7'>
                  <select name="newEvtRecurDOM" size="1" class='form-control pull-left'>
                    <?php
                      for($kk=1; $kk<=31; $kk++)
                      {
                        $DOM = date('dS',mktime(0,0,0,1,$kk,2000));
                        ?>
                        <option class="SmallText" value=<?= $kk ?>><?= $DOM ?></option>
                        <?php
                      }
                    ?>
                   </select>
                 </div>
              </div>
              <div class='row'>
                <div class='col-xs-5'>
                  <?php $render->Radio('Yearly', 'newEvtTypeRecur', 'yearly'); ?>
                </div>
                <div class='col-xs-7'>
                  <input type="text" class="form-control" name="newEvtRecurDOY" maxlength="10" id="nSD" size="11" placeholder='YYYY-MM-DD' data-provide="datepicker" data-format='mm/dd/yyyy' />
                </div>
              </div>
              </input>
              </td>
              <td class="TextColumn">
                <strong><?= gettext("DEFAULT") ?><br><?= gettext("START TIME") ?></strong>
                <div>
                  <select class="SmallText form-control" name="newEvtStartTime" size="1">
                    <?php createTimeDropdown(7,22,15,'',''); ?>
                  </select>
                </div>
              </td>
              <td class="TextColumn" colspan="2">
                <strong><?= gettext("ATTENDANCE COUNTS") ?></strong>
                <div><?= gettext("Total,") ?><?= gettext("[Every event type includes a Total count]") ?></div>
                <input class="SmallText form-control" type="Text" name="newEvtTypeCntLst" value="<?= $cCountList[$row] ?>" Maxlength="50" id="nETCL" size="30">
                <div class="SmallText"><?php echo gettext("[enter a list of the attendance counts you want to include with this event. <br> Separate each count_name with a comma. e.g. Members, Visitors, Campus, Children]"); ?></div>
              </td>
              <td colspan="2" align="center" valign="bottom">
                <input type="submit" Name="Action" <?= 'value="' . gettext("Save Changes") . '"' ?> class="btn btn-primary">
              </td>
              </form>
              </tr>
              <?php
            } else {
              ?>
              <td class="TextColumn"><?= $aTypeName[$row] ?></td>
              <td class="TextColumn"><?= $recur[$row] ?></td>
              <td class="TextColumn"><?= $aDefStartTime[$row] ?></td>
              <td class="TextColumn"><?= $cCountList[$row] ?></td>
              <td class="TextColumn" align="center">
                <form name="ProcessEventType" action="EventEditor.php" method="POST" class="pull-left">
                  <input type="hidden" name="EN_tyid" value="<?= $aTypeID[$row] ?>">
                  <input type="submit" name="Action" value="<?= gettext("Create Event") ?>" class="btn btn-default btn-sm">
                </form>
              </td>
              <td class="TextColumn" align="center">
                <form name="ProcessEventType" action="EditEventTypes.php" method="POST" class="pull-left">
                  <input type="hidden" name="EN_tyid" value="<?= $aTypeID[$row] ?>">
                  <input type="submit" class="SmallText btn btn-default btn-sm" name="Action" value="<?= gettext("Edit") ?>">
                </form>
              </td>
              <td class="TextColumn" align="center">
                <form name="ProcessEventType" action="EventNames.php" method="POST" class="pull-left">
                  <input type="hidden" name="theID" value="<?= $aTypeID[$row] ?>">
                  <input type="submit" class="SmallText btn btn-default btn-sm" name="Action" value="<?= gettext("Delete") ?>" onClick="return confirm('Deleting this event TYPE will NOT delete any existing Events or Attendance Counts.  Are you sure you want to DELETE Event Type ID: <?=  $aTypeID[$row] ?>')">
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
    <input type="submit" Name="Action" value="<?= gettext("Add Event Type") ?>" class="btn btn-primary">
    </form>
    </td>
</tr>
<?php } // if $numRows > 0 ?>

</table>
</div>

<?php require "Include/Footer.php" ?>
