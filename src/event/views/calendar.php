<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<!-- Calendar time-zone indicator. Server-renders the configured sTimeZone;
     the inline script below compares it to the browser time zone and reveals
     a warning if they differ, so misconfiguration is visible without opening
     the admin debug page. -->
<div class="mb-2 d-flex flex-wrap align-items-center small text-body-secondary" id="calendarTimezoneIndicator">
    <span class="me-2"><i class="fa fa-clock me-1"></i><?= _('Calendar time zone:') ?></span>
    <span class="badge bg-secondary-lt" id="calendarTimezoneConfigured"><?= htmlspecialchars($calendarJSArgs['sTimeZone'], ENT_QUOTES, 'UTF-8') ?></span>
    <span class="badge bg-warning-lt ms-2 d-none" id="calendarTimezoneWarning" role="alert">
        <i class="fa fa-triangle-exclamation me-1"></i>
        <span><?= _('Browser time zone differs:') ?></span>
        <span id="calendarTimezoneBrowser" class="fw-semibold"></span>
        <?php if ($isAdmin): ?>
            <a href="<?= htmlspecialchars($sRootPath, ENT_QUOTES, 'UTF-8') ?>/admin/system/debug#collapseTimezone" class="ms-1 text-reset text-decoration-underline"><?= _('Details') ?></a>
        <?php endif; ?>
    </span>
</div>

<div class="alert alert-danger d-none" id="calendarApiWarning">
    <div class="d-flex align-items-center">
        <i class="ti ti-alert-triangle me-2"></i>
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
            <i class="ti ti-stack-2 me-2 text-body-secondary"></i><?= _('Calendars') ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= _('Close') ?>"></button>
    </div>
    <div class="offcanvas-body p-0">
        <!-- User Calendars -->
        <div class="px-3 pt-3 pb-1">
            <div class="d-flex align-items-center justify-content-between">
                <span class="text-uppercase text-body-secondary small fw-bold" style="letter-spacing:.05em;">
                    <i class="ti ti-user me-1"></i><?= _('My Calendars') ?>
                </span>
            </div>
        </div>
        <div class="list-group list-group-flush" id="calendarUserList"></div>
        <div class="px-3 py-2 d-none" id="addCalendarBtn">
            <button class="btn btn-sm btn-ghost-primary w-100">
                <i class="ti ti-circle-plus me-1"></i><?= _('New Calendar') ?>
            </button>
        </div>

        <hr class="my-0">

        <!-- System Calendars -->
        <div class="px-3 pt-3 pb-1">
            <span class="text-uppercase text-body-secondary small fw-bold" style="letter-spacing:.05em;">
                <i class="ti ti-settings me-1"></i><?= _('System Calendars') ?>
            </span>
        </div>
        <div class="list-group list-group-flush" id="calendarSystemList"></div>
    </div>
</div>

<div id="calendar-event-app"></div>

<!-- System Settings Panel Component -->
<?php if ($isAdmin): ?>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<?php
$calendarSettingsPanelConfig = [
    'container'           => '#calendarSettings',
    'title'               => gettext('Calendar Settings'),
    'icon'                => 'fa-solid fa-sliders',
    'headerClass'         => 'bg-info-lt',
    'showAllSettingsLink' => false,
    'settings'            => [
        [
            'name'    => 'bEnabledEvents',
            'type'    => 'boolean',
            'label'   => gettext('Enable Events Menu'),
            'tooltip' => gettext('Show or hide the Events menu in the main navigation.'),
        ],
        [
            'name'    => 'bEnableExternalCalendarAPI',
            'type'    => 'boolean',
            'label'   => gettext('Enable External Calendar API'),
            'tooltip' => gettext('Allow unauthenticated access to calendar events via public HTML, ICS, and JSON URLs. Required for sharing calendars with external apps.'),
        ],
    ],
];
?>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
window.CRM = window.CRM || {};
window.CRM.calendarSettingsPanel = <?= json_encode($calendarSettingsPanelConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<?php endif; ?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
window.CRM = window.CRM || {};
window.CRM.calendarJSArgs = <?= json_encode($calendarJSArgs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;

// Reveal a warning next to the calendar time-zone badge when the browser's
// resolved time zone doesn't match the server's configured sTimeZone. A
// mismatch is the common silent cause of "my event shows at the wrong time".
// Both sides are canonicalized via Intl so that alias pairs like
// "US/Eastern" vs "America/New_York" or "Etc/UTC" vs "UTC" are treated as
// equal and do not trigger a false warning.
(function () {
    var configured = window.CRM.calendarJSArgs && window.CRM.calendarJSArgs.sTimeZone;
    if (!configured) return;
    var browser, canonicalConfigured;
    try {
        browser = Intl.DateTimeFormat().resolvedOptions().timeZone;
        canonicalConfigured = Intl.DateTimeFormat(undefined, { timeZone: configured }).resolvedOptions().timeZone;
    } catch (e) {
        return;
    }
    if (!browser || browser === canonicalConfigured) return;
    document.addEventListener('DOMContentLoaded', function () {
        var browserEl = document.getElementById('calendarTimezoneBrowser');
        var warnEl = document.getElementById('calendarTimezoneWarning');
        if (browserEl) browserEl.textContent = browser;
        if (warnEl) warnEl.classList.remove('d-none');
    });
})();
</script>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/calendar-event-editor.min.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/event-calendars.min.js') ?>"></script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
