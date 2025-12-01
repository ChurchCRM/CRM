<?php

use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext('People Verify Dashboard');
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="card">
    <div class="card-header with-border">
        <h3 class="card-title"><?= gettext('Functions') ?></h3>
    </div>
    <div class="card-body">
        <a href="<?= SystemURLs::getRootPath()?>/Reports/ConfirmReport.php" class="btn btn-app bg-danger">
            <i class="fa-solid fa-file-pdf fa-3x"></i><br>
            <?= gettext('Download family letters') ?>
        </a>
        <button type="button" class="btn btn-app bg-primary" id="verifyEmail">
            <i class="fa-solid fa-envelope fa-3x"></i><br>
            <?= gettext('Send family email') ?>
        </button>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= _("Self Verify") ?></h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                <table id="families-complete" class="table table-striped table-bordered data-table">
                    <tbody></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= _("Pending Self Verify") ?></h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                <table id="families-pending" class="table table-striped table-bordered data-table">
                    <tbody></tbody>
                </table>
                </div>
            </div>
        </div>
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
                        window.location= window.CRM.root + "/Reports/ConfirmReportEmail.php";
                    }
                }
            });
        });

        var dataTableConfig = {
            ajax: {
                url: window.CRM.root + "/api/families/self-verify",
                dataSrc: 'families'
            },
            responsive: false,
            autoWidth: false,
            columns: [
                {
                    width: '15%',
                    title: i18next.t('Family Id'),
                    data: 'Family.Id',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return '<a href=' + window.CRM.root + '/v2/family/' + data + '>' + data + '</a>';
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
            order: [[2, "desc"]]
        }
        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        $("#families-complete").DataTable(dataTableConfig);

          dataTableConfig = {
            ajax: {
                url: window.CRM.root + "/api/families/pending-self-verify",
                dataSrc: 'families'
            },
            responsive: false,
            autoWidth: false,
            columns: [
                {
                    width: '20%',
                    title: i18next.t('Family Id'),
                    data: 'FamilyId',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return '<a href=' + window.CRM.root + '/v2/family/' + data + '>' + data + '</a>';
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
            order: [[0, "asc"]]
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
