<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$sPageTitle = $calendarName;
$churchTz   = ChurchMetaData::getChurchTimeZone();

require SystemURLs::getDocumentRoot() ."/Include/HeaderNotLoggedIn.php";
?>
<script src="<?= SystemURLs::assetVersioned('/skin/external/fullcalendar/index.global.min.js') ?>"></script>
<div class="register-box w-100" style="margin-top:5px;">
    <div class="register-logo">
      <a href="<?= SystemURLs::getRootPath() ?>/"><?= ChurchMetaData::getChurchName() ?></a>: <?= InputUtils::escapeHTML($calendarName) ?>
      <?php if ($churchTz) : ?>
      <p class="text-muted small mb-0"><i class="ti ti-clock me-1"></i><?= gettext('All times shown in') ?> <?= InputUtils::escapeHTML($churchTz) ?></p>
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
          <i class="ti ti-clock me-2"></i><span id="eventDetailTimeText"></span>
        </div>
        <p class="mb-0" id="eventDetailDesc"></p>
      </div>
    </div>
  </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
document.addEventListener('DOMContentLoaded', function() {
  // Wait for the FullCalendar locale file (e.g. pt-br.js) to finish loading
  // and register before building the calendar, otherwise the toolbar buttons
  // (today / month / week / day / list) render with English defaults even
  // though the dates format correctly via the native Intl locale.
  window.CRM.onLocalesReady(function() {
  window.CRM.fullcalendar =  new FullCalendar.Calendar(document.getElementById('calendar'), {
      headerToolbar: {
        start: 'prev,next today',
        center: 'title',
        end: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
      },
      height: 600,
      selectable: false,
      editable: false,
      selectMirror: true,
      locale: window.CRM.lang,
      timeZone: '<?= InputUtils::escapeAttribute($churchTz ?: 'local') ?>',
      eventSources: [
        '<?= $eventSource ?>'
      ],
      eventClick: function(info) {
        info.jsEvent.preventDefault(); // prevent FullCalendar from following event.url

        var event = info.event;
        var props = event.extendedProps || {};

        document.getElementById('eventDetailModalLabel').textContent = event.title;

        // Use FullCalendar's own formatter — it applies the calendar's locale and timezone
        // so times are always shown in the church timezone regardless of the visitor's browser.
        var cal = window.CRM.fullcalendar;
        var dateFmt = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        var timeFmt = { hour: '2-digit', minute: '2-digit' };
        var timeStr = '';
        if (event.allDay) {
          timeStr = event.start ? cal.formatDate(event.start, dateFmt) : '';
        } else {
          var dateStr   = event.start ? cal.formatDate(event.start, dateFmt) : '';
          var startTime = event.start ? cal.formatDate(event.start, timeFmt) : '';
          var endTime   = event.end   ? cal.formatDate(event.end,   timeFmt) : '';
          timeStr = dateStr + (startTime ? ', ' + startTime : '') + (endTime ? ' – ' + endTime : '');
        }
        document.getElementById('eventDetailTimeText').textContent = timeStr;

        var descEl = document.getElementById('eventDetailDesc');
        descEl.textContent = props.description || '';
        descEl.style.display = props.description ? '' : 'none';

        bootstrap.Modal.getOrCreateInstance(document.getElementById('eventDetailModal')).show();
      }
  });

  window.CRM.fullcalendar.render();
  });
});
</script>

<?php
require SystemURLs::getDocumentRoot() ."/Include/FooterNotLoggedIn.php";
