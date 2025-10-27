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
                // Note: Dashboard doesn't show Remove buttons for families because
                // we don't have family member info here. Family member IDs are stored in cart,
                // not family IDs. To properly remove a family, use family-list.php instead.
                return (
                    '<a href="' +
                    window.CRM.root +
                    "/v2/family/" +
                    row.FamilyId +
                    '" class="btn-link"><button class="btn btn-sm btn-default" title="' +
                    i18next.t("View") +
                    '"><i class="fa-solid fa-search-plus"></i></button></a> ' +
                    '<a href="' +
                    window.CRM.root +
                    "/FamilyEditor.php?FamilyID=" +
                    row.FamilyId +
                    '" class="btn-link"><button class="btn btn-sm btn-default" title="' +
                    i18next.t("Edit") +
                    '"><i class="fa-solid fa-pen"></i></button></a> ' +
                    '<a class="AddToPeopleCart" data-cartpersonid="' +
                    row.FamilyId +
                    '-fam" data-familyid="' +
                    row.FamilyId +
                    '" href="#"><button class="btn btn-sm btn-primary" title="' +
                    i18next.t("Add to Cart") +
                    '"><i class="fa-solid fa-cart-plus"></i></button></a>'
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
    $("#latestFamiliesDashboardItem").DataTable(dataTableConfig);

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
    $("#updatedFamiliesDashboardItem").DataTable(dataTableConfig);

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
                    '<a href="' +
                    window.CRM.root +
                    "/PersonView.php?PersonID=" +
                    row.PersonId +
                    '" class="btn-link"><button class="btn btn-sm btn-default" title="' +
                    i18next.t("View") +
                    '"><i class="fa-solid fa-search-plus"></i></button></a> ' +
                    '<a href="' +
                    window.CRM.root +
                    "/PersonEditor.php?PersonID=" +
                    row.PersonId +
                    '" class="btn-link"><button class="btn btn-sm btn-default" title="' +
                    i18next.t("Edit") +
                    '"><i class="fa-solid fa-pen"></i></button></a> ' +
                    '<a class="AddToPeopleCart" data-cartpersonid="' +
                    row.PersonId +
                    '" href="#"><button class="btn btn-sm btn-primary" title="' +
                    i18next.t("Add to Cart") +
                    '"><i class="fa-solid fa-cart-plus"></i></button></a>'
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

    // Handle cart button clicks in dashboard tables
    $(document).on("click", ".AddToPeopleCart", function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        const $button = $(this);
        const personId = $button.data("cartpersonid");
        const familyId = $button.data("familyid");

        // Check if it's a family cart button (marked with '-fam' suffix)
        if (familyId) {
            // Add family to cart
            if (window.CRM && window.CRM.cartManager) {
                window.CRM.cartManager.addFamily(familyId, {
                    callback: function () {
                        // Update button to show remove state after successful add
                        $button
                            .removeClass("AddToPeopleCart")
                            .addClass("RemoveFromPeopleCart");
                        $button
                            .find("button")
                            .removeClass("btn-primary")
                            .addClass("btn-danger");
                        $button
                            .find("i")
                            .removeClass("fa-cart-plus")
                            .addClass("fa-shopping-cart");
                        $button
                            .find("button")
                            .attr("title", i18next.t("Remove from Cart"));
                    },
                });
            }
        } else {
            // Add person to cart
            if (window.CRM && window.CRM.cartManager) {
                window.CRM.cartManager.addPerson(personId, {
                    callback: function () {
                        // Update button to show remove state after successful add
                        $button
                            .removeClass("AddToPeopleCart")
                            .addClass("RemoveFromPeopleCart");
                        $button
                            .find("button")
                            .removeClass("btn-primary")
                            .addClass("btn-danger");
                        $button
                            .find("i")
                            .removeClass("fa-cart-plus")
                            .addClass("fa-shopping-cart");
                        $button
                            .find("button")
                            .attr("title", i18next.t("Remove from Cart"));
                    },
                });
            }
        }
    });

    // Handle remove from cart button clicks in dashboard tables
    $(document).on("click", ".RemoveFromPeopleCart", function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        const $button = $(this);
        const personId = $button.data("cartpersonid");
        const familyId = $button.data("familyid");

        // Check if it's a family cart button (marked with '-fam' suffix)
        if (familyId) {
            // Remove family from cart
            if (window.CRM && window.CRM.cartManager) {
                window.CRM.cartManager.removeFamily(familyId, {
                    callback: function () {
                        // Update button to show add state after successful remove
                        $button
                            .removeClass("RemoveFromPeopleCart")
                            .addClass("AddToPeopleCart");
                        $button
                            .find("button")
                            .removeClass("btn-danger")
                            .addClass("btn-primary");
                        $button
                            .find("i")
                            .removeClass("fa-shopping-cart")
                            .addClass("fa-cart-plus");
                        $button
                            .find("button")
                            .attr("title", i18next.t("Add to Cart"));
                    },
                });
            }
        } else {
            // Remove person from cart
            if (window.CRM && window.CRM.cartManager) {
                window.CRM.cartManager.removePerson(personId, {
                    confirm: false,
                    callback: function () {
                        // Update button to show add state after successful remove
                        $button
                            .removeClass("RemoveFromPeopleCart")
                            .addClass("AddToPeopleCart");
                        $button
                            .find("button")
                            .removeClass("btn-danger")
                            .addClass("btn-primary");
                        $button
                            .find("i")
                            .removeClass("fa-shopping-cart")
                            .addClass("fa-cart-plus");
                        $button
                            .find("button")
                            .attr("title", i18next.t("Add to Cart"));
                    },
                });
            }
        }
    });
});
