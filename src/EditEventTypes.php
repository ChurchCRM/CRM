<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

AuthenticationManager::redirectHomeIfNotAdmin();

$sPageTitle = gettext('Edit Event Types');
require_once 'Include/Header.php';

$editing = 'FALSE';
$tyid = InputUtils::legacyFilterInput($_POST['EN_tyid'], 'int');

if (strpos($_POST['Action'], 'DELETE_', 0) === 0) {
  $ctid = InputUtils::legacyFilterInput(mb_substr($_POST['Action'], 7), 'int');
  \ChurchCRM\model\ChurchCRM\EventCountNameQuery::create()->filterByCountid($ctid)->delete();
} else {
    switch ($_POST['Action']) {
    case 'ADD':
      $newCTName = InputUtils::legacyFilterInput($_POST['newCountName']);
      $theID = InputUtils::legacyFilterInput($_POST['EN_tyid'], 'int');
      $eventCount = new \ChurchCRM\model\ChurchCRM\EventCountName();
      $eventCount->setEventtypeid($theID);
      $eventCount->setCountname($newCTName);
      $eventCount->save();
      break;

        case 'NAME':
            $editing = 'FALSE';
            $eName = $_POST['newEvtName'];
            $theID = $_POST['EN_tyid'];
            $eventType = EventTypeQuery::create()->findOneById(InputUtils::legacyFilterInput($theID));
            $eventType->setName(InputUtils::legacyFilterInput($eName));
            $eventType->save();
            $theID = '';
            $_POST['Action'] = '';
            break;

        case 'TIME':
            $editing = 'FALSE';
            $eTime = $_POST['newEvtStartTime'];
            $theID = $_POST['EN_tyid'];
            $eventType = EventTypeQuery::create()->findOneById(InputUtils::legacyFilterInput($theID));
            $eventType->setDefStartTime(InputUtils::legacyFilterInput($eTime));
            $eventType->save();
            $theID = '';
            $_POST['Action'] = '';
            break;
    }
}

// Get data for the form as it now exists.
$eventType = EventTypeQuery::create()->findOneById($tyid);
$aTypeID = $eventType ? $eventType->getId() : null;
$aTypeName = $eventType ? $eventType->getName() : null;
$aDefStartTime = $eventType ? $eventType->getDefStartTime() : null;
$aDefRecurDOW = $eventType ? $eventType->getDefRecurDow() : null;
$aDefRecurDOM = $eventType ? $eventType->getDefRecurDom() : null;
$aDefRecurDOY = $eventType ? $eventType->getDefRecurDoy() : null;
$aDefRecurType = $eventType ? $eventType->getDefRecurType() : null;
if ($aDefStartTime) {
  $aStartTimeTokens = explode(':', $aDefStartTime);
  $aEventStartHour = $aStartTimeTokens[0];
  $aEventStartMins = $aStartTimeTokens[1];
}
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

// Get a list of the attendance counts currently associated with this event type
$counts = \ChurchCRM\model\ChurchCRM\EventCountNameQuery::create()->filterByEventtypeid($aTypeID)->orderByCountid()->find();
$numCounts = $counts->count();
$nr = $numCounts + 2;
$cCountID = [];
$cCountName = [];
if ($numCounts) {
  foreach ($counts as $i => $count) {
    $cCountID[$i+1] = $count->getCountid();
    $cCountName[$i+1] = $count->getCountname();
  }
}

// Construct the form
?>
<div class='card'>
  <div class='card-header'>
    <h3 class='card-title'><?= gettext('Edit Event Type') ?></h3>
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
      <tr data-cy="attendance-count-row">
        <td class="TextColumn" width="35%"><?= $cCountName[$c] ?></td>
        <td class="TextColumn" width="50%">
          <button type="submit" name="Action" value="DELETE_<?=  $cCountID[$c] ?>" class="btn btn-default" data-cy="remove-attendance-count"><?= gettext('Remove') ?></button>
        </td>
      </tr>
        <?php
    }
    ?>
    <tr>
      <td class="TextColumn" width="35%">
        <input class='form-control' type="text" name="newCountName" length="20" placeholder="New Attendance Count" data-cy="attendance-count-input" />
      </td>
      <td class="TextColumn" width="50%">
        <button type="submit" name="Action" value="ADD" class="btn btn-default" data-cy="add-attendance-count"><?= gettext('Add counter') ?></button>
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
<?php
require_once 'Include/Footer.php';
