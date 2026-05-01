<?php

use ChurchCRM\dto\SystemURLs;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="card">
  <div class="card-body">
    <!-- Title host — renderEventEditor renders the big inline title input here. -->
    <div id="event-editor-title-host" class="mb-3"></div>

    <!-- Form mount — renderEventEditor renders all fields (Type, Pinned Calendars,
         Date/Time, Description, Additional Information, + collapsible Advanced
         section with Active/Inactive, Linked Group, Attendance Counts) here. -->
    <div id="event-editor-mount">
      <div class="text-center py-5 text-body-secondary">
        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
        <?= gettext('Loading event editor…') ?>
      </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-4 d-none" id="event-editor-actions">
      <?php if ($eventExists && $eventId > 0): ?>
        <button type="button" class="btn btn-ghost-danger" id="event-editor-delete">
          <i class="ti ti-trash me-1"></i><?= gettext('Delete Event') ?>
        </button>
      <?php else: ?>
        <div></div>
      <?php endif; ?>
      <div class="d-flex gap-2">
        <a href="<?= $sRootPath ?>/event/dashboard" class="btn btn-secondary">
          <i class="ti ti-x me-1"></i><?= gettext('Cancel') ?>
        </a>
        <button type="button" class="btn btn-primary" id="event-editor-save" disabled>
          <i class="ti ti-device-floppy me-1"></i><?= gettext('Save Changes') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
window.CRM = window.CRM || {};
window.CRM.eventEditorPage = <?= json_encode([
    'eventId'      => $eventId > 0 ? (int) $eventId : 0,
    'typeId'       => $iTypeID > 0 ? (int) $iTypeID : 0,
    'eventExists'  => (bool) $eventExists,
    'redirectUrl'  => $sRootPath . '/event/dashboard',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/event-editor.min.js') ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
