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
      // The reset ends this session; the page's background calls then get 401 and would
      // send the page to sign-in before the administrator reads the default credentials.
      window.CRM.keepPageOnSignOut = true;
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
          window.CRM.keepPageOnSignOut = false;
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
function applyLanguage(select) {
  const save = (url) =>
    fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ value: select.value }),
    }).then((response) => {
      if (!response.ok) throw new Error(String(response.status));
    });
  select.disabled = true;
  Promise.all([
    save(buildAdminAPIUrl("system/config/sLanguage")),
    save(buildAPIUrl(`user/${window.CRM.userId}/setting/ui.locale`)),
  ])
    .then(() => window.location.assign(window.location.pathname))
    .catch(() => {
      select.disabled = false;
      window.CRM.notify(i18next.t("Error"), { type: "danger" });
    });
}

/** The supported locale closest to the browser's languages: exact, then the language's own country, then any. */
function browserLocale(supported) {
  for (const tag of navigator.languages ?? [navigator.language]) {
    const [language, country = language] = tag.toLowerCase().split("-");
    const match =
      supported.find((locale) => locale.toLowerCase() === `${language}_${country}`) ??
      supported.find((locale) => locale.toLowerCase() === `${language}_${language}`) ??
      supported.find((locale) => locale.toLowerCase().startsWith(`${language}_`));
    if (match) return match;
  }
  return null;
}

document.addEventListener("change", (e) => {
  if (e.target.id === "setupLanguage") applyLanguage(e.target);
});

// Once per browser, a new install starts in the visitor's own language.
document.addEventListener("DOMContentLoaded", () => {
  const select = document.getElementById("setupLanguage");
  if (!select) return;
  const key = "churchcrm.setupLanguageDetected";
  try {
    if (localStorage.getItem(key)) return;
    localStorage.setItem(key, "1");
  } catch {
    return;
  }
  const locale = browserLocale([...select.options].map((option) => option.value));
  if (locale && locale !== select.value) {
    select.value = locale;
    applyLanguage(select);
  }
});
