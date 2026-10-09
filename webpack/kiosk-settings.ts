document.addEventListener("DOMContentLoaded", () => {
  const container = document.getElementById("kioskSettings");
  if (!container) {
    return;
  }
  const { SettingsPanel } = window.CRM as unknown as { SettingsPanel: new () => { init(options: object): void } };
  new SettingsPanel().init({
    container: "#kioskSettings",
    title: container.dataset.title,
    icon: "fa-solid fa-desktop",
    settings: JSON.parse(container.dataset.settings ?? "[]"),
    showAllSettingsLink: false,
    autoSave: true,
  });
});
