<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\EventCountNameQuery;
use ChurchCRM\model\ChurchCRM\EventCountName;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

AuthenticationManager::redirectHomeIfNotAdmin();

$sPageTitle = gettext('Edit Event Types');
require_once 'Include/Header.php';

$editing = 'FALSE';

// Accept EN_tyid from POST or GET
if (isset($_POST['EN_tyid'])) {
    $tyid = InputUtils::legacyFilterInput($_POST['EN_tyid'], 'int');
} elseif (isset($_GET['EN_tyid'])) {
    $tyid = InputUtils::legacyFilterInput($_GET['EN_tyid'], 'int');
} else {
    $tyid = null;
}

if (strpos($_POST['Action'], 'DELETE_', 0) === 0) {
    $ctid = InputUtils::legacyFilterInput(mb_substr($_POST['Action'], 7), 'int');
    EventCountNameQuery::create()
        ->filterById($ctid)
        ->delete();
} else {
    switch ($_POST['Action']) {
        case 'ADD':
            $newCTName = InputUtils::legacyFilterInput($_POST['newCountName']);
            $theID = InputUtils::legacyFilterInput($_POST['EN_tyid'], 'int');
            $eventCount = new EventCountName();
            $eventCount->setTypeId($theID);
            $eventCount->setName($newCTName);
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
if ($eventType) {
  $aTypeID = $eventType->getId();
  $aTypeName = $eventType->getName();
  $aDefStartTime = $eventType->getDefStartTime();
  $aDefRecurDOW = $eventType->getDefRecurDow();
  $aDefRecurDOM = $eventType->getDefRecurDom();
  $aDefRecurDOY = $eventType->getDefRecurDoy();
  $aDefRecurType = $eventType->getDefRecurType();
  if ($aDefStartTime) {
    // Convert DateTime to string format if necessary
    $timeString = is_object($aDefStartTime) ? $aDefStartTime->format('H:i:s') : $aDefStartTime;
    $aStartTimeTokens = explode(':', $timeString);
    $aEventStartHour = $aStartTimeTokens[0];
    $aEventStartMins = $aStartTimeTokens[1];
  }
} else {
  $aTypeID = $aTypeName = $aDefStartTime = $aDefRecurDOW = $aDefRecurDOM = $aDefRecurDOY = $aDefRecurType = null;
}
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
$counts = EventCountNameQuery::create()->filterByTypeId($aTypeID)->orderById()->find();
$numCounts = $counts->count();
$nr = $numCounts + 2;
$cCountID = [];
$cCountName = [];
if ($numCounts) {
    foreach ($counts as $i => $count) {
        $cCountID[$i + 1] = $count->getId();
        $cCountName[$i + 1] = $count->getName();
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
      <button type="submit" Name="Action" value="NAME" class="btn btn-secondary"><?= gettext('Save Name') ?></button>
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
          <button type="submit" name="Action" value="DELETE_<?= $cCountID[$c] ?>"
                  class="btn btn-secondary" data-cy="remove-attendance-count">
            <?= gettext('Remove') ?>
          </button>
        </td>
      </tr>
        <?php
    }
    ?>
    <tr>
      <td class="TextColumn" width="35%">
        <input class="form-control" type="text" name="newCountName" length="20"
               placeholder="New Attendance Count" data-cy="attendance-count-input" />
      </td>
      <td class="TextColumn" width="50%">
        <button type="submit" name="Action" value="ADD" class="btn btn-secondary"
                data-cy="add-attendance-count">
          <?= gettext('Add counter') ?>
        </button>
      </td>
    </tr>
</table>
</form>
</div>

<div>
  <a href="EventNames.php" class='btn btn-secondary'>
    <i class='fa fa-chevron-left'></i>
    <?= gettext('Return to Event Types') ?>
  </a>
</div>
<?php
require_once 'Include/Footer.php';
