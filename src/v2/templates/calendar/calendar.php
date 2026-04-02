<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="alert alert-danger d-none" id="calendarApiWarning">
    <div class="d-flex align-items-center">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>
        <div>
            <h4 class="alert-title mb-1"><?= _('External Calendar API Disabled') ?></h4>
            <p class="mb-0"><?= _('Some calendars have access tokens, but external calendar sharing is currently disabled. Enable it via Calendar Settings to allow external apps to subscribe to your calendars.') ?></p>
        </div>
    </div>
</div>

<!-- Full-width calendar -->
<div class="card">
    <div class="card-body p-0">
        <div id="calendar"></div>
    </div>
</div>

<!-- Calendar Sidebar Offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="calendarSidebar" aria-labelledby="calendarSidebarLabel" style="width: 320px;">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="calendarSidebarLabel">
            <i class="fa-solid fa-layer-group me-2 text-muted"></i><?= _('Calendars') ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= _('Close') ?>"></button>
    </div>
    <div class="offcanvas-body p-0">
        <!-- User Calendars -->
        <div class="px-3 pt-3 pb-1">
            <div class="d-flex align-items-center justify-content-between">
                <span class="text-uppercase text-muted small fw-bold" style="letter-spacing:.05em;">
                    <i class="fa-solid fa-user me-1"></i><?= _('My Calendars') ?>
                </span>
            </div>
        </div>
        <div class="list-group list-group-flush" id="calendarUserList"></div>
        <div class="px-3 py-2 d-none" id="addCalendarBtn">
            <button class="btn btn-sm btn-ghost-primary w-100">
                <i class="fa-solid fa-circle-plus me-1"></i><?= _('New Calendar') ?>
            </button>
        </div>

        <hr class="my-0">

        <!-- System Calendars -->
        <div class="px-3 pt-3 pb-1">
            <span class="text-uppercase text-muted small fw-bold" style="letter-spacing:.05em;">
                <i class="fa-solid fa-gear me-1"></i><?= _('System Calendars') ?>
            </span>
        </div>
        <div class="list-group list-group-flush" id="calendarSystemList"></div>
    </div>
</div>

<div id="calendar-event-react-app"></div>

<!-- System Settings Panel Component -->
<?php if ($isAdmin): ?>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
    window.CRM.settingsPanel.init({
        container: '#calendarSettings',
        title: <?= json_encode(gettext('Calendar Settings')) ?>,
        icon: 'fa-solid fa-sliders',
        headerClass: 'bg-info-lt',
        settings: [
            {
                name: 'bEnabledEvents',
                type: 'boolean',
                label: <?= json_encode(gettext('Enable Events Menu')) ?>,
                tooltip: <?= json_encode(gettext('Show or hide the Events menu in the main navigation.')) ?>
            },
            {
                name: 'bEnableExternalCalendarAPI',
                type: 'boolean',
                label: <?= json_encode(gettext('Enable External Calendar API')) ?>,
                tooltip: <?= json_encode(gettext('Allow unauthenticated access to calendar events via public HTML, ICS, and JSON URLs. Required for sharing calendars with external apps.')) ?>
            }
        ],
        showAllSettingsLink: false,
        onSave: function() {
            setTimeout(function() {
                window.location.reload();
            }, 1500);
        }
    });
});
</script>
<?php endif; ?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM.calendarJSArgs = <?= json_encode($calendarJSArgs, JSON_THROW_ON_ERROR) ?>;
</script>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/calendar-event-editor.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/Calendar.js') ?>"></script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
