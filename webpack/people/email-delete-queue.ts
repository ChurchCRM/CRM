/**
 * "Delete Queue" on the Email Log's Email History card (administrators, #10356).
 *
 * Deletes every queued composer message through DELETE /admin/api/email/queue after a
 * confirmation that names the count, then reloads so the table shows what is left.
 */

import { buildAdminAPIUrl } from "../api-utils";

async function deleteQueue(button: HTMLButtonElement): Promise<void> {
  button.disabled = true;
  try {
    const res = await fetch(buildAdminAPIUrl("email/queue"), {
      method: "DELETE",
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    });
    const data = (await res.json().catch(() => ({}))) as { deleted?: number; message?: string };
    if (!res.ok) {
      window.CRM?.notify?.(data.message ?? i18next.t("Could not delete the queue."), { type: "danger", delay: 6000 });
      button.disabled = false;
      return;
    }
    window.CRM?.notify?.(i18next.t("Queued messages deleted: {{total}}", { total: data.deleted ?? 0 }), {
      type: "success",
    });
    window.location.reload();
  } catch (err) {
    console.error("[email-delete-queue] delete failed:", err);
    window.CRM?.notify?.(i18next.t("Could not delete the queue."), { type: "danger", delay: 6000 });
    button.disabled = false;
  }
}

function init(): void {
  const button = document.getElementById("email-delete-queue");
  if (!(button instanceof HTMLButtonElement)) return;
  button.addEventListener("click", () => {
    const total = Number(button.dataset.queuedCount ?? "0");
    window.bootbox?.confirm({
      title: i18next.t("Delete Queue"),
      message: i18next.t("Delete every queued message? They will not be sent. Queued messages: {{total}}", {
        total,
      }),
      buttons: {
        confirm: { label: i18next.t("Delete"), className: "btn-danger" },
        cancel: { label: i18next.t("Cancel"), className: "btn-secondary" },
      },
      callback: (confirmed: boolean) => {
        if (confirmed) deleteQueue(button).catch(console.error);
      },
    });
  });
}

if (typeof window.CRM?.onLocalesReady === "function") {
  window.CRM.onLocalesReady(init);
} else if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init);
} else {
  init();
}
