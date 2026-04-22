<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();

require_once SystemURLs::getDocumentRoot() . '/Include/QuillEditorHelper.php';
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="mb-3 d-flex justify-content-between align-items-center">
  <a href="<?= $sRootPath ?>/event/dashboard" class="btn btn-outline-secondary">
    <i class="ti ti-chevron-left me-1"></i><?= gettext('Return to Events') ?>
  </a>
  <?php if ($eventExists && $eventId > 0): ?>
    <div class="d-flex gap-2">
      <a href="<?= $sRootPath ?>/event/checkin/<?= $eventId ?>" class="btn btn-info">
        <i class="ti ti-clipboard-check me-1"></i><?= gettext('Manage Check-ins') ?>
      </a>
      <button type="button" class="btn btn-outline-danger delete-event"
              data-event_id="<?= $eventId ?>"
              data-event_title="<?= InputUtils::escapeAttribute($sEventTitle) ?>">
        <i class="ti ti-trash me-1"></i><?= gettext('Delete Event') ?>
      </button>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-status-top bg-primary"></div>
  <div class="card-header">
    <h3 class="mb-0">
      <?= !$eventExists ? gettext('Create a new Event') : gettext('Editing Event') . ': ' . InputUtils::escapeHTML($sEventTitle ?: 'ID ' . $eventId) ?>
    </h3>
  </div>
  <div class="card-body">
    <p class="text-secondary mb-3"><span class="text-danger">*</span> <?= gettext('Required fields') ?></p>

    <form method="post" action="<?= $sRootPath ?>/event/editor" name="EventsEditor">
      <input type="hidden" name="eventId" value="<?= $eventId ?>">
      <input type="hidden" name="EventExists" value="<?= $eventExists ? 1 : 0 ?>">

      <?php if (empty($iTypeID)): ?>
        <div class="row mb-3">
          <label for="event_type_id" class="col-md-3 col-form-label text-md-end fw-semibold">
            <span class="text-danger">*</span><?= gettext('Event Type') ?>
          </label>
          <div class="col-md-9">
            <select name="typeId" class="form-select" id="event_type_id">
              <option><?= gettext('Select your event type') ?></option>
              <?php foreach ($eventTypes as $et): ?>
                <option value="<?= (int) $et->getId() ?>"><?= InputUtils::escapeHTML($et->getName()) ?></option>
              <?php endforeach; ?>
            </select>
            <script nonce="<?= SystemURLs::getCSPNonce() ?>">
              $('#event_type_id').on('change', function(e) {
                e.preventDefault();
                window.location.href = '<?= $sRootPath ?>/event/editor?typeId=' + this.value;
              });
            </script>
          </div>
        </div>
      <?php else: ?>
        <div class="row mb-3">
          <div class="col-md-3 col-form-label text-md-end fw-semibold">
            <span class="text-danger">*</span><?= gettext('Event Type') ?>
          </div>
          <div class="col-md-9 d-flex align-items-center">
            <input type="hidden" name="EventTypeName" value="<?= InputUtils::escapeAttribute($sTypeName) ?>">
            <input type="hidden" name="typeId" value="<?= (int) $iTypeID ?>">
            <span class="badge bg-info-lt text-info" style="font-size: 1rem;">
              <?= InputUtils::escapeHTML($sTypeName) ?>
            </span>
          </div>
        </div>
        <div class="row mb-3">
          <label for="EventTitle" class="col-md-3 col-form-label text-md-end fw-semibold">
            <span class="text-danger">*</span><?= gettext('Event Title') ?>
          </label>
          <div class="col-md-9">
            <input type="text" name="EventTitle" id="EventTitle" value="<?= InputUtils::escapeAttribute($sEventTitle) ?>"
                   maxlength="100" class="form-control" placeholder="<?= gettext('Enter event title...') ?>" required>
          </div>
        </div>
        <div class="row mb-3 event-editor-advanced" <?= !$eventExists ? 'style="display:none;"' : '' ?>>
          <label class="col-md-3 col-form-label text-md-end fw-semibold"><?= gettext('Event Description') ?></label>
          <div class="col-md-9">
            <?= getQuillEditorContainer('EventDesc', 'EventDescInput', $sEventDesc, '', 'compact') ?>
          </div>
        </div>
        <div class="row mb-3">
          <label for="EventDateRange" class="col-md-3 col-form-label text-md-end fw-semibold">
            <span class="text-danger">*</span><?= gettext('Date & Time') ?>
          </label>
          <div class="col-md-9">
            <input type="text" name="EventDateRange" id="EventDateRange"
                   class="form-control" style="max-width: 400px;" required>
            <small class="form-text text-secondary">
              <?= gettext('Select start and end date/time.') ?>
              <?php if (!$eventExists && $iTypeID > 0): ?>
                <span class="text-info">
                  <i class="ti ti-sparkles me-1"></i>
                  <?= gettext('Pre-filled based on this event type — for recurring types, the date is the next occurrence after the last event of this type, and the time matches the type\'s default start time.') ?>
                </span>
              <?php endif; ?>
            </small>
          </div>
        </div>
        <?php if ($showLinkedGroup): ?>
          <div class="row mb-3 event-editor-advanced" <?= !$eventExists ? 'style="display:none;"' : '' ?>>
            <label for="LinkedGroupId" class="col-md-3 col-form-label text-md-end fw-semibold">
              <?= gettext('Linked Group') ?>
              <span class="d-block text-secondary small fw-normal mt-1">
                <i class="ti ti-info-circle me-1"></i><?= gettext('Required for Kiosk') ?>
              </span>
            </label>
            <div class="col-md-9">
              <select name="LinkedGroupId" id="LinkedGroupId" class="form-select" style="max-width: 400px;">
                <option value="0"><?= gettext('No Group') ?></option>
                <?php foreach ($groups as $group): ?>
                  <option value="<?= (int) $group->getId() ?>" <?= ($iLinkedGroupId === (int) $group->getId()) ? 'selected' : '' ?>>
                    <?= InputUtils::escapeHTML($group->getName()) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="form-text text-secondary mt-2 d-block">
                <strong><?= gettext('What is this for?') ?></strong>
                <?= gettext('A linked group ties this event to a class or ministry roster. When the event is assigned to a Kiosk, the kiosk pulls the group members and shows them as a tap-to-check-in list. For Sunday School classes, link the class group here so volunteers can check students in from a tablet.') ?>
              </small>
            </div>
          </div>
        <?php endif; ?>
        <div class="row mb-3">
          <div class="col-md-3 col-form-label text-md-end fw-semibold">
            <div><?= gettext('Attendance Counts') ?></div>
            <?php if (!empty($counts)): ?>
              <div class="mt-2">
                <input type="number" id="RealTotal" class="form-control" readonly value="0"
                       style="background-color: #e9ecef; font-weight: bold; max-width: 200px;">
                <small class="form-text text-secondary"><?= gettext('Auto-totalled from counts at right') ?></small>
              </div>
            <?php endif; ?>
          </div>
          <div class="col-md-9">
            <input type="hidden" name="NumAttendCounts" value="<?= count($counts) ?>">
            <?php if (empty($counts)): ?>
              <div class="alert alert-info mb-0">
                <i class="ti ti-info-circle me-1"></i>
                <?= gettext('No attendance count categories defined for this event type yet.') ?>
                <a href="<?= $sRootPath ?>/event/types/<?= (int) $iTypeID ?>" class="alert-link">
                  <?= gettext('Add categories on the Event Type page') ?>
                </a>
                <?= gettext('(e.g. Members, Visitors, Children) so volunteers can record headcounts here.') ?>
              </div>
            <?php else: ?>
              <small class="form-text text-secondary mb-2 d-block">
                <strong><?= gettext('What are these for?') ?></strong>
                <?= gettext('These are headcount buckets — volunteers fill them in after the event so the dashboard can break down attendance (e.g. how many members vs. visitors). They are separate from individual check-ins on the Check-in page.') ?>
              </small>
              <div class="row">
                <?php foreach ($counts as $i => $c): ?>
                  <div class="col-sm-6 col-md-4 mb-2">
                    <label for="EventCount_<?= $i ?>" class="fw-bold"><?= InputUtils::escapeHTML($c['name']) ?></label>
                    <input type="number" id="EventCount_<?= $i ?>" name="EventCount[]"
                           value="<?= (int) $c['count'] ?>" class="form-control attendance-count" min="0"
                           data-count-name="<?= InputUtils::escapeAttribute($c['name']) ?>">
                    <input type="hidden" name="EventCountID[]" value="<?= (int) $c['id'] ?>">
                    <input type="hidden" name="EventCountName[]" value="<?= InputUtils::escapeAttribute($c['name']) ?>">
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="mb-3 mt-3">
                <label for="EventCountNotes" class="fw-bold"><?= gettext('Attendance Notes') ?></label>
                <input type="text" id="EventCountNotes" name="EventCountNotes"
                       value="<?= InputUtils::escapeAttribute($sCountNotes) ?>" class="form-control"
                       placeholder="<?= gettext('Optional notes about attendance...') ?>">
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="row mb-3 event-editor-advanced" <?= !$eventExists ? 'style="display:none;"' : '' ?>>
          <label class="col-md-3 col-form-label text-md-end fw-semibold"><?= gettext('Sermon / Event Text') ?></label>
          <div class="col-md-9">
            <small class="form-text text-secondary mb-2"><?= gettext('Optional - Add sermon notes or additional event details') ?></small>
            <?= getQuillEditorContainer('EventText', 'EventTextInput', $sEventText, '', 'default') ?>
          </div>
        </div>
        <div class="row mb-3 event-editor-advanced" <?= !$eventExists ? 'style="display:none;"' : '' ?>>
          <label class="col-md-3 col-form-label text-md-end fw-semibold">
            <span class="text-danger">*</span><?= gettext('Event Status') ?>
          </label>
          <div class="col-md-9">
            <div class="btn-group" role="group">
              <input type="radio" class="btn-check" name="EventStatus" id="statusActive" value="0" <?= ($iEventStatus === 0) ? 'checked' : '' ?> autocomplete="off">
              <label class="btn btn-outline-success" for="statusActive">
                <i class="ti ti-check me-1"></i><?= gettext('Active') ?>
              </label>
              <input type="radio" class="btn-check" name="EventStatus" id="statusInactive" value="1" <?= ($iEventStatus === 1) ? 'checked' : '' ?> autocomplete="off">
              <label class="btn btn-outline-secondary" for="statusInactive">
                <i class="ti ti-ban me-1"></i><?= gettext('Inactive') ?>
              </label>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-9 offset-md-3">
            <button type="button" id="toggleAdvancedBtn" class="btn btn-link p-0 mb-3 d-block">
              <i class="ti ti-chevron-down me-1" id="toggleAdvancedIcon"></i>
              <span id="toggleAdvancedLabel"><?= !$eventExists ? gettext('Show More Options') : gettext('Hide Advanced Options') ?></span>
            </button>
            <button type="submit" name="SaveChanges" value="<?= gettext('Save Changes') ?>" class="btn btn-primary">
              <i class="ti ti-device-floppy me-1"></i><?= gettext('Save Changes') ?>
            </button>
          </div>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php
$startStr = sprintf('%s %s:%s', $defaults['sEventStartDate'], $defaults['iEventStartHour'], $defaults['iEventStartMins']);
$endStr = sprintf('%s %s:%s', $defaults['sEventEndDate'], $defaults['iEventEndHour'], $defaults['iEventEndMins']);
?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
window.CRM = window.CRM || {};
window.CRM.eventEditor = <?= json_encode([
    'startStr' => $startStr,
    'endStr' => $endStr,
    'eventExists' => (bool) $eventExists,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>" src="<?= SystemURLs::assetVersioned('/skin/v2/event-editor.min.js') ?>"></script>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
(function() {
  <?= getQuillEditorInitScript('EventDesc', 'EventDescInput', gettext("Enter event description..."), false) ?>
})();

(function() {
  <?= getQuillEditorInitScript('EventText', 'EventTextInput', gettext("Enter sermon notes or event text..."), false) ?>
})();
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
