<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= _("Families Without Emails") ?></h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                <table id="noEmails" class="table table-striped table-bordered data-table">
                    <tbody></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    function initializeEmailWithout() {
        var dataTableConfig = {
            ajax: {
                url: window.CRM.root + "/api/families/email/without",
                dataSrc: 'families'
            },
            responsive: false,
            autoWidth: false,
            columns: [
                {
                    title: i18next.t('Family'),
                    data: 'Name',
                    width: '40%',
                    render: function ( data, type, row ){
                        return "<a href='"+ window.CRM.root + "/v2/family/" + row.Id + "' target='family' />"+ data + "</a></li>";
                    },
                    searchable: true
                },
                {
                    title: i18next.t('Address'),
                    data: 'Address',
                    width: '60%'
                },
            ]
        }
        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        $("#noEmails").DataTable(dataTableConfig);
    }

    // Wait for locales to load before initializing
    $(document).ready(function () {
        window.CRM.onLocalesReady(initializeEmailWithout);
    });

    function peopleToString(people) {
        return people.length;
    }
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
