/**
 * The ministry page's team-only view: Positions · Volunteers · Schedules · Occurrences for the
 * teams of this ministry the team leader leads. A third adapter over `./components/*`, beside
 * `ministry.ts` (the whole ministry) and `../portal/teams.ts` (one team in the portal), calling
 * the same team-scoped endpoints. Ministry-wide controls are not in its markup, and the API
 * refuses them to a team leader regardless.
 */

import {
  addTeamPoolMember,
  createStaffedEvent,
  errorMessage,
  getTeam,
  getTeamQualificationMatrix,
  listTeamSchedules,
  type VolunteerPosition,
  type VolunteerTeam,
} from "./api";
import { createOccurrencesTable, type OccurrencesTableHandle } from "./components/occurrences-table";
import { createPositionsTable, type PositionsTableHandle } from "./components/positions-table";
import { createQualificationMatrix, type QualificationMatrixHandle } from "./components/qualification-matrix";
import { createSchedulesTable, type SchedulesTableHandle } from "./components/schedules-table";
import { byId, renderState } from "./components/ui";

type TabName = "positions" | "volunteers" | "schedules" | "occurrences";

let ministryId = 0;
let ministryName = "";
let sundaySchool = false;
/** The led teams in the order the page offers them; `?team=` puts that one first. */
let teamIds: number[] = [];
let docs: Map<number, { team: VolunteerTeam; positions: VolunteerPosition[] }> | null = null;
let pendingLoad: Promise<void> | null = null;

let positionsTable: PositionsTableHandle;
let matrixGrid: QualificationMatrixHandle;
let occurrencesTable: OccurrencesTableHandle;
let schedulesTable: SchedulesTableHandle;

function teams(): VolunteerTeam[] {
  return teamIds.flatMap((id) => {
    const doc = docs?.get(id);
    return doc ? [doc.team] : [];
  });
}

function positions(): VolunteerPosition[] {
  return teamIds.flatMap((id) => docs?.get(id)?.positions ?? []);
}

async function fetchTeams(): Promise<void> {
  renderState("positions", "loading");
  try {
    const loaded = await Promise.all(teamIds.map((id) => getTeam(id)));
    docs = new Map(loaded.map((doc) => [doc.team.id, doc]));
    positionsTable.render(positions());
  } catch (error) {
    docs = null;
    renderState("positions", "error", errorMessage(error, i18next.t("Could not load this team")));
  }
}

function load(force = false): Promise<void> {
  if (docs !== null && !force) {
    positionsTable.render(positions());

    return Promise.resolve();
  }
  if (pendingLoad !== null && !force) {
    return pendingLoad;
  }

  const request = fetchTeams().finally(() => {
    if (pendingLoad === request) {
      pendingLoad = null;
    }
  });
  pendingLoad = request;

  return request;
}

const ensureTeams = (): Promise<void> => (docs === null ? load() : Promise.resolve());

function buildComponents(): void {
  positionsTable = createPositionsTable({
    ministryId: () => ministryId,
    teams,
    positions,
    reload: () => load(true),
    invalidateMatrix: () => matrixGrid.invalidate(),
  });

  matrixGrid = createQualificationMatrix({
    teams,
    ensureContext: ensureTeams,
    fetch: (teamId) => getTeamQualificationMatrix(teamId ?? teamIds[0]),
    canRemoveVolunteer: () => false,
    teamAdd: true,
    addPoolMember: (personId, positionIds, teamId) => addTeamPoolMember(teamId ?? teamIds[0], personId, positionIds),
    ministryName: () => ministryName,
    reload: () => load(true),
  });

  occurrencesTable = createOccurrencesTable({
    ministryId: () => ministryId,
    fixedTeamId: () => (teamIds.length === 1 ? teamIds[0] : null),
    teams,
    ensureContext: ensureTeams,
    occurrenceUrl: (occurrenceId) => `${window.CRM?.root ?? ""}/ministries/occurrences/${occurrenceId}`,
    positions,
    staffEvent: (payload) => createStaffedEvent(ministryId, payload),
  });

  schedulesTable = createSchedulesTable({
    ministryId: () => ministryId,
    teams,
    positions,
    fetch: () =>
      Promise.all(teamIds.map((id) => listTeamSchedules(id))).then((lists) => ({
        schedules: lists.flatMap((list) => list.schedules),
      })),
    invalidateOccurrences: () => occurrencesTable.invalidate(),
    showOccurrences: (filter) => occurrencesTable.reveal(filter),
    classesAllowed: () => sundaySchool,
  });
}

function activate(tab: TabName): void {
  if (tab === "volunteers") {
    void matrixGrid.load();
  } else if (tab === "schedules") {
    void schedulesTable.load();
  } else if (tab === "occurrences") {
    void occurrencesTable.load();
  } else {
    void load();
  }
}

function wire(): void {
  for (const [navId, tab] of [
    ["nav-item-positions", "positions"],
    ["nav-item-volunteers", "volunteers"],
    ["nav-item-schedules", "schedules"],
    ["nav-item-occurrences", "occurrences"],
  ] as Array<[string, TabName]>) {
    byId(navId)?.addEventListener("shown.bs.tab", () => activate(tab));
  }

  for (const button of document.querySelectorAll(".volunteer-retry")) {
    const pane = button.closest(".tab-pane")?.id;
    button.addEventListener("click", () => {
      if (pane === "volunteers") {
        void matrixGrid.load(true);
      } else if (pane === "occurrences") {
        void occurrencesTable.load(true);
      } else if (pane === "schedules") {
        void schedulesTable.load(true);
      } else {
        void load(true);
      }
    });
  }
}

/** An occurrence page's Back button returns with `?tab=occurrences` and the search it came from. */
function restoreOccurrenceSearch(params: URLSearchParams): void {
  if (params.get("tab") !== "occurrences") {
    return;
  }
  const isDate = (value: string | null): value is string => value !== null && /^\d{4}-\d{2}-\d{2}$/.test(value);
  occurrencesTable.reveal({
    text: params.get("q") ?? "",
    teamId: Number(params.get("team")) || null,
    from: isDate(params.get("from")) ? (params.get("from") as string) : undefined,
    to: isDate(params.get("to")) ? (params.get("to") as string) : "",
  });
}

function init(): void {
  const config: CRMVolunteerTeamViewConfig = window.CRM?.volunteerTeamView ?? {
    ministryId: 0,
    ministryName: "",
    teams: [],
    sundaySchool: false,
  };
  ministryId = config.ministryId;
  ministryName = config.ministryName;
  sundaySchool = config.sundaySchool;
  teamIds = config.teams.map((team) => team.id);
  if (ministryId === 0 || teamIds.length === 0) {
    return;
  }

  const params = new URLSearchParams(window.location.search);
  const preferred = Number(params.get("team"));
  if (teamIds.includes(preferred)) {
    teamIds = [preferred, ...teamIds.filter((id) => id !== preferred)];
  }

  buildComponents();
  wire();
  void load().then(() => restoreOccurrenceSearch(params));
}

document.addEventListener("DOMContentLoaded", () => {
  if (window.CRM?.onLocalesReady) {
    window.CRM.onLocalesReady(init);
  } else {
    init();
  }
});
