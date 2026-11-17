/**
 * Group properties form editor — reorder and delete group-specific fields.
 */

import { buildAPIUrl, notifyAPIError } from "../api-utils";
import { confirmAction } from "../common/confirm-action";

function initializeGroupPropertiesForm() {
  const form = document.querySelector("form[name='GroupPropFormEditor']");
  const groupId = Number(form?.dataset.groupId);
  if (!groupId) return;

  const sendFieldChange = async (path, method, body, fallback) => {
    try {
      const res = await fetch(buildAPIUrl(`groups/${groupId}/formprops/${path}`), {
        method,
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      if (res.ok) {
        window.location.reload();
        return;
      }
      await notifyAPIError(res, fallback);
    } catch (_error) {
      window.CRM.notify(fallback, { type: "danger", delay: 5000 });
    }
  };

  $(document).on("click", ".js-reorder-field", function (e) {
    e.preventDefault();
    const $btn = $(this);
    sendFieldChange(
      `${$btn.data("prop-id")}/order`,
      "PUT",
      { direction: $btn.data("direction") },
      i18next.t("An error occurred. Please try again."),
    );
  });

  $(document).on("click", ".js-delete-field", function (e) {
    e.preventDefault();
    const $btn = $(this);
    const fieldName = window.CRM.escapeHtml(String($btn.data("field-name") ?? ""));
    const message =
      `${i18next.t("Are you sure you want to delete")} "${fieldName}"?` +
      `<br><br><strong>${i18next.t("Warning")}:</strong> ` +
      i18next.t("By deleting this field, you will irrevocably lose all group member data assigned for this field!");

    confirmAction(
      i18next.t("Delete Confirmation"),
      message,
      () =>
        sendFieldChange(
          String($btn.data("prop-id")),
          "DELETE",
          { field: $btn.data("field-id") },
          i18next.t("Failed to delete. Please try again."),
        ),
      { confirmLabel: i18next.t("Delete"), cancelLabel: i18next.t("Cancel") },
    );
  });

  $('[data-bs-toggle="tooltip"]').tooltip();
}

$(document).ready(() => {
  window.CRM.onLocalesReady(initializeGroupPropertiesForm);
});
