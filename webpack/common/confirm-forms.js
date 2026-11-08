import { confirmAction } from "./confirm-action";

export function initConfirmForms() {
  document.addEventListener("submit", (event) => {
    const form = event.target.closest("form[data-confirm]");
    if (!form) return;
    event.preventDefault();
    window.CRM.onLocalesReady(() => {
      confirmAction(i18next.t("Delete"), window.CRM.escapeHtml(form.dataset.confirm), () => {
        HTMLFormElement.prototype.submit.call(form);
      });
    });
  });
}
