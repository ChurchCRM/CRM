const initDepositChart = () => {
  const container = document.getElementById("deposit-lineGraph");
  if (!container) return;
  const $ = window.$;
  let requestId = 0;
  let chart = null;
  const cssVar = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

  const load = (days) => {
    const current = ++requestId;
    if (chart) {
      chart.destroy();
      chart = null;
    }
    $("#depositChartRow .card-body").html('<div id="deposit-lineGraph" class="deposit-line-graph"></div>');

    window.CRM.APIRequest({ method: "GET", path: "deposits/dashboard?days=" + days }).done((data) => {
      if (current !== requestId) return;
      if (!data || data.length === 0) {
        $("#depositChartRow .card-body").html(
          '<div class="empty py-4"><div class="empty-icon"><i class="fa-solid fa-circle-dollar-to-slot fa-2x text-muted"></i></div>' +
            '<p class="empty-title">' + i18next.t("No Deposits") + "</p>" +
            '<p class="empty-subtitle text-muted">' + i18next.t("No deposit data available yet") + "</p></div>",
        );
        return;
      }
      chart = new window.ApexCharts(document.getElementById("deposit-lineGraph"), {
        chart: { type: "line", height: 250, toolbar: { show: true } },
        series: [{ name: i18next.t("Deposit Value"), data: data.map((row) => row.totalAmount) }],
        xaxis: { categories: data.map((row) => window.moment(row.Date).format("MM-DD-YY")) },
        yaxis: { title: { text: i18next.t("Amount") } },
        colors: [cssVar("--tblr-primary")],
        stroke: { curve: "smooth", width: 2 },
        grid: { show: true, borderColor: cssVar("--tblr-border-color") },
      });
      chart.render();
    });
  };

  load($("#deposit-days").val());
  $("#deposit-days").on("change", function () {
    window.CRM.APIRequest({
      method: "POST",
      path: `user/${window.CRM.userId}/setting/ui.dashboard.deposit.days`,
      dataType: "json",
      data: JSON.stringify({ value: String(this.value) }),
      suppressErrorDialog: true,
    });
    load(this.value);
  });
};

document.addEventListener("DOMContentLoaded", () => {
  window.CRM.onLocalesReady(() => {
    const config = window.financeDashboardConfig;
    initDepositChart();
    const element = document.getElementById("payments-by-fund-chart");
    if (element && config.funds.length > 0) {
      const chart = new window.ApexCharts(element, {
        chart: { type: "bar", height: Math.max(220, config.funds.length * 40), toolbar: { show: false } },
        plotOptions: { bar: { horizontal: true, barHeight: "65%", borderRadius: 4 } },
        colors: [getComputedStyle(document.documentElement).getPropertyValue("--tblr-primary").trim()],
        series: [{ name: i18next.t("Amount"), data: config.funds.map((fund) => fund.amount) }],
        xaxis: {
          categories: config.funds.map((fund) => fund.name),
          labels: { formatter: (value) => window.CRM.currency.format(value, 0) },
        },
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: (value) => window.CRM.currency.format(value) } },
        legend: { show: false },
      });
      chart.render();
    }

    if (config.isAdmin) {
      window.CRM.settingsPanel.init({
        container: "#financialSettings",
        title: i18next.t("Financial Settings"),
        icon: "fa-solid fa-sliders",
        settings: [
          {
            name: "bEnabledFinance",
            type: "boolean",
            label: i18next.t("Finance Module"),
            tooltip: i18next.t("Enable or disable the Finance module and sidebar menu."),
          },
          {
            name: "bEnabledFundraiser",
            type: "boolean",
            label: i18next.t("Fundraiser Module"),
            tooltip: i18next.t("Enable or disable the Fundraiser module."),
          },
          {
            name: "iFYMonth",
            type: "choice",
            label: i18next.t("First month of the fiscal year"),
            choices: config.fiscalYearMonths,
          },
          {
            name: "iMaxTaxYears",
            type: "number",
            label: i18next.t("Maximum tax years shown per family"),
            min: 0,
            placeholder: "5",
          },
          {
            name: "sDepositSlipType",
            type: "choice",
            label: i18next.t("Deposit ticket type"),
            tooltip: config.depositSlipTooltip,
            choices: config.depositSlipTypes,
          },
          {
            name: "iChecksPerDepositForm",
            type: "number",
            label: i18next.t("Number of checks for Deposit Slip Report"),
            min: 1,
            max: 100,
          },
          { name: "bDisplayBillCounts", type: "boolean", label: i18next.t("Display bill counts on deposit slip") },
          { name: "bUseScannedChecks", type: "boolean", label: i18next.t("Enable use of scanned checks") },
          {
            name: "bRequireCheckNumber",
            type: "boolean",
            label: i18next.t("Require check number on check payments"),
            tooltip: config.requireCheckNumberTooltip,
          },
          { name: "bEnableNonDeductible", type: "boolean", label: i18next.t("Enable non-deductible payments") },
          { name: "bUseDonationEnvelopes", type: "boolean", label: i18next.t("Enable use of donation envelopes") },
        ],
        autoSave: true,
        showAllSettingsLink: false,
        reloadOnSave: true,
      });
    }
  });
});
