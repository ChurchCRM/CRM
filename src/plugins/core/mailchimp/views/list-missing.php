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
                <li class="breadcrumb-item"><a href="<?= SystemURLs::getRootPath() ?>/plugins"><?= gettext('Plugins') ?></a></li>
                <li class="breadcrumb-item"><a href="<?= SystemURLs::getRootPath() ?>/plugins/mailchimp/dashboard"><?= gettext('MailChimp') ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= gettext('Not in CRM') ?></li>
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
        <div class="card card-outline card-warning">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-user-slash mr-2"></i><?= gettext('Subscribers Not in CRM') ?>
                </h3>
                <div class="card-tools">
                    <span class="badge badge-warning" id="count-badge">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    <i class="fa-solid fa-info-circle mr-1"></i>
                    <?= gettext('These people are subscribed to your MailChimp audience but do not exist in ChurchCRM. Consider adding them to your database or removing them from MailChimp.') ?>
                </p>
                <div class="table-responsive">
                    <table id="missingTable" class="table table-striped table-hover data-table" style="width:100%">
                        <thead class="thead-light">
                            <tr>
                                <th><?= gettext('Name') ?></th>
                                <th><?= gettext('Email') ?></th>
                                <th><?= gettext('Status') ?></th>
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
    function initializeMissingTable() {
        var dataTableConfig = {
            ajax: {
                url: window.CRM.root + "/plugins/mailchimp/api/list/<?= $listId ?>/missing",
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
                    data: 'last',
                    render: function (data, type, row) {
                        return '<i class="fa-solid fa-user text-muted mr-2"></i>' + (row.first || '') + " " + (row.last || '');
                    },
                    searchable: true
                },
                {
                    title: i18next.t('Email'),
                    data: 'email',
                    render: function(data) {
                        return '<a href="mailto:' + data + '">' + data + '</a>';
                    }
                },
                {
                    title: i18next.t('Status'),
                    data: 'status',
                    render: function(data) {
                        var badgeClass = 'badge-secondary';
                        if (data === 'subscribed') badgeClass = 'badge-success';
                        else if (data === 'unsubscribed') badgeClass = 'badge-warning';
                        else if (data === 'cleaned') badgeClass = 'badge-danger';
                        return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                    }
                }
            ],
            language: {
                emptyTable: i18next.t("All MailChimp subscribers are in ChurchCRM!")
            }
        };
        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        $("#missingTable").DataTable(dataTableConfig);
    }

    $(document).ready(function () {
        window.CRM.onLocalesReady(initializeMissingTable);
    });
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
