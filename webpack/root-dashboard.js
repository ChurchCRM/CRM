/**
 * Root Dashboard — webpack entry
 * Initializes dashboard tables and ApexCharts deposit tracker
 */

import { initializeMainDashboard } from "../src/skin/js/MainDashboard";

document.addEventListener("DOMContentLoaded", () => {
  // Initialize dashboard components when locales are ready
  if (window.CRM && window.CRM.onLocalesReady) {
    window.CRM.onLocalesReady(initializeMainDashboard);
  }

  // Use global jQuery which should be set by skin-main.js
  const $ = window.$;
  if (!$) {
    console.error("jQuery not available - skin-main.js may not have loaded");
    return;
  }

  // Photo viewer click handlers
  $(document).on("click", ".view-person-photo", function (e) {
    var personId = $(e.currentTarget).data("person-id");
    if (window.CRM && window.CRM.showPhotoLightbox) {
      window.CRM.showPhotoLightbox("person", personId);
    }
    e.stopPropagation();
  });

  $(document).on("click", ".view-family-photo", function (e) {
    var familyId = $(e.currentTarget).data("family-id");
    if (window.CRM && window.CRM.showPhotoLightbox) {
      window.CRM.showPhotoLightbox("family", familyId);
    }
    e.stopPropagation();
  });
});
