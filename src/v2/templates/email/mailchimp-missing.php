<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-body">
                <table id="noEmails" class="table table-striped table-bordered table-responsive data-table">
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
                url: window.CRM.root + "/api/mailchimp/list/<?= $listId ?>/missing",
                dataSrc: 'members'
            },
            columns: [
                {
                    title: i18next.t('Name'),
                    data: 'last',
                    render: function ( data, type, row ){
                        return row.first + " " + row.last;
                    },
                    searchable: true
                },
                {
                    title: i18next.t('email'),
                    data: 'email',
                },
                {
                    title: i18next.t('Status'),
                    data: 'status',
                },
            ]
        }
        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        $("#noEmails").DataTable(dataTableConfig);
    });

    function peopleToString(people) {
        return people.length;
    }
</script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
?>
