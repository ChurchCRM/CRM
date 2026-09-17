/**
 * Member Portal — the small bundle every portal page loads.
 *
 * Deliberately tiny: the portal's chrome is server-rendered Twig, so the only
 * behaviour that belongs here is what needs the browser — opening the collapsed
 * navigation on a phone, the header's account menu, and running the toast
 * stack. Page-specific bundles (calendar, volunteering, teams) are separate
 * entries added by later issues.
 *
 * Strings go through the page's global i18next — the one the layout loads and
 * the locale loader initialises, never a bundled copy — and it is only
 * populated once the locale loader has finished, so anything user-visible
 * waits for onLocalesReady.
 */
import { type PortalToastType, portalToast, wireRenderedToasts } from "./portal-toast";
import "./portal.scss";

const NAV_ID = "portal-nav";
const NAV_TOGGLE_ID = "portal-nav-toggle";
const ACCOUNT_ID = "portal-account";
const ACCOUNT_TOGGLE_ID = "portal-account-toggle";
const ACCOUNT_MENU_ID = "portal-account-menu";

/**
 * The header's hamburger opens and closes the navigation on small screens.
 * From the tablet breakpoint up the CSS keeps the nav open and hides the
 * button, so this listener simply never fires there.
 */
function wireNavigationToggle(): void {
  const toggle = document.getElementById(NAV_TOGGLE_ID);
  const nav = document.getElementById(NAV_ID);
  if (!toggle || !nav) {
    return;
  }

  toggle.addEventListener("click", () => {
    const isOpen = nav.classList.toggle("is-open");
    toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
  });
}

/**
 * The header's "Hello <first name>" button and the menu it opens.
 *
 * Follows the WAI-ARIA menu-button pattern, which is what a screen reader and a
 * keyboard user expect from something that says `aria-haspopup="menu"`: the
 * button opens the menu and moves focus to its first item, the arrow keys walk
 * the items, Escape closes and hands focus back, and a click or a focus that
 * lands anywhere else closes it too.
 *
 * Hand-rolled rather than Bootstrap's dropdown: the portal's chrome is its own
 * and a church theme restyles it with the portal tokens, not with Bootstrap's.
 */
function wireAccountMenu(): void {
  const container = document.getElementById(ACCOUNT_ID);
  const toggle = document.getElementById(ACCOUNT_TOGGLE_ID);
  const menu = document.getElementById(ACCOUNT_MENU_ID);
  if (!container || !toggle || !menu) {
    return;
  }

  const items = (): HTMLElement[] => Array.from(menu.querySelectorAll<HTMLElement>('[role="menuitem"]'));

  const isOpen = (): boolean => !menu.hidden;

  const open = (focusFirst: boolean): void => {
    menu.hidden = false;
    toggle.setAttribute("aria-expanded", "true");
    if (focusFirst) {
      items()[0]?.focus();
    }
  };

  const close = (focusToggle: boolean): void => {
    if (!isOpen()) {
      return;
    }
    menu.hidden = true;
    toggle.setAttribute("aria-expanded", "false");
    if (focusToggle) {
      toggle.focus();
    }
  };

  toggle.addEventListener("click", () => {
    if (isOpen()) {
      close(false);
    } else {
      open(true);
    }
  });

  // Down from the button opens the menu on the first item, up on the last —
  // the pattern's two keyboard shortcuts into a closed menu.
  toggle.addEventListener("keydown", (event: KeyboardEvent) => {
    if (event.key === "ArrowDown" || event.key === "ArrowUp") {
      event.preventDefault();
      open(false);
      const entries = items();
      (event.key === "ArrowDown" ? entries[0] : entries[entries.length - 1])?.focus();
      return;
    }
    if (event.key === "Escape") {
      close(false);
    }
  });

  menu.addEventListener("keydown", (event: KeyboardEvent) => {
    const entries = items();
    const current = entries.indexOf(document.activeElement as HTMLElement);

    switch (event.key) {
      case "Escape":
        event.preventDefault();
        close(true);
        break;
      case "ArrowDown":
        event.preventDefault();
        entries[(current + 1) % entries.length]?.focus();
        break;
      case "ArrowUp":
        event.preventDefault();
        entries[(current - 1 + entries.length) % entries.length]?.focus();
        break;
      case "Home":
        event.preventDefault();
        entries[0]?.focus();
        break;
      case "End":
        event.preventDefault();
        entries[entries.length - 1]?.focus();
        break;
      default:
        break;
    }
  });

  // A click anywhere outside — the nav toggle included — closes the menu.
  document.addEventListener("click", (event: MouseEvent) => {
    if (isOpen() && !container.contains(event.target as Node)) {
      close(false);
    }
  });

  // Tabbing out of the menu closes it, without stealing the focus back from
  // wherever the member was heading. `relatedTarget` is where focus is going;
  // it is null when focus leaves the document entirely, which is not a reason
  // to close.
  container.addEventListener("focusout", (event: FocusEvent) => {
    const next = event.relatedTarget as Node | null;
    if (next !== null && !container.contains(next)) {
      close(false);
    }
  });
}

/**
 * One way for anything on a portal page — a page bundle, a plugin, a theme's
 * `theme.js` — to say something happened, without knowing how the portal draws
 * a notice.
 */
function publishToastHelper(): void {
  window.CRM = window.CRM || {};
  window.CRM.portalToast = (message: string, type: PortalToastType = "info") => {
    portalToast(message, type);
  };
}

/**
 * Anything that renders translated text runs here, once the locale files the
 * layout requested have actually arrived.
 */
function wireLocalisedLabels(): void {
  const toggle = document.getElementById(NAV_TOGGLE_ID);
  toggle?.setAttribute("aria-label", i18next.t("Toggle navigation"));
}

function start(): void {
  wireNavigationToggle();
  wireAccountMenu();
  publishToastHelper();
  wireRenderedToasts();

  if (typeof window.CRM?.onLocalesReady === "function") {
    window.CRM.onLocalesReady(wireLocalisedLabels);
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", start);
} else {
  start();
}
