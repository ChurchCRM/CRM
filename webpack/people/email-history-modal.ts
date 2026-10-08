/**
 * The email history modal (`src/people/views/partials/email-history-modal.php`). A click on
 * any `.email-history-open[data-email-log-id]` fetches GET /api/email/log/{id} and fills the
 * modal. The body is shown in a sandboxed iframe so stored HTML cannot run scripts or reach
 * the page.
 */
import { buildAPIUrl } from "../api-utils";

interface EmailLogRow {
  subject?: string;
  address?: string;
  dateSent?: string;
  kindLabel?: string;
  status?: string;
  error?: string | null;
  sentBy?: string | null;
  body?: string | null;
}

const FIELDS = ["address", "dateSent", "kindLabel", "status", "sentBy"];

function statusLabel(status: string): string {
  const labels: Record<string, string> = {
    sent: i18next.t("Sent"),
    failed: i18next.t("Failed"),
    skipped: i18next.t("Skipped"),
  };
  return labels[status] || status;
}

function setField(modalEl: HTMLElement, name: string, value: unknown): void {
  const el = modalEl.querySelector(`[data-field="${name}"]`);
  if (el) {
    el.textContent = value == null || value === "" ? "—" : String(value);
  }
}

function show(modalEl: HTMLElement, id: string): void {
  const title = document.getElementById("email-history-modal-title");
  const errorEl = document.getElementById("email-history-modal-error");
  const noBodyEl = document.getElementById("email-history-modal-nobody");
  const frame = document.getElementById("email-history-modal-body");
  if (!title || !errorEl || !noBodyEl || !frame) {
    return;
  }

  title.textContent = "…";
  errorEl.classList.add("d-none");
  noBodyEl.classList.add("d-none");
  frame.classList.add("d-none");
  frame.removeAttribute("srcdoc");
  for (const field of FIELDS) {
    setField(modalEl, field, "");
  }
  window.bootstrap.Modal.getOrCreateInstance(modalEl).show();

  fetch(buildAPIUrl(`email/log/${encodeURIComponent(id)}`), {
    credentials: "same-origin",
    headers: { Accept: "application/json" },
  })
    .then((res) => (res.ok ? res.json() : Promise.reject(new Error(String(res.status)))))
    .then((row: EmailLogRow) => {
      title.textContent = row.subject || "";
      setField(modalEl, "address", row.address);
      setField(modalEl, "dateSent", row.dateSent);
      setField(modalEl, "kindLabel", row.kindLabel);
      setField(modalEl, "status", statusLabel(row.status ?? "") + (row.error ? ` — ${row.error}` : ""));
      setField(modalEl, "sentBy", row.sentBy || i18next.t("Automatic"));
      if (row.body) {
        frame.classList.remove("d-none");
        frame.setAttribute("srcdoc", row.body);
      } else {
        noBodyEl.classList.remove("d-none");
      }
    })
    .catch(() => {
      errorEl.textContent = i18next.t("Could not load this email.");
      errorEl.classList.remove("d-none");
    });
}

document.addEventListener("click", (event) => {
  const modalEl = document.getElementById("email-history-modal");
  const target = event.target instanceof Element ? event.target : null;
  const link = target?.closest(".email-history-open[data-email-log-id]");
  if (!modalEl || !link) {
    return;
  }
  event.preventDefault();
  show(modalEl, link.getAttribute("data-email-log-id") ?? "");
});
