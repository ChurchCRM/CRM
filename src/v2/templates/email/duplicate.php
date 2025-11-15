<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= _("Duplicate Emails") ?></h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                <table id="dupEmails" class="table table-striped table-bordered data-table">
                    <tbody></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    function initializeDuplicateEmails() {
        var dataTableConfig = {
            ajax: {
                url: window.CRM.root + "/api/persons/duplicate/emails",
                dataSrc: 'emails'
            },
            responsive: false,
            autoWidth: false,
            columns: [
                {
                    title: i18next.t('Email'),
                    data: 'email',
                    width: '20%'
                },
                {
                    title: i18next.t('People'),
                    data: 'people',
                    width: '40%',
                    render: function ( data, type, row ){
                        var render ="<ul class='mb-0'>";
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
                    width: '40%',
                    render: function ( data, type, row ){
                        var render ="<ul class='mb-0'>";
                        $.each( data, function( key, value ) {
                            render += "<li><a href='"+ window.CRM.root + "/v2/family/" +value.id + "' target='family' />"+ value.name + "</a></li>";
                        });
                        render += "</ul>"
                        return render;
                    },
                    searchable: true
                }
            ]
        }
        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        $("#dupEmails").DataTable(dataTableConfig);
    }

    // Wait for locales to load before initializing
    $(document).ready(function () {
        window.CRM.onLocalesReady(initializeDuplicateEmails);
    });

    function peopleToString(people) {
        return people.length;
    }
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
