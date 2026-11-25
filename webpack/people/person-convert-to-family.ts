import { confirmAction } from "../common/confirm-action";
import { escapeHtml } from "../utils/escape-html";

/**
 * The person page's Convert to Family item (Actions menu, administrators, a person without a
 * family): confirms, posts to /admin/api/person/{id}/convert-to-family, then opens the new family.
 */

function t(key: string): string {
  if (typeof i18next === "undefined" || typeof i18next.t !== "function") {
    return key;
  }
  const translated = i18next.t(key);
  return translated === undefined || translated === "" ? key : translated;
}

async function convert(item: HTMLElement): Promise<void> {
  let response: Response;
  try {
    response = await fetch(`${window.CRM?.root ?? ""}/admin/api/person/${item.dataset.personId}/convert-to-family`, {
      method: "POST",
      headers: { "content-type": "application/json", "X-CSRF-Token": item.dataset.csrfToken ?? "" },
      body: "{}",
    });
  } catch {
    window.CRM?.notify?.(escapeHtml(t("The conversion failed. Nothing was changed.")), { type: "danger" });
    return;
  }
  let body: { message?: string; family?: { url?: string } } = {};
  try {
    body = await response.json();
  } catch {
    // An answer without JSON is reported with the fallback below.
  }
  if (!response.ok || !body.family?.url) {
    window.CRM?.notify?.(escapeHtml(body.message || t("The conversion failed. Nothing was changed.")), {
      type: "danger",
    });
    return;
  }
  window.location.assign(body.family.url);
}

export function initConvertToFamily(): void {
  const item = document.getElementById("convertToFamily");
  if (!item) {
    return;
  }
  item.addEventListener("click", (event) => {
    event.preventDefault();
    confirmAction(
      t("Convert to Family"),
      escapeHtml(t("Create a family for this person? Their address and home phone move to the new family.")),
      () => {
        void convert(item);
      },
      { tone: "primary", confirmLabel: t("Convert to Family"), cancelLabel: t("Cancel") },
    );
  });
}
