<?php

use ChurchCRM\dto\SystemURLs;

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
   <div class="col-lg-3 nav-tabs-custom" id="CalendarTypesPanel" >
    <ul class="nav nav-tabs">
      <li class="active"><a data-toggle="tab" href="#userCalendars">User</a></li>
      <li><a data-toggle="tab" href="#systemCalendars">System</a></li>
    </ul>
    <div class="tab-content" style="height:600px; overflow-y: scroll">
       
      <div class="tab-pane fade in active"  id="userCalendars"></div>
      <div class="tab-pane fade" id="systemCalendars" ></div>
      
    </div>
    
  </div>
</div>

<div id="calendar-event-react-app"></div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  window.CRM.calendarJSArgs = <?= json_encode($calendarJSArgs) ?>;
</script>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js-react/calendar-event-editor-app.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/ckeditor/ckeditor.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/Calendar.js" ></script>

<?php 
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
?>
