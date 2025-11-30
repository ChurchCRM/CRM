/**
 * Main Dashboard initialization script
 * Requires: moment.js (loaded globally), i18next, DataTables
 */

function initializeMainDashboard() {
    let dataTableDashboardDefaults = {
        paging: false,
        ordering: false,
        info: false,
        dom: "<'row'<'col-sm-12't>>",
    };

    let dataTableFamilyColumns = [
        {
            width: "100px",
            sortable: false,
            title: i18next.t("Action"),
            data: "FamilyId",
            render: function (data, type, row) {
                return (
                    '<div class="btn-group btn-group-sm" role="group">' +
                    '<a href="' +
                    window.CRM.root +
                    "/FamilyEditor.php?FamilyID=" +
                    row.FamilyId +
                    '" class="btn-link"><button class="btn btn-sm btn-default" title="' +
                    i18next.t("Edit") +
                    '"><i class="fa-solid fa-pen"></i></button></a>' +
                    '<div class="AddToCart" data-cart-id="' +
                    row.FamilyId +
                    '" data-cart-type="family">' +
                    '<button class="btn btn-sm btn-primary" title="' +
                    i18next.t("Add to Cart") +
                    '"><i class="fa-solid fa-cart-plus"></i></button>' +
                    "</div>" +
                    "</div>"
                );
            },
            searchable: false,
        },
        {
            width: "30%",
            title: i18next.t("Name"),
            data: "Name",
            render: function (data, type, row) {
                return '<a href="' + window.CRM.root + "/v2/family/" + row.FamilyId + '">' + row.Name + "</a>";
            },
        },
        {
            width: "35%",
            title: i18next.t("Address"),
            data: "Address",
        },
    ];

    let dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/families/latest",
            dataSrc: "families",
        },
        columns: $.extend(dataTableFamilyColumns, {
            title: i18next.t("Created"),
            data: "Created",
        }),
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $.extend(dataTableConfig, dataTableDashboardDefaults);
    let latestFamiliesTable = $("#latestFamiliesDashboardItem").DataTable(dataTableConfig);
    latestFamiliesTable.on("draw", function () {
        syncCartButtons();
    });

    dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/families/updated",
            dataSrc: "families",
        },
        columns: $.extend(dataTableFamilyColumns, {
            title: i18next.t("Updated"),
            data: "LastEdited",
        }),
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $.extend(dataTableConfig, dataTableDashboardDefaults);
    let updatedFamiliesTable = $("#updatedFamiliesDashboardItem").DataTable(dataTableConfig);
    updatedFamiliesTable.on("draw", function () {
        syncCartButtons();
    });

    dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/persons/birthday",
            dataSrc: "people",
        },
        columns: [
            {
                width: "40%",
                title: i18next.t("Name"),
                data: "FirstName",
                render: function (data, type, row) {
                    return (
                        '<a href="' +
                        window.CRM.root +
                        "/PersonView.php?PersonID=" +
                        row.PersonId +
                        '">' +
                        row.FormattedName +
                        "</a> "
                    );
                },
            },
            {
                width: "40%",
                title: i18next.t("Email"),
                data: "Email",
                render: function (data, type, row) {
                    return buildRenderEmail(data);
                },
            },
            {
                width: "20%",
                title: i18next.t("Birthday"),
                data: "Birthday",
            },
        ],
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $.extend(dataTableConfig, dataTableDashboardDefaults);
    let birthdayPersonTable = $("#PersonBirthdayDashboardItem").DataTable(dataTableConfig);
    birthdayPersonTable.on("draw", function () {
        syncCartButtons();
    });

    dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/families/anniversaries",
            dataSrc: "families",
        },
        columns: [
            {
                width: "60%",
                title: i18next.t("Name"),
                data: "Name",
                render: function (data, type, row) {
                    return '<a href="' + window.CRM.root + "/v2/family/" + row.FamilyId + '">' + data + "</a> ";
                },
            },
            {
                width: "40%",
                title: i18next.t("Anniversary"),
                data: "WeddingDate",
            },
        ],
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $.extend(dataTableConfig, dataTableDashboardDefaults);
    $("#FamiliesWithAnniversariesDashboardItem").DataTable(dataTableConfig);

    let dataTablePersonColumns = [
        {
            width: "22%",
            title: i18next.t("First Name"),
            data: "FirstName",
            render: function (data, type, row) {
                return (
                    '<a href="' +
                    window.CRM.root +
                    "/PersonView.php?PersonID=" +
                    row.PersonId +
                    '">' +
                    row.FirstName +
                    "</a>"
                );
            },
        },
        {
            width: "22%",
            title: i18next.t("Last Name"),
            data: "LastName",
        },
        {
            width: "20%",
            title: i18next.t("Email"),
            data: "Email",
            render: function (data, type, row) {
                return buildRenderEmail(data);
            },
        },
    ];

    dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/persons/updated",
            dataSrc: "people",
        },
        columns: $.extend(dataTablePersonColumns, {
            title: i18next.t("Updated"),
            data: "LastEdited",
        }),
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $.extend(dataTableConfig, dataTableDashboardDefaults);
    let updatedPersonTable = $("#updatedPersonDashboardItem").DataTable(dataTableConfig);
    updatedPersonTable.on("draw", function () {
        syncCartButtons();
    });

    dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/persons/latest",
            dataSrc: "people",
        },
        columns: $.extend(dataTablePersonColumns, {
            title: i18next.t("Created"),
            data: "Created",
        }),
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $.extend(dataTableConfig, dataTableDashboardDefaults);
    let latestPersonTable = $("#latestPersonDashboardItem").DataTable(dataTableConfig);
    latestPersonTable.on("draw", function () {
        syncCartButtons();
    });
    function syncCartButtons() {
        if (window.CRM && window.CRM.cartManager) {
            Promise.all([
                window.CRM.APIRequest({
                    method: "GET",
                    path: "cart/",
                    suppressErrorDialog: true,
                }),
                window.CRM.APIRequest({
                    method: "GET",
                    path: "families/familiesInCart",
                    suppressErrorDialog: true,
                }),
            ]).then(function (responses) {
                let cartData = responses[0];
                let familiesData = responses[1];

                let peopleInCart = cartData.PeopleCart || [];
                let familiesInCart = familiesData.familiesInCart || [];
                let groupsInCart = cartData.GroupCart || [];

                window.CRM.cartManager.syncButtonStates(peopleInCart, familiesInCart, groupsInCart);
            });
        }
    }

    function buildRenderEmail(email) {
        if (email) {
            return "<a href='mailto:" + email + "''>" + email + "</a>";
        }
        return "";
    }

    if ($("#depositChartRow").is(":visible")) {
        window.CRM.APIRequest({
            method: "GET",
            path: "deposits/dashboard",
        }).done(function (data) {
            let lineDataRaw = data;
            let lineData = {
                labels: [],
                datasets: [
                    {
                        label: "Value",
                        data: [],
                    },
                ],
            };
            $.each(lineDataRaw, function (i, val) {
                lineData.labels.push(moment(val.Date).format("MM-DD-YY"));
                lineData.datasets[0].data.push(val.totalAmount);
            });

            new Chart($("#deposit-lineGraph").get(0).getContext("2d"), {
                type: "line",
                data: lineData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                },
            });
        });
    }

    // CartManager handles all cart button clicks generically via data-cart-id and data-cart-type attributes
}

// Wait for locales to load before initializing
$(document).ready(function () {
    window.CRM.onLocalesReady(initializeMainDashboard);
});
