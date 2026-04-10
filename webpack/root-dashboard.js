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

  // Photo viewer click handlers are registered globally in avatar-loader.ts
});
