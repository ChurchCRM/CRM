<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/SimpleConfig.php';
require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header">
                <h3 class="box-title"><?= _("Families Without Emails") ?></h3>
            </div>
            <div class="box-body">
                <table id="noEmails" class="table table-striped table-bordered table-responsive data-table">
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function () {
        $("#noEmails").DataTable({
            "language": {
                "url": window.CRM.plugin.dataTable.language.url
            },
            ajax: {
                url: window.CRM.root + "/api/families/email/without",
                dataSrc: 'families'
            },
            "dom": window.CRM.plugin.dataTable.dom,
            "tableTools": {
                "sSwfPath": window.CRM.plugin.dataTable.tableTools.sSwfPath
            },
            responsive: true,
            columns: [
                {
                    title: i18next.t('Family'),
                    data: 'Name',
                    render: function ( data, type, row ){
                        return "<a href='"+ window.CRM.root + "/FamilyView.php?FamilyID=" + row.Id + "' target='family' />"+ data + "</a></li>";
                    },
                    searchable: true
                },
                {
                    title: i18next.t('Address'),
                    data: 'Address',
                },
            ]
        });
    });

    function peopleToString(people) {
        return people.lengh;
    }
</script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
?>
