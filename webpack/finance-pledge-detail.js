import { confirmAction } from "./common/confirm-action";

window.CRM.onLocalesReady(() => {
  const ROOT = window.CRM.root;

  const deleteBtn = document.getElementById("deletePledgeBtn");
  if (deleteBtn) {
    deleteBtn.addEventListener("click", function () {
      const groupKey = this.dataset.groupKey;
      const confirmMsg = window.CRM.pledgeDetail.isPledge
        ? i18next.t("Are you sure you want to permanently delete this pledge record?")
        : i18next.t("Are you sure you want to permanently delete this payment record?");
      confirmAction(i18next.t("Delete"), confirmMsg, async () => {
        try {
          const res = await fetch(ROOT + "/api/payments/" + encodeURIComponent(groupKey), {
            method: "DELETE",
          });
          if (res.ok) {
            window.CRM.notify(window.CRM.escapeHtml(i18next.t("Deleted successfully")), {
              type: "success",
              delay: 4000,
            });
            setTimeout(() => {
              window.location.href = ROOT + "/finance/";
            }, 800);
          } else {
            const data = await res.json().catch(() => ({}));
            const msg = (data && (data.error || data.message)) || i18next.t("Delete failed");
            window.CRM.notify(window.CRM.escapeHtml(msg), { type: "danger", delay: 4000 });
          }
        } catch (err) {
          window.CRM.notify(window.CRM.escapeHtml(i18next.t("Network error, please try again")), {
            type: "danger",
            delay: 4000,
          });
        }
      });
    });
  }
});
