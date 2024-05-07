function initPaymentTable() {
    var colDef = [
        {
            width: "auto",
            title: i18next.t("Family"),
            data: "FamilyString",
            render: function (data, type, full, meta) {
                var familyName = data ? data : i18next.t("Anonymous");
                return (
                    "<a href='PledgeEditor.php?linkBack=DepositSlipEditor.php?DepositSlipID=" +
                    depositSlipID +
                    "&GroupKey=" +
                    full.GroupKey +
                    "'><i class=\"fa " +
                    (isDepositClosed ? "fa-search-plus" : "fa-pencil") +
                    '"></i></a>' +
                    familyName
                );
            },
        },
        {
            width: "auto",
            title: i18next.t("Check Number"),
            data: "CheckNo",
        },
        {
            width: "auto",
            title: i18next.t("Amount"),
            data: "sumAmount",
        },
        {
            width: "auto",
            title: i18next.t("Method"),
            data: "Method",
        },
    ];

    if (depositType === "CreditCard") {
        colDef.push({
            width: "auto",
            title: i18next.t("Details"),
            data: "Id",
            render: function (data, type, full, meta) {
                return (
                    "<a href='PledgeDetails.php?PledgeID=" +
                    data +
                    "'>Details</a>"
                );
            },
        });
    }

    var dataTableConfig = {
        ajax: {
            url:
                window.CRM.root + "/api/deposits/" + depositSlipID + "/pledges",
            dataSrc: "",
        },
        columns: colDef,
        createdRow: function (row, data, index) {
            $(row).addClass("paymentRow");
        },
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    dataT = $("#paymentsTable").DataTable(dataTableConfig);
    dataT.on("xhr", function () {
        // var json = dataT.ajax.json();
        // console.log( json );
    });
}

function initDepositSlipEditor() {
    function format(d) {
        // `d` is the original data object for the row
        return (
            '<table cellpadding="5" cellspacing="0" border="0" style="padding-left:50px;">' +
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
        var formData = {
            depositDate: $("#DepositDate").val(),
            depositComment: $("#Comment").val(),
            depositClosed: $("#Closed").is(":checked"),
            depositType: depositType,
        };

        //process the form
        $.ajax({
            type: "POST", // define the type of HTTP verb we want to use (POST for our form)
            url: window.CRM.root + "/api/deposits/" + depositSlipID, // the url where we want to POST
            data: JSON.stringify(formData), // our data object
            dataType: "json", // what type of data do we expect back from the server
            contentType: "application/json; charset=utf-8",
            encode: true,
        })
            .done(function (data) {
                location.reload();
            })
            .fail(function () {});
    });

    $("#paymentsTable tbody").on("click", "td.details-control", function () {
        var tr = $(this).closest("tr");
        var row = dataT.row(tr);
        if (row.child.isShown()) {
            // This row is already open - close it
            row.child.hide();
            tr.removeClass("shown");
            $(this).html('<i class="fa fa-plus-circle"></i>');
        } else {
            // Open this row
            row.child(format(row.data())).show();
            tr.addClass("shown");
            $(this).html('<i class="fa fa-minus-circle"></i>');
        }
    });

    $(document).on("click", ".paymentRow", function (event) {
        if (
            !(
                $(event.target).hasClass("details-control") ||
                $(event.target).hasClass("fa")
            )
        ) {
            $(this).toggleClass("selected");
            var selectedRows = dataT.rows(".selected").data().length;
            $("#deleteSelectedRows").prop("disabled", !selectedRows);
            $("#deleteSelectedRows").text(
                "Delete (" + selectedRows + ") Selected Rows",
            );
        }
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
    var pieOptions = {
        //Boolean - Whether we should show a stroke on each segment
        segmentShowStroke: true,
        //String - The colour of each segment stroke
        segmentStrokeColor: "#fff",
        //Number - The width of each segment stroke
        segmentStrokeWidth: 2,
        //Number - The percentage of the chart that we cut out of the middle
        percentageInnerCutout: 50, // This is 0 for Pie charts
        //Boolean - Whether we animate the rotation of the Doughnut
        animateRotate: false,
        //Boolean - whether to make the chart responsive to window resizing
        responsive: true,
        // Boolean - whether to maintain the starting aspect ratio or not when responsive, if set to false, will take up entire container
        maintainAspectRatio: true,
    };

    var ctx = document.getElementById("type-donut").getContext("2d");
    var PieChart = new Chart(ctx, {
        type: "doughnut",
        data: {
            labels: pledgeLabels,
            datasets: [
                {
                    data: pledgeData,
                    backgroundColor: pledgeBackgroundColor,
                },
            ],
        },
        options: pieOptions,
    });

    var ctx = document.getElementById("fund-donut").getContext("2d");
    var PieChart = new Chart(ctx, {
        type: "doughnut",
        data: {
            labels: fundLabels,
            datasets: [
                {
                    data: fundData,
                    backgroundColor: fundBackgroundColor,
                },
            ],
        },
        options: pieOptions,
    });
}
