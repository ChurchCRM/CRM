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
                url: window.CRM.root + "/api/mailchimp/list/<?= $listId ?>/not-subscribed",
                dataSrc: 'members'
            },
            columns: [
                {
                    title: i18next.t('Name'),
                    data: 'name',
                    render: function ( data, type, row ){
                        return "<a href='"+ window.CRM.root + "/PersonView.php?PersonID=" + row.id + "' target='person' />"+ data + "</a></li>";
                    },
                    searchable: true
                },
                {
                    title: i18next.t('emails'),
                    data: 'emails',
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
