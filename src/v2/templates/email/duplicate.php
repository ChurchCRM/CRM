<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header">
                <h3 class="box-title"><?= _("Duplicate Emails") ?></h3>
            </div>
            <div class="box-body">
                <table id="dupEmails" class="table table-striped table-bordered table-responsive data-table">
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
                url: window.CRM.root + "/api/persons/duplicate/emails",
                dataSrc: 'emails'
            },
            columns: [
                {
                    title: i18next.t('Email'),
                    data: 'email',
                },
                {
                    title: i18next.t('People'),
                    data: 'people',
                    render: function ( data, type, row ){
                        var render ="<ul>";
                        $.each( data, function( key, value ) {
                            render += "<li><a href='"+ window.CRM.root + "/PersonView.php?PersonID=" +value.id + "' target='user' />"+ value.name + "</a></li>";
                        });
                        render += "</ul>"
                        return render;
                    },
                    searchable: true
                },
                {
                    title: i18next.t('Families'),
                    data: 'families',
                    render: function ( data, type, row ){
                        var render ="<ul>";
                        $.each( data, function( key, value ) {
                            render += "<li><a href='"+ window.CRM.root + "/FamilyView.php?FamilyID=" +value.id + "' target='family' />"+ value.name + "</a></li>";
                        });
                        render += "</ul>"
                        return render;
                    },
                    searchable: true
                }
            ]
        }
        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        $("#dupEmails").DataTable();
    });

    function peopleToString(people) {
        return people.lengh;
    }
</script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
?>
