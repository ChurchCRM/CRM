<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="card">
    <div class="card-header d-flex align-items-center flex-wrap gap-2">
        <h3 class="card-title mb-0">
            <?= _("People Without Emails") ?>
            <span id="personCount" class="badge bg-primary text-white ms-2 d-none"></span>
        </h3>
        <div class="ms-auto">
            <ul class="nav nav-pills" id="ageFilter">
                <li class="nav-item">
                    <a href="#" class="nav-link active" data-filter="all"><?= _("Everyone") ?></a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" data-filter="adults"><?= _("Adults") ?></a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" data-filter="children"><?= _("Children") ?></a>
                </li>
            </ul>
        </div>
    </div>
    <div style="overflow: visible;">
        <table id="noEmails" class="table table-vcenter table-hover card-table">
            <tbody></tbody>
        </table>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    function initializeEmailWithout() {
        var table = $("#noEmails").DataTable($.extend({}, window.CRM.plugin.dataTable, {
            ajax: {
                url: window.CRM.root + "/api/persons/email/without",
                dataSrc: function (json) {
                    $('#personCount').text(json.count).removeClass('d-none');
                    return json.persons;
                }
            },
            autoWidth: false,
            columns: [
                {
                    title: i18next.t('Name'),
                    data: 'FullName',
                    render: function (data, type, row) {
                        if (type !== 'display') {
                            return data;
                        }
                        return "<a href='" + window.CRM.root + "/PersonEditor.php?PersonID=" + row.Id + "'>" +
                               window.CRM.escapeHtml(data) + "</a>";
                    },
                    searchable: true
                },
                {
                    title: i18next.t('Family'),
                    data: 'FamilyName',
                    render: function (data, type, row) {
                        if (type !== 'display' || !row.FamilyId) {
                            return data;
                        }
                        return "<a href='" + window.CRM.root + "/people/family/" + row.FamilyId + "'>" +
                               window.CRM.escapeHtml(data) + "</a>";
                    },
                    searchable: true
                },
                {
                    title: i18next.t('Role'),
                    data: 'FamilyRole',
                    searchable: true
                },
                {
                    title: i18next.t('Classification'),
                    data: 'Classification',
                    searchable: true
                },
                {
                    title: i18next.t('Age'),
                    data: 'Age',
                    render: function (data, type) {
                        if (type !== 'display') {
                            return data !== null ? data : -1;
                        }
                        return data !== null ? data : '<span class="text-muted">—</span>';
                    },
                    searchable: false
                },
                {
                    // Hidden column used by age filter pills (values: "adult" | "child")
                    title: '',
                    data: 'IsChild',
                    visible: false,
                    searchable: true,
                    render: function (data) {
                        return data ? 'child' : 'adult';
                    }
                },
                {
                    title: i18next.t('Actions'),
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-end w-1 no-export',
                    render: function (data, type, row) {
                        return window.CRM.renderPersonActionMenu(row.Id, row.FullName);
                    }
                }
            ]
        }));

        $('#ageFilter a').on('click', function (e) {
            e.preventDefault();
            $('#ageFilter a').removeClass('active');
            $(this).addClass('active');
            var filter = $(this).data('filter');
            var search = filter === 'adults' ? '^adult$' : (filter === 'children' ? '^child$' : '');
            // column index 5 is the hidden IsChild column
            table.column(5).search(search, true, false).draw();
        });
    }

    // Wait for locales to load before initializing
    $(document).ready(function () {
        window.CRM.onLocalesReady(initializeEmailWithout);
    });
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
