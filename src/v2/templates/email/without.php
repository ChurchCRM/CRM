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
                url: window.CRM.root + "/api/families/email/without",
                dataSrc: 'families'
            },
            columns: [
                {
                    title: i18next.t('Family'),
                    data: 'Name',
                    render: function ( data, type, row ){
                        return "<a href='"+ window.CRM.root + "/v2/family/" + row.Id + "' target='family' />"+ data + "</a></li>";
                    },
                    searchable: true
                },
                {
                    title: i18next.t('Address'),
                    data: 'Address',
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
