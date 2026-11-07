import { initConfirmForms } from "./common/confirm-forms";

window.CRM.onLocalesReady(() => {
  initConfirmForms();

  (() => {
    var noSelectionMsg = i18next.t("Please select at least one buyer to generate statements.");

    document.getElementById("generateStatementsForm").addEventListener("submit", function (e) {
      var checked = document.querySelectorAll(".pledge-select:checked");

      // Guard: require at least one checkbox selected
      if (!checked.length) {
        e.preventDefault();
        window.CRM.notify(noSelectionMsg, { type: "warning" });
        return;
      }

      // Remove any previously injected hidden fields to avoid duplicates on re-submit
      this.querySelectorAll('input[type="hidden"][name^="Chk"]').forEach((el) => {
        el.remove();
      });
      checked.forEach((cb) => {
        var input = document.createElement("input");
        input.type = "hidden";
        input.name = "Chk" + cb.dataset.pnId;
        input.value = "1";
        this.appendChild(input);
      });
    });
  })();
});
