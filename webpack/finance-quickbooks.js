/** QuickBooks ticket editor: saves the complete set of positions with one request. */
document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("quickbooksForm");
  if (!form) {
    return;
  }
  const t = (key) => (window.i18next ? i18next.t(key) : key);

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    for (const input of form.elements) {
      input.classList?.toggle("is-invalid", !input.validity?.valid);
    }
    if (!form.checkValidity()) {
      window.CRM.notify(t("Every position must be a number from 0 to 1000 millimetres."), { type: "danger" });
      return;
    }
    const values = Object.fromEntries(new FormData(form));
    window.CRM.AdminAPIRequest({
      method: "POST",
      path: "finance/quickbooks",
      data: JSON.stringify(values),
      headers: { "X-CSRF-Token": form.dataset.csrfToken },
    }).done(() => window.CRM.notify(t("Saved"), { type: "success" }));
  });
});
