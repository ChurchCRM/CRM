<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

if (AuthenticationManager::getCurrentUser()->isAddEvent() === false) {
    RedirectUtils::securityRedirect('AddEvent');
}

$sPageTitle = gettext('Edit Event Types');

require_once __DIR__ . '/Include/Header.php';

if (isset($_POST['Action'])) {
    switch (InputUtils::legacyFilterInput($_POST['Action'])) {
        case 'CREATE':
            $eName = $_POST['newEvtName'];
            $eTime = $_POST['newEvtStartTime'];
            
            // Convert time from 12-hour format (h:mm A) to 24-hour format (HH:mm:ss)
            if (!empty($eTime)) {
                $dateTime = \DateTime::createFromFormat('h:i A', $eTime);
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
            RedirectUtils::redirect('EventNames.php');
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

$sSQL = 'SELECT * FROM event_types ORDER BY type_id';
$rsOpps = RunQuery($sSQL);
$numRows = mysqli_num_rows($rsOpps);

for ($row = 1; $row <= $numRows; $row++) {
    $aRow = mysqli_fetch_array($rsOpps, MYSQLI_BOTH);
    extract($aRow);

    $aTypeID[$row] = $type_id;
    $aTypeName[$row] = $type_name;
    
    // Convert 24-hour time to 12-hour AM/PM format for display
    if ($type_defstarttime) {
        $dateTime = \DateTime::createFromFormat('H:i:s', $type_defstarttime);
        $aDefStartTime[$row] = $dateTime ? $dateTime->format('g:i A') : $type_defstarttime;
    } else {
        $aDefStartTime[$row] = '';
    }
    
    $aDefRecurDOW[$row] = $type_defrecurDOW;
    $aDefRecurDOM[$row] = $type_defrecurDOM;
    $aDefRecurDOY[$row] = $type_defrecurDOY;
    $aDefRecurType[$row] = $type_defrecurtype;

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
  <div class='card card-primary mb-4'>
    <div class='card-header'>
      <h3 class="card-title mb-0"><i class="fas fa-plus mr-2"></i><?= gettext('Add New') . ' ' . gettext('Event Type') ?></h3>
    </div>
    <div class='card-body'>
      <form name="UpdateEventNames" action="EventNames.php" method="POST">
        <input type="hidden" name="theID" value="<?= $aTypeID[$row] ?>">
        
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="newEvtName" class="font-weight-bold"><?= gettext('Event Type Name') ?> <span class="text-danger">*</span></label>
              <input class="form-control" type="text" name="newEvtName" id="newEvtName" value="" maxlength="35" placeholder="<?= gettext('e.g., Sunday School, Bible Study...') ?>" autofocus required>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="newEvtStartTime" class="font-weight-bold"><?= gettext('Default Start Time') ?></label>
              <div class="d-flex align-items-center" style="gap: 5px; max-width: 250px;">
                <select class="form-control" id="newEvtHour" name="newEvtHour" style="width: 70px;">
                  <?php
                  for ($h = 1; $h <= 12; $h++) {
                      $selected = ($h == 9) ? 'selected' : '';
                      echo '<option value="' . $h . '" ' . $selected . '>' . $h . '</option>';
                  }
                  ?>
                </select>
                <span>:</span>
                <select class="form-control" id="newEvtMinute" name="newEvtMinute" style="width: 70px;">
                  <?php
                  for ($m = 0; $m < 60; $m += 15) {
                      $min = str_pad($m, 2, '0', STR_PAD_LEFT);
                      $selected = ($m == 0) ? 'selected' : '';
                      echo '<option value="' . $min . '" ' . $selected . '>' . $min . '</option>';
                  }
                  ?>
                </select>
                <select class="form-control" id="newEvtPeriod" name="newEvtPeriod" style="width: 70px;">
                  <option value="AM" selected>AM</option>
                  <option value="PM">PM</option>
                </select>
              </div>
              <input type="hidden" name="newEvtStartTime" id="newEvtStartTime" value="9:00 AM">
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="font-weight-bold"><?= gettext('Recurrence Pattern') ?></label>
          <div class="event-recurrence-patterns border rounded p-3 bg-light">
            <div class="form-check mb-2">
              <input class="form-check-input" type="radio" name="newEvtTypeRecur" id="recurNone" value="none" checked>
              <label class="form-check-label" for="recurNone"><?= gettext('None (one-time event)') ?></label>
            </div>
            <div class="form-check mb-2 d-flex align-items-center">
              <input class="form-check-input" type="radio" name="newEvtTypeRecur" id="recurWeekly" value="weekly">
              <label class="form-check-label mr-2" for="recurWeekly"><?= gettext('Weekly on') ?></label>
              <select name="newEvtRecurDOW" class="form-control form-control-sm" style="width: 150px;" disabled>
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
              <label class="form-check-label mr-2" for="recurMonthly"><?= gettext('Monthly on the') ?></label>
              <select name="newEvtRecurDOM" class="form-control form-control-sm" style="width: 100px;" disabled>
                <?php for ($kk = 1; $kk <= 31; $kk++) {
                    $DOM = date('jS', mktime(0, 0, 0, 1, $kk, 2000)); ?>
                  <option value="<?= $kk ?>"><?= $DOM ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="form-check d-flex align-items-center">
              <input class="form-check-input" type="radio" name="newEvtTypeRecur" id="recurYearly" value="yearly">
              <label class="form-check-label mr-2" for="recurYearly"><?= gettext('Yearly on') ?></label>
              <input type="text" class="form-control form-control-sm" name="newEvtRecurDOY" style="width: 150px;" placeholder="YYYY-MM-DD" data-provide="datepicker" disabled>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label for="newEvtTypeCntLst" class="font-weight-bold"><?= gettext('Attendance Count Categories') ?></label>
          <input class="form-control" type="text" name="newEvtTypeCntLst" id="newEvtTypeCntLst" value="" maxlength="50" placeholder="<?= gettext('Members, Visitors, Children') ?>">
          <small class="form-text text-muted">
            <?= gettext('Enter comma-separated count categories (e.g., Members, Visitors, Children).') ?>
          </small>
        </div>

        <hr>
        <div class="d-flex justify-content-between">
          <a href="EventNames.php" class="btn btn-outline-secondary">
            <i class="fas fa-times mr-1"></i><?= gettext('Cancel') ?>
          </a>
          <button type="submit" name="Action" value="CREATE" class="btn btn-primary">
            <i class="fas fa-save mr-1"></i><?= gettext('Save Event Type') ?>
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
  <div class="card-header">
    <h3 class="card-title"><?= gettext('Event Types') ?></h3>
    <?php if (InputUtils::legacyFilterInput($_POST['Action']) != 'NEW'): ?>
    <div class="card-tools">
      <form name="AddEventNames" action="EventNames.php" method="POST" class="mb-0">
        <button type="submit" name="Action" value="NEW" class="btn btn-primary btn-sm">
          <i class="fas fa-plus mr-1"></i><?= gettext('Add Event Type') ?>
        </button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <div class="card-body">
    <?php
    if ($numRows > 0) {
        ?>
      <div class="table-responsive">
      <table id="eventNames" class="table table-striped table-hover">
        <thead>
         <tr>
            <th><?= gettext('Name') ?></th>
            <th><?= gettext('Recurrence') ?></th>
            <th><?= gettext('Start Time') ?></th>
            <th><?= gettext('Attendance Counts') ?></th>
            <th class="text-nowrap"><?= gettext('Actions') ?></th>
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
              <td class="text-nowrap">
                <form name="CreateEvent" action="EventEditor.php" method="POST" class="d-inline">
                  <input type="hidden" name="EN_tyid" value="<?= $aTypeID[$row] ?>">
                  <button type="submit" name="Action" value="<?= gettext('Create Event') ?>" class="btn btn-success btn-sm" title="<?= gettext('Create Event') ?>">
                    <i class="fas fa-plus mr-1"></i><?= gettext('Create') ?>
                  </button>
                </form>
                <a href="EditEventTypes.php?EN_tyid=<?= $aTypeID[$row] ?>" class="btn btn-outline-secondary btn-sm" title="<?= gettext('Edit') ?>">
                  <i class="fas fa-pen"></i>
                </a>
                <form name="DeleteEventType" action="EventNames.php" method="POST" class="d-inline" onsubmit="return confirm('<?= gettext('Deleting this event type will NOT delete existing events. Are you sure?') ?>');">
                  <input type="hidden" name="theID" value="<?= $aTypeID[$row] ?>">
                  <button type="submit" class="btn btn-outline-danger btn-sm" title="<?= gettext('Delete') ?>" name="Action" value="DELETE">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
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
        <i class="fas fa-calendar-alt fa-3x mb-3"></i>
        <p><?= gettext('No event types defined yet.') ?></p>
        <p><?= gettext('Create an event type to get started.') ?></p>
      </div>
    <?php } ?>
  </div>
</div>

<?php
if (InputUtils::legacyFilterInput($_POST['Action']) != 'NEW') {
    // Add button is now in card header
}
?>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/event/EventUtils.js"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>" >
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
