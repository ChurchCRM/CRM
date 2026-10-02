/**
 * The core event view's Volunteers card (#9713, §3.5): the server renders each occurrence's
 * counts, and this words them the way every V2 screen does (D34).
 */
import { type RollupCounts, rollupStaffing, STAFFING_BADGE_CLASS, staffingTitle } from "./components/staffing-label";

function render(): void {
  for (const row of document.querySelectorAll<HTMLElement>("[data-volunteer-staffing]")) {
    const staffing = rollupStaffing(JSON.parse(row.dataset.volunteerStaffing ?? "{}") as RollupCounts);

    const badge = row.querySelector<HTMLElement>(".volunteer-staffing-badge");
    if (badge) {
      badge.className = `badge ${STAFFING_BADGE_CLASS[staffing.tone]} volunteer-staffing-badge`;
      badge.dataset.tone = staffing.tone;
      badge.title = staffingTitle(staffing);
      badge.textContent = staffing.label;
    }

    const detail = row.querySelector<HTMLElement>(".volunteer-staffing-detail");
    if (detail) {
      detail.textContent = staffing.detail;
    }
  }
}

document.addEventListener("DOMContentLoaded", () => {
  if (window.CRM?.onLocalesReady) {
    window.CRM.onLocalesReady(render);
  } else {
    render();
  }
});
