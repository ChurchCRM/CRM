/**
 * People Settings page only. The shared settings panel owns country and state.
 * This binds the "Copy from Church Info" button on the new-record defaults heading.
 */

import { fetchAdminAPIJSON } from "./api-utils";

document.addEventListener("DOMContentLoaded", () => {
  window.CRM.refreshGradePromotion = refreshGradePromotion;
  const promoteButton = document.getElementById("gradePromoteNow");
  if (promoteButton instanceof HTMLButtonElement) {
    promoteButton.addEventListener("click", () => promoteGrades(promoteButton));
  }

  const panel = document.getElementById("peopleNewMembers");
  if (!panel) {
    return;
  }

  const bind = () => {
    const button = document.getElementById("copy-church-address");
    if (!button || button.dataset.bound) {
      return Boolean(button);
    }
    button.dataset.bound = "1";
    button.addEventListener("click", () => copyChurchAddress(panel));
    return true;
  };

  if (!bind()) {
    const observer = new MutationObserver(() => {
      if (bind()) {
        observer.disconnect();
      }
    });
    observer.observe(panel, { childList: true, subtree: true });
  }
});

function countryCode(select, value) {
  if (!value) {
    return "";
  }
  const upper = String(value).toUpperCase();
  for (const option of select.options) {
    if (option.value.toUpperCase() === upper || option.textContent === value) {
      return option.value;
    }
  }
  return "";
}

function t(key) {
  return window.i18next ? i18next.t(key) : key;
}

async function copyChurchAddress(panel) {
  const keys = ["sChurchCountry", "sChurchState", "sChurchCity", "sChurchZip"];
  try {
    const values = await Promise.all(
      keys.map(async (key) => (await fetchAdminAPIJSON(`system/config/${key}`)).value || ""),
    );
    const country = panel.querySelector("[name='sDefaultCountry']");
    const code = countryCode(country, values[0]);
    if (!code && values[0]) {
      window.CRM.notify(t("Could not match the church country."), { type: "warning", delay: 4000 });
      return;
    }
    // The panel's country handler reads this so the state list keeps the church state.
    country.dataset.keepState = values[1];
    country.value = code;
    country.dispatchEvent(new Event("change", { bubbles: true }));

    ["sDefaultCity", "sDefaultZip"].forEach((name, index) => {
      const input = panel.querySelector(`[name='${name}']`);
      input.value = values[index + 2];
      input.dispatchEvent(new Event("change", { bubbles: true }));
    });
  } catch {
    window.CRM.notify(t("Could not copy the church address."), { type: "error", delay: 4000 });
  }
}

function refreshGradePromotion() {
  window.CRM.AdminAPIRequest({ method: "GET", path: "grades/promotion" }).done((status) => {
    document.getElementById("gradeNextPromotion").textContent = status.nextDateText;
    const button = document.getElementById("gradePromoteNow");
    button.dataset.expectedLastAt = status.lastAt;
    button.dataset.moved = String(status.preview.moved);
    button.dataset.finished = String(status.preview.finished);
    button.dataset.promotedThisYear = status.promotedThisYear ? "1" : "0";
    button.dataset.lastRun = status.lastRunText || "";
  });
}

function promoteGrades(button) {
  const { expectedLastAt, moved, finished, promotedThisYear, lastRun, csrfToken } = button.dataset;
  if (Number(moved) + Number(finished) === 0) {
    window.CRM.notify(t("Nobody has a grade to promote."), { type: "info", delay: 4000 });
    return;
  }
  const warning =
    promotedThisYear === "1"
      ? `<p class="text-danger fw-bold">${window.CRM.escapeHtml(
          i18next.t(
            "Grades were already promoted this year, on {{date}}. Promoting again moves everyone up another grade.",
            {
              date: lastRun,
            },
          ),
        )}</p>`
      : "";
  const message = [
    t("Everyone with a grade will move up one grade now, and this will count as this year's promotion."),
    i18next.t("Moving up: {{total}}", { total: Number(moved) }),
    i18next.t("Finishing the last grade (grade will be cleared): {{total}}", { total: Number(finished) }),
  ]
    .map((line) => `<p>${window.CRM.escapeHtml(line)}</p>`)
    .join("");
  const confirmMessage = warning + message;

  window.bootbox.confirm({
    title: t("Promote Grades"),
    message: confirmMessage,
    buttons: {
      cancel: { label: t("Cancel"), className: "btn-secondary" },
      confirm: { label: t("Promote Now"), className: "btn-primary" },
    },
    callback: (confirmed) => {
      if (!confirmed) {
        return;
      }
      button.disabled = true;
      window.CRM.AdminAPIRequest({
        method: "POST",
        path: "grades/promotion",
        data: JSON.stringify({ expectedLastAt }),
        headers: { "X-CSRF-Token": csrfToken },
      })
        .done(() => window.location.reload())
        .fail(() => {
          button.disabled = false;
        });
    },
  });
}
