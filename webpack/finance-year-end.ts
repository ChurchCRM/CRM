/**
 * Year-End Giving: the statement wording panel. Admins edit the wording in place; the panel saves
 * through the System Config API and reloads so the letter preview matches.
 */
function wire(): void {
  const host = document.getElementById("statementWording");
  const settings = host?.dataset.settings;
  if (!host || !settings || !window.CRM?.settingsPanel) {
    return;
  }

  window.CRM.settingsPanel.init({
    container: "#statementWording",
    title: host.dataset.title ?? "",
    icon: "fa-solid fa-pen-to-square",
    settings: JSON.parse(settings),
    onSave: () => window.location.reload(),
  });
}

if (typeof window.CRM?.onLocalesReady === "function") {
  window.CRM.onLocalesReady(wire);
} else {
  wire();
}

export {};
