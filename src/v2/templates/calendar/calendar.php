<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/SimpleConfig.php';
require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>
<div class="row">
  <div class="col-lg-9">
    <div class="box box-info">
        <div class="box-body no-padding">
            <!-- THE CALENDAR -->
            <div id="calendar"></div>
        </div>
        <!-- /.box-body -->
    </div>
    <!-- /. box -->
  </div>
   <div class="col-lg-3" class="panel-group" id="CalendarTypesPanel" style="height:600px; overflow-y: scroll">
    <div class="panel" >
        <div class="panel-heading">
          <h3 class="panel-title" data-toggle="collapse" data-parent="#CalendarTypesPanel" href="#userCalendarCollapse" aria-expanded="true"><?= gettext('User Calendars') ?></h3>
          <a id="showAllUser">Show All</a>
          
        </div>
        <div class="panel-collapse collapse in" id="userCalendarCollapse">
          <div class="panel-body"  id="userCalendars"></div>
        </div>
    </div>
    <div class="panel">
     <div class="panel-heading">
       <h3 class="panel-title" data-toggle="collapse" data-parent="#CalendarTypesPanel" href="#systemCalendarCollapse" aria-expanded="false" ><?= gettext('System Calendars') ?></h3>
     </div>
     <div class="panel-collapse collapse" id="systemCalendarCollapse">
       <div class="panel-body" id="systemCalendars" ></div>
     </div>
    </div>
  </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  window.CRM.calendarJSArgs = <?= json_encode($calendarJSArgs) ?>;
</script>

<script src="<?= SystemURLs::getRootPath() ?>/skin/external/ckeditor/ckeditor.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/Calendar.js" ></script>

<?php 
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
?>
