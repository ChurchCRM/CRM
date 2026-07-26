<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="row mb-3">
    <div class="col-6 col-lg-3">
        <a href="#families-card" class="card card-sm text-decoration-none">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-secondary text-white avatar rounded-circle">
                            <i class="ti ti-users icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body"><?= $familyCount ?></div>
                        <div class="text-body-secondary"><?= gettext('Families') ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="#people-card" class="card card-sm text-decoration-none">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-success text-white avatar rounded-circle">
                            <i class="ti ti-user icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body"><?= $personCount ?></div>
                        <div class="text-body-secondary"><?= gettext('People') ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card" id="families-card">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><?= gettext('Families') ?></h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="families" class="table table-bordered data-table">
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card" id="people-card">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><?= gettext('People') ?></h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="people" class="table table-bordered data-table">
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    function initializeSelfRegister() {

        var dataTableConfig = {
            ajax: {
                url: window.CRM.root + "/api/families/self-register",
                dataSrc: 'families'
            },
            autoWidth: false,
            columns: [
                {
                    width: '20%',
                    title: i18next.t('Family Id'),
                    data: 'Id',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return '<a href="' + window.CRM.root + '/people/family/' + encodeURIComponent(data) + '">' + data + '</a>';
                    }
                },
                {
                    width: '45%',
                    title: i18next.t('Family'),
                    data: 'FamilyString',
                    searchable: true
                },
                {
                    width: '25%',
                    title: i18next.t('Date'),
                    data: 'DateEntered',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return moment(data).format("MM-DD-YY");
                    }
                },
                {
                    title: i18next.t('Actions'),
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-end w-1 no-export',
                    width: '15%',
                    render: function (data, type, row) {
                        return window.CRM.renderFamilyActionMenu(row.Id, row.FamilyString);
                    }
                }
            ],
            order: [[2, "desc"]]
        };

        $.extend(dataTableConfig, window.CRM.plugin.dataTable);

        $("#families").DataTable(dataTableConfig);

        dataTableConfig = {
            ajax: {
                url: window.CRM.root + "/api/persons/self-register",
                dataSrc: 'people'
            },
            autoWidth: false,
            columns: [
                {
                    width: '12%',
                    title: i18next.t('ID'),
                    data: 'Id',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return '<a href="' + window.CRM.root + '/people/view/' + encodeURIComponent(data) + '">' + data + '</a>';
                    }
                },
                {
                    width: '28%',
                    title: i18next.t('First Name'),
                    data: 'FirstName',
                    searchable: true
                },
                {
                    width: '28%',
                    title: i18next.t('Last Name'),
                    data: 'LastName',
                    searchable: true
                },
                {
                    width: '20%',
                    title: i18next.t('Date'),
                    data: 'DateEntered',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return moment(data).format("MM-DD-YY");
                    }
                },
                {
                    title: i18next.t('Actions'),
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-end w-1 no-export',
                    width: '12%',
                    render: function (data, type, row) {
                        return window.CRM.renderPersonActionMenu(row.Id, row.FirstName + ' ' + row.LastName, { familyId: row.FamId });
                    }
                }
            ],
            order: [[3, "desc"]]
        };

        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        $("#people").DataTable(dataTableConfig);
    }

    // Wait for locales to load before initializing
    $(document).ready(function () {
        window.CRM.onLocalesReady(initializeSelfRegister);
    });
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
