/**
 * Member Portal — the one way the portal says something happened.
 *
 * Every notice in the portal, server-queued or raised by a page bundle after a
 * fetch, is a toast in the fixed container the layout renders
 * (`partials/flash.html.twig`, `#portal-toasts`). Fixed is the whole point: a
 * notice must never take part in the document flow, or the page shifts under
 * the member as it appears and again as it goes.
 *
 * It behaves like the admin side's `showGlobalMessage(…, "success")`: top-right,
 * green for success, gone on its own after about five seconds.
 *
 * Argument order follows `window.CRM.notify(message, …)`, the same shape the
 * admin bundles use.
 *
 * This lives in its own module rather than in `portal.ts` because the page
 * bundles (profile, family) are separate webpack entries: importing `portal.ts`
 * would pull its scss and its navigation wiring into each of them.
 */

export type PortalToastType = "success" | "danger" | "warning" | "info";

/** How long a toast stays, matching the admin side's showGlobalMessage. */
const TOAST_DURATION_MS = 5000;

/** Long enough for the fade in `_portal.scss` to finish. */
const FADE_MS = 300;

const CONTAINER_ID = "portal-toasts";

const ICONS: Record<PortalToastType, string> = {
  success: "fa-circle-check",
  danger: "fa-circle-exclamation",
  warning: "fa-triangle-exclamation",
  info: "fa-circle-info",
};

/**
 * Translate a user-visible string through the page's global i18next — the one
 * the layout loads and the locale loader initialises. The English literal is
 * the fallback, so a toast reads correctly before the locale files arrive.
 */
function translate(text: string): string {
  const translated = typeof i18next !== "undefined" ? i18next.t(text) : "";
  return translated || text;
}

/**
 * The container the layout rendered. A theme that overrode the layout and
 * dropped the include leaves us without one, so build a replacement rather than
 * swallow the message — but never in the flow, which is the bug this whole
 * component exists to prevent.
 */
function container(): HTMLElement {
  const existing = document.getElementById(CONTAINER_ID);
  if (existing) {
    return existing;
  }

  const created = document.createElement("div");
  created.id = CONTAINER_ID;
  created.className = "portal-toasts";
  created.setAttribute("role", "status");
  created.setAttribute("aria-live", "polite");
  document.body.append(created);
  return created;
}

/** Take a toast off the page, after its fade where one is wanted. */
function retire(toast: HTMLElement): void {
  if (!toast.isConnected) {
    return;
  }

  const reduceMotion = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false;
  if (reduceMotion) {
    toast.remove();
    return;
  }

  toast.classList.add("portal-flash-leaving");
  window.setTimeout(() => toast.remove(), FADE_MS);
}

/** The dismiss control, which only ever has to take its own toast away. */
function dismissButton(toast: HTMLElement): HTMLButtonElement {
  const button = document.createElement("button");
  button.type = "button";
  button.className = "portal-flash-dismiss";
  button.setAttribute("aria-label", translate("Dismiss"));

  const cross = document.createElement("i");
  cross.className = "fa-solid fa-xmark";
  cross.setAttribute("aria-hidden", "true");
  button.append(cross);

  button.addEventListener("click", () => toast.remove());
  return button;
}

/**
 * Show a message in the portal's toast stack.
 *
 * @param message already translated, and inserted as text — never as markup
 * @param type    success (green), danger, warning or info
 */
export function portalToast(message: string, type: PortalToastType = "info"): HTMLElement {
  const toast = document.createElement("div");
  toast.className = `portal-flash portal-flash-${type}`;
  toast.setAttribute("role", "status");

  const icon = document.createElement("i");
  icon.className = `fa-solid ${ICONS[type] ?? ICONS.info} portal-flash-icon`;
  icon.setAttribute("aria-hidden", "true");

  const text = document.createElement("span");
  text.className = "portal-flash-text";
  text.textContent = message;

  toast.append(icon, text, dismissButton(toast));
  container().append(toast);

  window.setTimeout(() => retire(toast), TOAST_DURATION_MS);

  return toast;
}

/**
 * Give the toasts the server rendered the same behaviour as the ones raised in
 * the browser: a working dismiss button, and a retirement on the same timer.
 */
export function wireRenderedToasts(): void {
  for (const toast of document.querySelectorAll<HTMLElement>(`#${CONTAINER_ID} .portal-flash`)) {
    toast.querySelector(".portal-flash-dismiss")?.addEventListener("click", () => toast.remove());
    window.setTimeout(() => retire(toast), TOAST_DURATION_MS);
  }
}
