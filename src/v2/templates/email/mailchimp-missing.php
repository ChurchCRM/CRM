<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
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
    function initializeMailchimpMissing() {
        var dataTableConfig = {
            ajax: {
                url: window.CRM.root + "/api/mailchimp/list/<?= $listId ?>/missing",
                dataSrc: 'members'
            },
            responsive: false,
            autoWidth: false,
            columns: [
                {
                    title: i18next.t('Name'),
                    data: 'last',
                    width: '30%',
                    render: function ( data, type, row ){
                        return row.first + " " + row.last;
                    },
                    searchable: true
                },
                {
                    title: i18next.t('email'),
                    data: 'email',
                    width: '40%'
                },
                {
                    title: i18next.t('Status'),
                    data: 'status',
                    width: '30%'
                },
            ]
        }
        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        $("#noEmails").DataTable(dataTableConfig);
    }

    // Wait for locales to load before initializing
    $(document).ready(function () {
        window.CRM.onLocalesReady(initializeMailchimpMissing);
    });

    function peopleToString(people) {
        return people.length;
    }
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
