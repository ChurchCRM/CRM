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
 * - BCC toggle
 * - "Copy Addresses" — clipboard (works for any list size)
 * - "Send" (server-side via SMTP) when window.CRM.comm.smtpConfigured is true —
 *   expands an inline subject + body form and POSTs to POST /api/email/send
 * - "Open in Email Client" — always available as a fallback (mailto:); limited to
 *   ≤50 recipients due to URL-length constraints in most mail clients
 */

import { buildAPIUrl } from "../api-utils";

/** Response shape from /api/people/emails and /api/cart/emails */
interface EmailListResponse {
  emails?: string[];
  byRole?: Record<string, string[]>;
}

/** Response shape from POST /api/email/send */
interface EmailSendResponse {
  sent?: number;
  failed?: number;
  errors?: string[];
  message?: string;
  error?: string;
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
/** "Send" button — shown only when SMTP is configured */
let sendBtn: HTMLButtonElement | null = null;
/** Title count badge — kept as a ref so toggling the default recipient can update it in place */
let countBadge: HTMLElement | null = null;
/** Hint element showing "too many recipients" alert. Created on first renderRecipients() call per
 * modal open; reset to null on modal close so it is re-created fresh on the next open. */
let tooManyHintEl: HTMLElement | null = null;
/** The scrollable <div> inside the collapsible recipient list — updated by rebuildRecipientList() */
let recipientListWrapperEl: HTMLElement | null = null;
/** The <summary> element inside the collapsible recipient list — updated by updateCountBadge() */
let recipientSummaryEl: HTMLElement | null = null;
/** The inline compose form appended to modal body when "Send" is clicked */
let composeFormEl: HTMLElement | null = null;
/** Subject input inside the compose form */
let subjectInputEl: HTMLInputElement | null = null;
/** Body textarea inside the compose form */
let bodyTextareaEl: HTMLTextAreaElement | null = null;
/** AbortController for an in-flight POST /api/email/send */
let sendController: AbortController | null = null;

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
/** AbortController for the in-flight /api fetch inside openFromEndpoint().
 * Aborted and replaced each time a new endpoint open is requested, preventing
 * a stale response from overwriting fresher modal state. */
let fetchController: AbortController | null = null;
/** Full byRole map stored so role checkboxes can recompute baseRecipients */
let byRoleMap: Record<string, string[]> = {};
/** Set of role names currently checked in the role-filter UI; empty = no filter UI shown */
let activeRoles: Set<string> = new Set();
/** Pending timer for copy-feedback reset — stored so it can be cancelled on state transitions */
let copyFeedbackTimer: ReturnType<typeof setTimeout> | null = null;
/** Whether the BCC toggle is active */
let bccMode = false;
/** Whether the compose form (subject/body) is currently expanded */
let composeFormVisible = false;

// ─────────────────────────────────────────────
//  Helpers
// ─────────────────────────────────────────────

/** Returns true when SMTP is configured and the server can send email. */
function isSmtpConfigured(): boolean {
  return window.CRM?.comm?.smtpConfigured === true;
}

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

function escapeHtml(s: string): string {
  return s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

// ─────────────────────────────────────────────
//  Compose-form helpers
// ─────────────────────────────────────────────

/**
 * Show/hide the inline subject+body compose form appended to modal body.
 * The form is created on first call and reused; the content is cleared on close.
 */
function toggleComposeForm(show: boolean): void {
  if (!modalBody) return;

  composeFormVisible = show;

  // Update the Send button label/icon to reflect expanded/collapsed state
  if (sendBtn) {
    const icon = sendBtn.querySelector("i");
    const label = sendBtn.lastChild;
    if (icon) icon.className = show ? "fa-solid fa-chevron-up me-1" : "fa-solid fa-paper-plane me-1";
    if (label instanceof Text) label.nodeValue = show ? i18next.t("Cancel") : i18next.t("Send");
    sendBtn.classList.toggle("btn-primary", !show);
    sendBtn.classList.toggle("btn-outline-primary", show);
  }

  if (!show) {
    if (composeFormEl) {
      composeFormEl.remove();
      composeFormEl = null;
      subjectInputEl = null;
      bodyTextareaEl = null;
    }
    return;
  }

  // Build the form
  composeFormEl = document.createElement("div");
  composeFormEl.className = "mt-3 border rounded p-3 bg-body-secondary";
  composeFormEl.id = "crm-email-compose-form";

  // Subject field
  const subjectGroup = document.createElement("div");
  subjectGroup.className = "mb-3";
  const subjectLabel = document.createElement("label");
  subjectLabel.className = "form-label fw-semibold";
  subjectLabel.setAttribute("for", "crm-email-subject");
  subjectLabel.textContent = i18next.t("Subject");
  subjectInputEl = document.createElement("input");
  subjectInputEl.type = "text";
  subjectInputEl.id = "crm-email-subject";
  subjectInputEl.className = "form-control";
  subjectInputEl.placeholder = i18next.t("Enter email subject…");
  subjectInputEl.required = true;
  subjectGroup.appendChild(subjectLabel);
  subjectGroup.appendChild(subjectInputEl);
  composeFormEl.appendChild(subjectGroup);

  // Body field
  const bodyGroup = document.createElement("div");
  bodyGroup.className = "mb-3";
  const bodyLabel = document.createElement("label");
  bodyLabel.className = "form-label fw-semibold";
  bodyLabel.setAttribute("for", "crm-email-body");
  bodyLabel.textContent = i18next.t("Message");
  bodyTextareaEl = document.createElement("textarea");
  bodyTextareaEl.id = "crm-email-body";
  bodyTextareaEl.className = "form-control";
  bodyTextareaEl.rows = 6;
  bodyTextareaEl.placeholder = i18next.t("Enter your message…");
  bodyTextareaEl.required = true;
  bodyGroup.appendChild(bodyLabel);
  bodyGroup.appendChild(bodyTextareaEl);
  composeFormEl.appendChild(bodyGroup);

  // Submit button
  const submitRow = document.createElement("div");
  submitRow.className = "d-flex justify-content-end gap-2";
  const submitBtn = makeBtn(
    "crm-email-send-submit",
    "btn btn-primary",
    "fa-solid fa-paper-plane",
    i18next.t("Send Email"),
  );
  submitRow.appendChild(submitBtn);
  composeFormEl.appendChild(submitRow);

  modalBody.appendChild(composeFormEl);

  // Focus the subject field
  subjectInputEl.focus();

  // Handle submit
  submitBtn.addEventListener("click", () => {
    doSendEmail(submitBtn).catch(console.error);
  });
}

/**
 * Execute the server-side send via POST /api/email/send.
 * Shows progress on the submit button, then a success/error banner in the form.
 */
async function doSendEmail(submitBtn: HTMLButtonElement): Promise<void> {
  if (!subjectInputEl || !bodyTextareaEl) return;

  const subject = subjectInputEl.value.trim();
  const body    = bodyTextareaEl.value.trim();

  if (!subject) {
    subjectInputEl.classList.add("is-invalid");
    subjectInputEl.focus();
    return;
  }
  subjectInputEl.classList.remove("is-invalid");

  if (!body) {
    bodyTextareaEl.classList.add("is-invalid");
    bodyTextareaEl.focus();
    return;
  }
  bodyTextareaEl.classList.remove("is-invalid");

  if (currentEmails.length === 0) return;

  // Abort any previous send
  sendController?.abort();
  sendController = new AbortController();

  // Show spinner on submit button
  submitBtn.disabled = true;
  const submitIcon = submitBtn.querySelector("i");
  if (submitIcon) submitIcon.className = "spinner-border spinner-border-sm me-1";
  const submitLabel = submitBtn.lastChild;
  if (submitLabel instanceof Text) submitLabel.nodeValue = i18next.t("Sending…");

  // Remove any previous result banner
  const oldBanner = composeFormEl?.querySelector(".crm-send-result");
  oldBanner?.remove();

  try {
    const url = buildAPIUrl("email/send");
    const res = await fetch(url, {
      method: "POST",
      credentials: "same-origin",
      signal: sendController.signal,
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        recipients: currentEmails,
        subject,
        body,
        bcc: bccMode,
      }),
    });

    const data = (await res.json().catch(() => ({}))) as EmailSendResponse;

    if (res.ok && (data.sent ?? 0) > 0) {
      // ── Success ──────────────────────────────────────────────────── //
      const banner = document.createElement("div");
      banner.className = "alert alert-success crm-send-result mt-2 mb-0";
      const bannerIcon = document.createElement("i");
      bannerIcon.className = "fa-solid fa-circle-check me-2";
      banner.appendChild(bannerIcon);
      banner.appendChild(
        document.createTextNode(
          i18next.t("Email sent to {{count}} recipient(s).", { count: data.sent }),
        ),
      );
      composeFormEl?.appendChild(banner);

      // Disable form so user can't accidentally re-send
      if (subjectInputEl) subjectInputEl.disabled = true;
      if (bodyTextareaEl) bodyTextareaEl.disabled = true;
      submitBtn.disabled = true;
      if (submitIcon) submitIcon.className = "fa-solid fa-check me-1";
      if (submitLabel instanceof Text) submitLabel.nodeValue = i18next.t("Sent");
    } else {
      // ── Failure ──────────────────────────────────────────────────── //
      const errMsg =
        data.message ?? data.error ?? (data.errors?.[0]) ??
        i18next.t("Failed to send email. Please try again or use 'Open in Email Client'.");
      const banner = document.createElement("div");
      banner.className = "alert alert-danger crm-send-result mt-2 mb-0";
      const bannerIcon = document.createElement("i");
      bannerIcon.className = "fa-solid fa-triangle-exclamation me-2";
      banner.appendChild(bannerIcon);
      banner.appendChild(document.createTextNode(errMsg));
      composeFormEl?.appendChild(banner);

      // Re-enable submit so user can retry
      submitBtn.disabled = false;
      if (submitIcon) submitIcon.className = "fa-solid fa-paper-plane me-1";
      if (submitLabel instanceof Text) submitLabel.nodeValue = i18next.t("Send Email");
    }
  } catch (err) {
    if (err instanceof DOMException && err.name === "AbortError") return;
    console.error("[email-composer] send failed:", err);
    const banner = document.createElement("div");
    banner.className = "alert alert-danger crm-send-result mt-2 mb-0";
    const bannerIcon = document.createElement("i");
    bannerIcon.className = "fa-solid fa-triangle-exclamation me-2";
    banner.appendChild(bannerIcon);
    banner.appendChild(
      document.createTextNode(
        i18next.t("Failed to send email. Please try again or use 'Open in Email Client'."),
      ),
    );
    composeFormEl?.appendChild(banner);

    submitBtn.disabled = false;
    if (submitIcon) submitIcon.className = "fa-solid fa-paper-plane me-1";
    if (submitLabel instanceof Text) submitLabel.nodeValue = i18next.t("Send Email");
  } finally {
    sendController = null;
  }
}

// ─────────────────────────────────────────────
//  Modal DOM bootstrap
// ─────────────────────────────────────────────

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

  // ── Footer buttons ────────────────────────────────────────────────
  const footer = document.getElementById("crm-email-composer-footer");

  bccToggle = makeBtn(
    "crm-email-bcc-toggle",
    "btn btn-sm btn-outline-secondary me-auto",
    "fa-solid fa-user-secret",
    i18next.t("BCC Mode"),
  );
  bccToggle.setAttribute("aria-pressed", "false");

  copyBtn = makeBtn(
    "crm-email-copy-btn",
    "btn btn-sm btn-outline-secondary",
    "fa-solid fa-copy",
    i18next.t("Copy Addresses"),
  );
  copyBtn.disabled = true;

  // "Open in Email Client" — always present as a mailto: fallback
  clientBtn = makeBtn(
    "crm-email-client-btn",
    "btn btn-sm btn-outline-secondary",
    "fa-solid fa-envelope",
    i18next.t("Open in Email Client"),
  );
  clientBtn.disabled = true;
  clientBtn.title = i18next.t("Open recipients in your local email application");

  // "Send" button — only shown when SMTP is configured (created unconditionally, visibility controlled)
  sendBtn = makeBtn(
    "crm-email-send-btn",
    "btn btn-sm btn-primary",
    "fa-solid fa-paper-plane",
    i18next.t("Send"),
  );
  sendBtn.disabled = true;

  const closeBtn = document.createElement("button");
  closeBtn.type = "button";
  closeBtn.className = "btn btn-sm btn-secondary";
  closeBtn.setAttribute("data-bs-dismiss", "modal");
  closeBtn.textContent = i18next.t("Close");

  footer?.appendChild(bccToggle);
  footer?.appendChild(copyBtn);
  footer?.appendChild(clientBtn);
  footer?.appendChild(sendBtn);
  footer?.appendChild(closeBtn);

  // ── BCC toggle ────────────────────────────────────────────────────
  bccToggle.addEventListener("click", () => {
    bccMode = !bccMode;
    bccToggle?.setAttribute("aria-pressed", String(bccMode));
    updateBccToggleAppearance();
    updateClientButtonHref();
  });

  // ── Copy handler ──────────────────────────────────────────────────
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

  // ── Open-in-client handler ────────────────────────────────────────
  clientBtn.addEventListener("click", () => {
    if (clientBtn?.getAttribute("aria-disabled") === "true") return;
    if (currentEmails.length === 0) return;
    const csv = currentEmails.join(",");
    const href = bccMode ? `mailto:?bcc=${encodeURIComponent(csv)}` : `mailto:${encodeURIComponent(csv)}`;
    window.open(href, "_blank", "noopener,noreferrer");
  });

  // ── Send button — toggle compose form ────────────────────────────
  sendBtn.addEventListener("click", () => {
    if (sendBtn?.getAttribute("aria-disabled") === "true") return;
    toggleComposeForm(!composeFormVisible);
  });
}

// ─────────────────────────────────────────────
//  Copy-feedback helpers
// ─────────────────────────────────────────────

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

// ─────────────────────────────────────────────
//  Button-state helpers
// ─────────────────────────────────────────────

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
  clientBtn.removeAttribute("disabled");
  const tooMany   = currentEmails.length > MAX_MAILTO_RECIPIENTS;
  const noEmails  = currentEmails.length === 0;
  const unavailable = tooMany || noEmails;

  if (unavailable) {
    clientBtn.setAttribute("aria-disabled", "true");
    clientBtn.classList.add("disabled");
  } else {
    clientBtn.removeAttribute("aria-disabled");
    clientBtn.classList.remove("disabled");
  }

  if (tooMany) {
    const reason = i18next.t(
      "Too many recipients for email client ({{count}} > {{max}}). Use Copy Addresses or Send instead.",
      { count: currentEmails.length, max: MAX_MAILTO_RECIPIENTS },
    );
    clientBtn.title = reason;
    clientBtn.setAttribute("aria-describedby", "crm-email-client-reason");
    const reasonEl = document.getElementById("crm-email-client-reason");
    if (reasonEl) reasonEl.textContent = reason;
  } else {
    clientBtn.title = i18next.t("Open recipients in your local email application");
    clientBtn.removeAttribute("aria-describedby");
    const reasonEl = document.getElementById("crm-email-client-reason");
    if (reasonEl) reasonEl.textContent = "";
  }
}

function resetClientButton(): void {
  if (!clientBtn) return;
  clientBtn.disabled = false;
  clientBtn.removeAttribute("disabled");
  clientBtn.removeAttribute("aria-disabled");
  clientBtn.removeAttribute("aria-describedby");
  clientBtn.classList.remove("disabled");
  clientBtn.title = i18next.t("Open recipients in your local email application");
  const reasonEl = document.getElementById("crm-email-client-reason");
  if (reasonEl) reasonEl.textContent = "";
}

/** Show/hide and enable/disable the Send button depending on SMTP config and recipient state. */
function updateSendButton(): void {
  if (!sendBtn) return;
  const smtp = isSmtpConfigured();
  // Hide the entire button when SMTP is not configured — no point confusing users.
  sendBtn.style.display = smtp ? "" : "none";
  if (!smtp) return;

  const hasRecipients = currentEmails.length > 0;
  if (hasRecipients) {
    sendBtn.removeAttribute("disabled");
    sendBtn.removeAttribute("aria-disabled");
    sendBtn.classList.remove("disabled");
  } else {
    sendBtn.setAttribute("aria-disabled", "true");
    sendBtn.classList.add("disabled");
  }
}

function getModal(): BootstrapModalInstance {
  return window.bootstrap.Modal.getOrCreateInstance(modalEl as Element);
}

// ─────────────────────────────────────────────
//  Render helpers
// ─────────────────────────────────────────────

function renderLoading(title: string): void {
  if (!modalTitle || !modalBody) return;
  currentEmails = [];
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
  if (clientBtn) {
    clientBtn.setAttribute("aria-disabled", "true");
    clientBtn.classList.add("disabled");
  }
  updateSendButton();
}

function renderError(title: string, message: string): void {
  if (!modalTitle || !modalBody) return;
  currentEmails = [];
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
  if (copyBtn) copyBtn.disabled = true;
  resetClientButton();
  if (clientBtn) {
    clientBtn.setAttribute("aria-disabled", "true");
    clientBtn.classList.add("disabled");
  }
  updateSendButton();
}

function recomputeBaseRecipients(): void {
  if (activeRoles.size === 0) {
    baseRecipients = [];
    return;
  }
  const seen = new Set<string>();
  baseRecipients = [];
  for (const role of activeRoles) {
    const roleEmails = byRoleMap[role] ?? [];
    for (const email of roleEmails) {
      const key = email.toLowerCase();
      if (!seen.has(key)) {
        seen.add(key);
        baseRecipients.push(email);
      }
    }
  }
}

function recomputeCurrentEmails(): void {
  currentEmails =
    baseRecipients.length > 0 && defaultToAddress !== "" && includeDefaultTo
      ? [...baseRecipients, defaultToAddress]
      : [...baseRecipients];
}

function updateCountBadge(): void {
  if (countBadge) countBadge.textContent = String(currentEmails.length);
  if (recipientSummaryEl) {
    const word = baseRecipients.length === 1 ? i18next.t("recipient") : i18next.t("recipients");
    recipientSummaryEl.textContent = `${baseRecipients.length} ${word} \u2014 ${i18next.t("click to expand")}`;
  }
}

function rebuildRecipientList(): void {
  if (!recipientListWrapperEl) return;
  recipientListWrapperEl.textContent = "";
  if (activeRoles.size > 0) {
    for (const role of activeRoles) {
      const roleEmails = byRoleMap[role] ?? [];
      if (roleEmails.length === 0) continue;
      const roleHeader = document.createElement("div");
      roleHeader.className = "text-body-secondary fw-semibold mt-2 mb-1";
      roleHeader.textContent = role;
      recipientListWrapperEl.appendChild(roleHeader);
      for (const email of roleEmails) {
        const line = document.createElement("div");
        line.className = "ps-2";
        line.textContent = email;
        recipientListWrapperEl.appendChild(line);
      }
    }
  }
}

function updateActionButtons(): void {
  if (copyBtn) copyBtn.disabled = currentEmails.length === 0;
  updateClientButtonHref();
  updateSendButton();

  // Keep the "too many for mailto" hint in sync.
  if (tooManyHintEl) {
    const tooMany = currentEmails.length > MAX_MAILTO_RECIPIENTS;
    // When SMTP is configured the hint isn't needed — Send handles large lists fine.
    tooManyHintEl.hidden = !tooMany || isSmtpConfigured();
    if (tooMany) {
      const textNode = tooManyHintEl.lastChild;
      if (textNode instanceof Text) {
        textNode.nodeValue = isSmtpConfigured()
          ? ""
          : i18next.t(
              "This list has {{count}} recipients \u2014 too many for a mailto: link. Use Copy Addresses instead.",
              { count: currentEmails.length },
            );
      }
    }
  }
}

function renderRecipients(
  title: string,
  emails: string[],
  byRole: Record<string, string[]> = {},
  defaultTo = "",
): void {
  if (!modalTitle || !modalBody) return;

  byRoleMap = byRole;
  const roleKeys = Object.keys(byRole);
  const hasRoles = roleKeys.length > 0;
  const showRoleFilter = roleKeys.length >= 2;
  activeRoles = showRoleFilter ? new Set(roleKeys) : new Set();

  if (hasRoles) {
    if (showRoleFilter) {
      recomputeBaseRecipients();
    } else {
      const seen = new Set<string>();
      baseRecipients = Object.values(byRole)
        .flat()
        .filter((v) => {
          const key = v.toLowerCase();
          if (seen.has(key)) return false;
          seen.add(key);
          return true;
        });
    }
  } else {
    baseRecipients = [...emails];
  }

  const trimmedDefault = defaultTo.trim();
  const alreadyPresent =
    trimmedDefault !== "" && baseRecipients.some((e) => e.toLowerCase() === trimmedDefault.toLowerCase());
  defaultToAddress = trimmedDefault !== "" && baseRecipients.length > 0 && !alreadyPresent ? trimmedDefault : "";

  recomputeCurrentEmails();

  // Title with count badge
  modalTitle.textContent = "";
  const titleSpan = document.createElement("span");
  titleSpan.textContent = title;
  countBadge = document.createElement("span");
  countBadge.className = "badge bg-primary-lt text-primary ms-2";
  countBadge.textContent = String(currentEmails.length);
  modalTitle.appendChild(titleSpan);
  modalTitle.appendChild(countBadge);

  modalBody.textContent = "";

  // Role filter checkboxes (≥2 roles)
  if (showRoleFilter) {
    const filterSection = document.createElement("div");
    filterSection.className = "mb-3";
    const filterLabel = document.createElement("div");
    filterLabel.className = "text-body-secondary small fw-semibold mb-2";
    filterLabel.textContent = `${i18next.t("Roles to include")}:`;
    filterSection.appendChild(filterLabel);
    const filterRow = document.createElement("div");
    filterRow.className = "d-flex flex-wrap gap-2";
    for (const role of roleKeys) {
      const roleEmails = byRoleMap[role] ?? [];
      const check = document.createElement("div");
      check.className = "form-check form-check-inline mb-0";
      const cbId = `crm-role-cb-${role.toLowerCase().replace(/[^a-z0-9]/g, "-")}`;
      const cb = document.createElement("input");
      cb.type = "checkbox";
      cb.className = "form-check-input";
      cb.id = cbId;
      cb.checked = true;
      cb.addEventListener("change", () => {
        if (cb.checked) {
          activeRoles.add(role);
        } else {
          activeRoles.delete(role);
        }
        recomputeBaseRecipients();
        recomputeCurrentEmails();
        updateCountBadge();
        updateActionButtons();
        rebuildRecipientList();
      });
      const lbl = document.createElement("label");
      lbl.className = "form-check-label small";
      lbl.setAttribute("for", cbId);
      lbl.textContent = `${role} (${roleEmails.length})`;
      check.appendChild(cb);
      check.appendChild(lbl);
      filterRow.appendChild(check);
    }
    filterSection.appendChild(filterRow);
    modalBody.appendChild(filterSection);
  }

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
    updateSendButton();
    return;
  }

  // Collapsible recipient list (member emails only)
  const details = document.createElement("details");
  const summary = document.createElement("summary");
  summary.className = "text-body-secondary small mb-2";
  const recipientWord = baseRecipients.length === 1 ? i18next.t("recipient") : i18next.t("recipients");
  summary.textContent = `${baseRecipients.length} ${recipientWord} — ${i18next.t("click to expand")}`;
  recipientSummaryEl = summary;
  details.appendChild(summary);

  const listWrapper = document.createElement("div");
  listWrapper.style.maxHeight = "200px";
  listWrapper.style.overflowY = "auto";
  listWrapper.className = "mt-2 border rounded p-2 small font-monospace";
  recipientListWrapperEl = listWrapper;

  if (hasRoles) {
    const rolesToRender = showRoleFilter ? activeRoles : new Set(Object.keys(byRole));
    for (const role of rolesToRender) {
      const roleEmails = byRoleMap[role] ?? [];
      if (roleEmails.length === 0) continue;
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

  // Removable default recipient (church sToEmailAddress)
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

  // "Too many for mailto" hint (shown only when SMTP is NOT configured)
  if (!tooManyHintEl) {
    tooManyHintEl = document.createElement("div");
    tooManyHintEl.className = "alert alert-info mt-3 mb-0 small";
    const hintIcon = document.createElement("i");
    hintIcon.className = "fa-solid fa-circle-info me-2";
    tooManyHintEl.appendChild(hintIcon);
    tooManyHintEl.appendChild(document.createTextNode(""));
  }
  const tooManyNow = currentEmails.length > MAX_MAILTO_RECIPIENTS;
  tooManyHintEl.hidden = !tooManyNow || isSmtpConfigured();
  const tooManyText = tooManyHintEl.lastChild;
  if (tooManyText instanceof Text) {
    tooManyText.nodeValue =
      tooManyNow && !isSmtpConfigured()
        ? i18next.t(
            "This list has {{count}} recipients — too many for a mailto: link. Use Copy Addresses instead.",
            { count: currentEmails.length },
          )
        : "";
  }
  modalBody.appendChild(tooManyHintEl);

  updateActionButtons();
}

// ─────────────────────────────────────────────
//  Public API
// ─────────────────────────────────────────────

function getConfiguredDefaultTo(): string {
  const v = window.CRM?.comm?.defaultEmailToAddress;
  return typeof v === "string" ? v : "";
}

export function openEmailComposer(options: CRMEmailComposerOptions): void {
  ensureModalExists();
  fetchController?.abort();
  fetchController = null;
  bccMode = false;
  updateBccToggleAppearance();
  includeDefaultTo = true;

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
  const defaultTo = typeof options.defaultTo === "string" ? options.defaultTo : getConfiguredDefaultTo();

  renderRecipients(options.title, sanitizedEmails, sanitizedByRole, defaultTo);
  getModal().show();
}

async function openFromEndpoint(endpoint: string, title: string): Promise<void> {
  ensureModalExists();
  if (fetchController) fetchController.abort();
  fetchController = new AbortController();
  const signal = fetchController.signal;

  bccMode = false;
  updateBccToggleAppearance();
  includeDefaultTo = true;
  renderLoading(title);
  getModal().show();

  try {
    const url = buildAPIUrl(endpoint);
    const res = await fetch(url, { credentials: "same-origin", signal });
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
    const safeByRole = Object.create(null) as Record<string, string[]>;
    for (const [role, val] of Object.entries(rawByRole)) {
      if (Array.isArray(val)) {
        safeByRole[role] = val
          .filter((v): v is string => typeof v === "string" && v.trim() !== "")
          .map((v) => v.trim());
      }
    }
    renderRecipients(title, emails, safeByRole, getConfiguredDefaultTo());
  } catch (err) {
    if (err instanceof DOMException && err.name === "AbortError") return;
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

  if (modalEl) {
    modalEl.addEventListener("hidden.bs.modal", () => {
      fetchController?.abort();
      fetchController = null;
      sendController?.abort();
      sendController = null;
      if (copyFeedbackTimer) {
        clearTimeout(copyFeedbackTimer);
        copyFeedbackTimer = null;
      }
      // Collapse the compose form without triggering its toggle animation
      if (composeFormEl) {
        composeFormEl.remove();
        composeFormEl = null;
        subjectInputEl = null;
        bodyTextareaEl = null;
      }
      composeFormVisible = false;
      // Reset Send button appearance
      if (sendBtn) {
        const icon = sendBtn.querySelector("i");
        if (icon) icon.className = "fa-solid fa-paper-plane me-1";
        const label = sendBtn.lastChild;
        if (label instanceof Text) label.nodeValue = i18next.t("Send");
        sendBtn.classList.replace("btn-outline-primary", "btn-primary");
      }
      tooManyHintEl = null;
      recipientListWrapperEl = null;
      recipientSummaryEl = null;
      byRoleMap = {};
      activeRoles = new Set();
      if (bccToggle) bccToggle.setAttribute("aria-pressed", "false");
    });
  }

  window.CRM = window.CRM || {};
  window.CRM.emailComposer = { open: openEmailComposer };
});
