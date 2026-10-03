<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="alert <?= $selfRegEnabled ? 'alert-success' : 'alert-warning' ?> d-flex align-items-center justify-content-between" role="status">
    <div>
        <i class="fa-solid <?= $selfRegEnabled ? 'fa-circle-check' : 'fa-circle-pause' ?> me-2"></i>
        <strong><?= $selfRegEnabled ? gettext('Self-registration is enabled') : gettext('Self-registration is disabled') ?></strong>
        <span class="ms-1"><?= $selfRegEnabled
            ? gettext('Visitors can sign up on your public registration form.')
            : gettext('The public registration form is turned off. Existing registrations are listed below.') ?></span>
    </div>
    <?php if ($isAdmin): ?>
        <a href="<?= $sRootPath ?>/admin/people" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-sliders me-1"></i><?= gettext('Change in People Settings') ?>
        </a>
    <?php endif; ?>
</div>

<div class="row mb-3">
    <div class="col-sm-6 col-lg-4">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-warning text-white avatar rounded-circle">
                            <i class="fa-solid fa-user-clock icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body"><?= $pendingCount ?></div>
                        <div class="text-body-secondary"><?= gettext('Pending review') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-success text-white avatar rounded-circle">
                            <i class="fa-solid fa-user-check icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body"><?= $approvedCount ?></div>
                        <div class="text-body-secondary"><?= gettext('Approved') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-secondary text-white avatar rounded-circle">
                            <i class="fa-solid fa-users icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body"><?= $pendingCount + $approvedCount ?></div>
                        <div class="text-body-secondary"><?= gettext('Total registrations') ?></div>
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
                <h3 class="card-title"><?= gettext('Pending Registrations') ?></h3>
            </div>
            <div class="card-body">
                <p class="text-body-secondary">
                    <?= gettext('Registrations awaiting review from your public registration form. Entries with no email or phone are flagged — verify contact info before following up.') ?>
                </p>
                <div id="bulkBar" class="d-none mb-3">
                    <button type="button" id="approveSelected" class="btn btn-success" disabled>
                        <i class="fa-solid fa-check me-1"></i><?= gettext('Approve selected') ?> (<span id="selectedCount">0</span>)
                    </button>
                </div>
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

    var canApprove = !!(window.CRM.permissions && window.CRM.permissions.editRecords);

    function updateSelectedCount() {
        var n = $('#selfRegistrations .row-select:checked').length;
        $('#selectedCount').text(n);
        $('#approveSelected').prop('disabled', n === 0);
    }

    function addMonthHeaders(api) {
        var cols = api.columns().count();
        var last = null;
        api.rows({ page: 'current' }).every(function () {
            var key = moment(this.data().dateEntered).format('YYYY-MM');
            $(this.node()).attr('data-month', key);
            if (key !== last) {
                last = key;
                $(this.node()).before(
                    '<tr class="month-group table-active"><td colspan="' + cols + '"><div class="d-flex align-items-center">' +
                    (canApprove ? '<input type="checkbox" class="form-check-input month-select me-2" data-month="' + key + '" aria-label="' + window.CRM.escapeHtml(i18next.t('Select month')) + '">' : '') +
                    '<strong>' + window.CRM.escapeHtml(moment(key + '-01').format('MMMM YYYY')) + '</strong></div></td></tr>'
                );
            }
        });
    }

    function initializeSelfRegister() {
        return $.when(
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
                    dateEntered: f.DateEntered,
                    needsReview: !!f.NeedsReview
                };
            });
            var people = (peopleResp[0].people || []).map(function (p) {
                return {
                    type: 'individual',
                    id: p.Id,
                    name: p.FullName,
                    email: p.Email,
                    phone: p.HomePhone || p.CellPhone,
                    dateEntered: p.DateEntered,
                    needsReview: !!p.NeedsReview
                };
            });

            var dataTableConfig = {
                data: families.concat(people),
                autoWidth: false,
                columns: [
                    {
                        title: canApprove ? '<input type="checkbox" class="form-check-input" id="selectAll" aria-label="' + window.CRM.escapeHtml(i18next.t('Select all')) + '">' : '',
                        data: null,
                        orderable: false,
                        searchable: false,
                        visible: canApprove,
                        className: 'w-1 no-export',
                        render: function (data, type, row) {
                            return '<input type="checkbox" class="form-check-input row-select" data-entity-type="' + row.type + '" data-entity-id="' + row.id + '">';
                        }
                    },
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
                        render: function (data, type) {
                            return type === 'display' ? moment(data).format("ll") : data;
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
                                ? window.CRM.renderFamilyActionMenu(row.id, row.name, { needsReview: row.needsReview })
                                : window.CRM.renderPersonActionMenu(row.id, row.name, { needsReview: row.needsReview });
                        }
                    }
                ],
                order: [[4, "desc"]],
                paging: false,
                drawCallback: function () {
                    $('#selfRegistrations tr.month-group').remove();
                    addMonthHeaders(this.api());
                    updateSelectedCount();
                }
            };

            $.extend(dataTableConfig, window.CRM.plugin.dataTable);
            $("#selfRegistrations").DataTable(dataTableConfig);
            $('#bulkBar').toggleClass('d-none', !canApprove);
        }).fail(function () {
            window.CRM.notify(
                i18next.t("Error loading self-registered entries"),
                { type: "danger", delay: 6000 }
            );
        });
    }

    var approvalInFlight = false;

    function reloadSelfRegister() {
        $('#selfRegistrations tr.month-group').remove();
        $('#selfRegistrations').DataTable().destroy();
        return initializeSelfRegister().always(function () {
            approvalInFlight = false;
        });
    }

    function approve(path, payload) {
        if (approvalInFlight) {
            return;
        }
        approvalInFlight = true;
        window.CRM.APIRequest({
            method: 'POST',
            path: path,
            data: payload ? JSON.stringify(payload) : undefined
        }).done(function () {
            window.CRM.notify(i18next.t('Approved'), { type: 'success', delay: 3000 });
            reloadSelfRegister();
        }).fail(function (xhr) {
            approvalInFlight = false;
            var msg = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : i18next.t('An error occurred');
            window.CRM.notify(msg, { type: 'danger', delay: 5000 });
        });
    }

    // Approve one self-registered family or family-less person from its row menu
    $(document).on('click', '.approve-review', function () {
        var entityType = $(this).data('entity-type');
        approve((entityType === 'family' ? 'family/' : 'person/') + $(this).data('entity-id') + '/approve-review');
    });

    // Approve every ticked row in one request
    $(document).on('click', '#approveSelected', function () {
        var payload = { families: [], persons: [] };
        $('#selfRegistrations .row-select:checked').each(function () {
            payload[$(this).data('entity-type') === 'family' ? 'families' : 'persons'].push($(this).data('entity-id'));
        });
        approve('persons/self-register/approve', payload);
    });

    $(document).on('change', '#selectAll', function () {
        $('#selfRegistrations .row-select').prop('checked', this.checked);
        $('#selfRegistrations .month-select').prop('checked', this.checked);
        updateSelectedCount();
    });

    $(document).on('change', '.month-select', function () {
        $('#selfRegistrations tr[data-month="' + $(this).data('month') + '"] .row-select').prop('checked', this.checked);
        updateSelectedCount();
    });

    $(document).on('change', '.row-select', updateSelectedCount);

    // Wait for locales to load before initializing
    $(document).ready(function () {
        window.CRM.onLocalesReady(initializeSelfRegister);
    });
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
