/**
 * Setup Dashboard: Finish setup ends the setup window for good.
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
