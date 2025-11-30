/**
 * Deposit Slip Editor
 * Requires: moment.js (loaded globally), i18next, DataTables
 */

function initPaymentTable() {
    var colDef = [
        {
            width: "35%",
            title: i18next.t("Family"),
            data: "FamilyString",
            render: function (data, type, full, meta) {
                var familyName =
                    data && data.trim() ? data : '<em class="text-muted">' + i18next.t("Anonymous") + "</em>";
                var icon = isDepositClosed
                    ? '<i class="fa-solid fa-magnifying-glass"></i>'
                    : '<i class="fa-solid fa-pen"></i>';
                return (
                    '<a class="btn btn-sm btn-outline-primary" href="PledgeEditor.php?linkBack=DepositSlipEditor.php?DepositSlipID=' +
                    depositSlipID +
                    "&GroupKey=" +
                    full.GroupKey +
                    '" title="' +
                    (isDepositClosed ? i18next.t("View") : i18next.t("Edit")) +
                    '">' +
                    icon +
                    "</a>&nbsp;<span>" +
                    familyName +
                    "</span>"
                );
            },
        },
        {
            width: "8%",
            title: i18next.t("Check Number"),
            data: "CheckNo",
            render: function (data, type, full, meta) {
                return data ? "<code>" + data + "</code>" : '<em class="text-muted">-</em>';
            },
        },
        {
            width: "30%",
            title: i18next.t("Fund"),
            data: "FundName",
            render: function (data, type, full, meta) {
                if (!data) {
                    return '<em class="text-muted">-</em>';
                }

                // For sorting and filtering, return plain text
                if (type === "sort" || type === "filter") {
                    return data;
                }

                // For display, split multiple funds and show as individual badges
                var funds = data.split(", ");
                var badges = funds.map(function (fund) {
                    return '<span class="badge badge-info text-white mr-1 mb-1">' + fund.trim() + "</span>";
                });
                return '<div class="d-flex flex-wrap">' + badges.join("") + "</div>";
            },
        },
        {
            width: "12%",
            title: i18next.t("Amount"),
            data: "sumAmount",
            render: function (data, type, full, meta) {
                if (type === "display") {
                    return '<strong class="text-end d-block">$' + parseFloat(data || 0).toFixed(2) + "</strong>";
                }
                return parseFloat(data || 0);
            },
        },
        {
            width: "10%",
            title: i18next.t("Method"),
            data: "Method",
            render: function (data, type, full, meta) {
                var badgeClass = "badge-secondary";
                var icon = "";
                if (data === "CHECK") {
                    badgeClass = "badge-primary";
                    icon = '<i class="fa-solid fa-check-double"></i> ';
                } else if (data === "CASH") {
                    badgeClass = "badge-success";
                    icon = '<i class="fa-solid fa-money-bill"></i> ';
                } else if (data === "CREDITCARD") {
                    badgeClass = "badge-warning";
                    icon = '<i class="fa-solid fa-credit-card"></i> ';
                }
                return '<span class="badge ' + badgeClass + '">' + icon + data + "</span>";
            },
        },
    ];

    if (depositType === "CreditCard") {
        colDef.push({
            width: "auto",
            title: i18next.t("Details"),
            data: "Id",
            render: function (data, type, full, meta) {
                return (
                    '<a class="btn btn-sm btn-info" href="PledgeDetails.php?PledgeID=' +
                    data +
                    '"><i class="fa-solid fa-info-circle"></i> Details</a>'
                );
            },
        });
    }

    var dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/deposits/" + depositSlipID + "/payments",
            dataSrc: "",
            error: function (xhr, error, thrown) {
                console.error("DataTable error:", xhr, error, thrown);
                showGlobalMessage(i18next.t("Error loading payments"), "danger");
            },
        },
        columns: colDef,
        createdRow: function (row, data, index) {
            $(row).addClass("paymentRow").css("cursor", "pointer");
        },
        initComplete: function () {
            // Update payment count badge
            var count = this.api().rows().count();
            $("#payment-count").text(count);
        },
        drawCallback: function () {
            // Update payment count on draw
            var count = this.api().rows().count();
            $("#payment-count").text(count);
        },
        order: [[1, "asc"]],
        language: {
            emptyTable:
                '<div class="alert alert-info mt-3 mb-0"><i class="fa-solid fa-circle-info"></i> ' +
                i18next.t('No payments yet. Click "Add Payment" to get started.') +
                "</div>",
        },
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    dataT = $("#paymentsTable").DataTable(dataTableConfig);

    // Add loading indicator
    dataT.on("xhr", function () {
        // Hide loading after data loads
    });
}

function initDepositSlipEditor() {
    // Handle Generate Report button - block if no payments exist
    $('[name="DepositSlipGeneratePDF"]').on("click", function () {
        var depositId = $(this).data("deposit-id");

        // Fetch payments for this deposit; if none, notify and block
        $.ajax({
            url: window.CRM.root + "/api/deposits/" + depositId + "/payments",
            method: "GET",
            dataType: "json",
        })
            .done(function (data) {
                var count = Array.isArray(data) ? data.length : 0;
                if (count === 0) {
                    window.CRM.notify(i18next.t("No payments on this deposit"), {
                        type: "warning",
                        delay: 5000,
                    });
                    return;
                }

                // There are payments; proceed to open/download the PDF
                window.CRM.VerifyThenLoadAPIContent(window.CRM.root + "/api/deposits/" + depositId + "/pdf");
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                // Fallback: show generic error and do not proceed
                var errorMsg = i18next.t("There was a problem retrieving the requested object");
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    errorMsg = jqXHR.responseJSON.message;
                }
                window.CRM.notify(errorMsg, { type: "danger", delay: 7000 });
            });
    });

    // Handle Clear Fund Filter button
    $("#clearFundFilter").on("click", function () {
        // Clear DataTable search
        dataT.search("").draw();

        // Hide the clear button
        $(this).hide();

        // Reset chart colors if available
        if (window.fundChartInstance) {
            window.fundChartInstance.data.datasets[0].backgroundColor = window.originalFundColors;
            window.fundChartInstance.update();
        }
    });

    function format(d) {
        // `d` is the original data object for the row
        return (
            '<table cellpadding="5" cellspacing="0" style="padding-left:50px;">' +
            "<tr>" +
            "<td>Date:</td>" +
            "<td>" +
            moment(d.Date).format("MM-DD-YYYY") +
            "</td>" +
            "</tr>" +
            "<tr>" +
            "<td>Fiscal Year:</td>" +
            "<td>" +
            d.FyId +
            "</td>" +
            "</tr>" +
            "<tr>" +
            "<td>Fund(s):</td>" +
            "<td>" +
            d.DonationFundName +
            "</td>" +
            "</tr>" +
            "<tr>" +
            "<td>Non Deductible:</td>" +
            "<td>" +
            d.Nondeductible +
            "</td>" +
            "</tr>" +
            "<tr>" +
            "<td>Comment:</td>" +
            "<td>" +
            d.Comment +
            "</td>" +
            "</tr>" +
            "</table>"
        );
    }

    $("#DepositSlipEditor").submit(function (e) {
        e.preventDefault();

        // Show loading indicator
        var submitBtn = $(this).find('button[type="submit"]');
        var originalText = submitBtn.html();
        submitBtn.prop("disabled", true).html('<i class="fa-solid fa-spinner fa-spin"></i> ' + i18next.t("Saving..."));

        var formData = {
            depositDate: $("#DepositDate").val(),
            depositComment: $("#Comment").val(),
            depositClosed: $("#Closed").is(":checked"),
            depositType: depositType,
        };

        // Validate date
        if (!formData.depositDate) {
            showGlobalMessage(i18next.t("Please select a date"), "warning");
            submitBtn.prop("disabled", false).html(originalText);
            return;
        }

        //process the form
        $.ajax({
            type: "POST",
            url: window.CRM.root + "/api/deposits/" + depositSlipID,
            data: JSON.stringify(formData),
            dataType: "json",
            contentType: "application/json; charset=utf-8",
            encode: true,
            timeout: 10000,
        })
            .done(function (data) {
                showGlobalMessage(i18next.t("Deposit saved successfully"), "success");
                setTimeout(function () {
                    location.reload();
                }, 1500);
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                var errorMsg = i18next.t("Error saving deposit");
                if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
                    errorMsg = jqXHR.responseJSON.error;
                }
                showGlobalMessage(errorMsg, "danger");
                submitBtn.prop("disabled", false).html(originalText);
            });
    });

    $("#paymentsTable tbody").on("click", "td.details-control", function () {
        var tr = $(this).closest("tr");
        var row = dataT.row(tr);
        if (row.child.isShown()) {
            // This row is already open - close it
            row.child.hide();
            tr.removeClass("shown");
            $(this).html('<i class="fa-solid fa-plus-circle"></i>');
        } else {
            // Open this row
            row.child(format(row.data())).show();
            tr.addClass("shown");
            $(this).html('<i class="fa-solid fa-minus-circle"></i>');
        }
    });

    $(document).on("click", ".paymentRow", function (event) {
        // Prevent selecting when clicking on buttons or links
        if (
            $(event.target).closest(".btn").length ||
            $(event.target).closest("a").length ||
            $(event.target).closest('input[type="checkbox"]').length ||
            $(event.target).hasClass("details-control") ||
            $(event.target).hasClass("fa")
        ) {
            return;
        }

        $(this).toggleClass("selected");
        var selectedRows = dataT.rows(".selected").data().length;
        var deleteBtn = $("#deleteSelectedRows");
        deleteBtn.prop("disabled", !selectedRows);

        if (selectedRows > 0) {
            deleteBtn
                .html('<i class="fa-solid fa-trash-can"></i> ' + i18next.t("Delete") + " (" + selectedRows + ")")
                .removeClass("btn-outline-danger")
                .addClass("btn-danger");
        } else {
            deleteBtn
                .html('<i class="fa-solid fa-trash-can"></i> ' + i18next.t("Delete"))
                .removeClass("btn-danger")
                .addClass("btn-outline-danger");
        }
    });

    // Delete selected rows
    $("#deleteSelectedRows").on("click", function () {
        var selectedRows = dataT.rows(".selected").data();
        if (selectedRows.length === 0) {
            showGlobalMessage(i18next.t("Please select rows to delete"), "warning");
            return;
        }

        bootbox.confirm({
            title: i18next.t("Confirm Delete"),
            message:
                "<p>" +
                i18next.t("Are you sure you want to delete the selected") +
                " " +
                selectedRows.length +
                " " +
                i18next.t("payment(s)?") +
                "</p>" +
                '<p><small class="text-muted">' +
                i18next.t("This action cannot be undone.") +
                "</small></p>",
            buttons: {
                cancel: {
                    label: i18next.t("Cancel"),
                    className: "btn-secondary",
                },
                confirm: {
                    label: '<i class="fa-solid fa-trash-can"></i> ' + i18next.t("Delete"),
                    className: "btn-danger",
                },
            },
            callback: function (result) {
                if (result) {
                    // Delete each selected payment
                    var deletePromises = [];
                    selectedRows.each(function (index) {
                        deletePromises.push(
                            $.ajax({
                                type: "DELETE",
                                url: window.CRM.root + "/api/payments/" + this.GroupKey,
                                dataType: "json",
                            }),
                        );
                    });

                    $.when
                        .apply($, deletePromises)
                        .done(function () {
                            showGlobalMessage(i18next.t("Payments deleted successfully"), "success");
                            dataT.ajax.reload();
                        })
                        .fail(function () {
                            showGlobalMessage(i18next.t("Error deleting payments"), "danger");
                        });
                }
            },
        });
    });
}

function initCharts(
    pledgeLabels,
    pledgeChartData,
    pledgeBackgroundColor,
    fundLabels,
    fundChartData,
    fundBackgroundColor,
) {
    // Funds Chart: Dynamic height based on number of funds
    // Minimum 120px for 1 fund, +40px for each additional fund
    var fundHeight = Math.max(120, fundLabels.length * 40);

    // Set canvas height
    document.getElementById("fund-bar").style.height = fundHeight + "px";

    var barOptions = {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: "y",
        plugins: {
            legend: {
                display: false,
            },
            tooltip: {
                callbacks: {
                    label: function (context) {
                        return "$" + context.parsed.x.toFixed(2);
                    },
                },
            },
        },
        scales: {
            x: {
                beginAtZero: true,
                ticks: {
                    callback: function (value) {
                        return "$" + value.toLocaleString();
                    },
                },
            },
            y: {
                ticks: {
                    font: {
                        size: 11,
                    },
                },
            },
        },
        onClick: function (event, activeElements) {
            if (activeElements.length > 0) {
                var index = activeElements[0].index;
                var fundName = fundLabels[index];

                // Filter the DataTable by the clicked fund
                dataT.search(fundName).draw();

                // Show clear filter button
                $("#clearFundFilter").fadeIn();

                // Scroll to table
                document.getElementById("paymentsTable").scrollIntoView({
                    behavior: "smooth",
                    block: "start",
                });

                // Highlight the chart bar
                highlightChartBar(fundChart, index);
            }
        },
        onHover: function (event, activeElements) {
            event.native.target.style.cursor = activeElements.length > 0 ? "pointer" : "default";
        },
    };

    // Funds Bar Chart
    var ctx = document.getElementById("fund-bar").getContext("2d");
    var fundChart = new Chart(ctx, {
        type: "bar",
        data: {
            labels: fundLabels,
            datasets: [
                {
                    label: i18next.t("Amount"),
                    data: fundChartData,
                    backgroundColor: fundBackgroundColor,
                    borderRadius: 4,
                    hoverBackgroundColor: fundBackgroundColor.map(function (color) {
                        // Darken the color on hover
                        return color.replace(")", ", 0.8)").replace("rgb", "rgba");
                    }),
                },
            ],
        },
        options: barOptions,
    });

    // Store chart reference and original colors globally
    window.fundChartInstance = fundChart;
    window.originalFundColors = fundBackgroundColor.slice(); // Clone array
}

// Helper function to highlight selected chart bar
function highlightChartBar(chart, index) {
    var originalColors = chart.data.datasets[0].backgroundColor;
    var newColors = originalColors.map(function (color, i) {
        return i === index ? color : color.replace(")", ", 0.3)").replace("rgb", "rgba");
    });
    chart.data.datasets[0].backgroundColor = newColors;
    chart.update();

    // Reset colors after 3 seconds
    setTimeout(function () {
        chart.data.datasets[0].backgroundColor = originalColors;
        chart.update();
    }, 3000);
}
