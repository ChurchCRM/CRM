/**
 * Member Portal — the small bundle every portal page loads.
 *
 * Deliberately tiny: the portal's chrome is server-rendered Twig, so the only
 * behaviour that belongs here is what needs the browser — opening the collapsed
 * navigation on a phone, the header's account menu, running the toast stack,
 * and filling in the home page's volunteering card. Page-specific bundles
 * (calendar, the two volunteer pages, teams) are separate entries.
 *
 * Strings go through the page's global i18next — the one the layout loads
 * (`skin/external/i18next`) and the one `locale-loader.min.js` actually calls
 * `init()` on, never a bundled copy: importing the npm package here would give
 * this bundle a second, permanently empty instance whose `t()` only ever echoes
 * the key back (#9867). That instance is only populated once the locale loader
 * has finished, so anything user-visible waits for onLocalesReady.
 */
import { ensureCrmHelpers } from "../common/crm-helpers";
import { formatWhat, formatWhen } from "../volunteer/member-ui";
import { type PortalToastType, portalToast, wireRenderedToasts } from "./portal-toast";
import "./portal.scss";

const NAV_ID = "portal-nav";
const NAV_TOGGLE_ID = "portal-nav-toggle";
const VOLUNTEERING_CARD_ID = "portal-volunteering-card";

/** The fields of `/api/volunteer/me/assignments` the home card reads. */
interface PortalHomeAssignment {
  positionName: string | null;
  ministryName: string | null;
  teamName: string | null;
  start: string | null;
  occurrenceDate: string | null;
  status: string;
}

function byId<T extends HTMLElement>(id: string): T | null {
  return document.getElementById(id) as T | null;
}

function show(el: Element | null, visible: boolean): void {
  el?.classList.toggle("d-none", !visible);
}
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
 * The home page's "My volunteering" card (design §5.1): what the member is on
 * for next, and how many of their assignments are still waiting for an answer.
 *
 * Read-only and best-effort. The card is server-rendered with its own empty and
 * loading text, so a failed request simply leaves the member with the link to
 * the volunteering pages — losing the home page over a summary would be the
 * wrong trade.
 */
async function loadVolunteeringCard(): Promise<void> {
  if (!byId(VOLUNTEERING_CARD_ID)) {
    return;
  }

  const loading = byId("portal-volunteering-loading");
  const next = byId("portal-volunteering-next");
  const pending = byId("portal-volunteering-pending");
  const empty = byId("portal-volunteering-empty");

  let assignments: PortalHomeAssignment[] = [];
  try {
    const root = window.CRM?.root ?? "";
    const response = await fetch(`${root}/api/volunteer/me/assignments`, {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    });
    if (!response.ok) {
      throw new Error(String(response.status));
    }
    const body = (await response.json()) as { assignments?: PortalHomeAssignment[] };
    assignments = body.assignments ?? [];
  } catch {
    show(loading, false);

    return;
  }

  show(loading, false);

  // The API returns the member's upcoming commitments soonest first. A slot they
  // have declined, or that was cancelled or covered by somebody else, is not
  // something they are "on for next".
  const live = assignments.filter((row) => row.status === "pending" || row.status === "accepted");
  const pendingCount = assignments.filter((row) => row.status === "pending").length;

  if (live.length === 0) {
    show(empty, true);

    return;
  }

  const soonest = live[0];
  if (next) {
    // Data, not a sentence: the date is formatted in the reader's own locale and
    // the rest is the ministry, team and position as they are named.
    next.textContent = [
      formatWhen(soonest.start, soonest.occurrenceDate),
      formatWhat(soonest.ministryName, soonest.teamName, soonest.positionName),
    ]
      .filter((part) => part !== "")
      .join(" · ");
    show(next, true);
  }

  if (pending && pendingCount > 0) {
    pending.textContent = i18next.t("{{count}} waiting for your answer", { count: pendingCount });
    show(pending, true);
  }
}

/**
 * Anything that renders translated text runs here, once the locale files the
 * layout requested have actually arrived.
 */
function wireLocalisedLabels(): void {
  const toggle = document.getElementById(NAV_TOGGLE_ID);
  toggle?.setAttribute("aria-label", i18next.t("Toggle navigation"));
  void loadVolunteeringCard();
}

function start(): void {
  // `window.CRM.escapeHtml`, `escapeAttribute` and `buildActionMenu` are defined
  // by `skin/js/CRMJSOM.js`, which is an ADMIN-shell script the portal
  // deliberately does not load. Several bundles the portal reuses — the two
  // volunteer member pages, and MP7's team pages — escape and build row menus
  // through them and degrade silently when they are missing, which in the portal
  // would mean no escaping and no row actions at all. Installed here, from the
  // shared module, before any page bundle runs (#9867, #9868).
  ensureCrmHelpers();
  wireNavigationToggle();
  wireAccountMenu();
  publishToastHelper();
  wireRenderedToasts();

  if (typeof window.CRM?.onLocalesReady === "function") {
    window.CRM.onLocalesReady(wireLocalisedLabels);
  } else {
    wireLocalisedLabels();
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", start);
} else {
  start();
}
