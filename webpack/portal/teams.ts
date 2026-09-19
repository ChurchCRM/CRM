/**
 * Member Portal — one team's page (MP7, #9868; Member Portal design §5.5).
 *
 * Four tabs — **Positions · Volunteers · Schedules · Dates** — narrowed to the one
 * team the page is about, and drawn by the SAME components the admin ministry page
 * draws (`webpack/ministries/components/*`). There is no second implementation of
 * the qualification grid, the positions table, the schedule dialog or the
 * occurrence list anywhere in this file; what is here is the CONTEXT those
 * components are missing — which team, which ministry, which endpoints, and where
 * an occurrence link points.
 *
 * The contrast with `ministry.ts` is the whole design:
 *
 *   ministry.ts  teams() = every team of the ministry      → selects, "All Teams"
 *   teams.ts     teams() = the one team                    → no selects at all
 *
 * and everything else follows from that. The controls a team leader must not have
 * — Add Volunteer, Add from Cart, Remove Volunteer, team rename and delete, the
 * Teams card, Ministry Coordinators, Help Wanted — are simply not in
 * `teams/team.html.twig`, and the components look their controls up by id, so the
 * wiring for them is inert. That is deliberate: hiding a control the API would
 * refuse anyway is a UI convenience, and the refusal is still the server's (D5).
 *
 * Endpoints, all of them team-gated so a team leader may call them (§4.4):
 *
 *   GET  /api/ministries/teams/{id}                       team + its positions
 *   GET  /api/ministries/teams/{id}/qualification-matrix  the grid
 *   GET  /api/ministries/teams/{id}/schedules             the schedules
 *   GET  /api/ministries/occurrences?teamId=…             the dates
 *
 * and the writes go to the position, schedule, qualification and occurrence routes
 * the admin page uses, each of which authorizes its own record.
 *
 * Every user-visible string is `i18next.t()` — in the components, and in this file
 * where it has one of its own. The extractor scans `webpack/**` and never a Twig
 * template, so a string that belongs to the browser must live here (§5.10, F31).
 */

import { ensureCrmHelpers } from "../common/crm-helpers";
import {
  createOneOffOccurrence,
  errorMessage,
  getTeam,
  getTeamQualificationMatrix,
  listTeamSchedules,
  type VolunteerPosition,
  type VolunteerTeam,
} from "../ministries/api";
import { createOccurrencesTable, type OccurrencesTableHandle } from "../ministries/components/occurrences-table";
import { createPositionsTable, type PositionsTableHandle } from "../ministries/components/positions-table";
import {
  createQualificationMatrix,
  type QualificationMatrixHandle,
} from "../ministries/components/qualification-matrix";
import { createSchedulesTable, type SchedulesTableHandle } from "../ministries/components/schedules-table";
import { byId, renderState, wireUnclippedRowMenus } from "../ministries/components/ui";

/** The page config `teams/team.html.twig` writes in its inline script. */
interface PortalTeamConfig {
  teamId: number;
  ministryId: number;
}

type TabName = "positions" | "volunteers" | "schedules" | "occurrences";

let teamId = 0;
let ministryId = 0;
/**
 * The team document — the team row and its positions, in one response. Cached
 * exactly as `ministry.ts` caches its ministry document, and cleared on error so
 * Retry is a genuine retry (§5.8).
 */
let team: { team: VolunteerTeam; positions: VolunteerPosition[] } | null = null;

let positionsTable: PositionsTableHandle;
let matrixGrid: QualificationMatrixHandle;
let occurrencesTable: OccurrencesTableHandle;
let schedulesTable: SchedulesTableHandle;

/**
 * The team list every select on this page is offered — which is the one team.
 *
 * This is what makes the shared components correct here without a single
 * conditional inside them: the qualification grid picks "the first team", the
 * position dialog defaults to "the first team", the schedule dialog defaults to
 * "the first team", and on this page all three are the same, only, right answer.
 */
function teams(): VolunteerTeam[] {
  return team === null ? [] : [team.team];
}

function positions(): VolunteerPosition[] {
  return team?.positions ?? [];
}

async function load(force = false): Promise<void> {
  if (team !== null && !force) {
    positionsTable.render(team.positions);

    return;
  }

  renderState("positions", "loading");

  try {
    team = await getTeam(teamId);
    positionsTable.render(team.positions);
  } catch (error) {
    team = null;
    renderState("positions", "error", errorMessage(error, i18next.t("Could not load this team")));
  }
}

const ensureTeam = (): Promise<void> => (team === null ? load() : Promise.resolve());

function buildComponents(): void {
  positionsTable = createPositionsTable({
    // A position is created under the ministry route, which is deliberately not
    // ministry-gated: the payload names the team and the service authorizes on
    // that, which is what lets a team leader add one (§4.6).
    ministryId: () => ministryId,
    teams,
    positions,
    reload: () => load(true),
    invalidateMatrix: () => matrixGrid.invalidate(),
  });

  matrixGrid = createQualificationMatrix({
    teams,
    ensureContext: ensureTeam,
    fetch: () => getTeamQualificationMatrix(teamId),
    // Never: "Remove Volunteer" ends someone's membership of the MINISTRY's pool,
    // which the API refuses a team leader (D5). The row menu is not offered, and
    // the template carries no `removeVolunteer` callback for it either.
    canRemoveVolunteer: () => false,
    reload: () => load(true),
  });

  occurrencesTable = createOccurrencesTable({
    // The team pins the query; the ministry would only widen it.
    ministryId: () => 0,
    fixedTeamId: () => teamId,
    teams,
    ensureContext: ensureTeam,
    occurrenceUrl: (occurrenceId) => `${window.CRM?.root ?? ""}/portal/teams/${teamId}/occurrences/${occurrenceId}`,
    positions,
    addOneOff: (payload) => createOneOffOccurrence(ministryId, payload),
  });

  schedulesTable = createSchedulesTable({
    ministryId: () => ministryId,
    teams,
    positions,
    fetch: () => listTeamSchedules(teamId),
    invalidateOccurrences: () => occurrencesTable.invalidate(),
  });
}

function activate(tab: TabName): void {
  if (tab === "volunteers") {
    void matrixGrid.load();

    return;
  }

  if (tab === "schedules") {
    void schedulesTable.load();

    return;
  }

  if (tab === "occurrences") {
    void occurrencesTable.load();

    return;
  }

  void load();
}

function wire(): void {
  // Every table here sits in a `.volunteer-scroll-x` wrapper so a narrow phone can
  // scroll it sideways — and that same overflow clips an open row menu at the
  // wrapper's edge (review, 2026-09-18: the Positions menu was cut off). The
  // open menu is re-anchored with `position: fixed`, exactly as the volunteers
  // grid already does for itself; see table-action-menu.md.
  wireUnclippedRowMenus("positions-table-wrapper");
  wireUnclippedRowMenus("schedules-table-wrapper");
  wireUnclippedRowMenus("occurrences-table-wrapper");

  for (const [navId, tab] of [
    ["nav-item-positions", "positions"],
    ["nav-item-volunteers", "volunteers"],
    ["nav-item-schedules", "schedules"],
    ["nav-item-occurrences", "occurrences"],
  ] as Array<[string, TabName]>) {
    byId(navId)?.addEventListener("shown.bs.tab", () => activate(tab));
  }

  // Retry re-runs the load that failed, per pane (§5.8) — the tab a Retry button
  // sits in is what says which load that was.
  for (const button of document.querySelectorAll(".volunteer-retry")) {
    const inMatrix = button.closest("#volunteers") !== null;
    const inOccurrences = button.closest("#occurrences") !== null;
    const inSchedules = button.closest("#schedules") !== null;
    button.addEventListener("click", () => {
      if (inMatrix) {
        void matrixGrid.load(true);
      } else if (inOccurrences) {
        void occurrencesTable.load(true);
      } else if (inSchedules) {
        void schedulesTable.load(true);
      } else {
        void load(true);
      }
    });
  }
}

function init(): void {
  const config = (window.CRM?.portalTeam ?? { teamId: 0, ministryId: 0 }) as PortalTeamConfig;
  teamId = config.teamId;
  ministryId = config.ministryId;

  if (teamId === 0) {
    return;
  }

  // The portal loads no part of the admin shell, so the escaping and row-menu
  // helpers the shared components use have to be installed. `portal.min.js` does
  // it too and both are idempotent; doing it here as well means this bundle is
  // correct even if a theme drops the portal script.
  ensureCrmHelpers();

  buildComponents();
  wire();
  void load();
}

// A module-scope i18next.t() returns undefined on a non-en_US locale until the
// catalogue has loaded (upstream #9609), so initialisation is deferred behind
// onLocalesReady — every string is built at render time, inside init()'s reach.
document.addEventListener("DOMContentLoaded", () => {
  if (window.CRM?.onLocalesReady) {
    window.CRM.onLocalesReady(init);
  } else {
    init();
  }
});
