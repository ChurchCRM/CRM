<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;

$sPageTitle = $calendarName;
require(SystemURLs::getDocumentRoot() ."/Include/HeaderNotLoggedIn.php");
?>
<script src="<?= SystemURLs::assetVersioned('/skin/external/moment/moment-with-locales.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/external/fullcalendar/index.global.min.js') ?>"></script>
<div class="register-box w-100" style="margin-top:5px;">
    <div class="register-logo">
      <a href="<?= SystemURLs::getRootPath() ?>/"><?=  ChurchMetaData::getChurchName() ?></a>: <?= $calendarName ?></h1>
      <p></p>
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
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
      timeZone: '<?= ChurchMetaData::getChurchTimeZone() ?>',
      eventSources: [
        '<?= $eventSource ?>'
      ],
      eventClick: function(info) {
        info.jsEvent.preventDefault(); // prevent FullCalendar from following event.url

        var event = info.event;
        var props = event.extendedProps || {};

        document.getElementById('eventDetailModalLabel').textContent = event.title;

        // Format date/time
        var timeStr = '';
        if (event.allDay) {
          timeStr = event.start ? event.start.toLocaleDateString([], { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '';
        } else {
          var dateStr = event.start ? event.start.toLocaleDateString([], { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '';
          var startTime = event.start ? event.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
          var endTime   = event.end   ? event.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
          timeStr = dateStr + (startTime ? ', ' + startTime : '') + (endTime ? ' – ' + endTime : '');
        }
        document.getElementById('eventDetailTimeText').textContent = timeStr;

        var descEl = document.getElementById('eventDetailDesc');
        descEl.textContent = props.description || '';
        descEl.style.display = props.description ? '' : 'none';

        var modal = new bootstrap.Modal(document.getElementById('eventDetailModal'));
        modal.show();
      }
  });

  window.CRM.fullcalendar.render();
});
</script>

<?php
require(SystemURLs::getDocumentRoot() ."/Include/FooterNotLoggedIn.php");
