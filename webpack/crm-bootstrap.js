/**
 * Turns the per-request JSON from Header.php into window.CRM.
 * skin-core.js installs applyPageConfig. Header calls it after moment.js loads.
 */

function formatCurrency(amount, decimals) {
  if (decimals === undefined) {
    decimals = 2;
  }
  const val = parseFloat(amount);
  if (Number.isNaN(val)) {
    return "";
  }
  const parts = val.toFixed(decimals).split(".");
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, this.thousand);
  const formatted = parts[0] + (decimals > 0 ? this.decimal + parts[1] : "");
  const sym = this.symbol.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
  return this.position === "after" ? `${formatted}\u00A0${sym}` : `${sym}\u00A0${formatted}`;
}

function dataTableConfig(pageLength, languageUrl) {
  return {
    pageLength,
    lengthMenu: [
      [10, 25, 50, 100, -1],
      [10, 25, 50, 100, "All"],
    ],
    language: { url: languageUrl },
    responsive: true,
    layout: {
      topStart: "search",
      topEnd: "buttons",
      bottomStart: "pageLength",
      bottomEnd: ["info", "paging"],
    },
    buttons: [
      {
        extend: "csv",
        text: '<i class="fa-solid fa-table"></i>',
        titleAttr: "Export CSV",
        exportOptions: { columns: ":not(.no-export)" },
      },
      {
        extend: "print",
        text: '<i class="fa-solid fa-print"></i>',
        titleAttr: "Print",
        exportOptions: { columns: ":not(.no-export)" },
      },
    ],
  };
}

export function installPageConfig() {
  window.CRM = window.CRM || {};
  window.CRM.applyPageConfig = (config) => {
    const pageLength = config.dataTablePageLength;
    const languageUrl = config.dataTableLanguageUrl;
    const localeConfig = config.localeConfig;
    delete config.dataTablePageLength;
    delete config.dataTableLanguageUrl;
    delete config.localeConfig;

    Object.assign(window.CRM, config);

    if (config.currency) {
      window.CRM.currency.format = formatCurrency;
    }
    if (pageLength !== undefined) {
      window.CRM.plugin = window.CRM.plugin || {};
      window.CRM.plugin.dataTable = dataTableConfig(pageLength, languageUrl);
    }
    if (window.moment && window.CRM.shortLocale) {
      window.moment.locale(window.CRM.shortLocale);
    }
    if (localeConfig && window.CRM.loadLocaleFiles) {
      window.CRM.loadLocaleFiles(localeConfig);
    }
  };
}
