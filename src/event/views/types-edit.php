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
    <?php
      // Convert "9:00 AM" → "09:00" for the native time input
      $start24h = '09:00';
      $dtParse = \DateTime::createFromFormat('g:i A', $startTimeDisplay);
      if ($dtParse) {
          $start24h = $dtParse->format('H:i');
      }
    ?>
    <form method="POST" action="<?= $sRootPath ?>/event/types/<?= (int) $eventType->getId() ?>" name="EventTypeEditForm">
      <input type="hidden" name="Action" value="TIME">
      <div class="mb-3">
        <label class="form-label fw-bold" for="newEvtStartTime"><?= gettext('Default Start Time') ?></label>
        <div class="row g-2 align-items-center">
          <div class="col-auto">
            <input type="time"
                   class="form-control"
                   id="newEvtStartTime"
                   name="newEvtStartTime"
                   value="<?= InputUtils::escapeAttribute($start24h) ?>"
                   style="max-width: 160px;" />
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary">
              <i class="ti ti-device-floppy me-1"></i><?= gettext('Save Time') ?>
            </button>
          </div>
        </div>
        <small class="text-body-secondary"><?= gettext('Used as the default start time when creating events of this type.') ?></small>
      </div>
    </form>

    <!-- Counts Form -->
    <form method="POST" action="<?= $sRootPath ?>/event/types/<?= (int) $eventType->getId() ?>">
      <div class="mb-3">
        <label class="form-label fw-bold"><?= gettext('Attendance Count Categories') ?></label>
        <div class="table-responsive">
          <table class="table table-sm table-vcenter">
            <thead>
              <tr>
                <th><?= gettext('Category Name') ?></th>
                <th class="no-export text-end w-1"><?= gettext('Actions') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($counts as $c): ?>
                <?php $cid = (int) $c['id']; ?>
                <tr data-cy="attendance-count-row">
                  <td>
                    <input
                      class="form-control form-control-sm"
                      type="text"
                      name="countName_<?= $cid ?>"
                      value="<?= InputUtils::escapeAttribute($c['name']) ?>"
                      maxlength="20"
                      data-cy="attendance-count-edit-input" />
                  </td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <button type="submit" name="Action" value="RENAME_<?= $cid ?>"
                              class="btn btn-outline-primary"
                              data-cy="rename-attendance-count"
                              title="<?= gettext('Save changes to this category name') ?>">
                        <i class="ti ti-device-floppy me-1"></i><?= gettext('Save') ?>
                      </button>
                      <button type="submit" name="Action" value="DELETE_<?= $cid ?>"
                              class="btn btn-outline-danger"
                              data-cy="remove-attendance-count"
                              data-confirm="<?= InputUtils::escapeAttribute(gettext('Remove this attendance count?')) ?>">
                        <i class="ti ti-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <tr class="table-light">
                <td>
                  <input class="form-control form-control-sm" type="text" name="newCountName" maxlength="20"
                         placeholder="<?= gettext('e.g., Visitors, Children') ?>" data-cy="attendance-count-input" />
                </td>
                <td class="text-end">
                  <button type="submit" name="Action" value="ADD" class="btn btn-primary btn-sm"
                          data-cy="add-attendance-count">
                    <i class="ti ti-plus me-1"></i><?= gettext('Add') ?>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <small class="text-body-secondary">
          <?= gettext('Edit a category name and click Save, or use the trash icon to remove. New rows are added via the bottom field.') ?>
        </small>
      </div>
    </form>
  </div>
</div>

<div class="mt-3">
  <a href="<?= $sRootPath ?>/event/types" class="btn btn-outline-secondary">
    <i class="ti ti-chevron-left me-1"></i><?= gettext('Return to Event Types') ?>
  </a>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
window.CRM = window.CRM || {};
window.CRM.eventTypeForm = {
  mode: 'edit',
  currentTime: <?= json_encode($startTimeDisplay) ?>
};
</script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>" src="<?= SystemURLs::assetVersioned('/skin/v2/event-types.min.js') ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
