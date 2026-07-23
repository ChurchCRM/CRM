/**
 * email-composer.ts — reusable in-app email composer modal
 *
 * Replaces mailto: links for multi-recipient "email list" actions.
 *
 * Usage patterns:
 *
 * 1. Data-attribute auto-wire (declarative):
 *    <button data-email-composer
 *            data-email-endpoint="cart/emails"
 *            data-email-title="Email Cart Members">Email</button>
 *
 * 2. Programmatic (for legacy JS callers such as GroupView.js):
 *    window.CRM.emailComposer.open({ emails, byRole, title });
 *
 * Features:
 * - Loading / error states while fetching
 * - Recipient count badge
 * - Collapsible recipient list (scrollable for large sets)
 * - BCC toggle (changes mailto: mode)
 * - "Copy Addresses" — clipboard (works for any list size)
 * - "Open in Email Client" — enabled ≤50 recipients; disabled with tooltip otherwise
 */

import { buildAPIUrl } from "../api-utils";

/** Response shape from /api/people/emails and /api/cart/emails */
interface EmailListResponse {
  emails?: string[];
  byRole?: Record<string, string[]>;
}

const MAX_MAILTO_RECIPIENTS = 50;

// ─────────────────────────────────────────────
//  Modal DOM — created once, reused
// ─────────────────────────────────────────────

let modalEl: HTMLElement | null = null;
let modalTitle: HTMLElement | null = null;
let modalBody: HTMLElement | null = null;
let bccToggle: HTMLButtonElement | null = null;
let copyBtn: HTMLButtonElement | null = null;
let clientBtn: HTMLButtonElement | null = null;

/** Current resolved email list */
let currentEmails: string[] = [];
/** Whether the BCC toggle is active */
let bccMode = false;

/** Helper: create an icon+text button element */
function makeBtn(id: string, cls: string, iconCls: string, label: string): HTMLButtonElement {
  const btn = document.createElement("button");
  btn.type = "button";
  btn.id = id;
  btn.className = cls;
  const icon = document.createElement("i");
  icon.className = `${iconCls} me-1`;
  btn.appendChild(icon);
  btn.appendChild(document.createTextNode(label));
  return btn;
}

function ensureModalExists(): void {
  if (modalEl) return;

  // ── Structural shell (no user-visible text here) ──────────────────
  modalEl = document.createElement("div");
  modalEl.className = "modal fade";
  modalEl.id = "crm-email-composer-modal";
  modalEl.setAttribute("tabindex", "-1");
  modalEl.setAttribute("role", "dialog");
  modalEl.setAttribute("aria-modal", "true");
  modalEl.setAttribute("aria-labelledby", "crm-email-composer-title");
  // Language: close button aria-label is handled by Bootstrap natively.
  modalEl.innerHTML = [
    '<div class="modal-dialog modal-lg modal-dialog-scrollable">',
    '  <div class="modal-content">',
    '    <div class="modal-header">',
    '      <h5 class="modal-title" id="crm-email-composer-title"></h5>',
    '      <button type="button" class="btn-close" data-bs-dismiss="modal"',
    `        aria-label="${escapeHtml(i18next.t("Close"))}"></button>`,
    "    </div>",
    '    <div class="modal-body" id="crm-email-composer-body"></div>',
    '    <div class="modal-footer flex-wrap gap-2" id="crm-email-composer-footer">',
    "    </div>",
    "  </div>",
    "</div>",
  ].join("");

  document.body.appendChild(modalEl);

  modalTitle = document.getElementById("crm-email-composer-title");
  modalBody = document.getElementById("crm-email-composer-body");

  // ── Footer buttons (text via i18next so they are translatable) ────
  const footer = document.getElementById("crm-email-composer-footer");

  bccToggle = makeBtn(
    "crm-email-bcc-toggle",
    "btn btn-sm btn-outline-secondary me-auto",
    "fa-solid fa-user-secret",
    i18next.t("BCC Mode"),
  );

  copyBtn = makeBtn(
    "crm-email-copy-btn",
    "btn btn-sm btn-outline-primary",
    "fa-solid fa-copy",
    i18next.t("Copy Addresses"),
  );
  copyBtn.disabled = true;

  clientBtn = makeBtn(
    "crm-email-client-btn",
    "btn btn-sm btn-primary",
    "fa-solid fa-paper-plane",
    i18next.t("Open in Email Client"),
  );
  clientBtn.disabled = true;

  const closeBtn = document.createElement("button");
  closeBtn.type = "button";
  closeBtn.className = "btn btn-sm btn-secondary";
  closeBtn.setAttribute("data-bs-dismiss", "modal");
  closeBtn.textContent = i18next.t("Close");

  footer?.appendChild(bccToggle);
  footer?.appendChild(copyBtn);
  footer?.appendChild(clientBtn);
  footer?.appendChild(closeBtn);

  // BCC toggle handler
  bccToggle.addEventListener("click", () => {
    bccMode = !bccMode;
    updateBccToggleAppearance();
    updateClientButtonHref();
  });

  // Copy handler — uses clipboard API, falls back to execCommand
  copyBtn.addEventListener("click", () => {
    const csv = currentEmails.join(", ");
    if (navigator.clipboard) {
      navigator.clipboard.writeText(csv).then(
        () => showCopyFeedback(true),
        () => legacyCopy(csv),
      );
    } else {
      legacyCopy(csv);
    }
  });

  // Open in client handler
  clientBtn.addEventListener("click", () => {
    // Guard aria-disabled: the button stays focusable/hoverable so the tooltip
    // remains discoverable, so we must block the action in the event handler.
    if (clientBtn?.getAttribute("aria-disabled") === "true") return;
    if (currentEmails.length === 0) return;
    const encoded = currentEmails.map(encodeURIComponent).join(",");
    const href = bccMode ? `mailto:?bcc=${encoded}` : `mailto:${encoded}`;
    window.open(href, "_blank", "noopener,noreferrer");
  });
}

function showCopyFeedback(success: boolean): void {
  if (!copyBtn) return;
  const icon = copyBtn.querySelector("i");
  if (icon) {
    icon.className = success ? "fa-solid fa-check me-1" : "fa-solid fa-triangle-exclamation me-1";
  }
  const label = copyBtn.lastChild;
  if (label instanceof Text) {
    label.nodeValue = success ? i18next.t("Copied!") : i18next.t("Failed");
  }
  copyBtn.disabled = true;
  setTimeout(() => {
    if (!copyBtn) return;
    // Only re-enable if we are still in a valid state (non-empty recipient list)
    if (currentEmails.length === 0) return;
    if (icon) icon.className = "fa-solid fa-copy me-1";
    if (label instanceof Text) label.nodeValue = i18next.t("Copy Addresses");
    copyBtn.disabled = false;
  }, 2000);
}

function legacyCopy(text: string): void {
  const ta = document.createElement("textarea");
  ta.value = text;
  ta.style.position = "fixed";
  ta.style.opacity = "0";
  document.body.appendChild(ta);
  ta.select();
  try {
    const ok = document.execCommand("copy");
    showCopyFeedback(ok);
  } catch {
    showCopyFeedback(false);
  }
  document.body.removeChild(ta);
}

function updateBccToggleAppearance(): void {
  if (!bccToggle) return;
  if (bccMode) {
    bccToggle.classList.replace("btn-outline-secondary", "btn-secondary");
  } else {
    bccToggle.classList.replace("btn-secondary", "btn-outline-secondary");
  }
}

function updateClientButtonHref(): void {
  if (!clientBtn) return;
  // Always clear the native disabled attribute first so aria-disabled logic
  // is the sole authority on interactivity (prevents permanent lock-out).
  clientBtn.removeAttribute("disabled");
  const tooMany = currentEmails.length > MAX_MAILTO_RECIPIENTS;
  const unavailable = tooMany || currentEmails.length === 0;
  // Use aria-disabled instead of the disabled attribute so the element remains
  // focusable/hoverable and the title tooltip is still discoverable by users.
  if (unavailable) {
    clientBtn.setAttribute("aria-disabled", "true");
    clientBtn.classList.add("disabled");
  } else {
    clientBtn.removeAttribute("aria-disabled");
    clientBtn.classList.remove("disabled");
  }
  if (tooMany) {
    clientBtn.title = i18next.t(
      "Too many recipients for email client ({{count}} > {{max}}). Use Copy Addresses instead.",
      { count: currentEmails.length, max: MAX_MAILTO_RECIPIENTS },
    );
  } else {
    clientBtn.title = "";
  }
}

function resetClientButton(): void {
  if (!clientBtn) return;
  clientBtn.disabled = false;
  clientBtn.removeAttribute("disabled"); // belt-and-suspenders: clear any setAttribute path too
  clientBtn.removeAttribute("aria-disabled");
  clientBtn.classList.remove("disabled");
  clientBtn.title = "";
}

function getModal(): BootstrapModalInstance {
  return window.bootstrap.Modal.getOrCreateInstance(modalEl as Element);
}

// ─────────────────────────────────────────────
//  Render helpers
// ─────────────────────────────────────────────

function renderLoading(title: string): void {
  if (!modalTitle || !modalBody) return;
  currentEmails = []; // clear stale recipients so pending copy-feedback cannot act on them
  modalTitle.textContent = title;
  modalBody.textContent = "";
  const spinner = document.createElement("div");
  spinner.className = "d-flex justify-content-center align-items-center py-4";
  const spinIcon = document.createElement("span");
  spinIcon.className = "spinner-border spinner-border-sm me-2";
  spinIcon.setAttribute("role", "status");
  spinIcon.setAttribute("aria-hidden", "true");
  const spinText = document.createElement("span");
  spinText.textContent = i18next.t("Loading recipients\u2026");
  spinner.appendChild(spinIcon);
  spinner.appendChild(spinText);
  modalBody.appendChild(spinner);
  if (copyBtn) copyBtn.disabled = true;
  resetClientButton();
  // Client button stays disabled until recipients load — updateClientButtonHref
  // will re-evaluate once renderRecipients() is called with real data.
  if (clientBtn) {
    clientBtn.setAttribute("aria-disabled", "true");
    clientBtn.classList.add("disabled");
  }
}

function renderError(title: string, message: string): void {
  if (!modalTitle || !modalBody) return;
  currentEmails = []; // clear stale recipients so no button can act on them
  modalTitle.textContent = title;
  const alert = document.createElement("div");
  alert.className = "alert alert-danger mb-0";
  const icon = document.createElement("i");
  icon.className = "fa-solid fa-triangle-exclamation me-2";
  alert.appendChild(icon);
  alert.appendChild(document.createTextNode(message));
  modalBody.textContent = "";
  modalBody.appendChild(alert);
  // Explicitly reset both footer buttons to a safe inert state so no
  // stale enabled/disabled styling leaks through from a previous render.
  if (copyBtn) copyBtn.disabled = true;
  resetClientButton();
  if (clientBtn) {
    clientBtn.setAttribute("aria-disabled", "true");
    clientBtn.classList.add("disabled");
  }
}

function renderRecipients(title: string, emails: string[], byRole: Record<string, string[]> = {}): void {
  if (!modalTitle || !modalBody) return;

  // When byRole is present, derive the flat list from byRole so that badge
  // count, Copy payload, and the visible grouped list are always in sync.
  // Any address that appears only in the flat `emails` but not in byRole
  // (e.g. sToEmailAddress appended only to the flat list) would create a
  // hidden discrepancy, so the byRole-derived list is the authoritative one.
  const hasRoles = Object.keys(byRole).length > 0;
  currentEmails = hasRoles ? Object.values(byRole).flat() : emails;

  // Update title with count badge
  modalTitle.textContent = "";
  const titleSpan = document.createElement("span");
  titleSpan.textContent = title;
  const badge = document.createElement("span");
  badge.className = "badge bg-primary-lt text-primary ms-2";
  badge.textContent = String(currentEmails.length);
  modalTitle.appendChild(titleSpan);
  modalTitle.appendChild(badge);

  modalBody.textContent = "";

  if (currentEmails.length === 0) {
    const empty = document.createElement("div");
    empty.className = "text-center text-body-secondary py-4";
    const emptyIcon = document.createElement("i");
    emptyIcon.className = "fa-solid fa-inbox fs-3 d-block mb-2";
    const emptyText = document.createElement("span");
    emptyText.textContent = i18next.t("No email addresses found.");
    empty.appendChild(emptyIcon);
    empty.appendChild(emptyText);
    modalBody.appendChild(empty);
    if (copyBtn) copyBtn.disabled = true;
    if (clientBtn) {
      clientBtn.removeAttribute("disabled");
      clientBtn.setAttribute("aria-disabled", "true");
      clientBtn.classList.add("disabled");
      clientBtn.title = "";
    }
    return;
  }

  // Recipient list (collapsible)
  const details = document.createElement("details");
  const summary = document.createElement("summary");
  summary.className = "text-body-secondary small mb-2";
  const recipientWord = currentEmails.length === 1 ? i18next.t("recipient") : i18next.t("recipients");
  summary.textContent = `${currentEmails.length} ${recipientWord} — ${i18next.t("click to expand")}`;
  details.appendChild(summary);

  const listWrapper = document.createElement("div");
  listWrapper.style.maxHeight = "200px";
  listWrapper.style.overflowY = "auto";
  listWrapper.className = "mt-2 border rounded p-2 small font-monospace";

  if (Object.keys(byRole).length > 0) {
    for (const [role, roleEmails] of Object.entries(byRole)) {
      const roleHeader = document.createElement("div");
      roleHeader.className = "text-body-secondary fw-semibold mt-2 mb-1";
      roleHeader.textContent = role;
      listWrapper.appendChild(roleHeader);
      for (const email of roleEmails) {
        const line = document.createElement("div");
        line.className = "ps-2";
        line.textContent = email;
        listWrapper.appendChild(line);
      }
    }
  } else {
    for (const email of emails) {
      const line = document.createElement("div");
      line.textContent = email;
      listWrapper.appendChild(line);
    }
  }

  details.appendChild(listWrapper);
  modalBody.appendChild(details);

  // Hint for large lists
  if (currentEmails.length > MAX_MAILTO_RECIPIENTS) {
    const hint = document.createElement("div");
    hint.className = "alert alert-info mt-3 mb-0 small";
    const hintIcon = document.createElement("i");
    hintIcon.className = "fa-solid fa-circle-info me-2";
    hint.appendChild(hintIcon);
    hint.appendChild(
      document.createTextNode(
        i18next.t("This list has {{count}} recipients — too many for a mailto: link. Use Copy Addresses instead.", {
          count: currentEmails.length,
        }),
      ),
    );
    modalBody.appendChild(hint);
  }

  if (copyBtn) copyBtn.disabled = false;
  updateClientButtonHref();
}

function escapeHtml(s: string): string {
  return s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

// ─────────────────────────────────────────────
//  Public API
// ─────────────────────────────────────────────

export function openEmailComposer(options: CRMEmailComposerOptions): void {
  ensureModalExists();
  bccMode = false;
  updateBccToggleAppearance();

  renderRecipients(options.title, options.emails, options.byRole ?? {});
  getModal().show();
}

async function openFromEndpoint(endpoint: string, title: string): Promise<void> {
  ensureModalExists();
  bccMode = false;
  updateBccToggleAppearance();
  renderLoading(title);
  getModal().show();

  try {
    const url = buildAPIUrl(endpoint);
    const res = await fetch(url, { credentials: "same-origin" });
    if (!res.ok) {
      const body = (await res.json().catch(() => ({}))) as { message?: string; error?: string };
      const msg = body.message ?? body.error ?? i18next.t("Request failed ({{status}})", { status: res.status });
      renderError(title, msg);
      return;
    }
    const data = (await res.json()) as EmailListResponse;
    const emails = Array.isArray(data.emails)
      ? data.emails.filter((v): v is string => typeof v === "string" && v.trim() !== "")
      : [];
    const rawByRole = data.byRole && typeof data.byRole === "object" ? data.byRole : {};
    // Use a null-prototype object to prevent prototype-pollution if a role
    // name ever matches special keys like '__proto__' or 'constructor'.
    const safeByRole = Object.create(null) as Record<string, string[]>;
    for (const [role, val] of Object.entries(rawByRole)) {
      if (Array.isArray(val)) {
        safeByRole[role] = val.filter((v): v is string => typeof v === "string");
      }
    }
    renderRecipients(title, emails, safeByRole);
  } catch (err) {
    console.error("[email-composer] fetch failed:", err);
    renderError(title, i18next.t("Failed to load recipients. Please try again."));
  }
}

// ─────────────────────────────────────────────
//  Auto-wire delegated handler for [data-email-composer]
// ─────────────────────────────────────────────

function wireDataAttributes(): void {
  document.addEventListener("click", (e) => {
    if (!(e.target instanceof Element)) return;
    const btn = e.target.closest("[data-email-composer]");
    if (!(btn instanceof HTMLElement)) return;

    const endpoint = btn.dataset.emailEndpoint ?? "";
    const title = btn.dataset.emailTitle ?? i18next.t("Email");

    if (!endpoint) {
      console.warn("[email-composer] missing data-email-endpoint on button", btn);
      return;
    }

    openFromEndpoint(endpoint, title).catch(console.error);
  });
}

// ─────────────────────────────────────────────
//  Init
// ─────────────────────────────────────────────

document.addEventListener("DOMContentLoaded", () => {
  ensureModalExists();
  wireDataAttributes();

  // Expose on window.CRM for legacy callers (GroupView.js etc.)
  window.CRM = window.CRM || {};
  window.CRM.emailComposer = { open: openEmailComposer };
});
