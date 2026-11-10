/**
 * window.CRM.notify(): Tabler toasts in the #crm-toasts live region.
 *
 * notify("Saved", { type: "success", delay: 4000 })
 * notify({ message: "Saved" }, { type: "success" })
 *
 * The message is inserted as HTML: callers pass escaped text or deliberate
 * markup. delay 0 keeps the toast until it is dismissed.
 */

import { Toast } from "@tabler/core";

const CONTAINER_ID = "crm-toasts";
const DEFAULT_DELAY_MS = 3000;

const ICONS = {
  success: "fa-circle-check",
  danger: "fa-circle-exclamation",
  warning: "fa-triangle-exclamation",
  info: "fa-circle-info",
};

/** bootstrap-notify called it "danger"; most call sites spell it "error". */
function toastType(type) {
  if (type === "error") {
    return "danger";
  }
  return ICONS[type] ? type : "info";
}

/** Layouts render the container; build it for any page that did not. */
function container() {
  const existing = document.getElementById(CONTAINER_ID);
  if (existing) {
    return existing;
  }
  const created = document.createElement("div");
  created.id = CONTAINER_ID;
  created.className = "toast-container position-fixed top-0 end-0 p-3";
  created.setAttribute("aria-live", "polite");
  document.body.append(created);
  return created;
}

function translate(text) {
  return (typeof i18next !== "undefined" && i18next.t(text)) || text;
}

export function notify(messageOrObject, options = {}) {
  const message = typeof messageOrObject === "string" ? messageOrObject : (messageOrObject?.message ?? "");
  const type = toastType(options.type);
  const delay = options.delay ?? DEFAULT_DELAY_MS;

  const toast = document.createElement("div");
  toast.className = `toast align-items-center border-0 text-bg-${type}`;
  toast.dataset.type = type;
  toast.setAttribute("role", type === "danger" ? "alert" : "status");
  toast.setAttribute("aria-atomic", "true");
  toast.innerHTML = `
    <div class="d-flex">
      <div class="toast-body d-flex align-items-start gap-2">
        <i class="fa-solid ${ICONS[type]} mt-1" aria-hidden="true"></i>
        <div class="crm-toast-message"></div>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>`;
  toast.querySelector(".crm-toast-message").innerHTML = message;
  toast.querySelector(".btn-close").setAttribute("aria-label", translate("Close"));

  container().append(toast);
  toast.addEventListener("hidden.bs.toast", () => toast.remove());
  new Toast(toast, { autohide: delay > 0, delay }).show();
  return toast;
}

if (typeof window !== "undefined") {
  window.CRM = window.CRM || {};
  window.CRM.notify = notify;
}
