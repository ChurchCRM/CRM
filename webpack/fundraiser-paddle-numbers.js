import { initConfirmForms } from "./common/confirm-forms";

initConfirmForms();

(() => {
  const form = document.getElementById("generateStatementsForm");
  if (!form) return;
  form.addEventListener("submit", function (e) {
    const checked = document.querySelectorAll(".pledge-select:checked");

    if (!checked.length) {
      e.preventDefault();
      window.CRM.onLocalesReady(() => {
        window.CRM.notify(i18next.t("Please select at least one buyer to generate statements."), { type: "warning" });
      });
      return;
    }

    // Remove any previously injected hidden fields to avoid duplicates on re-submit
    this.querySelectorAll('input[type="hidden"][name^="Chk"]').forEach((el) => {
      el.remove();
    });
    checked.forEach((cb) => {
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = "Chk" + cb.dataset.pnId;
      input.value = "1";
      this.appendChild(input);
    });
  });
})();
