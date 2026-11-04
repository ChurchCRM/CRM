/**
 * Admin Dashboard Page JavaScript
 */

import "./admin-dashboard.css";
import "./telemetry-consent";
import "./former-portal-accounts-notice";

document.addEventListener("DOMContentLoaded", () => {
  const reportingSettings = document.getElementById("reportingSettings");
  if (reportingSettings) {
    window.CRM.onLocalesReady(() => {
      new window.CRM.SettingsPanel().init({
        container: "#reportingSettings",
        title: i18next.t("Reporting"),
        icon: "fa-solid fa-file-pdf",
        settings: JSON.parse(reportingSettings.dataset.settings),
        autoSave: true,
        showAllSettingsLink: false,
      });
    });
  }

  // Bootstrap's data API is on window.bootstrap. The page bundle's jQuery
  // does not get $.fn.tooltip.
  window.bootstrap?.Tooltip &&
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
      window.bootstrap.Tooltip.getOrCreateInstance(el);
    });

  // Add smooth scroll behavior
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute("href"));
      if (target) {
        target.scrollIntoView({
          behavior: "smooth",
        });
      }
    });
  });
});
