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
use ChurchCRM\Utils\InputUtils;

if (!AuthenticationManager::getCurrentUser()->isAdmin()) {
    header('Location: Menu.php');
}
$sPageTitle = gettext('Edit Event Types');
require 'Include/Header.php';

//
//  process the ACTION button inputs from the form page
//
$editing = 'FALSE';
$tyid = $_POST['EN_tyid'];

if (strpos($_POST['Action'], 'DELETE_', 0) === 0) {
    $ctid = mb_substr($_POST['Action'], 7);
    $sSQL = "DELETE FROM eventcountnames_evctnm WHERE evctnm_countid='$ctid' LIMIT 1";
    RunQuery($sSQL);
} else {
    switch ($_POST['Action']) {
        case 'ADD':
            $newCTName = $_POST['newCountName'];
            $theID = $_POST['EN_tyid'];
            $sSQL = "INSERT eventcountnames_evctnm (evctnm_eventtypeid, evctnm_countname) VALUES ('$theID','$newCTName')";
            RunQuery($sSQL);
            break;

        case 'NAME':
            $editing = 'FALSE';
            $eName = $_POST['newEvtName'];
            $theID = $_POST['EN_tyid'];
            $sSQL = "UPDATE event_types SET type_name='" . InputUtils::legacyFilterInput($eName) . "' WHERE type_id='" . InputUtils::legacyFilterInput($theID) . "'";
            RunQuery($sSQL);
            $theID = '';
            $_POST['Action'] = '';
            break;

        case 'TIME':
            $editing = 'FALSE';
            $eTime = $_POST['newEvtStartTime'];
            $theID = $_POST['EN_tyid'];
            $sSQL = "UPDATE event_types SET type_defstarttime='" . InputUtils::legacyFilterInput($eTime) . "' WHERE type_id='" . InputUtils::legacyFilterInput($theID) . "'";
            RunQuery($sSQL);
            $theID = '';
            $_POST['Action'] = '';
            break;
    }
}

// Get data for the form as it now exists.
$sSQL = "SELECT * FROM event_types WHERE type_id='$tyid'";
$rsOpps = RunQuery($sSQL);
$aRow = mysqli_fetch_array($rsOpps, MYSQLI_BOTH);
extract($aRow);
$aTypeID = $type_id;
$aTypeName = $type_name;
$aDefStartTime = $type_defstarttime;
    $aStartTimeTokens = explode(':', $aDefStartTime);
    $aEventStartHour = $aStartTimeTokens[0];
    $aEventStartMins = $aStartTimeTokens[1];
$aDefRecurDOW = $type_defrecurDOW;
$aDefRecurDOM = $type_defrecurDOM;
$aDefRecurDOY = $type_defrecurDOY;
$aDefRecurType = $type_defrecurtype;
switch ($aDefRecurType) {
    case 'none':
        $recur = gettext('None');
        break;
    case 'weekly':
        $recur = gettext('Weekly on') . ' ' . gettext($aDefRecurDOW . 's');
        break;
    case 'monthly':
        $recur = gettext('Monthly on') . ' ' . date('dS', mktime(0, 0, 0, 1, $aDefRecurDOM, 2000));
        break;
    case 'yearly':
        $recur = gettext('Yearly on') . ' ' . mb_substr($aDefRecurDOY, 5);
        break;
    default:
        $recur = gettext('None');
}

// Get a list of the attendance counts currently associated with thisevent type
$cSQL = "SELECT evctnm_countid, evctnm_countname FROM eventcountnames_evctnm WHERE evctnm_eventtypeid='$aTypeID' ORDER BY evctnm_countid";
$cOpps = RunQuery($cSQL);
$numCounts = mysqli_num_rows($cOpps);
$nr = $numCounts + 2;
$cCountName = '';
if ($numCounts) {
    $cCountName = '';
    for ($c = 1; $c <= $numCounts; $c++) {
        $cRow = mysqli_fetch_array($cOpps, MYSQLI_BOTH);
        extract($cRow);
        $cCountID[$c] = $evctnm_countid;
        $cCountName[$c] = $evctnm_countname;
    }
}

// Construct the form
?>
<div class='box'>
  <div class='box-header'>
    <h3 class='box-title'><?= gettext('Edit Event Type') ?></h3>
  </div>

  <form method="POST" action="EditEventTypes.php" name="EventTypeEditForm">
  <input type="hidden" name="EN_tyid" value="<?= $aTypeID ?>">
  <input type="hidden" name="EN_ctid" value="<?= $cCountID[$c] ?>">

<table class='table'>
  <tr>
    <td class="LabelColumn" width="15%">
      <strong><?= gettext('Event Type') . ':' . $aTypeID ?></strong>
    </td>
    <td class="TextColumn" width="35%">
      <input type="text" class="form-control" name="newEvtName" value="<?= $aTypeName ?>" size="30" maxlength="35" autofocus />
    </td>
    <td class="TextColumn" width="50%">
      <button type="submit" Name="Action" value="NAME" class="btn btn-default"><?= gettext('Save Name') ?></button>
    </td>
  </tr>
  <tr>
    <td class="LabelColumn" width="15%">
      <strong><?= gettext('Recurrence Pattern') ?></strong>
    </td>
    <td class="TextColumn" width="35%">
      <?= $recur ?>
    </td>
    <td class="TextColumn" width="50%">
      <select class='form-control' name="newEvtStartTime" size="1" onchange="javascript:$('#newEvtStartTimeSubmit').click()">
        <?php createTimeDropdown(7, 18, 15, $aEventStartHour, $aEventStartMins); ?>
      </select>
      <button class='hidden' type="submit" name="Action" value="TIME" id="newEvtStartTimeSubmit"></button>
    </td>
  </tr>

   <tr>
      <td class="LabelColumn" width="15%" rowspan="<?= $nr ?>" colspan="1">
        <strong><?= gettext('Attendance Counts') ?></strong>
      </td>
    </tr>
    <?php
    for ($c = 1; $c <= $numCounts; $c++) {
        ?>
      <tr>
        <td class="TextColumn" width="35%"><?= $cCountName[$c] ?></td>
        <td class="TextColumn" width="50%">
          <button type="submit" name="Action" value="DELETE_<?=  $cCountID[$c] ?>" class="btn btn-default"><?= gettext('Remove') ?></button>
        </td>
      </tr>
        <?php
    }
    ?>
      <tr>
        <td class="TextColumn" width="35%">
           <input class='form-control' type="text" name="newCountName" length="20" placeholder="New Attendance Count" />
        </td>
        <td class="TextColumn" width="50%">
           <button type="submit" name="Action" value="ADD" class="btn btn-default"><?= gettext('Add counter') ?></button>
        </td>
      </tr>
</table>
</form>
</div>

<div>
  <a href="EventNames.php" class='btn btn-default'>
    <i class='fa fa-chevron-left'></i>
    <?= gettext('Return to Event Types') ?>
  </a>
</div>

<?php require 'Include/Footer.php' ?>
