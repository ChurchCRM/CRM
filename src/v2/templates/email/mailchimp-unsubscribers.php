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
    function initializeMailchimpUnsubscribers() {
        var dataTableConfig = {
            ajax: {
                url: window.CRM.root + "/api/mailchimp/list/<?= $listId ?>/not-subscribed",
                dataSrc: 'members'
            },
            responsive: false,
            autoWidth: false,
            columns: [
                {
                    title: i18next.t('Name'),
                    data: 'name',
                    width: '40%',
                    render: function ( data, type, row ){
                        return "<a href='"+ window.CRM.root + "/PersonView.php?PersonID=" + row.id + "' target='person' />"+ data + "</a></li>";
                    },
                    searchable: true
                },
                {
                    title: i18next.t('emails'),
                    data: 'emails',
                    width: '60%'
                },
            ]
        }
        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        $("#noEmails").DataTable(dataTableConfig);
    }

    // Wait for locales to load before initializing
    $(document).ready(function () {
        window.CRM.onLocalesReady(initializeMailchimpUnsubscribers);
    });

    function peopleToString(people) {
        return people.length;
    }
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
