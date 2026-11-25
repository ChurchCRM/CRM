/** Finance editor pages: one auto-saving settings panel, described by the container's data attributes. */
document.addEventListener("DOMContentLoaded", () => {
  const panel = document.querySelector("[data-settings]");
  if (!panel) {
    return;
  }
  new window.CRM.SettingsPanel().init({
    container: `#${panel.id}`,
    title: panel.dataset.title,
    icon: panel.dataset.icon,
    settings: JSON.parse(panel.dataset.settings),
    autoSave: true,
  });
});
