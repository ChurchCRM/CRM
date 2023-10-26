<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="row">
  <div class="col-lg-9">
    <div class="card box-info">
        <div class="card-body no-padding">
            <!-- THE CALENDAR -->
            <div id="calendar"></div>
        </div>
        <!-- /.box-body -->
    </div>
    <!-- /. box -->
  </div>
    <div class="col-lg-3">
    <div class="card card-primary card-outline card-outline-tabs">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="custom-tabs-four-tab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="userCalendars-tab" data-toggle="pill" href="#userCalendars" role="tab" aria-controls="userCalendars" aria-selected="true"><?= _("User")?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="systemCalendars-tab" data-toggle="pill" href="#systemCalendars" role="tab" aria-controls="systemCalendars" aria-selected="false"><?= _("System") ?></a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="custom-tabs-four-tabContent">
                <div class="tab-pane fade show active" id="userCalendars" role="tabpanel" aria-labelledby="userCalendars-tab"></div>
                <div class="tab-pane fade" id="systemCalendars" role="tabpanel" aria-labelledby="systemCalendars-tab"></div>
            </div>
        </div>
        <!-- /.card -->
    </div>
    </div>
</div>

<div id="calendar-event-react-app"></div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  window.CRM.calendarJSArgs = <?= json_encode($calendarJSArgs, JSON_THROW_ON_ERROR) ?>;
</script>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js-react/calendar-event-editor-app.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/ckeditor/ckeditor.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/Calendar.js" ></script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
?>
