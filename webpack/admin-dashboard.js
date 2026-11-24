/**
 * Admin Dashboard Page JavaScript
 */

import "./admin-dashboard.css";
import "./telemetry-consent";
import "./former-portal-accounts-notice";

document.addEventListener("DOMContentLoaded", () => {
  window.CRM.onLocalesReady(() => {
    [
      ["reportingSettings", i18next.t("Reporting"), "fa-solid fa-file-pdf"],
      ["setupSettings", i18next.t("Setup Dashboard"), "fa-solid fa-list-check"],
    ].forEach(([id, title, icon]) => {
      const panel = document.getElementById(id);
      if (!panel) return;
      new window.CRM.SettingsPanel().init({
        container: `#${id}`,
        title,
        icon,
        settings: JSON.parse(panel.dataset.settings),
        autoSave: true,
        showAllSettingsLink: false,
      });
    });
  });

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
