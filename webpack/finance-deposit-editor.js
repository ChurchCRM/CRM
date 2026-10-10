/**
 * Deposit editor (/finance/deposit/{id}).
 * Config comes from window.depositEditorConfig (see finance/views/deposits/editor.php).
 * Webpack entry (skin/v2/finance-deposit-editor.min.js). i18next, DataTables, bootbox
 * and ApexCharts come from the global skin bundle.
 */
(() => {
  const config = window.depositEditorConfig;
  if (!config) {
    return;
  }

  const METHOD_BADGES = {
    CHECK: { cls: "bg-blue-lt text-blue", icon: "fa-money-check" },
    CASH: { cls: "bg-green-lt text-green", icon: "fa-money-bill" },
    CREDITCARD: { cls: "bg-orange-lt text-orange", icon: "fa-credit-card" },
    BANKDRAFT: { cls: "bg-purple-lt text-purple", icon: "fa-building-columns" },
  };

  let paymentsTable;
  // Selection is kept by GroupKey so it survives paging, sorting and filtering.
  const selectedKeys = new Set();
  let fundChart;

  function paymentUrl(groupKey, suffix) {
    return `${window.CRM.root}/finance/pledge/${encodeURIComponent(groupKey)}${suffix}`;
  }

  function buildColumns() {
    const columns = [];

    if (config.canDelete) {
      columns.push({
        data: null,
        title:
          '<input type="checkbox" class="form-check-input" id="selectAllPayments" aria-label="' +
          window.CRM.escapeAttribute(i18next.t("Select all")) +
          '">',
        orderable: false,
        searchable: false,
        className: "w-1",
        render: (_data, _type, row) =>
          `<input type="checkbox" class="form-check-input row-select"${selectedKeys.has(String(row.GroupKey)) ? " checked" : ""}>`,
      });
    }

    columns.push(
      {
        title: i18next.t("Family"),
        data: "FamilyString",
        render: (data) =>
          data?.trim()
            ? window.CRM.escapeHtml(data)
            : `<em class="text-body-secondary">${window.CRM.escapeHtml(i18next.t("Anonymous"))}</em>`,
      },
      {
        title: i18next.t("Check Number"),
        data: "CheckNo",
        render: (data) =>
          data ? `<code>${window.CRM.escapeHtml(String(data))}</code>` : '<span class="text-body-secondary">-</span>',
      },
      {
        title: i18next.t("Fund"),
        data: "FundName",
        render: (data, type) => {
          if (!data) {
            return '<span class="text-body-secondary">-</span>';
          }
          if (type === "sort" || type === "filter") {
            return data;
          }
          return `<div class="d-flex flex-wrap gap-1">${data
            .split(", ")
            .map((fund) => `<span class="badge bg-info-lt text-info">${window.CRM.escapeHtml(fund.trim())}</span>`)
            .join("")}</div>`;
        },
      },
      {
        title: i18next.t("Amount"),
        data: "sumAmount",
        className: "text-end",
        render: (data, type) =>
          type === "display" ? `<strong>${window.CRM.currency.format(data)}</strong>` : parseFloat(data || 0),
      },
      {
        title: i18next.t("Method"),
        data: "Method",
        render: (data) => {
          const badge = METHOD_BADGES[data] || { cls: "bg-secondary-lt text-secondary", icon: "fa-circle-question" };
          return `<span class="badge ${badge.cls}"><i class="fa-solid ${badge.icon} me-1"></i>${window.CRM.escapeHtml(String(data ?? ""))}</span>`;
        },
      },
      {
        title: i18next.t("Actions"),
        data: "GroupKey",
        orderable: false,
        searchable: false,
        className: "w-1 no-export",
        render: (groupKey) => {
          const key = window.CRM.escapeAttribute(String(groupKey));
          const linkBack = encodeURIComponent(`/finance/deposit/${config.depositId}`);
          const open = config.isClosed
            ? `<a class="dropdown-item" href="${paymentUrl(groupKey, "")}"><i class="fa-solid fa-eye me-2"></i>${i18next.t("View")}</a>`
            : `<a class="dropdown-item" href="${paymentUrl(groupKey, "")}/edit?linkBack=${linkBack}"><i class="fa-solid fa-pencil me-2"></i>${i18next.t("Edit")}</a>`;
          const remove = config.canDelete
            ? `<div class="dropdown-divider"></div><button type="button" class="dropdown-item text-danger delete-payment" data-group-key="${key}"><i class="fa-solid fa-trash me-2"></i>${i18next.t("Delete")}</button>`
            : "";
          return `<div class="dropdown"><button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false"><i class="fa-solid fa-ellipsis-vertical"></i></button><div class="dropdown-menu dropdown-menu-end">${open}${remove}</div></div>`;
        },
      },
    );

    return columns;
  }

  function initPaymentsTable() {
    const settings = {
      ajax: {
        url: `${window.CRM.root}/api/deposits/${config.depositId}/payments`,
        dataSrc: "",
        error: () => window.CRM.notify(i18next.t("Error loading payments"), { type: "danger", delay: 6000 }),
      },
      columns: buildColumns(),
      order: [[config.canDelete ? 2 : 1, "asc"]],
      drawCallback: function () {
        $("#payment-count").text(this.api().rows().count());
        updateSelectionState(this.api());
      },
    };
    $.extend(settings, window.CRM.plugin.dataTable);
    settings.language = {
      ...settings.language,
      emptyTable: i18next.t('No payments yet. Click "Add Payment" to get started.'),
    };
    paymentsTable = $("#paymentsTable").DataTable(settings);
  }

  function visibleGroupKeys(table = paymentsTable) {
    return table
      .rows({ search: "applied" })
      .data()
      .toArray()
      .map((row) => String(row.GroupKey));
  }

  // drawCallback fires during init, before paymentsTable is assigned, so it passes its own API.
  function updateSelectionState(table = paymentsTable) {
    const selected = selectedKeys.size;
    const visible = visibleGroupKeys(table);
    const visibleSelected = visible.filter((key) => selectedKeys.has(key)).length;
    $("#deleteSelectedRows")
      .prop("disabled", selected === 0)
      .html(
        `<i class="fa-solid fa-trash-can me-1"></i>${i18next.t("Delete Selected")}${selected ? ` (${selected})` : ""}`,
      );
    $("#selectAllPayments")
      .prop("checked", visible.length > 0 && visibleSelected === visible.length)
      .prop("indeterminate", visibleSelected > 0 && visibleSelected < visible.length);
  }

  function confirmDelete(groupKeys) {
    bootbox.confirm({
      title: i18next.t("Confirm Delete"),
      message:
        `<p>${i18next.t("Are you sure you want to delete the selected")} ${groupKeys.length} ${i18next.t("payment(s)?")}</p>` +
        `<p class="text-body-secondary mb-0">${i18next.t("This action CANNOT be undone, and may have legal implications!")}</p>`,
      buttons: {
        cancel: { label: i18next.t("Cancel"), className: "btn-secondary" },
        confirm: {
          label: `<i class="fa-solid fa-trash-can me-1"></i>${i18next.t("Delete")}`,
          className: "btn-danger",
        },
      },
      callback: (confirmed) => {
        if (!confirmed) {
          return;
        }
        $("#deleteSelectedRows").prop("disabled", true);
        // Wait for every request to settle so the outcome (and the reload) reflects the server state.
        const results = groupKeys.map((key) =>
          $.ajax({
            type: "DELETE",
            url: `${window.CRM.root}/api/payments/${encodeURIComponent(key)}`,
            dataType: "json",
          }).then(
            () => ({ key, ok: true }),
            () => ({ key, ok: false }),
          ),
        );
        $.when(...results).done((...settled) => {
          const failed = settled.filter((result) => !result.ok);
          if (failed.length === 0) {
            window.CRM.notify(i18next.t("Payments deleted successfully"), { type: "success", delay: 2000 });
            setTimeout(() => window.location.reload(), 600);
            return;
          }
          settled.filter((result) => result.ok).forEach((result) => selectedKeys.delete(result.key));
          const message =
            failed.length === settled.length
              ? i18next.t("Error deleting payments")
              : `${i18next.t("Some payments could not be deleted")} (${failed.length}/${settled.length})`;
          window.CRM.notify(message, { type: "danger", delay: 6000 });
          paymentsTable.ajax.reload(() => updateSelectionState(), false);
        });
      },
    });
  }

  function initActions() {
    $("#paymentsTable").on("change", ".row-select", function () {
      const key = String(paymentsTable.row($(this).closest("tr")).data().GroupKey);
      if (this.checked) {
        selectedKeys.add(key);
      } else {
        selectedKeys.delete(key);
      }
      updateSelectionState();
    });
    $("#paymentsTable").on("change", "#selectAllPayments", function () {
      const checked = this.checked;
      visibleGroupKeys().forEach((key) => (checked ? selectedKeys.add(key) : selectedKeys.delete(key)));
      $("#paymentsTable tbody .row-select").prop("checked", checked);
      updateSelectionState();
    });
    $("#paymentsTable").on("click", ".delete-payment", function () {
      confirmDelete([String($(this).data("group-key"))]);
    });
    $("#deleteSelectedRows").on("click", () => {
      const keys = [...selectedKeys];
      if (keys.length > 0) {
        confirmDelete(keys);
      }
    });

    $("#generateDepositReport").on("click", function () {
      const depositId = $(this).data("deposit-id");
      $.getJSON(`${window.CRM.root}/api/deposits/${depositId}/payments`)
        .done((data) => {
          if (!Array.isArray(data) || data.length === 0) {
            window.CRM.notify(i18next.t("No payments on this deposit"), { type: "warning", delay: 5000 });
            return;
          }
          window.CRM.VerifyThenLoadAPIContent(`${window.CRM.root}/api/deposits/${depositId}/pdf`);
        })
        .fail((jqXHR) => {
          const message =
            jqXHR.responseJSON?.message || i18next.t("There was a problem retrieving the requested object");
          window.CRM.notify(message, { type: "danger", delay: 7000 });
        });
    });

    $("#DepositSlipEditor").on("submit", (event) => {
      event.preventDefault();
      const date = $("#DepositDate").val();
      if (!date) {
        window.CRM.notify(i18next.t("Please select a date"), { type: "warning", delay: 4000 });
        return;
      }

      const saveButton = $("#saveDeposit");
      const originalHtml = saveButton.html();
      saveButton
        .prop("disabled", true)
        .html(`<span class="spinner-border spinner-border-sm me-1"></span>${i18next.t("Saving...")}`);

      $.ajax({
        type: "POST",
        url: `${window.CRM.root}/api/deposits/${config.depositId}`,
        data: JSON.stringify({
          depositDate: date,
          depositComment: $("#Comment").val(),
          depositClosed: $("#Closed").is(":checked"),
          depositType: config.depositType,
        }),
        dataType: "json",
        contentType: "application/json; charset=utf-8",
        timeout: 10000,
      })
        .done(() => {
          window.CRM.notify(i18next.t("Deposit saved successfully"), { type: "success", delay: 2000 });
          setTimeout(() => window.location.reload(), 800);
        })
        .fail((jqXHR) => {
          const message = jqXHR.responseJSON?.message || jqXHR.responseJSON?.error || i18next.t("Error saving deposit");
          window.CRM.notify(message, { type: "danger", delay: 6000 });
          saveButton.prop("disabled", false).html(originalHtml);
        });
    });

    $("#clearFundFilter").on("click", function () {
      paymentsTable.search("").columns().search("").draw();
      $(this).addClass("d-none");
    });
  }

  function initFundChart() {
    const element = document.getElementById("fund-bar");
    if (!element || config.fundLabels.length === 0) {
      return;
    }

    fundChart = new window.ApexCharts(element, {
      chart: {
        type: "bar",
        height: Math.max(200, config.fundLabels.length * 40),
        toolbar: { show: false },
        events: {
          dataPointSelection: (_event, _chart, opts) => {
            // Match whole fund names in the Fund column only, so other columns can't leak in.
            const label = $.fn.dataTable.util.escapeRegex(config.fundLabels[opts.dataPointIndex]);
            paymentsTable
              .column(config.canDelete ? 3 : 2)
              .search(`(^|, )${label}(,|$)`, true, false)
              .draw();
            $("#clearFundFilter").removeClass("d-none");
            document.getElementById("paymentsTable").scrollIntoView({ behavior: "smooth", block: "start" });
          },
        },
      },
      plotOptions: { bar: { horizontal: true, barHeight: "70%", borderRadius: 4, distributed: true } },
      series: [{ name: i18next.t("Amount"), data: config.fundData }],
      legend: { show: false },
      dataLabels: { formatter: (value) => window.CRM.currency.format(value) },
      xaxis: {
        categories: config.fundLabels,
        labels: { formatter: (value) => window.CRM.currency.format(value) },
      },
      tooltip: { y: { formatter: (value) => window.CRM.currency.format(value) } },
    });
    fundChart.render();
  }

  $(document).ready(() => {
    window.CRM.onLocalesReady(() => {
      initPaymentsTable();
      initFundChart();
      initActions();
    });
  });
})();
