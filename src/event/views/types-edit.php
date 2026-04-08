<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="card">
  <div class="card-status-top bg-primary"></div>
  <div class="card-header">
    <h3 class="card-title mb-0">
      <i class="ti ti-pencil me-2"></i><?= gettext('Edit Event Type') ?>: <?= InputUtils::escapeHTML($eventType->getName()) ?>
    </h3>
  </div>
  <div class="card-body">
    <!-- Name + Settings Form -->
    <form method="POST" action="<?= $sRootPath ?>/event/types/<?= (int) $eventType->getId() ?>">
      <div class="mb-3">
        <label for="newEvtName" class="form-label fw-bold"><?= gettext('Event Type Name') ?></label>
        <div class="row">
          <div class="col-md-8">
            <input type="text" class="form-control" name="newEvtName" id="newEvtName"
                   value="<?= InputUtils::escapeAttribute($eventType->getName()) ?>"
                   maxlength="35" autofocus />
          </div>
          <div class="col-md-4">
            <button type="submit" name="Action" value="NAME" class="btn btn-primary">
              <i class="ti ti-device-floppy me-1"></i><?= gettext('Save Name') ?>
            </button>
          </div>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold"><?= gettext('Settings') ?></label>
        <div class="row align-items-end">
          <div class="col-md-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" value="1" id="type_active" name="type_active"
                     <?= ((int) $eventType->getActive() === 1) ? 'checked' : '' ?> />
              <label class="form-check-label" for="type_active"><?= gettext('Active') ?></label>
            </div>
          </div>
          <div class="col-md-6">
            <label class="visually-hidden" for="type_grpid"><?= gettext('Linked Group') ?></label>
            <select class="form-select" id="type_grpid" name="type_grpid">
              <option value="0"><?= gettext('No Group') ?></option>
              <?php foreach ($groups as $group): ?>
                <option value="<?= (int) $group->getId() ?>" <?= ((int) $eventType->getGroupId() === (int) $group->getId()) ? 'selected' : '' ?>>
                  <?= InputUtils::escapeHTML($group->getName()) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <button type="submit" name="Action" value="SAVE" class="btn btn-secondary">
              <?= gettext('Save Settings') ?>
            </button>
          </div>
        </div>
      </div>
    </form>

    <div class="mb-3">
      <label class="form-label fw-bold"><?= gettext('Recurrence Pattern') ?></label>
      <div class="border rounded p-3 bg-light">
        <?= InputUtils::escapeHTML($recurText) ?>
      </div>
    </div>

    <!-- Time Form -->
    <form method="POST" action="<?= $sRootPath ?>/event/types/<?= (int) $eventType->getId() ?>" name="EventTypeEditForm">
      <input type="hidden" name="Action" value="TIME">
      <div class="mb-3">
        <label class="form-label fw-bold"><?= gettext('Default Start Time') ?></label>
        <div class="d-flex align-items-center" style="gap: 5px; max-width: 250px;">
          <select class="form-select" id="EventHour" name="EventHour" style="width: 70px;">
            <?php for ($h = 1; $h <= 12; $h++): ?>
              <option value="<?= $h ?>"><?= $h ?></option>
            <?php endfor; ?>
          </select>
          <span>:</span>
          <select class="form-select" id="EventMinute" name="EventMinute" style="width: 70px;">
            <?php for ($m = 0; $m < 60; $m += 15):
                $min = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
              <option value="<?= $min ?>"><?= $min ?></option>
            <?php endfor; ?>
          </select>
          <select class="form-select" id="EventPeriod" name="EventPeriod" style="width: 70px;">
            <option value="AM">AM</option>
            <option value="PM">PM</option>
          </select>
        </div>
        <input type="hidden" name="newEvtStartTime" id="newEvtStartTime" value="<?= InputUtils::escapeAttribute($startTimeDisplay) ?>">
      </div>
    </form>

    <!-- Counts Form -->
    <form method="POST" action="<?= $sRootPath ?>/event/types/<?= (int) $eventType->getId() ?>">
      <div class="mb-3">
        <label class="form-label fw-bold"><?= gettext('Attendance Count Categories') ?></label>
        <div class="table-responsive">
          <table class="table table-sm table-bordered">
            <thead>
              <tr>
                <th><?= gettext('Category Name') ?></th>
                <th style="width: 120px;" class="no-export"><?= gettext('Actions') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($counts as $c): ?>
                <tr data-cy="attendance-count-row">
                  <td><?= InputUtils::escapeHTML($c['name']) ?></td>
                  <td class="text-center">
                    <button type="submit" name="Action" value="DELETE_<?= (int) $c['id'] ?>"
                            class="btn btn-outline-danger btn-sm" data-cy="remove-attendance-count"
                            onclick="return confirm('<?= gettext('Remove this attendance count?') ?>');">
                      <i class="ti ti-trash me-1"></i><?= gettext('Remove') ?>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
              <tr>
                <td>
                  <input class="form-control form-control-sm" type="text" name="newCountName" maxlength="20"
                         placeholder="<?= gettext('e.g., Visitors, Children') ?>" data-cy="attendance-count-input" />
                </td>
                <td class="text-center">
                  <button type="submit" name="Action" value="ADD" class="btn btn-primary btn-sm"
                          data-cy="add-attendance-count">
                    <i class="ti ti-plus me-1"></i><?= gettext('Add') ?>
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

<div class="mt-3">
  <a href="<?= $sRootPath ?>/event/types" class="btn btn-outline-secondary">
    <i class="ti ti-chevron-left me-1"></i><?= gettext('Return to Event Types') ?>
  </a>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/event-types.min.js') ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
  const currentTime = '<?= InputUtils::escapeAttribute($startTimeDisplay) ?>';
  window.CRM.EventUtils.initializeTimePicker(currentTime, 'EventHour', 'EventMinute', 'EventPeriod');
  window.CRM.EventUtils.setupTimePickerAutoSubmit(
    'form[name="EventTypeEditForm"]',
    'EventHour', 'EventMinute', 'EventPeriod',
    'newEvtStartTime', currentTime
  );
});
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
