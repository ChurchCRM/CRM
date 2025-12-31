<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\EventCountNameQuery;
use ChurchCRM\model\ChurchCRM\EventCountName;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

AuthenticationManager::redirectHomeIfNotAdmin();

$sPageTitle = gettext('Edit Event Types');
require_once __DIR__ . '/Include/Header.php';

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
            
            // Convert time from 12-hour format (g:mm A) to 24-hour format (HH:mm:ss)
            $dateTime = \DateTime::createFromFormat('g:i A', $eTime);
            if ($dateTime) {
                $eTime = $dateTime->format('H:i:s');
            }
            
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
    // Convert DateTime to 12-hour format for display (h:mm A)
    $timeString = is_object($aDefStartTime) ? $aDefStartTime->format('H:i:s') : $aDefStartTime;
    $dateTime = \DateTime::createFromFormat('H:i:s', $timeString);
    $aEventStartTime = $dateTime ? $dateTime->format('g:i A') : '9:00 AM';
  } else {
    $aEventStartTime = '9:00 AM';
  }
} else {
  $aTypeID = $aTypeName = $aDefStartTime = $aDefRecurDOW = $aDefRecurDOM = $aDefRecurDOY = $aDefRecurType = null;
  $aEventStartTime = '9:00 AM';
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
<div class='card card-primary'>
  <div class='card-header'>
    <h3 class='card-title mb-0'><i class="fas fa-edit mr-2"></i><?= gettext('Edit Event Type') ?></h3>
  </div>

  <div class='card-body'>
    <form method="POST" action="EditEventTypes.php" name="EventTypeEditForm">
      <input type="hidden" name="EN_tyid" value="<?= $aTypeID ?>">

      <div class="form-group">
        <label for="newEvtName" class="font-weight-bold"><?= gettext('Event Type Name') ?></label>
        <div class="row">
          <div class="col-md-8">
            <input type="text" class="form-control" name="newEvtName" id="newEvtName" value="<?= InputUtils::escapeAttribute($aTypeName) ?>" maxlength="35" autofocus />
          </div>
          <div class="col-md-4">
            <button type="submit" name="Action" value="NAME" class="btn btn-primary">
              <i class="fas fa-save mr-1"></i><?= gettext('Save Name') ?>
            </button>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="font-weight-bold"><?= gettext('Recurrence Pattern') ?></label>
        <div class="border rounded p-3 bg-light">
          <?= $recur ?>
        </div>
      </div>

      <div class="form-group">
        <label class="font-weight-bold"><?= gettext('Default Start Time') ?></label>
        <div class="row">
          <div class="col-md-8">
            <div class="d-flex align-items-center" style="gap: 5px; max-width: 250px;">
              <select class="form-control" id="EventHour" name="EventHour" style="width: 70px;">
                <?php
                for ($h = 1; $h <= 12; $h++) {
                    echo '<option value="' . $h . '">' . $h . '</option>';
                }
                ?>
              </select>
              <span>:</span>
              <select class="form-control" id="EventMinute" name="EventMinute" style="width: 70px;">
                <?php
                for ($m = 0; $m < 60; $m += 15) {
                    $min = str_pad($m, 2, '0', STR_PAD_LEFT);
                    echo '<option value="' . $min . '">' . $min . '</option>';
                }
                ?>
              </select>
              <select class="form-control" id="EventPeriod" name="EventPeriod" style="width: 70px;">
                <option value="AM">AM</option>
                <option value="PM">PM</option>
              </select>
            </div>
            <input type="hidden" name="newEvtStartTime" id="newEvtStartTime" value="<?= $aEventStartTime ?>">
            <input type="hidden" name="Action" value="TIME">
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="font-weight-bold"><?= gettext('Attendance Count Categories') ?></label>
        <div class="table-responsive">
          <table class="table table-sm table-bordered">
            <thead class="thead-light">
              <tr>
                <th><?= gettext('Category Name') ?></th>
                <th style="width: 120px;"><?= gettext('Actions') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php
              for ($c = 1; $c <= $numCounts; $c++) {
                  ?>
                <tr data-cy="attendance-count-row">
                  <td><?= InputUtils::escapeHTML($cCountName[$c]) ?></td>
                  <td class="text-center">
                    <button type="submit" name="Action" value="DELETE_<?= $cCountID[$c] ?>"
                            class="btn btn-outline-danger btn-sm" data-cy="remove-attendance-count"
                            onclick="return confirm('<?= gettext('Remove this attendance count?') ?>');">
                      <i class="fas fa-trash mr-1"></i><?= gettext('Remove') ?>
                    </button>
                  </td>
                </tr>
                  <?php
              }
              ?>
              <tr>
                <td>
                  <input class="form-control form-control-sm" type="text" name="newCountName" maxlength="20"
                         placeholder="<?= gettext('e.g., Visitors, Children') ?>" data-cy="attendance-count-input" />
                </td>
                <td class="text-center">
                  <button type="submit" name="Action" value="ADD" class="btn btn-primary btn-sm"
                          data-cy="add-attendance-count">
                    <i class="fas fa-plus mr-1"></i><?= gettext('Add') ?>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/event/EventUtils.js"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
    var currentTime = '<?= $aEventStartTime ?>';
    
    // Initialize time picker dropdowns with current time
    window.CRM.EventUtils.initializeTimePicker(currentTime, 'EventHour', 'EventMinute', 'EventPeriod');
    
    // Setup auto-submit on time picker change
    window.CRM.EventUtils.setupTimePickerAutoSubmit(
        'form[name="EventTypeEditForm"]',
        'EventHour',
        'EventMinute',
        'EventPeriod',
        'newEvtStartTime',
        currentTime
    );
});
</script>

<div class="mb-4">
  <a href="EventNames.php" class='btn btn-outline-secondary'>
    <i class='fas fa-chevron-left mr-1'></i><?= gettext('Return to Event Types') ?>
  </a>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
