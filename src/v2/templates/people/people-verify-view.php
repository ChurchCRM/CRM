<?php

use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\dto\SystemURLs;

//Set the page title
$sPageTitle = gettext(ucfirst($sMode)) . ' ' . gettext('People Verify Dashboard');
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title"><?= gettext('Functions') ?></h3>
    </div>
    <div class="box-body">
        <a href="<?= SystemURLs::getRootPath()?>/Reports/ConfirmReport.php" class="btn btn-app"><i class="fa fa-file-pdf-o"></i><?= gettext('Download family letters') ?></a>
        <a href="<?= SystemURLs::getRootPath()?>/Reports/ConfirmReportEmail.php" class="btn btn-app"><i class="fa  fa-envelope-o"></i><?= gettext('Send family email') ?></a>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header">
                <h3 class="box-title"><?= _("Self Verify") ?></h3>
            </div>
            <div class="box-body">
                <table id="families-complete" class="table table-striped table-bordered table-responsive data-table">
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header">
                <h3 class="box-title"><?= _("Pending Self Verify") ?></h3>
            </div>
            <div class="box-body">
                <table id="families-pending" class="table table-striped table-bordered table-responsive data-table">
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function () {

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
                        return '<a href=' + window.CRM.root + '/FamilyView.php?FamilyID=' + data + '>' + data + '</a>';
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
                        return '<a href=' + window.CRM.root + '/FamilyView.php?FamilyID=' + data + '>' + data + '</a>';
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
