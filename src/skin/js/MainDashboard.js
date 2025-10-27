$(document).ready(function () {
    let dataTableDashboardDefaults = {
        paging: false,
        ordering: false,
        info: false,
        dom: "<'row'<'col-sm-12't>>",
    };

    let dataTableFamilyColumns = [
        {
            width: "130px",
            sortable: false,
            title: i18next.t("Action"),
            data: "FamilyId",
            render: function (data, type, row) {
                return (
                    '<div class="btn-group btn-group-sm" role="group">' +
                    '<a href="' +
                    window.CRM.root +
                    "/v2/family/" +
                    row.FamilyId +
                    '" class="btn-link"><button class="btn btn-sm btn-default" title="' +
                    i18next.t("View") +
                    '"><i class="fa-solid fa-search-plus"></i></button></a>' +
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
                return (
                    '<a href="' +
                    window.CRM.root +
                    "/v2/family/" +
                    row.FamilyId +
                    '">' +
                    row.Name +
                    "</a>"
                );
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
    let latestFamiliesTable = $("#latestFamiliesDashboardItem").DataTable(
        dataTableConfig,
    );
    latestFamiliesTable.on("draw", function () {
        updateFamilyCartButtons();
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
    let updatedFamiliesTable = $("#updatedFamiliesDashboardItem").DataTable(
        dataTableConfig,
    );
    updatedFamiliesTable.on("draw", function () {
        updateFamilyCartButtons();
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
    $("#PersonBirthdayDashboardItem").DataTable(dataTableConfig);

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
                    return (
                        '<a href="' +
                        window.CRM.root +
                        "/v2/family/" +
                        row.FamilyId +
                        '">' +
                        data +
                        "</a> "
                    );
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
            width: "130px",
            sortable: false,
            title: i18next.t("Action"),
            data: "PersonId",
            render: function (data, type, row) {
                return (
                    '<div class="btn-group btn-group-sm" role="group">' +
                    '<a href="' +
                    window.CRM.root +
                    "/PersonView.php?PersonID=" +
                    row.PersonId +
                    '" class="btn-link"><button class="btn btn-sm btn-default" title="' +
                    i18next.t("View") +
                    '"><i class="fa-solid fa-search-plus"></i></button></a>' +
                    '<a href="' +
                    window.CRM.root +
                    "/PersonEditor.php?PersonID=" +
                    row.PersonId +
                    '" class="btn-link"><button class="btn btn-sm btn-default" title="' +
                    i18next.t("Edit") +
                    '"><i class="fa-solid fa-pen"></i></button></a>' +
                    '<div class="AddToCart" data-cart-id="' +
                    row.PersonId +
                    '" data-cart-type="person">' +
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
            width: "22%",
            title: i18next.t("First Name"),
            data: "FirstName",
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
    $("#updatedPersonDashboardItem").DataTable(dataTableConfig);

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
    $("#latestPersonDashboardItem").DataTable(dataTableConfig);

    function updateFamilyCartButtons() {
        if (window.CRM && window.CRM.cartManager) {
            window.CRM.APIRequest({
                method: "GET",
                path: "families/familiesInCart",
                suppressErrorDialog: true,
            }).done(function (data) {
                if (
                    data &&
                    data.familiesInCart &&
                    Array.isArray(data.familiesInCart)
                ) {
                    data.familiesInCart.forEach(function (familyId) {
                        window.CRM.cartManager.updateButtonState(
                            familyId,
                            true,
                            "family",
                        );
                    });
                }
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
});
