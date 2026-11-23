/**
 * First run: the setup-mode language picker, and Start Fresh, which erases the
 * demo data and every setting, back to a clean install.
 */

import { buildAdminAPIUrl, buildAPIUrl } from "./api-utils";
import { alertAction, confirmAction } from "./common/confirm-action";

document.addEventListener("click", (e) => {
  const btn = e.target.closest(".js-start-fresh");
  if (!btn) return;

  confirmAction(
    i18next.t("Start Fresh"),
    `<p>${i18next.t("This erases the demo data and every setting, and returns ChurchCRM to a new install.")}</p>` +
      `<p class="mb-0">${i18next.t("You will sign in again with the default administrator account and enter your church information.")}</p>`,
    () => {
      document.querySelectorAll(".js-start-fresh").forEach((el) => {
        el.disabled = true;
      });
      fetch(buildAdminAPIUrl("database/reset"), { method: "DELETE" })
        .then((response) => {
          if (!response.ok) throw new Error(String(response.status));
          return response.json();
        })
        .then((data) => {
          alertAction(
            i18next.t("Ready for your church"),
            `<p>${i18next.t("The demo data is gone. Sign in with the default administrator account to set up your church.")}</p>` +
              `<div class="alert alert-info mb-0"><strong>${i18next.t("Default admin credentials")}:</strong> ` +
              `<code>${window.CRM.escapeHtml(data.defaultUsername)}</code> / <code>${window.CRM.escapeHtml(data.defaultPassword)}</code></div>`,
            {
              onClose: () => {
                window.location.assign(`${window.CRM.root}/session/begin`);
              },
            },
          );
        })
        .catch(() => {
          document.querySelectorAll(".js-start-fresh").forEach((el) => {
            el.disabled = false;
          });
          window.CRM.notify(i18next.t("Database reset failed"), { type: "danger" });
        });
    },
    { tone: "danger", confirmLabel: i18next.t("Start Fresh"), cancelLabel: i18next.t("Cancel") },
  );
});

// The church's language, and the signed-in administrator's own.
document.addEventListener("change", (e) => {
  if (e.target.id !== "setupLanguage") return;
  const save = (url) =>
    fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ value: e.target.value }),
    }).then((response) => {
      if (!response.ok) throw new Error(String(response.status));
    });
  e.target.disabled = true;
  Promise.all([
    save(buildAdminAPIUrl("system/config/sLanguage")),
    save(buildAPIUrl(`user/${window.CRM.userId}/setting/ui.locale`)),
  ])
    .then(() => window.location.assign(window.location.pathname))
    .catch(() => {
      e.target.disabled = false;
      window.CRM.notify(i18next.t("Error"), { type: "danger" });
    });
});
