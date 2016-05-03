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

//
//  process the ACTION button inputs from the form page
//

if (isset ($_POST['Action'])) {
	switch (FilterInput($_POST['Action'])){
    case "CREATE":
    // Insert into the event_name table
	  $eName = $_POST["newEvtName"];
	  $eTime = $_POST["newEvtStartTime"];
	  $eDOM = $_POST["newEvtRecurDOM"];
	  $eDOW = $_POST["newEvtRecurDOW"];
	  $eDOY = $_POST["newEvtRecurDOY"];
	  $eRecur=$_POST["newEvtTypeRecur"];
	  $eCntLst = $_POST["newEvtTypeCntLst"];
	  $eCntArray = array_filter(array_map('trim', explode(",", $eCntLst)));
	  $eCntArray[] = "Total";
	  $eCntNum = count($eCntArray);
	  $theID=$_POST["theID"];

	  $sSQL = "INSERT INTO event_types (type_name, type_defstarttime, type_defrecurtype, type_defrecurDOW, type_defrecurDOM, type_defrecurDOY)
             VALUES ('" . FilterInput($eName)  . "',
                     '" . FilterInput($eTime)  . "',
                     '" . FilterInput($eRecur) . "',
                     '" . FilterInput($eDOW)   . "',
                     '" . FilterInput($eDOM)   . "',
                     '" . FilterInput($eDOY)   . "')";

	  RunQuery($sSQL);
    $theID = mysql_insert_id();

	  for($j=0; $j<$eCntNum; $j++)
	  {
	    $cCnt = ltrim(rtrim($eCntArray[$j]));
	    $sSQL = "INSERT eventcountnames_evctnm (evctnm_eventtypeid, evctnm_countname) VALUES ('".FilterInput($theID)."','".FilterInput($cCnt)."') ON DUPLICATE KEY UPDATE evctnm_countname='$cCnt'";
	    RunQuery($sSQL);
	  }
    Redirect("EventNames.php"); // clear POST
    break;

	case "DELETE":
	  $theID = $_POST["theID"];
	  $sSQL = "DELETE FROM event_types WHERE type_id='" . FilterInput($theID) . "' LIMIT 1";
	  RunQuery($sSQL);
	  $sSQL = "DELETE FROM eventcountnames_evctnm WHERE evctnm_eventtypeid='" . FilterInput($theID) . "'";
	  RunQuery($sSQL);
	  $theID='';
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

if (FilterInput($_POST["Action"]) == "NEW")
{
  ?>
  <div class='box box-primary'>
    <div class='box-body'>
      <form name="UpdateEventNames" action="EventNames.php" method="POST" class='form-horizontal'>
        <input type="hidden" name="theID" value="<?= $aTypeID[$row] ?>">
        <div class='row form-group'>
          <div class='col-sm-4 control-label text-bold'>
            <?= gettext("EVENT TYPE NAME") ?>
          </div>
          <div class='col-sm-6'>
            <input class="form-control" type="text" name="newEvtName" value="<?= $aTypeName[$row] ?>" size="30" maxlength="35" autofocus>
          </div>
        </div>
        <div class='row form-group'>
          <div class='col-sm-4 control-label text-bold'>
            <?= gettext("RECURRANCE PATTERN") ?>
          </div>
          <div class='col-sm-6 event-recurrance-patterns'>
            <div class='row form-radio-list'>
              <div class='col-xs-12'>
                <?php $render->Radio('None', 'newEvtTypeRecur', 'none', true); ?>
              </div>
            </div>
            <div class='row form-radio-list'>
              <div class='col-xs-5'>
                <?php $render->Radio('Weekly', 'newEvtTypeRecur', 'weekly'); ?>
              </div>
              <div class='col-xs-7'>
                <select name="newEvtRecurDOW" size="1" class='form-control pull-left' disabled>
                  <option value=1><?= gettext("Sundays") ?></option>
                  <option value=2><?= gettext("Mondays") ?></option>
                  <option value=3><?= gettext("Tuesdays") ?></option>
                  <option value=4><?= gettext("Wednesdays") ?></option>
                  <option value=5><?= gettext("Thursdays") ?></option>
                  <option value=6><?= gettext("Fridays") ?></option>
                  <option value=7><?= gettext("Saturdays") ?></option>
                </select>
              </div>
            </div>
            <div class='row form-radio-list'>
              <div class='col-xs-5'>
                <?php $render->Radio('Monthly', 'newEvtTypeRecur', 'monthly'); ?>
              </div>
              <div class='col-xs-7'>
                <select name="newEvtRecurDOM" size="1" class='form-control pull-left' disabled>
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
            <div class='row form-radio-list'>
              <div class='col-xs-5'>
                <?php $render->Radio('Yearly', 'newEvtTypeRecur', 'yearly'); ?>
              </div>
              <div class='col-xs-7'>
                <input type="text" disabled class="form-control" name="newEvtRecurDOY" maxlength="10" id="nSD" size="11" placeholder='YYYY-MM-DD' data-provide="datepicker" data-format='mm/dd/yyyy' />
              </div>
            </div>
          </div>
        </div>
        <div class='row form-group'>
          <div class='col-sm-4 control-label text-bold'>
            <?= gettext("DEFAULT START TIME") ?>
          </div>
          <div class='col-sm-6'>
            <select class="form-control" name="newEvtStartTime">
              <?php createTimeDropdown(7,22,15,'',''); ?>
            </select>
          </div>
        </div>
        <div class='row form-group'>
          <div class='col-sm-4 control-label text-bold'>
            <?= gettext("ATTENDANCE COUNTS") ?>
          </div>
          <div class='col-sm-6'>
            <input class="form-control" type="Text" name="newEvtTypeCntLst" value="<?= $cCountList[$row] ?>" Maxlength="50" id="nETCL" size="30" placeholder="<?= gettext('Optional') ?>">
            <div class='text-sm'><?= gettext("Enter a list of the attendance counts you want to include with this event.")?></div>
            <div class='text-sm'><?= gettext("Separate each count_name with a comma. e.g. Members, Visitors, Campus, Children"); ?></div>
            <div class='text-sm'><?= gettext("Every event type includes a Total count, you do not need to include it.") ?></div>
          </div>
        </div>
        <div class='row form-group'>
          <div class='col-sm-8 col-sm-offset-4'>
            <a href="EventNames.php" class='btn btn-default'>
              <?= gettext('Cancel') ?>
            </a>
            <button type="submit" Name="Action" value="CREATE" class="btn btn-primary">
              <?= gettext("Save Changes") ?>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <?php
}

// Construct the form
?>
<div class="box">
  <div class="box-header">
    <?php if ($numRows > 0) { ?>
      <h3 class="box-title"><?= gettext("There currently ".($numRows == 1 ? "is ".$numRows." event":"are ".$numRows." custom event types")) ?></h3>
    <?php } ?>
  </div>

  <div class='box-body'>
    <?php
    if ($numRows > 0)
    {
      ?>
      <table class="table table-striped table-bordered data-table">
        <thead>
         <tr>
            <th><?= gettext("Event Type") ?></th>
            <th><?= gettext("Name") ?></th>
            <th><?= gettext("Recurrance Pattern") ?></th>
            <th><?= gettext("Start Time") ?></th>
            <th><?= gettext("Attendance Counts") ?></th>
            <th><?= gettext("Action") ?></th>
          </tr>
        </thead>
        <tbody>
          <?php
          for ($row=1; $row <= $numRows; $row++)
          {
            ?>
            <tr>
              <td><?= $aTypeID[$row] ?></td>
              <td><?= $aTypeName[$row] ?></td>
              <td><?= $recur[$row] ?></td>
              <td><?= $aDefStartTime[$row] ?></td>
              <td><?= $cCountList[$row] ?></td>
              <td>
                <table class='table-simple-padding'>
                  <tr>
                    <td>
                      <form name="ProcessEventType" action="EventEditor.php" method="POST" class="pull-left">
                        <input type="hidden" name="EN_tyid" value="<?= $aTypeID[$row] ?>">
                        <button type="submit" name="Action" value="<?= gettext("Create Event") ?>" class="btn btn-default btn-sm">
                          <?= gettext("Create Event") ?>
                        </button>
                      </form>
                    </td>
                    <td>
                      <form name="ProcessEventType" action="EditEventTypes.php" method="POST" class="pull-left">
                        <input type="hidden" name="EN_tyid" value="<?= $aTypeID[$row] ?>">
                        <button type="submit" class="btn btn-default btn-sm" name="Action" title="<?= gettext("Edit") ?>" data-tooltip value="<?= gettext("Edit") ?>">
                          <i class='fa fa-pencil'></i>
                        </button>
                      </form>
                    </td>
                    <td>
                      <form name="ProcessEventType" action="EventNames.php" method="POST" class="pull-left">
                        <input type="hidden" name="theID" value="<?= $aTypeID[$row] ?>">
                        <button type="submit" class="btn btn-default btn-sm" title="<?= gettext("Delete") ?>" data-tooltip name="Action" value="DELETE" onClick="return confirm('Deleting this event TYPE will NOT delete any existing Events or Attendance Counts.  Are you sure you want to DELETE Event Type ID: <?=  $aTypeID[$row] ?>')">
                          <i class='fa fa-trash'></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <?php
          }
          ?>
        </tbody>
      </table>
      <?php
    }
    ?>
  </div>
</div>

<?php
if (FilterInput($_POST["Action"]) != "NEW")
{
  ?>
  <div class="text-center">
    <form name="AddEventNames" action="EventNames.php" method="POST">
      <button type="submit" Name="Action" value="NEW" class="btn btn-primary">
        <?= gettext("Add Event Type") ?>
      </button
    </form>
  </div>
  <?php
}
?>
<?php require "Include/Footer.php" ?>
