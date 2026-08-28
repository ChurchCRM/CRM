<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$sPageTitle = $calendarName;
$churchTz   = ChurchMetaData::getChurchTimeZone();

// Allow this page to be embedded in a third-party <iframe>.
// See src/Include/Header-Security.php for the opt-in mechanism.
$allowFraming = true;

require SystemURLs::getDocumentRoot() ."/Include/HeaderNotLoggedIn.php";
?>
<!-- FullCalendar v7 CSS (webpack-extracted from external-calendar.js: skeleton + Forma theme + blue palette) -->
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/external-calendar.min.css') ?>">
<div class="register-box w-100" style="margin-top:5px;">
    <div class="register-logo">
      <a href="<?= SystemURLs::getRootPath() ?>/"><?= InputUtils::escapeHTML(ChurchMetaData::getChurchName()) ?></a>: <?= InputUtils::escapeHTML($calendarName) ?>
      <?php if ($churchTz) : ?>
      <p class="text-muted small mb-0"><i class="fa-solid fa-clock me-1"></i><?= gettext('All times shown in') ?> <?= InputUtils::escapeHTML($churchTz) ?></p>
      <?php endif; ?>
    </div>
    <div class="row">
      <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                <!-- THE CALENDAR -->
                <div id="calendar"></div>
            </div>
        </div>
        <!-- /. box -->
      </div>
    </div>
</div>

<!-- Event detail modal -->
<div class="modal fade" id="eventDetailModal" tabindex="-1" aria-labelledby="eventDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-semibold" id="eventDetailModalLabel"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center text-body-secondary small mb-3" id="eventDetailTime">
          <i class="fa-solid fa-clock me-2"></i><span id="eventDetailTimeText"></span>
        </div>
        <p class="mb-0" id="eventDetailDesc"></p>
      </div>
    </div>
  </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
// Server-side data for the FullCalendar public calendar.
// Read by webpack/external-calendar.js after DOMContentLoaded.
window.CRM = window.CRM || {};
window.CRM.externalCalendarArgs = <?= json_encode([
    'eventSource' => $eventSource,
    'timeZone'    => $churchTz ?: 'local',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/external-calendar.min.js') ?>"></script>

<?php
require SystemURLs::getDocumentRoot() ."/Include/FooterNotLoggedIn.php";
