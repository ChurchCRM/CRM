/**
 * Admin Dashboard Page JavaScript
 */

import "./admin-dashboard.css";
import "./telemetry-consent";
import "./former-portal-accounts-notice";
import "./volunteer-v1-notice";

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

  // Smooth-scroll in-page links. Header menus use href="#", which is not a selector.
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    const href = anchor.getAttribute("href");
    if (!href || href === "#") {
      return;
    }
    anchor.addEventListener("click", (e) => {
      let target;
      try {
        target = document.querySelector(href);
      } catch {
        return;
      }
      if (!target) {
        return;
      }
      e.preventDefault();
      target.scrollIntoView({
        behavior: "smooth",
      });
    });
  });
});
