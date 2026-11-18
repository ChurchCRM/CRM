document.addEventListener("DOMContentLoaded", () => {
  window.CRM.onLocalesReady(() => {
    const config = window.financeDashboardConfig;
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

    initDepositTrackingChart();

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

function initDepositTrackingChart() {
  const $ = window.jQuery || window.$;
  if (!$("#deposit-lineGraph").length) {
    return;
  }

  const allowedDepositDays = [30, 90, 365];
  const defaultDepositDays = 90;
  let depositChartRequestId = 0;

  const resolveDepositDays = (rawDays) => {
    const days = parseInt(rawDays, 10);
    return allowedDepositDays.includes(days) ? days : defaultDepositDays;
  };

  const loadDepositChart = (rawDays) => {
    const days = resolveDepositDays(rawDays);
    const requestId = ++depositChartRequestId;

    if (window.depositChart) {
      window.depositChart.destroy();
      window.depositChart = null;
    }

    $("#depositChartRow .card-body").html('<div id="deposit-lineGraph" class="deposit-line-graph"></div>');

    window.CRM.APIRequest({
      method: "GET",
      path: "deposits/dashboard?days=" + days,
    }).done((data) => {
      if (requestId !== depositChartRequestId) {
        return;
      }

      const lineDataRaw = data;

      if (!lineDataRaw || lineDataRaw.length === 0) {
        $("#depositChartRow .card-body").html(
          '<div class="empty py-4">' +
            '<div class="empty-icon"><i class="fa-solid fa-circle-dollar-to-slot fa-2x text-muted"></i></div>' +
            '<p class="empty-title">' +
            i18next.t("No Deposits") +
            "</p>" +
            '<p class="empty-subtitle text-muted">' +
            i18next.t("No deposit data available yet") +
            "</p>" +
            "</div>",
        );
        return;
      }

      const labels = [];
      const values = [];
      $.each(lineDataRaw, (i, val) => {
        labels.push(moment(val.Date).format("MM-DD-YY"));
        values.push(val.totalAmount);
      });

      const depositChartOptions = {
        chart: {
          type: "line",
          height: 250,
          sparkline: {
            enabled: false,
          },
          toolbar: {
            show: true,
            tools: {
              download: true,
              selection: true,
              zoom: true,
              zoomin: true,
              zoomout: true,
              reset: true,
            },
          },
        },
        series: [
          {
            name: i18next.t("Deposit Value"),
            data: values,
          },
        ],
        xaxis: {
          categories: labels,
        },
        yaxis: {
          title: {
            text: i18next.t("Amount"),
          },
        },
        stroke: {
          curve: "smooth",
          width: 2,
        },
        grid: {
          show: true,
          borderColor: "#e0e0e0",
        },
      };

      const depositChartElement = document.getElementById("deposit-lineGraph");
      if (depositChartElement) {
        const depositChart = new window.ApexCharts(depositChartElement, depositChartOptions);
        depositChart.render();
        window.depositChart = depositChart;
      }
    });
  };

  const initialDays = resolveDepositDays($("#deposit-days").val());
  loadDepositChart(initialDays);

  $("#deposit-days").on("change", function () {
    loadDepositChart($(this).val());
  });
}
