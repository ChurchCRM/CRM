<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="row mb-3">
    <div class="col-6 col-lg-3">
        <div class="card card-sm">
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
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-success text-white avatar rounded-circle">
                            <i class="ti ti-user icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body"><?= $individualCount ?></div>
                        <div class="text-body-secondary"><?= gettext('Individuals (no family)') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><?= gettext('New Self-Registrations') ?></h3>
            </div>
            <div class="card-body">
                <p class="text-body-secondary">
                    <?= gettext('Review new sign-ups from your public registration form below. Entries with no email or phone are flagged — verify contact info before following up.') ?>
                </p>
                <div style="overflow-x: clip; overflow-y: visible;">
                    <table id="selfRegistrations" class="table table-bordered data-table">
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    function renderSelfRegisterContact(email, phone) {
        if (!email && !phone) {
            return '<span class="badge bg-warning-lt text-warning">' + i18next.t('No contact info') + '</span>';
        }
        var parts = [];
        if (email) {
            parts.push('<div>' + window.CRM.escapeHtml(email) + '</div>');
        }
        if (phone) {
            parts.push('<div class="text-body-secondary">' + window.CRM.escapeHtml(phone) + '</div>');
        }
        return parts.join('');
    }

    function initializeSelfRegister() {
        $.when(
            $.get(window.CRM.root + "/api/families/self-register"),
            $.get(window.CRM.root + "/api/persons/self-register")
        ).done(function (familiesResp, peopleResp) {
            var families = (familiesResp[0].families || []).map(function (f) {
                return {
                    type: 'family',
                    id: f.Id,
                    name: f.FamilyString,
                    email: f.Email,
                    phone: f.HomePhone,
                    dateEntered: f.DateEntered
                };
            });
            var people = (peopleResp[0].people || []).map(function (p) {
                return {
                    type: 'individual',
                    id: p.Id,
                    name: p.FullName,
                    email: p.Email,
                    phone: p.HomePhone || p.CellPhone,
                    dateEntered: p.DateEntered
                };
            });

            var dataTableConfig = {
                data: families.concat(people),
                autoWidth: false,
                columns: [
                    {
                        title: i18next.t('Type'),
                        data: 'type',
                        width: '12%',
                        render: function (data) {
                            return data === 'family'
                                ? '<span class="badge bg-secondary-lt text-secondary">' + i18next.t('Family') + '</span>'
                                : '<span class="badge bg-info-lt text-info">' + i18next.t('Individual') + '</span>';
                        }
                    },
                    {
                        title: i18next.t('Name'),
                        data: 'name',
                        width: '33%',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return data;
                            }
                            var url = row.type === 'family'
                                ? window.CRM.root + '/people/family/' + encodeURIComponent(row.id)
                                : window.CRM.root + '/people/view/' + encodeURIComponent(row.id);
                            return '<a href="' + url + '">' + window.CRM.escapeHtml(data) + '</a>';
                        }
                    },
                    {
                        title: i18next.t('Contact'),
                        data: null,
                        orderable: false,
                        searchable: false,
                        width: '25%',
                        render: function (data, type, row) {
                            return renderSelfRegisterContact(row.email, row.phone);
                        }
                    },
                    {
                        title: i18next.t('Registered'),
                        data: 'dateEntered',
                        width: '15%',
                        render: function (data) {
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
                            return row.type === 'family'
                                ? window.CRM.renderFamilyActionMenu(row.id, row.name)
                                : window.CRM.renderPersonActionMenu(row.id, row.name);
                        }
                    }
                ],
                order: [[3, "desc"]]
            };

            $.extend(dataTableConfig, window.CRM.plugin.dataTable);
            $("#selfRegistrations").DataTable(dataTableConfig);
        }).fail(function () {
            window.CRM.notify(
                i18next.t("Error loading self-registered entries"),
                { type: "danger", delay: 6000 }
            );
        });
    }

    // Wait for locales to load before initializing
    $(document).ready(function () {
        window.CRM.onLocalesReady(initializeSelfRegister);
    });
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
