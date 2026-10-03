/**
 * Libraries that used to be separate script tags in Footer.php.
 * jquery-global.js is imported first so plugins see window.jQuery.
 * skin-core.js imports this module.
 */
import "./jquery-global";

import bootbox from "bootbox";
import DataTable from "datatables.net-bs5";
import i18next from "i18next";
import JustValidate from "just-validate";
import Stepper from "bs-stepper";
import moment from "moment";

import "./inputmask-global";
import "inputmask/dist/jquery.inputmask";
import "inputmask/dist/bindings/inputmask.binding.js";
import "bootstrap-datepicker/dist/js/bootstrap-datepicker";
import "daterangepicker";
import "datatables.net-buttons-bs5";
import "datatables.net-buttons/js/buttons.html5.mjs";
import "datatables.net-buttons/js/buttons.print.mjs";
import "datatables.net-responsive-bs5";
import "datatables.net-select-bs5";

window.moment = moment;
window.bootbox = bootbox;
window.i18next = i18next;
window.JustValidate = JustValidate;
window.Stepper = Stepper;
window.DataTable = DataTable;

document.addEventListener("DOMContentLoaded", () => {
  document.getElementById("fullscreenToggle")?.addEventListener("click", (e) => {
    e.preventDefault();
    const icon = e.currentTarget.querySelector("i");
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen();
      if (icon) {
        icon.className = "fa-solid fa-compress";
      }
    } else {
      document.exitFullscreen();
      if (icon) {
        icon.className = "fa-solid fa-maximize";
      }
    }
  });
});
