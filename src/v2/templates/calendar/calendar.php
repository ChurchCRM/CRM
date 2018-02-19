<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/SimpleConfig.php';
require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>
<div class="row">
  <div class="col-lg-10">
    <div class="box box-info">
        <div class="box-body no-padding">
            <!-- THE CALENDAR -->
            <div id="calendar"></div>
        </div>
        <!-- /.box-body -->
    </div>
    <!-- /. box -->
  </div>
   <div class="col-lg-2">
     <div class="box">
      <div class="box-header with-border">
        <h3 class="box-title"><?= gettext('User Calendars') ?></h3>
      </div>
      <div class="box-body" id="userCalendars">


      </div>
     </div>
  </div>
  <div class="col-lg-2">
     <div class="box">
      <div class="box-header with-border">
        <h3 class="box-title"><?= gettext('System Calendars') ?></h3>
      </div>
      <div class="box-body" id="systemCalendars">
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
