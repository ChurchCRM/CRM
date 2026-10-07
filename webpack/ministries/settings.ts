/**
 * Admin → Ministry Settings: the background-job times the page renders on the server,
 * in ChurchCRM's locale, and the "Run background jobs now" button. The settings panel
 * itself is `system-settings-panel`.
 */
import { formatTimeElements } from "./components/ui";

function runJobsNow(button: HTMLButtonElement): void {
  button.disabled = true;
  window.CRM?.APIRequest?.({
    method: "POST",
    path: "background/timerjobs",
    data: JSON.stringify({ force: true }),
  })
    .done(() => {
      window.CRM?.notify?.(i18next.t("Background jobs ran"), { type: "success" });
      setTimeout(() => window.location.reload(), 1200);
    })
    .fail(() => {
      button.disabled = false;
    });
}

document.addEventListener("DOMContentLoaded", () => {
  formatTimeElements();

  const runButton = document.getElementById("ministry-run-jobs-btn");
  if (runButton instanceof HTMLButtonElement) {
    runButton.addEventListener("click", () => runJobsNow(runButton));
  }
});
