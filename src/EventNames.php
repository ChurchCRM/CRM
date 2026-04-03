<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventCountName;
use ChurchCRM\model\ChurchCRM\EventCountNameQuery;
use ChurchCRM\model\ChurchCRM\EventType;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

if (AuthenticationManager::getCurrentUser()->isAddEvent() === false) {
    RedirectUtils::securityRedirect('AddEvent');
}

$sPageTitle = gettext('Edit Event Types');
$sPageSubtitle = gettext('Manage event names and categories');

$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Events'), '/ListEvents.php'],
    [gettext('Event Types')],
]);
require_once __DIR__ . '/Include/Header.php';

if (isset($_POST['Action'])) {
    switch (InputUtils::legacyFilterInput($_POST['Action'])) {
        case 'CREATE':
            $eName = $_POST['newEvtName'];
            $eTime = $_POST['newEvtStartTime'];
            
            // Convert time from 12-hour format (g:mm A) to 24-hour format (HH:mm:ss)
            if (!empty($eTime)) {
                $dateTime = \DateTime::createFromFormat('g:i A', $eTime);
                if ($dateTime) {
                    $eTime = $dateTime->format('H:i:s');
                }
            }
            
            $eDOM = $_POST['newEvtRecurDOM'];
            $eDOW = $_POST['newEvtRecurDOW'];
            $eDOY = $_POST['newEvtRecurDOY'];
            $eRecur = $_POST['newEvtTypeRecur'];
            $eCntLst = $_POST['newEvtTypeCntLst'];
            $eCntArray = array_filter(array_map('trim', explode(',', $eCntLst)));
            $eCntNum = count($eCntArray);
            $theID = $_POST['theID'];

            $eventType = new EventType();
            $eventType->setName(InputUtils::legacyFilterInput($eName));
            if (!empty($eTime)) {
                $eventType->setDefStartTime(InputUtils::legacyFilterInput($eTime));
            }
            if (!empty($eRecur)) {
                $eventType->setDefRecurType(InputUtils::legacyFilterInput($eRecur));
            }
            if (!empty($eDOW)) {
                $dayOfWeekMap = ['1' => 'Sunday', '2' => 'Monday', '3' => 'Tuesday', '4' => 'Wednesday', '5' => 'Thursday', '6' => 'Friday', '7' => 'Saturday'];
                $dowValue = InputUtils::legacyFilterInput($eDOW);
                if (isset($dayOfWeekMap[$dowValue])) {
                    $dowValue = $dayOfWeekMap[$dowValue];
                }
                $eventType->setDefRecurDOW($dowValue);
            }
            if (!empty($eDOM)) {
                $eventType->setDefRecurDOM(InputUtils::legacyFilterInput($eDOM));
            }
            if (!empty($eDOY)) {
                $eventType->setDefRecurDOY(InputUtils::legacyFilterInput($eDOY));
            }
            $eventType->save();
            $theID = $eventType->getId();

            for ($j = 0; $j < $eCntNum; $j++) {
                $cCnt = trim($eCntArray[$j]);
                $existing = EventCountNameQuery::create()
                    ->filterByTypeId((int)$theID)
                    ->filterByName($cCnt)
                    ->findOne();
                if ($existing === null) {
                    $countName = new EventCountName();
                    $countName->setTypeId((int)$theID);
                    $countName->setName($cCnt);
                    $countName->save();
                }
            }
            RedirectUtils::redirect('EventNames.php');
            break;

        case 'DELETE':
            $theID = (int)$_POST['theID'];
            EventTypeQuery::create()->filterById($theID)->delete();
            EventCountNameQuery::create()->filterByTypeId($theID)->delete();
            $theID = '';
            $_POST['Action'] = '';
            break;
    }
}

$eventTypes = EventTypeQuery::create()->orderById()->find();
$numRows = count($eventTypes);

$row = 0;
foreach ($eventTypes as $et) {
    $row++;

    $aTypeID[$row] = $et->getId();
    $aTypeName[$row] = $et->getName();
    
    // Convert 24-hour time to 12-hour AM/PM format for display
    $startTime = $et->getDefStartTime();
    if ($startTime instanceof \DateTime) {
        $aDefStartTime[$row] = $startTime->format('g:i A');
    } elseif (is_string($startTime) && $startTime !== '') {
        $dateTime = \DateTime::createFromFormat('H:i:s', $startTime);
        $aDefStartTime[$row] = $dateTime ? $dateTime->format('g:i A') : $startTime;
    } else {
        $aDefStartTime[$row] = '';
    }

    $aDefRecurDOW[$row] = $et->getDefRecurDOW();
    $aDefRecurDOM[$row] = $et->getDefRecurDOM();
    $aDefRecurDOY[$row] = $et->getDefRecurDOY();
    $aDefRecurType[$row] = $et->getDefRecurType();

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
    $countNames = EventCountNameQuery::create()
        ->filterByTypeId((int)$aTypeID[$row])
        ->orderById()
        ->find();
    if (count($countNames) > 0) {
        $cCountName = [];
        foreach ($countNames as $cn) {
            $cCountName[] = $cn->getName();
        }
        $cCountList[$row] = implode(', ', $cCountName);
    } else {
        $cCountList[$row] = '';
    }
}

if (InputUtils::legacyFilterInput($_POST['Action']) === 'NEW') {
    ?>
  <div class='card mb-4'>
    <div class='card-header'>
      <h3 class="card-title mb-0"><i class="fa-solid fa-plus me-2"></i><?= gettext('Add New') . ' ' . gettext('Event Type') ?></h3>
    </div>
    <div class='card-body'>
      <form name="UpdateEventNames" action="EventNames.php" method="POST">
        <input type="hidden" name="theID" value="<?= $aTypeID[$row] ?>">
        
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label for="newEvtName" class="fw-bold"><?= gettext('Event Type Name') ?> <span class="text-danger">*</span></label>
              <input class="form-control" type="text" name="newEvtName" id="newEvtName" value="" maxlength="35" placeholder="<?= gettext('e.g., Sunday School, Bible Study...') ?>" autofocus required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-3">
              <label for="newEvtStartTime" class="fw-bold"><?= gettext('Default Start Time') ?></label>
              <div class="d-flex align-items-center" style="gap: 5px; max-width: 250px;">
                <select class="form-select" id="newEvtHour" name="newEvtHour" style="width: 70px;">
                  <?php
                  for ($h = 1; $h <= 12; $h++) {
                      $selected = ($h === 9) ? 'selected' : '';
                      echo '<option value="' . $h . '" ' . $selected . '>' . $h . '</option>';
                  }
                  ?>
                </select>
                <span>:</span>
                <select class="form-select" id="newEvtMinute" name="newEvtMinute" style="width: 70px;">
                  <?php
                  for ($m = 0; $m < 60; $m += 15) {
                      $min = str_pad($m, 2, '0', STR_PAD_LEFT);
                      $selected = ($m === 0) ? 'selected' : '';
                      echo '<option value="' . $min . '" ' . $selected . '>' . $min . '</option>';
                  }
                  ?>
                </select>
                <select class="form-select" id="newEvtPeriod" name="newEvtPeriod" style="width: 70px;">
                  <option value="AM" selected>AM</option>
                  <option value="PM">PM</option>
                </select>
              </div>
              <input type="hidden" name="newEvtStartTime" id="newEvtStartTime" value="9:00 AM">
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label class="fw-bold"><?= gettext('Recurrence Pattern') ?></label>
          <div class="event-recurrence-patterns border rounded p-3 bg-light">
            <div class="form-check mb-2">
              <input class="form-check-input" type="radio" name="newEvtTypeRecur" id="recurNone" value="none" checked>
              <label class="form-check-label" for="recurNone"><?= gettext('None (one-time event)') ?></label>
            </div>
            <div class="form-check mb-2 d-flex align-items-center">
              <input class="form-check-input" type="radio" name="newEvtTypeRecur" id="recurWeekly" value="weekly">
              <label class="form-check-label me-2" for="recurWeekly"><?= gettext('Weekly on') ?></label>
              <select name="newEvtRecurDOW" class="form-select form-select-sm" style="width: 150px;" disabled>
                <option value="1"><?= gettext('Sundays') ?></option>
                <option value="2"><?= gettext('Mondays') ?></option>
                <option value="3"><?= gettext('Tuesdays') ?></option>
                <option value="4"><?= gettext('Wednesdays') ?></option>
                <option value="5"><?= gettext('Thursdays') ?></option>
                <option value="6"><?= gettext('Fridays') ?></option>
                <option value="7"><?= gettext('Saturdays') ?></option>
              </select>
            </div>
            <div class="form-check mb-2 d-flex align-items-center">
              <input class="form-check-input" type="radio" name="newEvtTypeRecur" id="recurMonthly" value="monthly">
              <label class="form-check-label me-2" for="recurMonthly"><?= gettext('Monthly on the') ?></label>
              <select name="newEvtRecurDOM" class="form-select form-select-sm" style="width: 100px;" disabled>
                <?php for ($kk = 1; $kk <= 31; $kk++) {
                    $DOM = date('jS', mktime(0, 0, 0, 1, $kk, 2000)); ?>
                  <option value="<?= $kk ?>"><?= $DOM ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="form-check d-flex align-items-center">
              <input class="form-check-input" type="radio" name="newEvtTypeRecur" id="recurYearly" value="yearly">
              <label class="form-check-label me-2" for="recurYearly"><?= gettext('Yearly on') ?></label>
              <input type="text" class="form-control form-control-sm" name="newEvtRecurDOY" style="width: 150px;" placeholder="YYYY-MM-DD" data-provide="datepicker" disabled>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label for="newEvtTypeCntLst" class="fw-bold"><?= gettext('Attendance Count Categories') ?></label>
          <input class="form-control" type="text" name="newEvtTypeCntLst" id="newEvtTypeCntLst" value="" maxlength="50" placeholder="<?= gettext('Members, Visitors, Children') ?>">
          <small class="form-text text-muted">
            <?= gettext('Enter comma-separated count categories (e.g., Members, Visitors, Children).') ?>
          </small>
        </div>

        <hr>
        <div class="d-flex justify-content-between">
          <a href="EventNames.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-times me-1"></i><?= gettext('Cancel') ?>
          </a>
          <button type="submit" name="Action" value="CREATE" class="btn btn-primary">
            <i class="fa-solid fa-floppy-disk me-1"></i><?= gettext('Save Event Type') ?>
          </button>
        </div>
      </form>
    </div>
  </div>
    <?php
}

// Construct the form
?>
<div class="card">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title"><?= gettext('Event Types') ?></h3>
    <?php if (InputUtils::legacyFilterInput($_POST['Action']) !== 'NEW'): ?>
    <div class="card-tools ms-auto">
      <form name="AddEventNames" action="EventNames.php" method="POST" class="mb-0">
        <button type="submit" name="Action" value="NEW" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-plus me-1"></i><?= gettext('Add Event Type') ?>
        </button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <div class="card-body">
    <?php
    if ($numRows > 0) {
        ?>
      <div style="overflow: visible;">
      <table id="eventNames" class="table table-hover">
        <thead>
         <tr>
            <th><?= gettext('Name') ?></th>
            <th><?= gettext('Recurrence') ?></th>
            <th><?= gettext('Start Time') ?></th>
            <th><?= gettext('Attendance Counts') ?></th>
            <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php
            for ($row = 1; $row <= $numRows; $row++) {
                ?>
            <tr>
              <td><strong><?= InputUtils::escapeHTML($aTypeName[$row]) ?></strong></td>
              <td><?= $recur[$row] ?></td>
              <td><?= $aDefStartTime[$row] ?: '<span class="text-muted">—</span>' ?></td>
              <td>
                <?php if (!empty($cCountList[$row])): ?>
                  <?= InputUtils::escapeHTML($cCountList[$row]) ?>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="w-1">
                <div class="dropdown">
                    <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                        <i class="ti ti-dots-vertical"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <button type="button" class="dropdown-item" onclick="submitNewEvent(<?= (int)$aTypeID[$row] ?>)">
                            <i class="ti ti-plus me-2"></i><?= gettext('Create Event') ?>
                        </button>
                        <a class="dropdown-item" href="EditEventTypes.php?EN_tyid=<?= (int)$aTypeID[$row] ?>">
                            <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                        </a>
                        <div class="dropdown-divider"></div>
                        <button type="button" class="dropdown-item text-danger" onclick="deleteEventType(<?= (int)$aTypeID[$row] ?>)">
                            <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                        </button>
                    </div>
                </div>
              </td>
            </tr>
                <?php
            } ?>
        </tbody>
      </table>
      </div>
        <?php
    } else { ?>
      <div class="text-center text-muted py-4">
        <i class="fa-solid fa-calendar-days fa-3x mb-3"></i>
        <p><?= gettext('No event types defined yet.') ?></p>
        <p><?= gettext('Create an event type to get started.') ?></p>
      </div>
    <?php } ?>
  </div>
</div>

<?php
if (InputUtils::legacyFilterInput($_POST['Action']) !== 'NEW') {
    // Add button is now in card header
}
?>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/event/EventUtils.js"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>" >
  function submitNewEvent(tyid) {
    var f = document.createElement('form');
    f.method = 'POST';
    f.action = 'EventEditor.php';
    var i = document.createElement('input');
    i.type = 'hidden'; i.name = 'EN_tyid'; i.value = tyid;
    f.appendChild(i);
    var a = document.createElement('input');
    a.type = 'hidden'; a.name = 'Action'; a.value = '<?= gettext('Create Event') ?>';
    f.appendChild(a);
    document.body.appendChild(f);
    f.submit();
  }

  function deleteEventType(tyid) {
    bootbox.confirm({
      title: i18next.t('Delete Event Type'),
      message: i18next.t('Deleting this event type will NOT delete existing events. Are you sure?'),
      buttons: {
        confirm: { label: i18next.t('Yes'), className: 'btn-danger' },
        cancel:  { label: i18next.t('No'),  className: 'btn-default' }
      },
      callback: function(result) {
        if (result) {
          var f = document.createElement('form');
          f.method = 'POST';
          f.action = 'EventNames.php';
          var id = document.createElement('input');
          id.type = 'hidden'; id.name = 'theID'; id.value = tyid;
          f.appendChild(id);
          var act = document.createElement('input');
          act.type = 'hidden'; act.name = 'Action'; act.value = 'DELETE';
          f.appendChild(act);
          document.body.appendChild(f);
          f.submit();
        }
      }
    });
  }

  $(document).ready(function () {
    $('#eventNames').DataTable(window.CRM.plugin.dataTable);

    // Update hidden time field before form submission
    $('form[name="UpdateEventNames"]').on('submit', function() {
      const hour = $('#newEvtHour').val();
      const minute = $('#newEvtMinute').val();
      const period = $('#newEvtPeriod').val();
      if (hour && minute && period) {
        $('#newEvtStartTime').val(window.CRM.EventUtils.formatTime12Hour(hour, minute, period));
      }
    });

    // Event recurrence pattern form handling
    $(".event-recurrence-patterns input[type=radio]").change(function () {
        // Disable all recurrence inputs first
        $(".event-recurrence-patterns select, .event-recurrence-patterns input[type=text]").prop("disabled", true);
        
        // Enable only the input in the same form-check div as the selected radio
        $(this).closest(".form-check").find("select, input[type=text]").prop("disabled", false);
    });
  });
</script>
<?php
require_once __DIR__ . '/Include/Footer.php';
