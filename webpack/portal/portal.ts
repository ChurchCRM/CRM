/**
 * Member Portal — the small bundle every portal page loads.
 *
 * Deliberately tiny: the portal's chrome is server-rendered Twig, so the only
 * behaviour that belongs here is what needs the browser — opening the collapsed
 * navigation on a phone and dismissing a flash message. Page-specific bundles
 * (calendar, volunteering, teams) are separate entries added by later issues.
 *
 * Strings go through the page's global i18next — the one the layout loads and
 * the locale loader initialises, never a bundled copy — and it is only
 * populated once the locale loader has finished, so anything user-visible
 * waits for onLocalesReady.
 */
import "./portal.scss";

const NAV_ID = "portal-nav";
const NAV_TOGGLE_ID = "portal-nav-toggle";

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
 * Anything that renders translated text runs here, once the locale files the
 * layout requested have actually arrived.
 */
function wireLocalisedLabels(): void {
  const toggle = document.getElementById(NAV_TOGGLE_ID);
  toggle?.setAttribute("aria-label", i18next.t("Toggle navigation"));
}

function start(): void {
  wireNavigationToggle();
  wireFlashDismissal();

  if (typeof window.CRM?.onLocalesReady === "function") {
    window.CRM.onLocalesReady(wireLocalisedLabels);
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", start);
} else {
  start();
}
