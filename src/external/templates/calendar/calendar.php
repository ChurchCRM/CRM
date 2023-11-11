<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;

// Set the page title and include HTML header
$sPageTitle = $calendarName;
require(SystemURLs::getDocumentRoot() . "/Include/HeaderNotLoggedIn.php");
?>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/moment/moment-with-locales.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/fullcalendar/index.global.min.js"></script>
<div class="register-box" style="width: 100%; margin-top:5px;">
    <div class="register-logo">
      <a href="<?= SystemURLs::getRootPath() ?>/"><?=  ChurchMetaData::getChurchName() ?></a>: <?= $calendarName ?></h1>
      <p></p>
    </div>
    <div class="row">
      <div class="col-xs-12">
        <div class="card card-info">
            <div class="card-body no-padding">
                <!-- THE CALENDAR -->
                <div id="calendar"></div>
            </div>
            <!-- /.box-body -->
        </div>
        <!-- /. box -->
      </div>
    </div>
</div>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
document.addEventListener('DOMContentLoaded', function() {
  window.CRM.fullcalendar =  new FullCalendar.Calendar(document.getElementById('calendar'), {
      header: {
        start: 'prev,next today',
        center: 'title',
        end: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
      },
      height: 600,
      selectable: false,
      editable: false,
      selectMirror: true,
      locale: window.CRM.lang,
      eventSources: [
        '<?= $eventSource ?>'
      ]
  });

  calendar.render();
});
</script>

<?php
// Add the page footer
require(SystemURLs::getDocumentRoot() . "/Include/FooterNotLoggedIn.php");
