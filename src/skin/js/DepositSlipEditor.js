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
      render: (data, type, full, meta) => {
        if (!data || !data.trim()) {
          return '<em class="text-body-secondary">' + i18next.t("Anonymous") + "</em>";
        }
        // Extract just the family name (before the colon) - FamilyString includes address
        // Guard against 0-HoH families which produce no colon in the string
        var colonIdx = data.indexOf(":");
        var familyName = colonIdx !== -1 ? data.substring(0, colonIdx).trim() : data.trim();
        return familyName;
      },
    },
    {
      width: "10%",
      title: i18next.t("Check Number"),
      data: "CheckNo",
      render: (data, type, full, meta) =>
        data ? "<code>" + data + "</code>" : '<em class="text-body-secondary">-</em>',
    },
    {
      width: "25%",
      title: i18next.t("Fund"),
      data: "FundName",
      render: (data, type, full, meta) => {
        if (!data) {
          return '<em class="text-body-secondary">-</em>';
        }

        // For sorting and filtering, return plain text
        if (type === "sort" || type === "filter") {
          return data;
        }

        // For display, split multiple funds and show as individual badges using Tabler style
        var funds = data.split(", ");
        var badges = funds.map((fund) => '<span class="badge bg-info-lt text-info">' + fund.trim() + "</span>");
        return '<div class="d-flex flex-wrap gap-1">' + badges.join("") + "</div>";
      },
    },
    {
      width: "12%",
      title: i18next.t("Amount"),
      data: "sumAmount",
      render: (data, type, full, meta) => {
        if (type === "display") {
          return '<strong class="text-end d-block">' + window.CRM.currency.format(data) + "</strong>";
        }
        return parseFloat(data || 0);
      },
    },
    {
      width: "12%",
      title: i18next.t("Method"),
      data: "Method",
      render: (data, type, full, meta) => {
        var badgeClass = "bg-secondary-lt text-secondary";
        var icon = "";
        if (data === "CHECK") {
          badgeClass = "bg-primary-lt text-primary";
          icon = '<i class="ti ti-check me-1"></i>';
        } else if (data === "CASH") {
          badgeClass = "bg-success-lt text-success";
          icon = '<i class="ti ti-coins me-1"></i>';
        } else if (data === "CREDITCARD") {
          badgeClass = "bg-warning-lt text-warning";
          icon = '<i class="ti ti-credit-card me-1"></i>';
        }
        return '<span class="badge ' + badgeClass + '">' + icon + data + "</span>";
      },
    },
    {
      width: "6%",
      title: i18next.t("Actions"),
      orderable: false,
      data: null,
      render: (data, type, full, meta) => {
        var linkBack = encodeURIComponent("/DepositSlipEditor.php?DepositSlipID=" + depositSlipID);
        var editUrl =
          window.CRM.root + "/finance/pledge/" + encodeURIComponent(full.GroupKey) + "/edit?linkBack=" + linkBack;
        var detailsUrl = "PledgeDetails.php?PledgeID=" + full.Id;
        var familyUrl = window.CRM.root + "/people/family/" + full.FamId;

        var html =
          '<div class="dropdown">' +
          '<button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">' +
          '<i class="ti ti-dots-vertical"></i>' +
          "</button>" +
          '<div class="dropdown-menu dropdown-menu-end">' +
          '<a class="dropdown-item" href="' +
          editUrl +
          '">' +
          '<i class="ti ti-' +
          (isDepositClosed ? "eye" : "pencil") +
          ' me-2"></i>' +
          (isDepositClosed ? i18next.t("View") : i18next.t("Edit")) +
          "</a>";

        if (full.FamId) {
          html +=
            '<a class="dropdown-item" href="' +
            familyUrl +
            '">' +
            '<i class="ti ti-users me-2"></i>' +
            i18next.t("View Family") +
            "</a>";
        }

        if (depositType === "CreditCard") {
          html +=
            '<div class="dropdown-divider"></div>' +
            '<a class="dropdown-item" href="' +
            detailsUrl +
            '">' +
            '<i class="ti ti-info-circle me-2"></i>' +
            i18next.t("Details") +
            "</a>";
        }

        html += "</div></div>";
        return html;
      },
    },
  ];

  var dataTableConfig = {
    ajax: {
      url: window.CRM.root + "/api/deposits/" + depositSlipID + "/payments",
      dataSrc: "",
      error: (xhr, error, thrown) => {
        console.error("DataTable error:", xhr, error, thrown);
        showGlobalMessage(i18next.t("Error loading payments"), "danger");
      },
    },
    columns: colDef,
    createdRow: (row, data, index) => {
      $(row).addClass("paymentRow");
      // Only allow selection on open deposits
      if (!isDepositClosed) {
        $(row).css("cursor", "pointer");
      }
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
  dataT.on("xhr", () => {
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
      .done((data) => {
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
      .fail((jqXHR, textStatus, errorThrown) => {
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

    // Reset chart highlight by clearing the dimmed colors
    if (window.fundChartInstance) {
      window.fundChartInstance.updateOptions({ colors: undefined });
    }
  });

  function format(d) {
    // `d` is the original data object for the row
    return (
      '<table cellpadding="5" cellspacing="0" style="padding-left:50px;">' +
      "<tr>" +
      "<td>" +
      i18next.t("Date") +
      ":</td>" +
      "<td>" +
      moment(d.Date).format("MM-DD-YYYY") +
      "</td>" +
      "</tr>" +
      "<tr>" +
      "<td>" +
      i18next.t("Fiscal Year") +
      ":</td>" +
      "<td>" +
      d.FyId +
      "</td>" +
      "</tr>" +
      "<tr>" +
      "<td>" +
      i18next.t("Fund(s)") +
      ":</td>" +
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
    submitBtn.prop("disabled", true).html('<i class="fa-solid fa-spinner fa-spin"></i>' + i18next.t("Saving..."));

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
      .done((data) => {
        showGlobalMessage(i18next.t("Deposit saved successfully"), "success");
        setTimeout(() => {
          location.reload();
        }, 1500);
      })
      .fail((jqXHR, textStatus, errorThrown) => {
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
      $(this).html('<i class="fa-solid fa-circle-plus"></i>');
    } else {
      // Open this row
      row.child(format(row.data())).show();
      tr.addClass("shown");
      $(this).html('<i class="fa-solid fa-circle-minus"></i>');
    }
  });

  $(document).on("click", ".paymentRow", function (event) {
    // Don't allow selection on closed deposits
    if (isDepositClosed) {
      return;
    }

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
        .html('<i class="fa-solid fa-trash-can"></i>' + i18next.t("Delete") + " (" + selectedRows + ")")
        .removeClass("btn-outline-danger")
        .addClass("btn-danger");
    } else {
      deleteBtn
        .html('<i class="fa-solid fa-trash-can"></i>' + i18next.t("Delete"))
        .removeClass("btn-danger")
        .addClass("btn-outline-danger");
    }
  });

  // Delete selected rows
  $("#deleteSelectedRows").on("click", () => {
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
          label: '<i class="fa-solid fa-trash-can"></i>' + i18next.t("Delete"),
          className: "btn-danger",
        },
      },
      callback: (result) => {
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
            .done(() => {
              showGlobalMessage(i18next.t("Payments deleted successfully"), "success");
              dataT.ajax.reload();
            })
            .fail(() => {
              showGlobalMessage(i18next.t("Error deleting payments"), "danger");
            });
        }
      },
    });
  });
}

function initCharts(pledgeLabels, pledgeChartData, fundLabels, fundChartData) {
  // Funds Chart: Dynamic height based on number of funds
  // Minimum 120px for 1 fund, +40px for each additional fund
  var fundHeight = Math.max(250, fundLabels.length * 40);

  // Funds Bar Chart using ApexCharts
  var fundChartOptions = {
    chart: {
      type: "bar",
      height: fundHeight,
      toolbar: {
        show: false,
      },
      events: {
        click: (event, chartContext, opts) => {
          if (opts.dataPointIndex !== undefined) {
            var index = opts.dataPointIndex;
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
            highlightChartBar(fundChartInstance, index);
          }
        },
      },
    },
    plotOptions: {
      bar: {
        horizontal: true,
        barHeight: "70%",
        borderRadius: 4,
        distributed: true,
      },
    },
    series: [
      {
        name: i18next.t("Amount"),
        data: fundChartData,
      },
    ],
    // Use ApexCharts default color palette (distributed: true assigns one per bar)
    xaxis: {
      categories: fundLabels,
      labels: {
        formatter: (value) => window.CRM.currency.format(value),
      },
    },
    yaxis: {
      tickFormatter: (value) => value,
    },
    tooltip: {
      y: {
        formatter: (value) => window.CRM.currency.format(value),
      },
    },
    states: {
      hover: {
        filter: {
          type: "none",
        },
      },
    },
  };

  var fundChartElement = document.getElementById("fund-bar");
  if (fundChartElement) {
    window.fundChartInstance = new window.ApexCharts(fundChartElement, fundChartOptions);
    window.fundChartInstance.render();
  }
}

// Helper function to highlight selected chart bar
function highlightChartBar(chart, index) {
  if (!chart) return;

  var originalColors = chart.w.globals.colors.slice();

  var newColors = originalColors.map((color, i) => {
    if (i === index) {
      return color;
    }
    // Convert hex to rgba with 0.3 opacity for dimming
    var hex = color.replace("#", "");
    var r = parseInt(hex.substring(0, 2), 16);
    var g = parseInt(hex.substring(2, 4), 16);
    var b = parseInt(hex.substring(4, 6), 16);
    return "rgba(" + r + "," + g + "," + b + ", 0.3)";
  });

  // Update the chart with new colors
  chart.updateOptions({
    colors: newColors,
  });

  // Reset colors after 3 seconds
  setTimeout(() => {
    chart.updateOptions({
      colors: originalColors,
    });
  }, 3000);
}
