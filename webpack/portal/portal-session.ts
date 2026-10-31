/**
 * Member Portal — what a page does when its session has ended under it.
 *
 * The API answers 401 once the portal session is gone. The remembered-device
 * cookie travels only with /portal page requests, never with /api calls, so
 * reloading the page is what lets that device sign the member back in, or
 * brings them to /portal/login with this page as the way back.
 */

const RELOADED_AT_KEY = "portal-signed-out-reload";
const RELOAD_GUARD_MS = 10000;

/** Staff pages share some API clients with the portal; only a portal page reloads. */
function onPortalPage(): boolean {
  const portalPath = `${window.CRM?.root ?? ""}/portal`;
  const path = window.location.pathname;
  return path === portalPath || path.startsWith(`${portalPath}/`);
}

export function reloadWhenSignedOut(status: number): void {
  if (status !== 401) {
    if (status < 400) {
      forgetReload();
    }
    return;
  }
  if (!onPortalPage()) {
    return;
  }
  // A page that answers 401 again right after its own reload would only reload forever.
  try {
    if (Date.now() - Number(window.sessionStorage.getItem(RELOADED_AT_KEY) ?? 0) < RELOAD_GUARD_MS) {
      return;
    }
    window.sessionStorage.setItem(RELOADED_AT_KEY, String(Date.now()));
  } catch {
    // Without session storage the reload still happens; only the guard is lost.
  }
  window.location.reload();
}

/** An answered call shows the reload worked, so the next ended session reloads again. */
function forgetReload(): void {
  try {
    window.sessionStorage.removeItem(RELOADED_AT_KEY);
  } catch {
    // Without session storage there is no guard to forget.
  }
}
