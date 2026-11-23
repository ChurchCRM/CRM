/**
 * Setup Dashboard: Finish setup ends the setup window for good. The sidebar's
 * Explore with Demo Data link (?explore=1) opens the import dialog.
 */

import { buildAdminAPIUrl } from "./api-utils";
import { confirmAction } from "./common/confirm-action";

document.addEventListener("click", (e) => {
  const btn = e.target.closest("#finishSetup");
  if (!btn) return;

  confirmAction(
    i18next.t("Finish setup"),
    i18next.t(
      "Administrators will sign in to the standard dashboard from now on. The Setup Dashboard will not open at sign-in again.",
    ),
    () => {
      btn.disabled = true;
      fetch(buildAdminAPIUrl("system/config/bSetupFinished"), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ value: "1" }),
      })
        .then((response) => {
          if (!response.ok) throw new Error(String(response.status));
          window.location.assign(btn.dataset.dashboardUrl);
        })
        .catch(() => {
          btn.disabled = false;
          window.CRM.notify(i18next.t("Error"), { type: "danger" });
        });
    },
    { tone: "primary", confirmLabel: i18next.t("Finish setup"), cancelLabel: i18next.t("Cancel") },
  );
});

// jQuery runs ready handlers in order, after importDemoData.js has queued the one that wires the button.
window.jQuery(() => {
  if (!new URLSearchParams(window.location.search).has("explore")) return;
  window.CRM.onLocalesReady(() => document.getElementById("importDemoDataSetup")?.click());
});
