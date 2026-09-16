/**
 * Member Portal — the small bundle every portal page loads.
 *
 * Deliberately tiny: the portal's chrome is server-rendered Twig, so the only
 * behaviour that belongs here is what needs the browser — opening the collapsed
 * navigation on a phone, dismissing a flash message, and filling in the home
 * page's volunteering card. Page-specific bundles (the two volunteer pages, and
 * calendar and teams later) are separate entries.
 *
 * Strings go through i18next, and i18next is only populated once the locale
 * loader has finished, so anything user-visible waits for onLocalesReady.
 *
 * `i18next` is the GLOBAL the layout loads (`skin/external/i18next`), the one
 * `locale-loader.min.js` actually calls `init()` on — importing the npm package
 * here would give this bundle a second, permanently empty instance whose `t()`
 * only ever echoes the key back (#9867).
 */
import { formatWhat, formatWhen } from "../volunteer/member-ui";

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
 * Flash messages are one-shot: the server has already forgotten them, so
 * dismissing one only has to remove it from the page.
 */
function wireFlashDismissal(): void {
  for (const button of document.querySelectorAll<HTMLElement>(".portal-flash-dismiss")) {
    button.addEventListener("click", () => {
      button.closest(".portal-flash")?.remove();
    });
  }
}

/**
 * `window.CRM.escapeHtml` is defined by `skin/js/CRMJSOM.js`, which is an
 * ADMIN-shell script the portal deliberately does not load. Several bundles the
 * portal reuses — the two volunteer pages among them — escape through it and
 * fall back to the raw string when it is missing, which in the portal would mean
 * no escaping at all. Define it here, with the same implementation, before any
 * page bundle runs (#9867).
 */
function ensureEscapeHtml(): void {
  window.CRM = window.CRM || {};
  const crm = window.CRM;
  if (typeof crm.escapeHtml === "function") {
    return;
  }

  crm.escapeHtml = (text: string): string => {
    if (text === null || text === undefined) {
      return "";
    }
    const div = document.createElement("div");
    div.textContent = String(text);

    return div.innerHTML;
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
  ensureEscapeHtml();
  wireNavigationToggle();
  wireFlashDismissal();

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
