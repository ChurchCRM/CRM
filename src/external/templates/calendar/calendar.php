<?php
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;

// Set the page title and include HTML header
$sPageTitle = $calendarName;
require(SystemURLs::getDocumentRoot() . "/Include/HeaderNotLoggedIn.php");
?>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/moment/moment-with-locales.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/fullcalendar/fullcalendar.min.js"></script>
<div class="register-box" style="width: 100%; margin-top:5px;">
    <div class="register-logo">
      <a href="<?= SystemURLs::getRootPath() ?>/"><?=  ChurchMetaData::getChurchName() ?></a>: <?= $calendarName ?></h1>
      <p></p>
    </div>
    <div class="row">
      <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-body no-padding">
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
window.CRM.fullcalendar =  $('#calendar').fullCalendar({
    header: {
      left: 'prev,next today',
      center: 'title',
      right: 'month,agendaWeek,agendaDay,listMonth'
    },
    height: 600,
    selectable: false,
    editable: false,
    eventStartEditable: false,
    eventDurationEditable: false,
    selectHelper: true,
    locale: window.CRM.lang,
    eventSources: [ 
      '<?= $eventSource ?>'
    ]
  });
</script>
  
<?php
// Add the page footer
require(SystemURLs::getDocumentRoot() . "/Include/FooterNotLoggedIn.php");
