/**
 * Get Started — onboarding wizard page
 */

import "./get-started.css";

document.addEventListener("DOMContentLoaded", () => {
  window.bootstrap?.Tooltip &&
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
      window.bootstrap.Tooltip.getOrCreateInstance(el);
    });
});
