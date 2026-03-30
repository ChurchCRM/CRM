<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>
<!-- Breadcrumb Navigation with Actions -->
<div class="row mb-3">
    <div class="col-12 d-flex align-items-center">
        <nav aria-label="breadcrumb" class="flex-grow-1">
            <ol class="breadcrumb mb-0 bg-light">
                <li class="breadcrumb-item"><a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard"><i class="fa-solid fa-home"></i></a></li>
                <li class="breadcrumb-item"><a href="<?= SystemURLs::getRootPath() ?>/plugins/management"><?= gettext('Plugins') ?></a></li>
                <li class="breadcrumb-item"><a href="<?= SystemURLs::getRootPath() ?>/plugins/mailchimp/dashboard"><?= gettext('MailChimp') ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= gettext('Not Subscribed') ?></li>
            </ol>
        </nav>
        <div class="btn-group btn-group-sm ms-2">
            <a href="https://login.mailchimp.com/" target="_blank" class="btn btn-outline-warning" title="<?= gettext('Open MailChimp') ?>">
                <i class="fa-brands fa-mailchimp fa-fw"></i>
            </a>
            <a href="<?= SystemURLs::getRootPath() ?>/plugins/management/mailchimp" class="btn btn-outline-secondary" title="<?= gettext('Plugin Settings') ?>">
                <i class="fa-solid fa-cog fa-fw"></i>
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title">
                    <i class="fa-solid fa-user-plus me-2"></i><?= gettext('CRM Members Not Subscribed') ?>
                </h3>
                <div class="card-tools ms-auto">
                    <span class="badge bg-primary" id="count-badge">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <p class="text-muted mb-3 px-3 pt-3">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    <?= gettext('These people exist in ChurchCRM with email addresses but are not subscribed to this MailChimp audience. Consider inviting them to subscribe.') ?>
                </p>
                    <table id="unsubscribedTable" class="table table-vcenter table-hover card-table">
                        <thead>
                            <tr>
                                <th><?= gettext('Name') ?></th>
                                <th><?= gettext('Email Addresses') ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
            </div>
            <div class="card-footer">
                <a href="<?= SystemURLs::getRootPath() ?>/plugins/mailchimp/dashboard" class="btn btn-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i><?= gettext('Back to Dashboard') ?>
                </a>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    // Helper to escape HTML and prevent XSS
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function initializeUnsubscribedTable() {
        var dataTableConfig = {
            ajax: {
                url: window.CRM.root +"/plugins/mailchimp/api/list/<?= $listId ?>/not-subscribed",
                dataSrc: function(json) {
                    var count = json.data.members ? json.data.members.length : 0;
                    $("#count-badge").text(count +"" + i18next.t("people"));
                    return json.data.members || [];
                }
            },
            responsive: true,
            autoWidth: false,
            columns: [
                {
                    title: i18next.t('Name'),
                    data: 'name',
                    render: function (data, type, row) {
                        // row.id is from CRM (integer), safe to use directly
                        return '<a href="' + window.CRM.root + '/PersonView.php?PersonID=' + parseInt(row.id, 10) + '" class="text-primary">' +
                            '<i class="fa-solid fa-user me-2"></i>' + escapeHtml(data) + '</a>';
                    },
                    searchable: true
                },
                {
                    title: i18next.t('Email Addresses'),
                    data: 'emails',
                    render: function (data, type, row) {
                        if (!data || data.length === 0) return '<span class="text-muted">' + i18next.t('No email') + '</span>';
                        return data.map(function(email) {
                            var escaped = escapeHtml(email);
                            return '<a href="mailto:' + encodeURIComponent(email) + '" class="badge bg-light text-dark me-1">' +
                                '<i class="fa-solid fa-envelope me-1"></i>' + escaped + '</a>';
                        }).join(' ');
                    }
                }
            ],
            language: {
                emptyTable: i18next.t("All CRM members with email are subscribed to this list!")
            }
        };
        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        $("#unsubscribedTable").DataTable(dataTableConfig);
    }

    $(document).ready(function () {
        window.CRM.onLocalesReady(initializeUnsubscribedTable);
    });
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
