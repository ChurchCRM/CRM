<?php

use ChurchCRM\dto\SystemURLs;

//Set the page title
$sPageTitle = gettext('People Verify Dashboard');
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="card">
    <div class="card-header with-border">
        <h3 class="card-title"><?= gettext('Functions') ?></h3>
    </div>
    <div class="card-body">
        <a href="<?= SystemURLs::getRootPath()?>/Reports/ConfirmReport.php" class="btn btn-app"><i class="fa fa-file-pdf"></i><?= gettext('Download family letters') ?></a>
        <div class="btn btn-app" id="verifyEmail"><i class="fa  fa-envelope"></i><?= gettext('Send family email') ?></div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= _("Self Verify") ?></h3>
            </div>
            <div class="card-body">
                <table id="families-complete" class="table table-striped table-bordered table-responsive data-table">
                    <tbody></tbody>
                </table>
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
                <table id="families-pending" class="table table-striped table-bordered table-responsive data-table">
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function () {

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
            columns: [
                {
                    title: i18next.t('Family Id'),
                    data: 'Family.Id',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return '<a href=' + window.CRM.root + '/v2/family/' + data + '>' + data + '</a>';
                    }
                },
                {
                    title: i18next.t('Family'),
                    data: 'Family.FamilyString',
                    searchable: true
                },
                {
                    title: i18next.t('Comments'),
                    data: 'Text',
                    searchable: true
                },
                {
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
            columns: [
                {
                    title: i18next.t('Family Id'),
                    data: 'FamilyId',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return '<a href=' + window.CRM.root + '/v2/family/' + data + '>' + data + '</a>';
                    }
                },
                {
                    title: i18next.t('Family'),
                    data: 'FamilyName',
                    searchable: true
                },
                {
                    title: i18next.t('Valid Until'),
                    data: 'ValidUntilDate',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return moment(data).format("MM-DD-YY");
                    }
                }
            ],
            order: [[2, "desc"]]
        }

          $.extend(dataTableConfig, window.CRM.plugin.dataTable);

        $("#families-pending").DataTable(dataTableConfig);
    });
</script>

<?php
require SystemURLs::getDocumentRoot() .  '/Include/Footer.php';
?>
