<?php

use ChurchCRM\dto\SystemURLs;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="card">
  <div class="card-status-top bg-primary"></div>
  <div class="card-header">
    <h3 class="card-title mb-0">
      <i class="ti ti-plus me-2"></i><?= gettext('Add New') . ' ' . gettext('Event Type') ?>
    </h3>
  </div>
  <div class="card-body">
    <form name="UpdateEventNames" action="<?= $sRootPath ?>/event/types/new" method="POST">
      <div class="row">
        <div class="col-md-6">
          <div class="mb-3">
            <label for="newEvtName" class="fw-bold form-label">
              <?= gettext('Event Type Name') ?> <span class="text-danger">*</span>
            </label>
            <input class="form-control" type="text" name="newEvtName" id="newEvtName"
                   maxlength="35"
                   placeholder="<?= gettext('e.g., Sunday School, Bible Study...') ?>"
                   autofocus required>
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="newEvtStartTime" class="fw-bold form-label"><?= gettext('Default Start Time') ?></label>
            <div class="d-flex align-items-center" style="gap: 5px; max-width: 250px;">
              <select class="form-select" id="newEvtHour" name="newEvtHour" style="width: 70px;">
                <?php for ($h = 1; $h <= 12; $h++): ?>
                  <option value="<?= $h ?>" <?= $h === 9 ? 'selected' : '' ?>><?= $h ?></option>
                <?php endfor; ?>
              </select>
              <span>:</span>
              <select class="form-select" id="newEvtMinute" name="newEvtMinute" style="width: 70px;">
                <?php for ($m = 0; $m < 60; $m += 15):
                    $min = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                  <option value="<?= $min ?>" <?= $m === 0 ? 'selected' : '' ?>><?= $min ?></option>
                <?php endfor; ?>
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
        <label class="fw-bold form-label"><?= gettext('Recurrence Pattern') ?></label>
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
              <?php for ($k = 1; $k <= 31; $k++): ?>
                <option value="<?= $k ?>"><?= date('jS', mktime(0, 0, 0, 1, $k, 2000)) ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="form-check d-flex align-items-center">
            <input class="form-check-input" type="radio" name="newEvtTypeRecur" id="recurYearly" value="yearly">
            <label class="form-check-label me-2" for="recurYearly"><?= gettext('Yearly on') ?></label>
            <input type="text" class="form-control form-control-sm" name="newEvtRecurDOY"
                   style="width: 150px;" placeholder="YYYY-MM-DD" disabled>
          </div>
        </div>
      </div>

      <div class="mb-3">
        <label for="newEvtTypeCntLst" class="fw-bold form-label"><?= gettext('Attendance Count Categories') ?></label>
        <input class="form-control" type="text" name="newEvtTypeCntLst" id="newEvtTypeCntLst"
               maxlength="50" placeholder="<?= gettext('Members, Visitors, Children') ?>">
        <small class="form-text text-muted">
          <?= gettext('Enter comma-separated count categories (e.g., Members, Visitors, Children).') ?>
        </small>
      </div>

      <hr>
      <div class="d-flex justify-content-between">
        <a href="<?= $sRootPath ?>/event/types" class="btn btn-outline-secondary">
          <i class="ti ti-x me-1"></i><?= gettext('Cancel') ?>
        </a>
        <button type="submit" class="btn btn-primary">
          <i class="ti ti-device-floppy me-1"></i><?= gettext('Save Event Type') ?>
        </button>
      </div>
    </form>
  </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
window.CRM = window.CRM || {};
window.CRM.eventTypeForm = { mode: 'new' };
</script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>" src="<?= SystemURLs::assetVersioned('/skin/v2/event-types.min.js') ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
