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
        <div class="btn-group btn-group-sm ml-2">
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
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-user-plus mr-2"></i><?= gettext('CRM Members Not Subscribed') ?>
                </h3>
                <div class="card-tools">
                    <span class="badge badge-primary" id="count-badge">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    <i class="fa-solid fa-info-circle mr-1"></i>
                    <?= gettext('These people exist in ChurchCRM with email addresses but are not subscribed to this MailChimp audience. Consider inviting them to subscribe.') ?>
                </p>
                <div class="table-responsive">
                    <table id="unsubscribedTable" class="table table-striped table-hover data-table" style="width:100%">
                        <thead class="thead-light">
                            <tr>
                                <th><?= gettext('Name') ?></th>
                                <th><?= gettext('Email Addresses') ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <a href="<?= SystemURLs::getRootPath() ?>/plugins/mailchimp/dashboard" class="btn btn-secondary">
                    <i class="fa-solid fa-arrow-left mr-1"></i><?= gettext('Back to Dashboard') ?>
                </a>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    function initializeUnsubscribedTable() {
        var dataTableConfig = {
            ajax: {
                url: window.CRM.root + "/plugins/mailchimp/api/list/<?= $listId ?>/not-subscribed",
                dataSrc: function(json) {
                    var count = json.data.members ? json.data.members.length : 0;
                    $("#count-badge").text(count + " " + i18next.t("people"));
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
                        return '<a href="' + window.CRM.root + '/PersonView.php?PersonID=' + row.id + '" class="text-primary">' +
                            '<i class="fa-solid fa-user mr-2"></i>' + data + '</a>';
                    },
                    searchable: true
                },
                {
                    title: i18next.t('Email Addresses'),
                    data: 'emails',
                    render: function (data, type, row) {
                        if (!data || data.length === 0) return '<span class="text-muted">' + i18next.t('No email') + '</span>';
                        return data.map(function(email) {
                            return '<a href="mailto:' + email + '" class="badge badge-light mr-1">' +
                                '<i class="fa-solid fa-envelope mr-1"></i>' + email + '</a>';
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
