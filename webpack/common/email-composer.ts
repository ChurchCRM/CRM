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
 * The church default "to" address (sToEmailAddress) is read from a single config,
 * window.CRM.comm.defaultEmailToAddress (set once by Header.php for email-enabled users),
 * and offered as a removable default recipient — pages do not pass it per-button.
 *
 * 2. Programmatic (for JS callers that already hold the recipient list):
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
/** Title count badge — kept as a ref so toggling the default recipient can update it in place */
let countBadge: HTMLElement | null = null;

/** Current resolved email list (member recipients plus the default "to" when included) */
let currentEmails: string[] = [];
/** Member recipients only — excludes the optional church default "to" address */
let baseRecipients: string[] = [];
/**
 * The church default "to" address (sToEmailAddress) offered as a removable recipient.
 * Empty string means "not offered" (unset, no members, or already among the members).
 */
let defaultToAddress = "";
/** Whether the default "to" address is currently included (user can uncheck to drop it) */
let includeDefaultTo = true;
/** Pending timer for copy-feedback reset — stored so it can be cancelled on state transitions */
let copyFeedbackTimer: ReturnType<typeof setTimeout> | null = null;
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
  // Set aria-label via i18next since Bootstrap doesn't localize it automatically.
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
    // Visually-hidden span: screen-reader description for disabled Email Client button
    '<span id="crm-email-client-reason" class="visually-hidden"></span>',
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
    // Match CommunicationUtils.openMailto/openBcc: encode the full CSV string so
    // commas between addresses are percent-encoded (%2C), not left as bare separators.
    const csv = currentEmails.join(",");
    const href = bccMode ? `mailto:?bcc=${encodeURIComponent(csv)}` : `mailto:${encodeURIComponent(csv)}`;
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
  if (copyFeedbackTimer) clearTimeout(copyFeedbackTimer);
  copyFeedbackTimer = setTimeout(() => {
    copyFeedbackTimer = null;
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
    const reason = i18next.t(
      "Too many recipients for email client ({{count}} > {{max}}). Use Copy Addresses instead.",
      { count: currentEmails.length, max: MAX_MAILTO_RECIPIENTS },
    );
    clientBtn.title = reason;
    clientBtn.setAttribute("aria-describedby", "crm-email-client-reason");
    const reasonEl = document.getElementById("crm-email-client-reason");
    if (reasonEl) reasonEl.textContent = reason;
  } else {
    clientBtn.title = "";
    clientBtn.removeAttribute("aria-describedby");
    const reasonEl = document.getElementById("crm-email-client-reason");
    if (reasonEl) reasonEl.textContent = "";
  }
}

function resetClientButton(): void {
  if (!clientBtn) return;
  clientBtn.disabled = false;
  clientBtn.removeAttribute("disabled"); // belt-and-suspenders: clear any setAttribute path too
  clientBtn.removeAttribute("aria-disabled");
  clientBtn.removeAttribute("aria-describedby");
  clientBtn.classList.remove("disabled");
  clientBtn.title = "";
  const reasonEl = document.getElementById("crm-email-client-reason");
  if (reasonEl) reasonEl.textContent = "";
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
  if (copyFeedbackTimer) {
    clearTimeout(copyFeedbackTimer);
    copyFeedbackTimer = null;
  }
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
  if (copyFeedbackTimer) {
    clearTimeout(copyFeedbackTimer);
    copyFeedbackTimer = null;
  }
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

/** Recompute currentEmails from the member list plus the optional default recipient. */
function recomputeCurrentEmails(): void {
  currentEmails =
    defaultToAddress !== "" && includeDefaultTo ? [...baseRecipients, defaultToAddress] : [...baseRecipients];
}

/** Update the title count badge to match the current recipient total. */
function updateCountBadge(): void {
  if (countBadge) countBadge.textContent = String(currentEmails.length);
}

/** Sync the footer action buttons (Copy / Open in client) with the current recipient total. */
function updateActionButtons(): void {
  if (copyBtn) copyBtn.disabled = currentEmails.length === 0;
  updateClientButtonHref();
}

function renderRecipients(
  title: string,
  emails: string[],
  byRole: Record<string, string[]> = {},
  defaultTo = "",
): void {
  if (!modalTitle || !modalBody) return;

  // Member recipients only. When byRole is present, derive the flat list from it so the
  // visible grouped list and the action payload stay in sync.
  const hasRoles = Object.keys(byRole).length > 0;
  baseRecipients = hasRoles ? Object.values(byRole).flat() : [...emails];

  // Offer the church default "to" address (sToEmailAddress) as a removable recipient only
  // when it is configured, there is at least one member recipient, and it is not already
  // present among the members (case-insensitive). The composer — not the backend — owns
  // whether it is actually sent, so the user can uncheck it.
  const trimmedDefault = defaultTo.trim();
  const alreadyPresent =
    trimmedDefault !== "" && baseRecipients.some((e) => e.toLowerCase() === trimmedDefault.toLowerCase());
  defaultToAddress = trimmedDefault !== "" && baseRecipients.length > 0 && !alreadyPresent ? trimmedDefault : "";

  recomputeCurrentEmails();

  // Update title with count badge (kept as a ref so the toggle can update it in place)
  modalTitle.textContent = "";
  const titleSpan = document.createElement("span");
  titleSpan.textContent = title;
  countBadge = document.createElement("span");
  countBadge.className = "badge bg-primary-lt text-primary ms-2";
  countBadge.textContent = String(currentEmails.length);
  modalTitle.appendChild(titleSpan);
  modalTitle.appendChild(countBadge);

  modalBody.textContent = "";

  if (baseRecipients.length === 0) {
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

  // Recipient list (collapsible) — shows member recipients only; the default "to" address
  // is represented by its own checkbox below, not mixed into the role groups.
  const details = document.createElement("details");
  const summary = document.createElement("summary");
  summary.className = "text-body-secondary small mb-2";
  const recipientWord = baseRecipients.length === 1 ? i18next.t("recipient") : i18next.t("recipients");
  summary.textContent = `${baseRecipients.length} ${recipientWord} — ${i18next.t("click to expand")}`;
  details.appendChild(summary);

  const listWrapper = document.createElement("div");
  listWrapper.style.maxHeight = "200px";
  listWrapper.style.overflowY = "auto";
  listWrapper.className = "mt-2 border rounded p-2 small font-monospace";

  if (hasRoles) {
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
    for (const email of baseRecipients) {
      const line = document.createElement("div");
      line.textContent = email;
      listWrapper.appendChild(line);
    }
  }

  details.appendChild(listWrapper);
  modalBody.appendChild(details);

  // Removable default recipient (church sToEmailAddress). Checked by default; unchecking
  // drops it from the badge count and the Copy / Open-in-client payloads.
  if (defaultToAddress !== "") {
    const check = document.createElement("div");
    check.className = "form-check mt-3";
    const input = document.createElement("input");
    input.className = "form-check-input";
    input.type = "checkbox";
    input.id = "crm-email-include-default";
    input.checked = includeDefaultTo;
    const label = document.createElement("label");
    label.className = "form-check-label small";
    label.setAttribute("for", "crm-email-include-default");
    label.textContent = i18next.t("Also send to church address ({{email}})", { email: defaultToAddress });
    input.addEventListener("change", () => {
      includeDefaultTo = input.checked;
      recomputeCurrentEmails();
      updateCountBadge();
      updateActionButtons();
    });
    check.appendChild(input);
    check.appendChild(label);
    modalBody.appendChild(check);
  }

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

  updateActionButtons();
}

function escapeHtml(s: string): string {
  return s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

// ─────────────────────────────────────────────
//  Public API
// ─────────────────────────────────────────────

/**
 * Single source of truth for the church default "to" address (sToEmailAddress).
 * Rendered once into window.CRM.comm by Header.php for email-enabled users; empty
 * string otherwise. The composer offers it as a removable default recipient.
 */
function getConfiguredDefaultTo(): string {
  const v = window.CRM?.comm?.defaultEmailToAddress;
  return typeof v === "string" ? v : "";
}

export function openEmailComposer(options: CRMEmailComposerOptions): void {
  ensureModalExists();
  bccMode = false;
  updateBccToggleAppearance();
  includeDefaultTo = true; // reset: each open starts with the default recipient included

  // Sanitize programmatic inputs the same way the fetch path does
  const sanitizedEmails = (options.emails ?? [])
    .filter((v): v is string => typeof v === "string" && v.trim() !== "")
    .map((v) => v.trim());
  const rawByRole = options.byRole ?? {};
  const sanitizedByRole = Object.create(null) as Record<string, string[]>;
  for (const [role, vals] of Object.entries(rawByRole)) {
    if (Array.isArray(vals)) {
      sanitizedByRole[role] = vals
        .filter((v): v is string => typeof v === "string" && v.trim() !== "")
        .map((v) => v.trim());
    }
  }
  // Callers may override, but the default comes from the single window.CRM.comm config.
  const defaultTo = typeof options.defaultTo === "string" ? options.defaultTo : getConfiguredDefaultTo();

  renderRecipients(options.title, sanitizedEmails, sanitizedByRole, defaultTo);
  getModal().show();
}

async function openFromEndpoint(endpoint: string, title: string): Promise<void> {
  ensureModalExists();
  bccMode = false;
  updateBccToggleAppearance();
  includeDefaultTo = true; // reset: each open starts with the default recipient included
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
      ? data.emails.filter((v): v is string => typeof v === "string" && v.trim() !== "").map((v) => v.trim())
      : [];
    const rawByRole = data.byRole && typeof data.byRole === "object" ? data.byRole : {};
    // Use a null-prototype object to prevent prototype-pollution if a role
    // name ever matches special keys like '__proto__' or 'constructor'.
    const safeByRole = Object.create(null) as Record<string, string[]>;
    for (const [role, val] of Object.entries(rawByRole)) {
      if (Array.isArray(val)) {
        safeByRole[role] = val
          .filter((v): v is string => typeof v === "string" && v.trim() !== "")
          .map((v) => v.trim());
      }
    }
    // The church default address (sToEmailAddress) is a system setting read from the
    // single window.CRM.comm config, not returned by the email-list API.
    renderRecipients(title, emails, safeByRole, getConfiguredDefaultTo());
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
