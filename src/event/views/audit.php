<?php

use ChurchCRM\dto\SystemURLs;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="card mb-3">
    <div class="card-status-top bg-orange"></div>
    <div class="card-body">
        <p class="mb-2">
            <i class="ti ti-info-circle me-1 text-info"></i>
            <strong><?= gettext('What does this find?') ?></strong>
        </p>
        <p class="mb-0 text-secondary">
            <?= gettext("This report lists past events that are still marked Active and still have at least one person who was checked in but never checked out. Common causes: a volunteer forgot to tap 'Check Out', the event was created and abandoned, or the kiosk lost connectivity. Closing an event from this report will check everyone out (using the current time) and mark the event Inactive.") ?>
        </p>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title m-0">
            <i class="ti ti-alert-triangle me-1 text-warning"></i>
            <?= gettext('Stuck Events') ?>
        </h3>
        <span id="auditCount" class="badge bg-warning-lt text-warning ms-2"><?= gettext('Loading...') ?></span>
        <div class="ms-auto d-flex gap-2">
            <button id="auditRefreshBtn" type="button" class="btn btn-sm btn-outline-secondary">
                <i class="ti ti-refresh me-1"></i><?= gettext('Refresh') ?>
            </button>
            <button id="auditCloseAllBtn" type="button" class="btn btn-sm btn-warning d-none">
                <i class="ti ti-circle-check me-1"></i><?= gettext('Close All Stuck Events') ?>
            </button>
        </div>
    </div>

    <div id="auditEmpty" class="card-body text-center text-muted py-5 d-none">
        <i class="ti ti-mood-happy mb-2 d-block" style="font-size: 3rem;"></i>
        <h3 class="text-muted"><?= gettext('All clear!') ?></h3>
        <p class="text-muted mb-0"><?= gettext('No past events have un-checked-out attendees.') ?></p>
    </div>

    <div id="auditLoading" class="card-body text-center text-muted py-5">
        <span class="spinner-border spinner-border-sm me-2"></span><?= gettext('Looking for stuck events...') ?>
    </div>

    <div id="auditTableWrap" class="table-responsive d-none">
        <table class="table table-vcenter table-hover mb-0">
            <thead>
                <tr>
                    <th><?= gettext('Event') ?></th>
                    <th><?= gettext('Type') ?></th>
                    <th><?= gettext('Ended') ?></th>
                    <th class="text-center"><?= gettext('Still Checked In') ?></th>
                    <th class="text-end"><?= gettext('Action') ?></th>
                </tr>
            </thead>
            <tbody id="auditTbody"></tbody>
        </table>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
(function () {
    var $countBadge = $('#auditCount');
    var $closeAllBtn = $('#auditCloseAllBtn');
    var $tableWrap = $('#auditTableWrap');
    var $tbody = $('#auditTbody');
    var $empty = $('#auditEmpty');
    var $loading = $('#auditLoading');

    function escapeHtml(s) { return window.CRM.escapeHtml(String(s == null ? '' : s)); }

    function loadAudit() {
        $loading.removeClass('d-none');
        $tableWrap.addClass('d-none');
        $empty.addClass('d-none');
        $closeAllBtn.addClass('d-none');
        $countBadge.text(i18next.t('Loading...'));

        window.CRM.APIRequest({ method: 'GET', path: 'events/audit/stuck' })
            .done(function (resp) {
                var events = (resp && resp.events) || [];
                $countBadge.text(events.length);
                if (events.length === 0) {
                    $empty.removeClass('d-none');
                    $loading.addClass('d-none');
                    return;
                }
                $tbody.empty();
                events.forEach(function (e) {
                    var row =
                        '<tr data-event-id="' + e.id + '">' +
                        '<td><a href="' + window.CRM.root + '/event/view/' + e.id + '">' + escapeHtml(e.title) + '</a></td>' +
                        '<td><span class="badge bg-azure-lt">' + escapeHtml(e.typeName) + '</span></td>' +
                        '<td class="text-secondary small">' + escapeHtml(e.end) + '</td>' +
                        '<td class="text-center"><span class="badge bg-warning text-dark">' + e.stillCheckedIn + '</span></td>' +
                        '<td class="text-end">' +
                        '<button type="button" class="btn btn-sm btn-warning audit-close-one" data-event-id="' + e.id + '">' +
                        '<i class="ti ti-circle-check me-1"></i>' + i18next.t('Close') +
                        '</button></td>' +
                        '</tr>';
                    $tbody.append(row);
                });
                $tableWrap.removeClass('d-none');
                $closeAllBtn.removeClass('d-none');
                $loading.addClass('d-none');
            })
            .fail(function () {
                $loading.addClass('d-none');
                window.CRM.notify(i18next.t('Failed to load audit. Please try again.'), { type: 'danger', delay: 5000 });
            });
    }

    function closeEvents(eventIds, message) {
        bootbox.confirm({
            title: i18next.t('Close stuck events?'),
            message: message,
            buttons: {
                cancel: { label: '<i class="ti ti-x"></i> ' + i18next.t('Cancel') },
                confirm: { label: '<i class="ti ti-circle-check"></i> ' + i18next.t('Close'), className: 'btn-warning' },
            },
            callback: function (confirmed) {
                if (!confirmed) return;
                window.CRM.APIRequest({
                    method: 'POST',
                    path: 'events/audit/close',
                    data: JSON.stringify({ eventIds: eventIds }),
                })
                    .done(function (resp) {
                        window.CRM.notify(
                            i18next.t('Closed') + ' ' + (resp.eventsClosed || 0) + ' ' + i18next.t('events') +
                            ' (' + (resp.peopleCheckedOut || 0) + ' ' + i18next.t('check-outs') + ')',
                            { type: 'success', delay: 4000 },
                        );
                        loadAudit();
                    })
                    .fail(function () {
                        window.CRM.notify(i18next.t('Failed to close events. Please try again.'), { type: 'danger', delay: 5000 });
                    });
            },
        });
    }

    $('#auditRefreshBtn').on('click', loadAudit);

    $closeAllBtn.on('click', function () {
        var ids = $tbody.find('tr').map(function () { return parseInt($(this).data('event-id'), 10); }).get();
        if (ids.length === 0) return;
        closeEvents(ids, i18next.t('Check everyone out and deactivate all') + ' ' + ids.length + ' ' + i18next.t('events?'));
    });

    $(document).on('click', '.audit-close-one', function () {
        var id = parseInt($(this).data('event-id'), 10);
        if (!id) return;
        closeEvents([id], i18next.t('Check everyone out and deactivate this event?'));
    });

    // Initial load on document ready
    if (window.CRM && window.CRM.localesLoaded) {
        loadAudit();
    } else {
        window.addEventListener('CRM.localesReady', loadAudit, { once: true });
    }
})();
</script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
