/**
 * Admin Dashboard Page JavaScript
 */

import "./admin-dashboard.css";
import "./telemetry-consent";

document.addEventListener("DOMContentLoaded", () => {
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
