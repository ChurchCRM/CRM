/**
 * Year-End Giving: the statement wording panel. Each field saves as it changes, through the
 * finance config API, like the Statement Settings page.
 */
function wire(): void {
  const host = document.getElementById("statementWording");
  const settings = host?.dataset.settings;
  if (!host || !settings || !window.CRM?.SettingsPanel) {
    return;
  }

  new window.CRM.SettingsPanel().init({
    container: "#statementWording",
    title: host.dataset.title ?? "",
    icon: "fa-solid fa-pen-to-square",
    settings: JSON.parse(settings),
    showAllSettingsLink: false,
    autoSave: true,
    columns: 1,
    configApiPath: "/finance/api/config",
  });
}

if (typeof window.CRM?.onLocalesReady === "function") {
  window.CRM.onLocalesReady(wire);
} else {
  wire();
}

export {};
