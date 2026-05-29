<?php

use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext('People Verify Dashboard');
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><?= gettext('Functions') ?></h3>
    </div>
    <div class="card-body">
        <div class="btn-group" role="group">
            <a href="<?= SystemURLs::getRootPath()?>/v2/people/report/verify" class="btn btn-outline-danger" title="<?= gettext('Generate and download confirmation letters') ?>">
                <i class="fa-solid fa-file-pdf me-2"></i><?= gettext('Letters') ?>
            </a>
            <button type="button" class="btn btn-outline-primary" id="verifyEmail" title="<?= gettext('Send email to families') ?>">
                <i class="fa-solid fa-envelope me-2"></i><?= gettext('Email Families') ?>
            </button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><?= _("Self Verify") ?></h3>
    </div>
    <div class="table-responsive">
        <table id="families-complete" class="table table-vcenter table-hover card-table">
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><?= _("Pending Self Verify") ?></h3>
    </div>
    <div class="table-responsive">
        <table id="families-pending" class="table table-vcenter table-hover card-table">
            <tbody></tbody>
        </table>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    function initializePeopleVerify() {

        $("#verifyEmail").click(function() {
            bootbox.confirm({
                title: i18next.t("Send Family Verification Emails Now?"),
                message: i18next.t("Send data verification request emails to all people in database?")+"<br/><br/>"+i18next.t("This process can take a while depending on the size of your database."),
                buttons: {
                    cancel : {
                        label: i18next.t("Cancel")
                    },
                    confirm: {
                        label: i18next.t("Send Emails")
                    }
                },
                callback: function(result) {
                    if (result) {
                        window.location= window.CRM.root +"/v2/people/report/verify/email";
                    }
                }
            });
        });

        var dataTableConfig = {
            ajax: {
                url: window.CRM.root +"/api/families/self-verify",
                dataSrc: 'families'
            },

            autoWidth: false,
            columns: [
                {
                    width: '15%',
                    title: i18next.t('Family Id'),
                    data: 'Family.Id',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return '<a href=' + window.CRM.root + '/people/family/' + data + '>' + data + '</a>';
                    }
                },
                {
                    width: '30%',
                    title: i18next.t('Family'),
                    data: 'Family.FamilyString',
                    searchable: true
                },
                {
                    width: '35%',
                    title: i18next.t('Comments'),
                    data: 'Text',
                    searchable: true
                },
                {
                    width: '20%',
                    title: i18next.t('Date'),
                    data: 'DateEntered',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return moment(data).format("MM-DD-YY");
                    }
                }
            ],
            order: [[2,"desc"]]
        }
        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        $("#families-complete").DataTable(dataTableConfig);

          dataTableConfig = {
            ajax: {
                url: window.CRM.root +"/api/families/pending-self-verify",
                dataSrc: 'families'
            },

            autoWidth: false,
            columns: [
                {
                    width: '20%',
                    title: i18next.t('Family Id'),
                    data: 'FamilyId',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return '<a href=' + window.CRM.root + '/people/family/' + data + '>' + data + '</a>';
                    }
                },
                {
                    width: '50%',
                    title: i18next.t('Family'),
                    data: 'FamilyName',
                    searchable: true
                },
                {
                    width: '30%',
                    title: i18next.t('Valid Until'),
                    data: 'ValidUntilDate',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return moment(data).format("MM-DD-YY");
                    }
                }
            ],
            order: [[0,"asc"]]
        }

          $.extend(dataTableConfig, window.CRM.plugin.dataTable);

        $("#families-pending").DataTable(dataTableConfig);
    }

    // Wait for locales to load before initializing
    $(document).ready(function () {
        window.CRM.onLocalesReady(initializePeopleVerify);
    });
</script>
<?php
require SystemURLs::getDocumentRoot() .  '/Include/Footer.php';
