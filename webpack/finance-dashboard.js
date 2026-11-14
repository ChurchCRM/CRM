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
