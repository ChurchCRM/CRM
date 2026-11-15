/**
 * Person and family custom fields editors — reorder and delete fields.
 */

import { buildAdminAPIUrl, notifyAPIError } from "../api-utils";
import { confirmAction } from "../common/confirm-action";

function initializeCustomFieldsEditor() {
  const form = document.querySelector("form[data-custom-field-type]");
  const recordType = form?.dataset.customFieldType;
  if (recordType !== "person" && recordType !== "family") return;

  const sendFieldChange = async (path, method, body, onSuccessUrl, fallback) => {
    try {
      const res = await fetch(buildAdminAPIUrl(`system/custom-fields/${recordType}/${path}`), {
        method,
        headers: { "Content-Type": "application/json" },
        body: body === null ? undefined : JSON.stringify(body),
      });
      if (res.ok) {
        window.location.assign(onSuccessUrl);
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
      `${encodeURIComponent($btn.data("field-id"))}/order`,
      "PUT",
      { direction: $btn.data("direction") },
      window.location.pathname,
      i18next.t("An error occurred. Please try again."),
    );
  });

  $(document).on("click", ".js-delete-field", function (e) {
    e.preventDefault();
    const $btn = $(this);
    const fieldName = window.CRM.escapeHtml(String($btn.data("field-name") ?? ""));
    const dataLossWarning =
      recordType === "person"
        ? i18next.t("By deleting this field, you will irrevocably lose all person data assigned for this field!")
        : i18next.t("By deleting this field, you will irrevocably lose all family data assigned for this field!");
    const message =
      `${i18next.t("Are you sure you want to delete")} "${fieldName}"?` +
      `<br><br><strong>${i18next.t("Warning")}:</strong> ${dataLossWarning}`;

    confirmAction(
      i18next.t("Delete Confirmation"),
      message,
      () =>
        sendFieldChange(
          encodeURIComponent($btn.data("field-id")),
          "DELETE",
          null,
          `${window.location.pathname}?deleted=1`,
          i18next.t("Failed to delete. Please try again."),
        ),
      { confirmLabel: i18next.t("Delete"), cancelLabel: i18next.t("Cancel") },
    );
  });

  if (new URLSearchParams(window.location.search).get("deleted") === "1") {
    window.CRM.notify(i18next.t("Field deleted successfully"), { type: "success" });
  }
}

$(document).ready(() => {
  window.CRM.onLocalesReady(initializeCustomFieldsEditor);
});
