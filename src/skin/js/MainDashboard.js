$(document).ready(function () {
    let dataTableDashboardDefaults = {
        "paging": false,
        "ordering": false,
        "info": false,
        dom: "<'row'<'col-sm-12't>>"
    }

    let dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/families/latest",
            dataSrc: "families"
        },
        columns: [
            {
                width: '15px',
                sortable: false,
                title: i18next.t('Action'),
                data: 'Id',
                render: function (data, type, row) {
                    return buildFamilyActionButtons(row.FamilyId);
                },
                searchable: false
            },
            {
                title: i18next.t('Name'),
                data: 'Name'
            },
            {
                title: i18next.t('Address'),
                data: 'Address'
            },
            {
                title: i18next.t('Created'),
                data: 'Created'
            }
        ]
    }
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $.extend(dataTableConfig, dataTableDashboardDefaults);
    $("#latestFamiliesDashboardItem").DataTable(dataTableConfig);

    dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/persons/birthday",
            dataSrc: "people"
        },
        columns: [
            {
                title: i18next.t('Name'),
                data: 'FirstName',
                render: function (data, type, row) {
                    return '<a href="' + window.CRM.root + '/PersonView.php?PersonID=' + row.PersonId + '">'+ row.FormattedName +'</a> '
                }
            },
            {
                title: i18next.t('Email'),
                data: 'Email',
                render: function (data, type, row) {
                    return buildRenderEmail(data);
                }
            },
            {
                title: i18next.t('Birthday'),
                data: 'Birthday'
            }
        ]
    }
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $.extend(dataTableConfig, dataTableDashboardDefaults);
    $("#PersonBirthdayDashboardItem").DataTable(dataTableConfig);

    dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/families/anniversaries",
            dataSrc: "families"
        },
        columns: [
            {
                title: i18next.t('Name'),
                data: 'Name',
                render: function (data, type, row) {
                    return '<a href="' + window.CRM.root + '/v2/family/' + row.FamilyId + '">'+ data +'</a> '
                }
            },
            {
                title: i18next.t('Anniversary'),
                data: 'WeddingDate'
            }
        ]
    }
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $.extend(dataTableConfig, dataTableDashboardDefaults);
    $("#FamiliesWithAnniversariesDashboardItem").DataTable(dataTableConfig);

    dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/families/updated",
            dataSrc: "families"
        },
        columns: [
            {
                width: '15px',
                sortable: false,
                title: i18next.t('Action'),
                data: 'Id',
                render: function (data, type, row) {
                    return buildFamilyActionButtons(row.FamilyId);
                },
                searchable: false
            },
            {
                title: i18next.t('Name'),
                data: 'Name'
            },
            {
                title: i18next.t('Address'),
                data: 'Address'
            },
            {
                title: i18next.t('Updated'),
                data: 'LastEdited'
            }
        ]
    }
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $.extend(dataTableConfig, dataTableDashboardDefaults);
    $("#updatedFamiliesDashboardItem").DataTable(dataTableConfig);

    function buildFamilyActionButtons(familyId) {
        return '<a href="' + window.CRM.root + '/v2/family/' + familyId + '><button class="btn btn-default"><i class="fa fa-search-plus"></i></button></a> ' +
            '<a href="' + window.CRM.root + '/FamilyEditor.php?FamilyID=' + familyId + '><button class="btn btn-default"><i class="fa fa-pencil"></i></button></a>';
    }

    dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/persons/updated",
            dataSrc: "people"
        },
        columns: [
            {
                width: '15px',
                sortable: false,
                title: i18next.t('Action'),
                data: 'Id',
                render: function (data, type, row) {
                    return buildPersonActionButtons(row.PersonId);
                },
                searchable: false
            },
            {
                title: i18next.t('First Name'),
                data: 'FirstName'
            },
            {
                title: i18next.t('Last Name'),
                data: 'LastName'
            },
            {
                title: i18next.t('Email'),
                data: 'Email',
                render: function (data, type, row) {
                    return buildRenderEmail(data);
                }
            },
            {
                title: i18next.t('Updated'),
                data: 'LastEdited'
            }
        ]
    }
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $.extend(dataTableConfig, dataTableDashboardDefaults);
    $("#updatedPersonDashboardItem").DataTable(dataTableConfig);


    dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/persons/latest",
            dataSrc: "people"
        },
        columns: [
            {
                width: '15px',
                sortable: false,
                title: i18next.t('Action'),
                data: 'Id',
                render: function (data, type, row) {
                    return buildPersonActionButtons(row.PersonId);
                },
                searchable: false
            },
            {
                title: i18next.t('First Name'),
                data: 'FirstName'
            },
            {
                title: i18next.t('Last Name'),
                data: 'LastName'
            },
            {
                title: i18next.t('Email'),
                data: 'Email',
                render: function (data, type, row) {
                    return buildRenderEmail(data);
                }
            },
            {
                title: i18next.t('Created'),
                data: 'Created'
            }
        ]
    }
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $.extend(dataTableConfig, dataTableDashboardDefaults);
    $("#latestPersonDashboardItem").DataTable(dataTableConfig);

    function buildRenderEmail(email) {
        if (email) {
            return "<a href='mailto:"+ email +"''>"+ email + "</a>";
        }
        return "";
    }

    function buildPersonActionButtons(personId) {
        return '<a href="' + window.CRM.root + '/PersonView.php?PersonID=' + personId + '><button class="btn btn-default"><i class="fa fa-search-plus"></i></button></a> ' +
            '<a href="' + window.CRM.root + '/PersonView.php?PersonID=' + personId + '><button class="btn btn-default"><i class="fa fa-pencil"></i></button></a>';
    }

    if ($('#depositChartRow').length > 0) {
        window.CRM.APIRequest({
            method: 'GET',
            path: 'deposits/dashboard'
        }).done(function (data) {
            let lineDataRaw = data;
            let lineData = {
                labels: [],
                datasets: [
                    {
                        label: "Value",
                        data: []
                    }
                ]
            };
            $.each(lineDataRaw, function (i, val) {
                lineData.labels.push(moment(val.Date).format("MM-DD-YY"));
                lineData.datasets[0].data.push(val.totalAmount);
            });

            new Chart($("#deposit-lineGraph").get(0).getContext("2d"), {
                    type: 'line',
                    data: lineData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                }
            );
        });
    }

});
