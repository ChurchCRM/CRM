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
            width: "15%",
            sortable: false,
            title: i18next.t("Action"),
            data: "FamilyId",
            render: function (data, type, row) {
                return (
                    '<a href="' +
                    window.CRM.root +
                    "/FamilyEditor.php?FamilyID=" +
                    row.FamilyId +
                    '" class="btn btn-sm btn-default" title="' +
                    i18next.t("Edit") +
                    '"><i class="fa-solid fa-pen"></i></a> ' +
                    '<span class="AddToCart" data-cart-id="' +
                    row.FamilyId +
                    '" data-cart-type="family">' +
                    '<button class="btn btn-sm btn-primary" title="' +
                    i18next.t("Add to Cart") +
                    '"><i class="fa-solid fa-cart-plus"></i></button>' +
                    "</span>"
                );
            },
            searchable: false,
        },
        {
            width: "35%",
            title: i18next.t("Name"),
            data: "Name",
            render: function (data, type, row) {
                return (
                    '<a href="' +
                    window.CRM.root +
                    "/v2/family/" +
                    row.FamilyId +
                    '"><strong>' +
                    row.Name +
                    "</strong></a>"
                );
            },
        },
        {
            width: "30%",
            title: i18next.t("Location"),
            data: "Address",
            render: function (data, type, row) {
                if (!data) return '<span class="text-muted">—</span>';
                // Extract city and state from address (last parts before country)
                let parts = data.split(",").map(function (s) {
                    return s.trim();
                });
                if (parts.length >= 2) {
                    // Try to get city and state (usually 2nd and 3rd from end, before country)
                    let cityState = parts.slice(-3, -1).join(", ");
                    if (cityState) {
                        return '<span title="' + data + '">' + cityState + "</span>";
                    }
                }
                return (
                    '<span title="' + data + '">' + data.substring(0, 30) + (data.length > 30 ? "..." : "") + "</span>"
                );
            },
        },
    ];

    let latestFamilyColumns = dataTableFamilyColumns.slice();
    latestFamilyColumns.push({
        width: "20%",
        title: i18next.t("Created"),
        data: "Created",
        render: function (data) {
            if (!data) return "";
            return '<small class="text-muted">' + moment(data).fromNow() + "</small>";
        },
    });

    let dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/families/latest",
            dataSrc: "families",
        },
        columns: latestFamilyColumns,
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $.extend(dataTableConfig, dataTableDashboardDefaults);
    let latestFamiliesTable = $("#latestFamiliesDashboardItem").DataTable(dataTableConfig);
    latestFamiliesTable.on("draw", function () {
        syncCartButtons();
    });

    let updatedFamilyColumns = dataTableFamilyColumns.slice();
    updatedFamilyColumns.push({
        width: "20%",
        title: i18next.t("Updated"),
        data: "LastEdited",
        render: function (data) {
            if (!data) return "";
            return '<small class="text-muted">' + moment(data).fromNow() + "</small>";
        },
    });

    dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/families/updated",
            dataSrc: "families",
        },
        columns: updatedFamilyColumns,
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
            dataSrc: function (json) {
                // Hide card if no data
                if (!json.people || json.people.length === 0) {
                    $("#birthdayCard").hide();
                }
                return json.people || [];
            },
        },
        columns: [
            {
                width: "60%",
                title: i18next.t("Name"),
                data: "FirstName",
                render: function (data, type, row) {
                    let ageText = row.Age ? ' <small class="text-muted">(' + row.Age + ")</small>" : "";
                    let photo =
                        '<img data-image-entity-type="person" data-image-entity-id="' +
                        row.PersonId +
                        '" class="photo-tiny rounded-circle mr-2" style="width:30px;height:30px;object-fit:cover;">';
                    return (
                        photo +
                        '<a href="' +
                        window.CRM.root +
                        "/PersonView.php?PersonID=" +
                        row.PersonId +
                        '"><strong>' +
                        row.FormattedName +
                        "</strong></a>" +
                        ageText
                    );
                },
            },
            {
                width: "40%",
                title: i18next.t("Birthday"),
                data: "DaysUntil",
                render: function (data, type, row) {
                    if (row.Birthday === undefined) return "";
                    let diff = row.DaysUntil;

                    let badge = "";
                    if (diff === 0) {
                        badge = '<span class="badge badge-success">' + i18next.t("Today") + "!</span>";
                    } else if (diff > 0) {
                        badge =
                            '<span class="badge badge-info">' +
                            i18next.t("in") +
                            " " +
                            diff +
                            " " +
                            (diff === 1 ? i18next.t("day") : i18next.t("days")) +
                            "</span>";
                    } else {
                        badge =
                            '<span class="badge badge-secondary">' +
                            Math.abs(diff) +
                            " " +
                            (Math.abs(diff) === 1 ? i18next.t("day") : i18next.t("days")) +
                            " " +
                            i18next.t("ago") +
                            "</span>";
                    }
                    return row.Birthday + " " + badge;
                },
            },
        ],
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $.extend(dataTableConfig, dataTableDashboardDefaults);
    let birthdayPersonTable = $("#PersonBirthdayDashboardItem").DataTable(dataTableConfig);
    birthdayPersonTable.on("draw", function () {
        syncCartButtons();
        // Refresh image loader for dynamically added photos
        if (window.CRM && window.CRM.peopleImageLoader) {
            window.CRM.peopleImageLoader.refresh();
        }
    });

    dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/families/anniversaries",
            dataSrc: function (json) {
                // Hide card if no data
                if (!json.families || json.families.length === 0) {
                    $("#anniversaryCard").hide();
                }
                return json.families || [];
            },
        },
        columns: [
            {
                width: "50%",
                title: i18next.t("Name"),
                data: "Name",
                render: function (data, type, row) {
                    return (
                        '<a href="' +
                        window.CRM.root +
                        "/v2/family/" +
                        row.FamilyId +
                        '"><strong>' +
                        data +
                        "</strong></a>"
                    );
                },
            },
            {
                width: "50%",
                title: i18next.t("Anniversary"),
                data: "WeddingDate",
                render: function (data, type, row) {
                    if (!data) return "";
                    let weddingDate = moment(data, ["MMMM D, YYYY", "MMMM D", "MM-DD-YYYY"]);
                    let thisYear = moment().year();
                    let anniversaryThisYear = weddingDate.clone().year(thisYear);
                    let today = moment().startOf("day");
                    let diff = anniversaryThisYear.diff(today, "days");
                    let years = thisYear - weddingDate.year();

                    let badge = "";
                    if (diff === 0) {
                        badge =
                            '<span class="badge badge-success ml-2">' +
                            years +
                            " " +
                            i18next.t("years") +
                            " " +
                            i18next.t("Today") +
                            "!</span>";
                    } else if (diff > 0) {
                        badge =
                            '<span class="badge badge-info ml-2">' +
                            i18next.t("in") +
                            " " +
                            diff +
                            " " +
                            (diff === 1 ? i18next.t("day") : i18next.t("days")) +
                            "</span>";
                    } else {
                        badge =
                            '<span class="badge badge-secondary ml-2">' +
                            Math.abs(diff) +
                            " " +
                            (Math.abs(diff) === 1 ? i18next.t("day") : i18next.t("days")) +
                            " " +
                            i18next.t("ago") +
                            "</span>";
                    }
                    return data + badge;
                },
            },
        ],
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $.extend(dataTableConfig, dataTableDashboardDefaults);
    $("#FamiliesWithAnniversariesDashboardItem").DataTable(dataTableConfig);

    let dataTablePersonColumns = [
        {
            width: "15%",
            sortable: false,
            title: i18next.t("Action"),
            data: "PersonId",
            render: function (data, type, row) {
                return (
                    '<a href="' +
                    window.CRM.root +
                    "/PersonEditor.php?PersonID=" +
                    row.PersonId +
                    '" class="btn btn-sm btn-default" title="' +
                    i18next.t("Edit") +
                    '"><i class="fa-solid fa-pen"></i></a> ' +
                    '<span class="AddToCart" data-cart-id="' +
                    row.PersonId +
                    '" data-cart-type="person">' +
                    '<button class="btn btn-sm btn-primary" title="' +
                    i18next.t("Add to Cart") +
                    '"><i class="fa-solid fa-cart-plus"></i></button>' +
                    "</span>"
                );
            },
            searchable: false,
        },
        {
            width: "25%",
            title: i18next.t("Name"),
            data: "FirstName",
            render: function (data, type, row) {
                return (
                    '<a href="' +
                    window.CRM.root +
                    "/PersonView.php?PersonID=" +
                    row.PersonId +
                    '"><strong>' +
                    row.FirstName +
                    " " +
                    row.LastName +
                    "</strong></a>"
                );
            },
        },
        {
            width: "25%",
            title: i18next.t("Family"),
            data: "FamilyName",
            render: function (data, type, row) {
                if (!row.FamilyId || !row.FamilyName) {
                    return '<span class="text-muted">—</span>';
                }
                return '<a href="' + window.CRM.root + "/v2/family/" + row.FamilyId + '">' + row.FamilyName + "</a>";
            },
        },
    ];

    let updatedPersonColumns = dataTablePersonColumns.slice();
    updatedPersonColumns.push({
        width: "20%",
        title: i18next.t("Updated"),
        data: "LastEdited",
        render: function (data) {
            if (!data) return "";
            return '<small class="text-muted">' + moment(data).fromNow() + "</small>";
        },
    });

    dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/persons/updated",
            dataSrc: "people",
        },
        columns: updatedPersonColumns,
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $.extend(dataTableConfig, dataTableDashboardDefaults);
    let updatedPersonTable = $("#updatedPersonDashboardItem").DataTable(dataTableConfig);
    updatedPersonTable.on("draw", function () {
        syncCartButtons();
    });

    let latestPersonColumns = dataTablePersonColumns.slice();
    latestPersonColumns.push({
        width: "20%",
        title: i18next.t("Created"),
        data: "Created",
        render: function (data) {
            if (!data) return "";
            return '<small class="text-muted">' + moment(data).fromNow() + "</small>";
        },
    });

    dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/persons/latest",
            dataSrc: "people",
        },
        columns: latestPersonColumns,
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

            // Hide the card if there's no deposit data
            if (!lineDataRaw || lineDataRaw.length === 0) {
                $("#depositChartRow").hide();
                return;
            }

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
