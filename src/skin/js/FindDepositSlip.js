var dataT = 0;

document.addEventListener("DOMContentLoaded", function () {
    function updateSelectedCount() {
        var selectedRows = dataT.rows(".selected").data().length;
        $("#deleteSelectedRows").prop("disabled", !selectedRows);
        $("#deleteSelectedRows").text(
            "Delete (" + selectedRows + ") Selected Rows",
        );
        $("#exportSelectedRows").prop("disabled", !selectedRows);
        $("#exportSelectedRows").html(
            '<i class="fa fa-download"></i> Export (' +
                selectedRows +
                ") Selected Rows (OFX)",
        );
        $("#exportSelectedRowsCSV").prop("disabled", !selectedRows);
        $("#exportSelectedRowsCSV").html(
            '<i class="fa fa-download"></i> Export (' +
                selectedRows +
                ") Selected Rows (CSV)",
        );
        $("#generateDepositSlip").prop("disabled", !selectedRows);
        $("#generateDepositSlip").html(
            '<i class="fa fa-download"></i> Generate Deposit Split for Selected (' +
                selectedRows +
                ") Rows (PDF)",
        );
    }

    $("#deleteSelectedRows").click(function () {
        var deletedRows = dataT.rows(".selected").data();
        bootbox.confirm({
            title: i18next.t("Confirm Delete"),
            message:
                "<p>" +
                i18next.t("Are you sure you want to delete the selected") +
                " " +
                deletedRows.length +
                " " +
                i18next.t("Deposit(s)") +
                "?</p>" +
                "<p>" +
                i18next.t(
                    "This will also delete all payments associated with this deposit",
                ) +
                "</p>" +
                "<p>" +
                i18next.t(
                    "This action CANNOT be undone, and may have legal implications!",
                ) +
                "</p>" +
                "<p>" +
                i18next.t("Please ensure this what you want to do.") +
                "</p>",
            buttons: {
                cancel: {
                    label: i18next.t("Close"),
                },
                confirm: {
                    label: i18next.t("Delete"),
                },
            },
            callback: function (result) {
                if (result) {
                    $.each(deletedRows, function (index, value) {
                        window.CRM.APIRequest({
                            method: "DELETE",
                            path: "deposits/" + value.Id,
                        }).done(function (data) {
                            dataT.rows(".selected").remove().draw(false);
                            updateSelectedCount();
                        });
                    });
                }
            },
        });
    });

    $("#depositDate")
        .datepicker({ format: "yyyy-mm-dd", language: window.CRM.lang })
        .datepicker("setDate", new Date());
    $("#addNewDeposit").click(function (e) {
        var newDeposit = {
            depositType: $("#depositType option:selected").val(),
            depositComment: $("#depositComment").val(),
            depositDate: $("#depositDate").val(),
        };

        if (!$("#depositComment").val().trim()) {
            bootbox.confirm({
                title: i18next.t("Add New Deposit"),
                message: i18next.t(
                    "You are about to add a new deposit without a comment",
                ),
                buttons: {
                    cancel: {
                        label: i18next.t("Cancel"),
                    },
                    confirm: {
                        label: i18next.t("Confirm"),
                    },
                },
                callback: function (result) {
                    if (result == true) {
                        addNewDepositRequest(newDeposit);
                    }
                },
            });
        } else {
            addNewDepositRequest(newDeposit);
        }
    });

    function addNewDepositRequest(newDeposit) {
        $.ajax({
            method: "POST",
            url: window.CRM.root + "/api/deposits",
            data: JSON.stringify(newDeposit),
            contentType: "application/json; charset=utf-8",
            dataType: "json",
        }).done(function (data) {
            data.totalAmount = "";
            dataT.row.add(data);
            dataT.rows().invalidate().draw(true);
        });
    }

    var dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/deposits",
            dataSrc: "Deposits",
        },
        deferRender: true,
        columns: [
            {
                title: i18next.t("Deposit ID"),
                data: "Id",
                render: function (data, type, full, meta) {
                    if (type === "display") {
                        return (
                            "<a href='DepositSlipEditor.php?DepositSlipID=" +
                            full.Id +
                            '\'><i class="fa fa-search-plus"></i></a>' +
                            full.Id
                        );
                    } else {
                        return parseInt(full.Id);
                    }
                },
                type: "num",
            },
            {
                title: i18next.t("Deposit Date"),
                data: "Date",
                render: function (data, type, full, meta) {
                    if (type === "display") {
                        return moment(data).format("MM-DD-YY");
                    } else {
                        return data;
                    }
                },
                searchable: true,
            },
            {
                title: i18next.t("Deposit Total"),
                data: "totalAmount",
                searchable: false,
            },
            {
                title: i18next.t("Deposit Comment"),
                data: "Comment",
                searchable: true,
            },
            {
                title: i18next.t("Closed"),
                data: "Closed",
                searchable: true,
                render: function (data, type, full, meta) {
                    return data == 1 ? "Yes" : "No";
                },
            },
            {
                title: i18next.t("Deposit Type"),
                data: "Type",
                searchable: true,
            },
        ],
        order: [0, "desc"],
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    dataT = $("#depositsTable").DataTable(dataTableConfig);

    $("#depositsTable tbody").on("click", "tr", function () {
        $(this).toggleClass("selected");
        updateSelectedCount();
    });

    $(".exportButton").click(function (sender) {
        var selectedRows = dataT.rows(".selected").data();
        var type = this.getAttribute("data-exportType");
        $.each(selectedRows, function (index, value) {
            window.CRM.VerifyThenLoadAPIContent(
                window.CRM.root + "/api/deposits/" + value.Id + "/" + type,
            );
        });
    });
});
