<?php

/*******************************************************************************
 *
 *  filename    : EventNames.php
 *  last change : 2005-09-10
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2005 Todd Pillars
 *
 *  function    : List all Church Events
  *
 *
 *  Modified by Stephen Shaffer, Oct 2006
 *  feature changes - added recurring defaults and customizable attendance count
 *  fields
 *
 ******************************************************************************/

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isAddEvent());

$sPageTitle = gettext('Edit Event Types');

require 'Include/Header.php';

//
//  process the ACTION button inputs from the form page
//

if (isset($_POST['Action'])) {
    switch (InputUtils::legacyFilterInput($_POST['Action'])) {
        case 'CREATE':
        // Insert into the event_name table
            $eName = $_POST['newEvtName'];
            $eTime = $_POST['newEvtStartTime'];
            $eDOM = $_POST['newEvtRecurDOM'];
            $eDOW = $_POST['newEvtRecurDOW'];
            $eDOY = $_POST['newEvtRecurDOY'];
            $eRecur = $_POST['newEvtTypeRecur'];
            $eCntLst = $_POST['newEvtTypeCntLst'];
            $eCntArray = array_filter(array_map('trim', explode(',', $eCntLst)));
            $eCntArray[] = 'Total';
            $eCntNum = count($eCntArray);
            $theID = $_POST['theID'];

          # We need to be able to handle database configurations where MySQL Strict mode is enabled. (#4273)
          # See: https://dev.mysql.com/doc/refman/en/sql-mode.html#sql-mode-strict
          # Special thanks to @chiebert (GitHub) for the fix!
            $insert = "INSERT INTO event_types (type_name";
            $values = " VALUES ('" . InputUtils::legacyFilterInput($eName) . "'";
            if (!empty($eTime)) {
                $insert .= ", type_defstarttime";
                $values .= ",'" . InputUtils::legacyFilterInput($eTime) . "'";
            }
            if (!empty($eRecur)) {
                $insert .= ", type_defrecurtype";
                $values .= ",'" . InputUtils::legacyFilterInput($eRecur) . "'";
            }
            if (!empty($eDOW)) {
                $insert .= ", type_defrecurDOW";
                $values .= ",'" . InputUtils::legacyFilterInput($eDOW) . "'";
            }
            if (!empty($eDOM)) {
                $insert .= ", type_defrecurDOM";
                $values .= ",'" . InputUtils::legacyFilterInput($eDOM) . "'";
            }
            if (!empty($eDOY)) {
                $insert .= ", type_defrecurDOY";
                $values .= ",'" . InputUtils::legacyFilterInput($eDOY) . "'";
            }
            $insert .= ")";
            $values .= ")";

            $sSQL = $insert . $values;
            RunQuery($sSQL);
            $theID = mysqli_insert_id($cnInfoCentral);

            for ($j = 0; $j < $eCntNum; $j++) {
                $cCnt = ltrim(rtrim($eCntArray[$j]));
                $sSQL = "INSERT eventcountnames_evctnm (evctnm_eventtypeid, evctnm_countname) VALUES ('" . InputUtils::legacyFilterInput($theID) . "','" . InputUtils::legacyFilterInput($cCnt) . "') ON DUPLICATE KEY UPDATE evctnm_countname='$cCnt'";
                RunQuery($sSQL);
            }
            RedirectUtils::redirect('EventNames.php'); // clear POST
            break;

        case 'DELETE':
            $theID = $_POST['theID'];
            $sSQL = "DELETE FROM event_types WHERE type_id='" . InputUtils::legacyFilterInput($theID) . "' LIMIT 1";
            RunQuery($sSQL);
            $sSQL = "DELETE FROM eventcountnames_evctnm WHERE evctnm_eventtypeid='" . InputUtils::legacyFilterInput($theID) . "'";
            RunQuery($sSQL);
            $theID = '';
            $_POST['Action'] = '';
            break;
    }
}

// Get data for the form as it now exists.

$sSQL = 'SELECT * FROM event_types ORDER BY type_id';
$rsOpps = RunQuery($sSQL);
$numRows = mysqli_num_rows($rsOpps);

        // Create arrays of the event types
for ($row = 1; $row <= $numRows; $row++) {
    $aRow = mysqli_fetch_array($rsOpps, MYSQLI_BOTH);
    extract($aRow);

    $aTypeID[$row] = $type_id;
    $aTypeName[$row] = $type_name;
    $aDefStartTime[$row] = $type_defstarttime;
    $aDefRecurDOW[$row] = $type_defrecurDOW;
    $aDefRecurDOM[$row] = $type_defrecurDOM;
    $aDefRecurDOY[$row] = $type_defrecurDOY;
    $aDefRecurType[$row] = $type_defrecurtype;
    //                echo "$row:::DOW = $aDefRecurDOW[$row], DOM=$aDefRecurDOM[$row], DOY=$adefRecurDOY[$row] type=$aDefRecurType[$row]\n\r\n<br>";

    switch ($aDefRecurType[$row]) {
        case 'none':
                $recur[$row] = gettext('None');
            break;
        case 'weekly':
                  $recur[$row] = gettext('Weekly on') . ' ' . gettext($aDefRecurDOW[$row] . 's');
            break;
        case 'monthly':
            $recur[$row] = gettext('Monthly on') . ' ' . date('dS', mktime(0, 0, 0, 1, $aDefRecurDOM[$row], 2000));
            break;
        case 'yearly':
            $recur[$row] = gettext('Yearly on') . ' ' . mb_substr($aDefRecurDOY[$row], 5);
            break;
        default:
            $recur[$row] = gettext('None');
    }
    // recur types = 1-DOW for weekly, 2-DOM for monthly, 3-DOY for yearly.
    // repeats on DOW, DOM or DOY
    //
    // new - check the count definitions table for a list of count fields
    $cSQL = "SELECT evctnm_countid, evctnm_countname FROM eventcountnames_evctnm WHERE evctnm_eventtypeid='$aTypeID[$row]' ORDER BY evctnm_countid";
    $cOpps = RunQuery($cSQL);
    $numCounts = mysqli_num_rows($cOpps);
    if ($numCounts) {
        $cCountName = [];
        for ($c = 1; $c <= $numCounts; $c++) {
            $cRow = mysqli_fetch_array($cOpps, MYSQLI_BOTH);
            extract($cRow);
            $cCountID[$c] = $evctnm_countid;
            $cCountName[$c] = $evctnm_countname;
        }
        $cCountList[$row] = implode(', ', $cCountName);
    } else {
        $cCountList[$row] = '';
    }
}

if (InputUtils::legacyFilterInput($_POST['Action']) == 'NEW') {
    ?>
  <div class='card card-primary'>
    <div class='card-body'>
      <form name="UpdateEventNames" action="EventNames.php" method="POST" class='form-horizontal'>
        <input type="hidden" name="theID" value="<?= $aTypeID[$row] ?>">
        <div class='row form-group'>
          <div class='col-sm-4 control-label text-bold'>
            <?= gettext('EVENT TYPE NAME') ?>
          </div>
          <div class='col-sm-6'>
            <input class="form-control" type="text" name="newEvtName" value="<?= $aTypeName[$row] ?>" size="30" maxlength="35" autofocus>
          </div>
        </div>
        <div class='row form-group'>
          <div class='col-sm-4 control-label text-bold'>
            <?= gettext('Recurrence Pattern') ?>
          </div>
          <div class='col-sm-6 event-recurrence-patterns'>
            <div class='row form-radio-list'>
              <div class='col-xs-12'>
                <input type="radio" name="newEvtTypeRecur" value="none" checked/> <?= gettext('None'); ?>
              </div>
            </div>
            <div class='row form-radio-list'>
              <div class='col-xs-5'>
                <input type="radio" name="newEvtTypeRecur" value="weekly"/> <?= gettext('Weekly') ?>
              </div>
              <div class='col-xs-7'>
                <select name="newEvtRecurDOW" size="1" class='form-control pull-left' disabled>
                  <option value=1><?= gettext('Sundays') ?></option>
                  <option value=2><?= gettext('Mondays') ?></option>
                  <option value=3><?= gettext('Tuesdays') ?></option>
                  <option value=4><?= gettext('Wednesdays') ?></option>
                  <option value=5><?= gettext('Thursdays') ?></option>
                  <option value=6><?= gettext('Fridays') ?></option>
                  <option value=7><?= gettext('Saturdays') ?></option>
                </select>
              </div>
            </div>
            <div class='row form-radio-list'>
              <div class='col-xs-5'>
                <input type="radio" name="newEvtTypeRecur" value="monthly"/> <?= gettext('Monthly')?>
              </div>
              <div class='col-xs-7'>
                <select name="newEvtRecurDOM" size="1" class='form-control pull-left' disabled>
                  <?php
                    for ($kk = 1; $kk <= 31; $kk++) {
                        $DOM = date('dS', mktime(0, 0, 0, 1, $kk, 2000)); ?>
                      <option class="SmallText" value=<?= $kk ?>><?= $DOM ?></option>
                        <?php
                    } ?>
                 </select>
               </div>
            </div>
            <div class='row form-radio-list'>
              <div class='col-xs-5'>
                <input type="radio" name="newEvtTypeRecur" value="yearly"/> <?= gettext('Yearly')?>
              </div>
              <div class='col-xs-7'>
                <input type="text" disabled class="form-control" name="newEvtRecurDOY" maxlength="10" id="nSD" size="11" placeholder='YYYY-MM-DD' data-provide="datepicker" data-format='mm/dd/yyyy' />
              </div>
            </div>
          </div>
        </div>
        <div class='row form-group'>
          <div class='col-sm-4 control-label text-bold'>
            <?= gettext('DEFAULT START TIME') ?>
          </div>
          <div class='col-sm-6'>
            <select class="form-control" name="newEvtStartTime">
              <?php createTimeDropdown(7, 22, 15, '', ''); ?>
            </select>
          </div>
        </div>
        <div class='row form-group'>
          <div class='col-sm-4 control-label text-bold'>
            <?= gettext('ATTENDANCE COUNTS') ?>
          </div>
          <div class='col-sm-6'>
            <input class="form-control" type="Text" name="newEvtTypeCntLst" value="<?= $cCountList[$row] ?>" Maxlength="50" id="nETCL" size="30" placeholder="<?= gettext('Optional') ?>">
            <div class='text-sm'><?= gettext('Enter a list of the attendance counts you want to include with this event.')?></div>
            <div class='text-sm'><?= gettext('Separate each count_name with a comma. e.g. Members, Visitors, Campus, Children'); ?></div>
            <div class='text-sm'><?= gettext('Every event type includes a Total count, you do not need to include it.') ?></div>
          </div>
        </div>
        <div class='row form-group'>
          <div class='col-sm-8 col-sm-offset-4'>
            <a href="EventNames.php" class='btn btn-default'>
              <?= gettext('Cancel') ?>
            </a>
            <button type="submit" Name="Action" value="CREATE" class="btn btn-primary">
              <?= gettext('Save Changes') ?>
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
<div class="card">
  <div class="card-header">
    <?php if ($numRows > 0) {
        ?>
      <h3 class="card-title"><?= ($numRows == 1 ? gettext('There currently is') : gettext('There currently are')) . ' ' . $numRows . ' ' . ($numRows == 1 ? gettext('custom event type') : gettext('custom event types')) ?></h3>
        <?php
    } ?>
  </div>

  <div class='card-body'>
    <?php
    if ($numRows > 0) {
        ?>
      <table  id="eventNames" class="table table-striped table-bordered data-table">
        <thead>
         <tr>
            <th><?= gettext('Event Type') ?></th>
            <th><?= gettext('Name') ?></th>
            <th><?= gettext('Recurrence Pattern') ?></th>
            <th><?= gettext('Start Time') ?></th>
            <th><?= gettext('Attendance Counts') ?></th>
            <th><?= gettext('Action') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php
            for ($row = 1; $row <= $numRows; $row++) {
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
                        <button type="submit" name="Action" value="<?= gettext('Create Event') ?>" class="btn btn-default btn-sm">
                          <?= gettext('Create Event') ?>
                        </button>
                      </form>
                    </td>
                    <td>
                      <form name="ProcessEventType" action="EditEventTypes.php" method="POST" class="pull-left">
                        <input type="hidden" name="EN_tyid" value="<?= $aTypeID[$row] ?>">
                        <button type="submit" class="btn btn-default btn-sm" name="Action" title="<?= gettext('Edit') ?>" value="<?= gettext('Edit') ?>">
                          <i class='fas fa-pen'></i>
                        </button>
                      </form>
                    </td>
                    <td>
                      <form name="ProcessEventType" action="EventNames.php" method="POST" class="pull-left">
                        <input type="hidden" name="theID" value="<?= $aTypeID[$row] ?>">
                        <button type="submit" class="btn btn-default btn-sm" title="<?= gettext('Delete') ?>" name="Action" value="DELETE" onClick="return confirm("<?= gettext('Deleting this event TYPE will NOT delete any existing Events or Attendance Counts.  Are you sure you want to DELETE Event Type ID: ') . $aTypeID[$row] ?>")">
                          <i class='fa fa-trash'></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
                <?php
            } ?>
        </tbody>
      </table>
        <?php
    }
    ?>
  </div>
</div>

<?php
if (InputUtils::legacyFilterInput($_POST['Action']) != 'NEW') {
    ?>
  <div class="text-center">
    <form name="AddEventNames" action="EventNames.php" method="POST">
      <button type="submit" Name="Action" value="NEW" class="btn btn-primary">
        <?= gettext('Add Event Type') ?>
      </button
    </form>
  </div>
    <?php
}
?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>" >
  $(document).ready(function () {
//Added by @saulowulhynek to translation of datatable nav terms
    $('#eventNames').DataTable(window.CRM.plugin.dataTable);
  });
</script>

<?php require 'Include/Footer.php' ?>
