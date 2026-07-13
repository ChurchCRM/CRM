<?php

require_once __DIR__ . '/../Include/Config.php';
require_once __DIR__ . '/../Include/PageInit.php';

$sPageTitle = gettext('Self Registrations');
require_once __DIR__ . '/../Include/Header.php';

use ChurchCRM\dto\SystemURLs;

?>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><?= _("Families") ?></h3>
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
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><?= _("People") ?></h3>
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
                url: window.CRM.root +"/api/families/self-register",
                dataSrc: 'families'
            },
            autoWidth: false,
            columns: [
                {
                    width: '15%',
                    title: i18next.t('Family Id'),
                    data: 'Id',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return '<a href="' + window.CRM.root + '/people/family/' + encodeURIComponent(data) + '">' + data + '</a>';
                    }
                },
                {
                    width: '40%',
                    title: i18next.t('Family'),
                    data: 'FamilyString',
                    searchable: true
                },
                {
                    width: '15%',
                    title: i18next.t('Date'),
                    data: 'DateEntered',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return moment(data).format("MM-DD-YY");
                    }
                },
                {
                    width: '15%',
                    title: i18next.t('Status'),
                    data: 'NeedsReview',
                    searchable: false,
                    render: function (data, type, row) {
                        return renderReviewStatusBadge(data);
                    }
                },
                {
                    title: i18next.t('Actions'),
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-end w-1 no-export',
                    width: '15%',
                    render: function(data, type, row) {
                        return renderApproveButton(row.NeedsReview, row.Id, 'family') + window.CRM.renderFamilyActionMenu(row.Id, row.FamilyString);
                    }
                }
            ],
            order: [[2,"desc"]]
        }

        $.extend(dataTableConfig, window.CRM.plugin.dataTable);

        $("#families").DataTable(dataTableConfig);

        dataTableConfig = {
            ajax: {
                url: window.CRM.root +"/api/persons/self-register",
                dataSrc: 'people'
            },
            autoWidth: false,
            columns: [
                {
                    width: '10%',
                    title: i18next.t('Id'),
                    data: 'Id',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return '<a href="' + window.CRM.root + '/people/view/' + encodeURIComponent(data) + '">' + data + '</a>';
                    }
                },
                {
                    width: '22%',
                    title: i18next.t('First Name'),
                    data: 'FirstName',
                    searchable: true
                },
                {
                    width: '22%',
                    title: i18next.t('Last Name'),
                    data: 'LastName',
                    searchable: true
                },
                {
                    width: '15%',
                    title: i18next.t('Date'),
                    data: 'DateEntered',
                    searchable: false,
                    render: function (data, type, full, meta) {
                        return moment(data).format("MM-DD-YY");
                    }
                },
                {
                    width: '15%',
                    title: i18next.t('Status'),
                    data: 'NeedsReview',
                    searchable: false,
                    render: function (data, type, row) {
                        return renderReviewStatusBadge(data);
                    }
                },
                {
                    title: i18next.t('Actions'),
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-end w-1 no-export',
                    width: '16%',
                    render: function(data, type, row) {
                        return renderApproveButton(row.NeedsReview, row.Id, 'person') + window.CRM.renderPersonActionMenu(row.Id, row.FirstName + ' ' + row.LastName, { familyId: row.FamId });
                    }
                }
            ],
            order: [[3,"desc"]]
        }
        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        $("#people").DataTable(dataTableConfig);
    }

    /**
     * Render a small badge showing whether a self-registered record still needs admin review.
     * @param {boolean} needsReview
     * @returns {string} HTML string
     */
    function renderReviewStatusBadge(needsReview) {
        return needsReview
            ? '<span class="badge bg-yellow-lt">' + i18next.t('Needs Review') + '</span>'
            : '<span class="badge bg-green-lt">' + i18next.t('Approved') + '</span>';
    }

    /**
     * Render an Approve button for a self-registered family/person still awaiting review.
     * @param {boolean} needsReview
     * @param {number} id
     * @param {'family'|'person'} entityType
     * @returns {string} HTML string
     */
    function renderApproveButton(needsReview, id, entityType) {
        if (!needsReview) {
            return '';
        }
        return '<button type="button" class="btn btn-sm btn-outline-success approve-review me-1"' +
            ' data-entity-type="' + entityType + '" data-entity-id="' + id + '"' +
            ' title="' + i18next.t('Approve') + '">' +
            '<i class="ti ti-check"></i>' +
            '</button>';
    }

    // Approve a self-registered family or person, clearing its needs-review flag
    $(document).on('click', '.approve-review', function () {
        var $button = $(this);
        var entityType = $button.data('entity-type');
        var entityId = $button.data('entity-id');
        var apiPath = (entityType === 'family' ? 'family/' : 'person/') + entityId + '/approve-review';

        window.CRM.APIRequest({
            method: 'POST',
            path: apiPath
        }).done(function () {
            window.CRM.notify(i18next.t('Approved'), { type: 'success', delay: 3000 });
            $('#families').DataTable().ajax.reload(null, false);
            $('#people').DataTable().ajax.reload(null, false);
        });
    });

    // Wait for locales to load before initializing
    $(document).ready(function () {
        window.CRM.onLocalesReady(initializeSelfRegister);
    });
</script>
<?php
require_once __DIR__ . '/../Include/Footer.php';
