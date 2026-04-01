<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="alert alert-danger d-none" id="calendarApiWarning">
    <div class="d-flex align-items-center">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>
        <div>
            <h4 class="alert-title mb-1"><?= _('External Calendar API Disabled') ?></h4>
            <p class="mb-0"><?= _('bEnableExternalCalendarAPI is disabled, but some calendars have access tokens. For calendars to be shared, the bEnableExternalCalendarAPI setting must be enabled.') ?></p>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-9 col-md-8 col-sm-12">
        <div class="card">
            <div class="card-body p-0">
                <!-- THE CALENDAR -->
                <div id="calendar"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-4 col-sm-12">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="userCalendars-tab" data-bs-toggle="tab" href="#userCalendars" role="tab" aria-controls="userCalendars" aria-selected="true">
                            <i class="fa-solid fa-user me-1"></i><?= _('User') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="systemCalendars-tab" data-bs-toggle="tab" href="#systemCalendars" role="tab" aria-controls="systemCalendars" aria-selected="false">
                            <i class="fa-solid fa-gear me-1"></i><?= _('System') ?>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body p-0">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="userCalendars" role="tabpanel" aria-labelledby="userCalendars-tab">
                        <div class="list-group list-group-flush" id="calendarUserList"></div>
                        <div class="p-2 text-center d-none" id="addCalendarBtn">
                            <button class="btn btn-sm btn-ghost-primary w-100">
                                <i class="fa-solid fa-circle-plus me-1"></i><?= _('New Calendar') ?>
                            </button>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="systemCalendars" role="tabpanel" aria-labelledby="systemCalendars-tab">
                        <div class="list-group list-group-flush" id="calendarSystemList"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="calendar-event-react-app"></div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM.calendarJSArgs = <?= json_encode($calendarJSArgs, JSON_THROW_ON_ERROR) ?>;
</script>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/calendar-event-editor.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/Calendar.js') ?>"></script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
