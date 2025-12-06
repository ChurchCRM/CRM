<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

if (AuthenticationManager::getCurrentUser()->isAddEvent() === false) {
    RedirectUtils::securityRedirect('AddEvent');
}

$sPageTitle = gettext('Edit Event Types');

require_once 'Include/Header.php';

if (isset($_POST['Action'])) {
    switch (InputUtils::legacyFilterInput($_POST['Action'])) {
        case 'CREATE':
            $eName = $_POST['newEvtName'];
            $eTime = $_POST['newEvtStartTime'];
            $eDOM = $_POST['newEvtRecurDOM'];
            $eDOW = $_POST['newEvtRecurDOW'];
            $eDOY = $_POST['newEvtRecurDOY'];
            $eRecur = $_POST['newEvtTypeRecur'];
            $eCntLst = $_POST['newEvtTypeCntLst'];
            $eCntArray = array_filter(array_map('trim', explode(',', $eCntLst)));
            // Normally, the order should not matter. The problem arises if the
            // subscript already exists among others. Here, keeping it at the
            // head of the array would be needed in some (or most) PHP versions
            // especially if it is used in loops that take a total value without
            // being part of the rest of the array.
            array_unshift($eCntArray, 'Total');
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
    $aDefStartTime[$row] = $type_defstarttime;
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
      <h3 class="card-title mb-0"><i class="fas fa-plus mr-2"></i><?= gettext('Add New Event Type') ?></h3>
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
              <select class="form-control" name="newEvtStartTime" id="newEvtStartTime">
                <?php createTimeDropdown(7, 22, 15, '', ''); ?>
              </select>
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
            <?= gettext('Enter comma-separated count categories. "Total" is automatically included.') ?>
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
            <th style="width: 200px;"><?= gettext('Actions') ?></th>
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
              <td>
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

<script nonce="<?= SystemURLs::getCSPNonce() ?>" >
  $(document).ready(function () {
    $('#eventNames').DataTable(window.CRM.plugin.dataTable);

    // Event recurrence pattern form handling
    $(".event-recurrence-patterns input[type=radio]").change(function () {
        let $el = $(this);
        let $container = $el.closest(".row");
        let $input = $container
            .find("select, input[type=text]")
            .prop({ disabled: false });
        $container
            .parent()
            .find("select, input[type=text]")
            .not($input)
            .prop({ disabled: true });
    });
  });
</script>
<?php
require_once 'Include/Footer.php';
