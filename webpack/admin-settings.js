// Renders every server-described settings section on the page as an auto-saving panel.
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-settings][data-title]").forEach((panel) => {
    const reload = panel.closest("[data-reload-on-save]") !== null;
    new window.CRM.SettingsPanel().init({
      container: `#${panel.id}`,
      title: panel.dataset.title,
      icon: panel.dataset.icon,
      settings: JSON.parse(panel.dataset.settings),
      autoSave: true,
      columns: Number(panel.dataset.columns) || 3,
      onSave: reload ? () => setTimeout(() => window.location.reload(), 800) : null,
    });
  });
});
