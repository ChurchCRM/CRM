<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><?= _("Families Without Emails") ?></h3>
            </div>
            <div class="card-body p-0" style="overflow: visible;">
                <table id="noEmails" class="table table-vcenter table-hover card-table">
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    function initializeEmailWithout() {
        var dataTableConfig = {
            ajax: {
                url: window.CRM.root +"/api/families/email/without",
                dataSrc: 'families'
            },
            autoWidth: false,
            columns: [
                {
                    title: i18next.t('Family'),
                    data: 'Name',
                    render: function ( data, type, row ){
                        return"<a href='"+ window.CRM.root +"/v2/family/" + row.Id +"'>"+ $('<div>').text(data).html() +"</a>";
                    },
                    searchable: true
                },
                {
                    title: i18next.t('Address'),
                    data: 'Address'
                },
                {
                    title: i18next.t('Actions'),
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-end w-1 no-export',
                    render: function(data, type, row) {
                        return window.CRM.renderFamilyActionMenu(row.Id, row.Name);
                    }
                }
            ]
        }
        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        $("#noEmails").DataTable(dataTableConfig);
    }

    // Wait for locales to load before initializing
    $(document).ready(function () {
        window.CRM.onLocalesReady(initializeEmailWithout);
    });
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
