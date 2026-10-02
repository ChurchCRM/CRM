/**
 * S3 — ministry detail (#9715 and #9707, design §5.4 as amended).
 *
 * Seven tabs: **Overview · Positions · Volunteers · Schedules · Occurrences ·
 * Calendar · Help Wanted**. Overview (its three counts, its description and its teams card) and
 * Positions are rendered from ONE `GET /api/ministries/ministries/{id}` response
 * that is fetched on first use and cached; Volunteers is one
 * `GET .../qualification-matrix` because it has its own `?teamId=` filter. Both
 * obey the same rule, which is the point §5.4 actually makes: the grid must
 * handle 15–200 people "without re-fetching per cell", so the whole thing —
 * people, positions and every tick — arrives in a single document and a checkbox
 * writes exactly one row.
 *
 * The volunteer-pool panel is gone. The V2 pool endpoints and the Groups module
 * still own the roster; this page no longer shows it, qualifying somebody still
 * brings them into the pool, and "Remove Volunteer" on the Volunteers tab is the
 * one place membership is ended from here.
 *
 * **Since #9868 this file is an ADAPTER.** The Positions table, the qualification
 * grid, the Schedules table and the Occurrences list live in
 * `./components/*`, because the Member Portal's My Teams page (MP7) shows the
 * same four things narrowed to one team. Every one of them was moved verbatim and
 * is handed a context object saying which ministry it is about, which teams its
 * selects may offer and where an occurrence link points — so this page behaves
 * exactly as it did, which its own Cypress specs are the proof of. What is left
 * here is what is genuinely about a MINISTRY: the overview counts, the teams card
 * and its dialog, Help wanted, and the coordinator/team-leader card.
 *
 * Adding a tab is: one `<li>` and one `.tab-pane` in the view, one entry in
 * `TAB_RENDERERS` (or one branch in `activate()`) here. Nothing else moves.
 *
 * Every state §5.8 requires is driven by `renderState()`: the loading block is
 * re-shown at the start of **every** attempt, the error block carries a Retry that
 * genuinely re-runs the load, and the empty state is a first-class Tabler `.empty`
 * rather than a blank table. Toasts use `window.CRM.notify` with `"danger"` —
 * never `"error"`, which renders blue (U5/E-7).
 *
 * Every user-visible string is `i18next.t()` in this file: the extractor scans
 * only `webpack/**` and `src/skin/js/**`, so the same call inside the .php view
 * would never be translated (design §5.10, F31).
 */

import { attachToModal } from "../common/person-select";
import {
  addPoolMember,
  addPoolMembersFromCart,
  createStaffedEvent,
  createTeam,
  deleteMinistry,
  deleteTeam,
  errorMessage,
  getMinistry,
  getQualificationMatrix,
  getTeamClassEvents,
  grantScope,
  type LinkableClass,
  listLinkableClasses,
  listSchedules,
  type MinistryDetail,
  notifyError,
  notifySuccess,
  removeVolunteerFromMinistry,
  revokeScope,
  type TeamClassEvents,
  type TeamClassLink,
  updateMinistry,
  updateTeam,
  type VolunteerPosition,
  type VolunteerTeam,
  type VolunteerTeamLeader,
} from "./api";
import { createMinistryEventsTab, type MinistryEventsHandle } from "./components/ministry-events";
import { createOccurrencesTable, type OccurrencesTableHandle } from "./components/occurrences-table";
import { createPositionsTable, type PositionsTableHandle } from "./components/positions-table";
import { createQualificationMatrix, type QualificationMatrixHandle } from "./components/qualification-matrix";
import {
  createSchedulesTable,
  type MinistryEventPrefill,
  type SchedulesTableHandle,
} from "./components/schedules-table";
import {
  actionMenu,
  byId,
  confirmDelete,
  destroyDataTable,
  escapeHtml,
  hideModal,
  initDataTable,
  modal,
  renderState,
  show,
  showModalError,
  statusBadge,
  tText,
  wireModalFadeGuard,
} from "./components/ui";
import { initVolunteerScopes } from "./scopes";

interface MinistryConfig {
  ministryId: number;
  isManager: boolean;
  /**
   * Advisory: "the server believes you coordinate this ministry or better". It
   * decides whether "Remove Volunteer" is OFFERED and nothing else — the API
   * authorizes independently with the ministry-level entity middleware (D5).
   */
  isMinistryCoordinator: boolean;
  /** Where a team's linked class opens (`…/{groupId}`), or null when the viewer cannot open groups. */
  classUrlBase: string | null;
}

/**
 * Panes rendered from the cached ministry document. The teams card lives on
 * Overview and keeps its own `teams-*` state block, so it is a pane name here
 * even though it is no longer a tab.
 */
type PaneName = "overview" | "teams" | "positions";
/**
 * Every tab in the strip. The volunteer grid and the occurrence list each have
 * their own fetch — a different document with a different filter — so they carry
 * their own names and load themselves rather than riding on the ministry detail.
 */
type TabName = "overview" | "positions" | "volunteers" | "occurrences" | "schedules" | "calendar" | "help-wanted";

let ministryId = 0;
let isManager = false;
/** Advisory only — see `MinistryConfig.isMinistryCoordinator`. */
let isMinistryCoordinator = false;
let classUrlBase: string | null = null;
let detail: MinistryDetail | null = null;
/**
 * The leader grants the open team dialog started with.
 *
 * Normally none or one — the UI treats a team as having at most one leader — but
 * the scope table carries no uniqueness constraint, so every row the API reported
 * is held and every one of them is revoked when the field is cleared. Comparing
 * this against what the picker ends up holding is what decides whether saving the
 * team also has to grant or revoke a scope.
 */
let teamLeadersOnOpen: VolunteerTeamLeader[] = [];
/** Which record a modal is editing; 0 means "new". */
let editingTeamId = 0;
/** The class the open team dialog started with (D23); 0 means none. */
let teamClassOnOpen = 0;
/** The classes the open team dialog offers, with their teacher counts. */
let linkableClasses: LinkableClass[] = [];
/** D28: the ministry's own events of the class the open team dialog started with. */
let teamClassEvents: TeamClassEvents | null = null;

// ─── The shared components, given this page's ministry-wide context ──────────
//
// Built inside `init()`, never at module scope: each factory wires its own
// listeners and (for the grid) attaches a person picker to a dialog, so it must
// not run before the document is ready — exactly as this page's wiring always
// has. The handles are the four caches the tabs are drawn from.

const teams = (): VolunteerTeam[] => detail?.teams ?? [];
const positions = (): VolunteerPosition[] => detail?.positions ?? [];
/** D29: may this ministry's teams, schedules and events use a Sunday School class. */
const sundaySchool = (): boolean => detail?.ministry.sundaySchool ?? false;
const ensureDetail = (): Promise<void> => (detail === null ? load() : Promise.resolve());

let positionsTable: PositionsTableHandle;
let matrixGrid: QualificationMatrixHandle;
let occurrencesTable: OccurrencesTableHandle;
let schedulesTable: SchedulesTableHandle;
let ministryEvents: MinistryEventsHandle;

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
    ensureContext: ensureDetail,
    fetch: (teamId) => getQualificationMatrix(ministryId, teamId),
    canRemoveVolunteer: () => isMinistryCoordinator,
    addPoolMember: (personId) => addPoolMember(ministryId, personId),
    addPoolFromCart: () => addPoolMembersFromCart(ministryId),
    removeVolunteer: (personId) => removeVolunteerFromMinistry(ministryId, personId),
    removeScopeName: () => detail?.ministry.name ?? "",
    ministryName: () => detail?.ministry.name ?? "",
    reload: () => load(true),
  });

  occurrencesTable = createOccurrencesTable({
    ministryId: () => ministryId,
    // The ministry page's list spans the ministry; its Team select decides.
    fixedTeamId: () => null,
    teams,
    ensureContext: ensureDetail,
    occurrenceUrl: (occurrenceId) => `${window.CRM?.root ?? ""}/ministries/occurrences/${occurrenceId}`,
    positions,
    staffEvent: (payload) => createStaffedEvent(ministryId, payload),
    // Staffing an event from the Calendar tab changes that tab's staffing column.
    onStaffed: () => {
      ministryEvents.invalidate();
      if (byId("calendar")?.classList.contains("active")) {
        void ministryEvents.load();
      }
    },
  });

  schedulesTable = createSchedulesTable({
    ministryId: () => ministryId,
    teams,
    positions,
    fetch: () => listSchedules(ministryId),
    invalidateOccurrences: () => {
      occurrencesTable.invalidate();
      ministryEvents.invalidate();
    },
    showOccurrences: (filter) => occurrencesTable.reveal(filter),
    classesAllowed: sundaySchool,
    // D30: creating the ministry's events is a coordinator's, like the Calendar tab's own buttons.
    ...(isMinistryCoordinator
      ? {
          addEvents: (prefill: MinistryEventPrefill) => {
            byId("nav-item-calendar")?.click();
            void ministryEvents.openNew(true, prefill);
          },
        }
      : {}),
  });

  ministryEvents = createMinistryEventsTab({
    ministryId: () => ministryId,
    sundaySchool,
    teams,
    ensureContext: ensureDetail,
    occurrenceUrl: (occurrenceId) => `${window.CRM?.root ?? ""}/ministries/occurrences/${occurrenceId}`,
    staffEvent: (event) => {
      void occurrencesTable.openStaff(event);
    },
    invalidateStaffing: () => {
      schedulesTable.invalidate();
      occurrencesTable.invalidate();
      void load(true);
    },
    showOccurrences: (filter) => occurrencesTable.reveal(filter),
    addSchedule: (prefill) => {
      byId("nav-item-schedules")?.click();
      schedulesTable.openNew(prefill);
    },
  });
}

// ─── Renderers ───────────────────────────────────────────────────────────────

/**
 * The overview strip's three counts, then the description, then the teams card.
 *
 * Every number comes from the server's own `summary` block — `teamCount`,
 * `volunteerCount` (the pool Group's membership) and `unfilledPositionCount`
 * (open slots across every future scheduled occurrence the viewer may see,
 * derived from the ONE gap implementation). Nothing is re-derived in the browser:
 * the unfilled count in particular is scope-dependent, and only the server knows
 * which occurrences this viewer is allowed to be counted over.
 */
function renderOverview(data: MinistryDetail): void {
  const set = (id: string, value: string): void => {
    const el = byId(id);
    if (el) {
      el.textContent = value;
    }
  };

  set("overview-team-count", String(data.summary?.teamCount ?? data.teams.length));
  set("overview-volunteer-count", String(data.summary?.volunteerCount ?? 0));
  set("overview-unfilled-count", String(data.summary?.unfilledPositionCount ?? 0));
  set("overview-description", data.ministry.description ?? "");
  renderState("overview", "loaded");
  renderTeams(data);
}

/** The leaders of one team, comma-separated, each linking to their person record. */
function teamLeaderCell(team: VolunteerTeam): string {
  const root = window.CRM?.root ?? "";
  const leaders = team.leaders ?? [];

  if (leaders.length === 0) {
    return "";
  }

  // A team is treated as having at most one leader, but the scope table carries
  // no uniqueness constraint — so if the API ever holds more than one, all of them
  // are named rather than one of them silently winning.
  return leaders
    .map(
      (leader: VolunteerTeamLeader) =>
        `<a href="${root}/people/view/${leader.personId}">${escapeHtml(leader.personName)}</a>`,
    )
    .join(", ");
}

/** D23: the Sunday School class a team is linked to, under its name — a link when the viewer may open groups. */
function teamClassLine(team: VolunteerTeam): string {
  if (!team.classGroupId) {
    return "";
  }

  const name = escapeHtml(team.classGroupName ?? "");
  const label = classUrlBase
    ? `<a href="${window.CRM?.root ?? ""}${classUrlBase}${team.classGroupId}" class="volunteer-team-class-link">${name}</a>`
    : name;

  return `<div class="small text-body-secondary volunteer-team-class"><i class="fa-solid fa-chalkboard-user me-1"></i>${label}</div>`;
}

/**
 * The teams card on Overview — the list that used to open the Teams tab, plus the
 * team's leader.
 *
 * The row menu no longer sets or clears the leader: a leader is a property of the
 * team, so it is a field of the Add/Edit team dialog and the menu is Edit and
 * Delete again. The Team Leader COLUMN stays, and stays visible to anyone who can
 * open the page — the names ride on the ministry document rather than on the
 * manager-only `/scopes` listing, so a ministry coordinator sees who leads what
 * without being able to change it.
 */
function renderTeams(data: MinistryDetail): void {
  const body = document.querySelector("#volunteerTeamsTable tbody");
  if (!body) {
    return;
  }

  destroyDataTable("volunteerTeamsTable");

  if (data.teams.length === 0) {
    body.innerHTML = "";
    renderState("teams", "empty");

    return;
  }

  body.innerHTML = data.teams
    .map((team: VolunteerTeam) => {
      const menu = actionMenu([
        {
          type: "button",
          icon: "fa-solid fa-pencil",
          label: i18next.t("Edit"),
          className: "volunteer-team-edit",
          data: { "team-id": team.id },
        },
        { type: "divider" },
        {
          type: "button",
          icon: "fa-solid fa-trash",
          label: i18next.t("Delete"),
          className: "volunteer-team-delete",
          danger: true,
          data: { "team-id": team.id, "team-name": team.name },
        },
      ]);

      return `<tr data-team-id="${team.id}">
          <td><span class="fw-bold">${escapeHtml(team.name)}</span>${teamClassLine(team)}</td>
          <td>${team.description ? escapeHtml(team.description) : '<span class="text-body-secondary">—</span>'}</td>
          <td class="volunteer-team-leader-cell">${teamLeaderCell(team)}</td>
          <td class="text-center"><span class="badge bg-blue-lt">${team.positionCount}</span></td>
          <td class="text-center">${statusBadge(team.active)}</td>
          <td class="w-1">${menu}</td>
        </tr>`;
    })
    .join("");

  renderState("teams", "loaded");
  initDataTable("volunteerTeamsTable");
}

/**
 * The panes rendered straight from the cached ministry document. `teams` is not
 * here because it is not a tab any more — `renderOverview()` draws it.
 */
const TAB_RENDERERS: Record<"overview" | "positions", (data: MinistryDetail) => void> = {
  overview: renderOverview,
  positions: (data: MinistryDetail) => positionsTable.render(data.positions),
};

// ─── Loading ─────────────────────────────────────────────────────────────────

/** Panes that have been activated at least once, so a reload re-renders them. */
const activated = new Set<"overview" | "positions">(["overview"]);

/** The state blocks a pane owns — Overview owns the teams card's as well. */
function paneStates(pane: "overview" | "positions"): Array<TabName | PaneName> {
  return pane === "overview" ? ["overview", "teams"] : [pane];
}

/**
 * The ministry fetch still out, if any. A tab opened before it lands waits for it
 * (its pane is drawn when it lands): a second fetch would draw the pane twice, and
 * the second drawing replaces the rows — closing a row menu the user just opened.
 */
let pendingLoad: Promise<void> | null = null;

function load(force = false): Promise<void> {
  if (detail !== null && !force) {
    renderHelpWanted(detail);
    for (const pane of activated) {
      TAB_RENDERERS[pane](detail);
    }

    return Promise.resolve();
  }

  if (pendingLoad !== null && !force) {
    return pendingLoad;
  }

  const request = fetchAndRender().finally(() => {
    if (pendingLoad === request) {
      pendingLoad = null;
    }
  });
  pendingLoad = request;

  return request;
}

async function fetchAndRender(): Promise<void> {
  for (const pane of activated) {
    for (const state of paneStates(pane)) {
      renderState(state, "loading");
    }
  }

  try {
    detail = await getMinistry(ministryId);
    renderHelpWanted(detail);
    for (const pane of activated) {
      TAB_RENDERERS[pane](detail);
    }
  } catch (error) {
    // Clearing the cache is what makes Retry a genuine retry (§5.8).
    detail = null;
    const message = errorMessage(error, i18next.t("Could not load this ministry"));
    for (const pane of activated) {
      for (const state of paneStates(pane)) {
        renderState(state, "error", message);
      }
    }
  }
}

function activate(tab: TabName): void {
  // The volunteer grid is its own document with its own team filter, so it loads
  // itself — and it makes sure the ministry document is there first, because the
  // team it filters on comes from that.
  if (tab === "volunteers") {
    void matrixGrid.load();

    return;
  }

  // So is the occurrence list: a date window, not a slice of the ministry detail.
  if (tab === "occurrences") {
    void occurrencesTable.load();

    return;
  }

  // And so is the schedule list: its own collection under the ministry.
  if (tab === "schedules") {
    void schedulesTable.load();

    return;
  }

  // And the ministry's own events (D24).
  if (tab === "calendar") {
    void ministryEvents.load();

    return;
  }

  // Help wanted has no fetch of its own: it is two fields of the ministry
  // document, filled on every load.
  if (tab === "help-wanted") {
    if (detail === null) {
      void load();
    }

    return;
  }

  activated.add(tab);
  if (detail === null) {
    void load();

    return;
  }
  TAB_RENDERERS[tab](detail);
}

// ─── Editors ─────────────────────────────────────────────────────────────────

/**
 * The team dialog's person picker, or null for a viewer who may not use one.
 *
 * The shared selector (CR1/#9819) initialises on `shown.bs.modal`, so the leader
 * the dialog was opened with is handed to it through `teamLeadersOnOpen` rather
 * than set here — by the time `openTeamModal()` returns, the picker does not
 * exist yet.
 */
let teamLeaderPicker: PersonSelectModalHandle | null = null;

function openTeamModal(team?: VolunteerTeam): void {
  editingTeamId = team?.id ?? 0;
  teamLeadersOnOpen = team?.leaders ?? [];
  show(byId("team-form-error"), false);

  const name = byId<HTMLInputElement>("team-form-name");
  const description = byId<HTMLInputElement>("team-form-description");
  const active = byId<HTMLInputElement>("team-form-active");
  const title = byId("teamModalTitle");

  if (name) {
    name.value = team?.name ?? "";
  }
  if (description) {
    description.value = team?.description ?? "";
  }
  if (active) {
    active.checked = team?.active ?? true;
  }
  if (title) {
    title.textContent = team ? i18next.t("Edit team") : i18next.t("Add team");
  }

  // A viewer who may not grant sees the leader as text, because the scope API is
  // manager-only and offering a control it will refuse is worse than not offering
  // one. The hint beside it says who can.
  const readOnly = byId<HTMLInputElement>("team-form-leader-readonly");
  if (readOnly) {
    readOnly.value = teamLeadersOnOpen.map((leader) => leader.personName).join(", ");
  }

  teamClassOnOpen = team?.classGroupId ?? 0;
  show(byId("team-form-class-row"), sundaySchool() || teamClassOnOpen !== 0);
  loadTeamClasses(team);
  loadTeamClassEvents(team);

  modal("teamModal")?.show();
}

/** D28: how many events this ministry made for the team's class, fetched once per open. */
function loadTeamClassEvents(team?: VolunteerTeam): void {
  teamClassEvents = null;
  const keep = document.querySelector<HTMLInputElement>('input[name="team-form-class-events"][value="keep"]');
  if (keep) {
    keep.checked = true;
  }
  renderTeamClassEvents();
  if (!team?.classGroupId) {
    return;
  }

  const teamId = team.id;
  getTeamClassEvents(teamId)
    .then((result) => {
      if (editingTeamId === teamId) {
        teamClassEvents = result;
        renderTeamClassEvents();
      }
    })
    .catch((error: unknown) => {
      notifyError(errorMessage(error, i18next.t("Could not count the class's events")));
    });
}

/**
 * "{Ministry} created N events for {old class} (M upcoming)." with Keep / Remove / Move, shown
 * once the class is changed away from one those events use. Move needs a new class.
 */
function renderTeamClassEvents(): void {
  const select = byId<HTMLSelectElement>("team-form-class");
  const chosen = Number(select?.value ?? teamClassOnOpen);
  const counted = teamClassEvents;
  const visible = counted !== null && counted.total > 0 && teamClassOnOpen !== 0 && chosen !== teamClassOnOpen;
  show(byId("team-form-class-events"), visible);
  if (!visible || counted === null) {
    return;
  }

  const oldClass = counted.classGroupName ?? "";
  const newClass = select?.selectedOptions[0]?.text ?? "";
  const text = (id: string, value: string): void => {
    const el = byId(id);
    if (el) {
      el.textContent = value;
    }
  };
  text(
    "team-form-class-events-text",
    tText("{{ministry}} created {{total}} events for {{className}} ({{upcoming}} upcoming).", {
      ministry: counted.ministryName,
      total: counted.total,
      className: oldClass,
      upcoming: counted.upcoming,
    }),
  );
  text("team-form-class-events-keep", tText("Keep them on {{className}}", { className: oldClass }));
  text("team-form-class-events-remove", i18next.t("Remove the class from them"));
  text("team-form-class-events-move", tText("Move them to {{className}}", { className: newClass }));

  show(byId("team-form-class-events-move-row"), chosen !== 0);
  const move = document.querySelector<HTMLInputElement>('input[name="team-form-class-events"][value="move"]');
  if (chosen === 0 && move?.checked) {
    const keep = document.querySelector<HTMLInputElement>('input[name="team-form-class-events"][value="keep"]');
    if (keep) {
      keep.checked = true;
    }
  }
}

/**
 * D23: fill the dialog's Sunday School class select. It starts out holding only
 * the team's current class, so saving before the list arrives cannot unlink it.
 */
function loadTeamClasses(team?: VolunteerTeam): void {
  const select = byId<HTMLSelectElement>("team-form-class");
  if (!select) {
    return;
  }

  const fill = (classes: LinkableClass[]): void => {
    linkableClasses = classes;
    select.replaceChildren(new Option(tText("None"), "0"));
    for (const linkable of classes) {
      select.append(new Option(linkable.name, String(linkable.id)));
    }
    select.value = String(teamClassOnOpen);
    renderTeamImport();
  };

  fill(team?.classGroupId ? [{ id: team.classGroupId, name: team.classGroupName ?? "", teacherCount: 0 }] : []);
  if (!sundaySchool()) {
    return;
  }
  listLinkableClasses(ministryId, team?.id ?? 0)
    .then((result) => fill(result.classes))
    .catch((error: unknown) => {
      notifyError(errorMessage(error, i18next.t("Could not load the Sunday School classes")));
    });
}

/**
 * "Qualify its current teachers for": shown when the chosen class is a NEW link
 * and has teachers. It offers the team's positions, and a new "Teacher" position
 * when the team has none of that name — the only choice for a team being added.
 */
function renderTeamImport(): void {
  const chosen = Number(byId<HTMLSelectElement>("team-form-class")?.value ?? 0);
  const linkable = linkableClasses.find((candidate) => candidate.id === chosen);
  const importing = chosen !== 0 && chosen !== teamClassOnOpen && (linkable?.teacherCount ?? 0) > 0;
  show(byId("team-form-import"), importing);

  const select = byId<HTMLSelectElement>("team-form-import-position");
  if (!importing || !select || !linkable) {
    return;
  }

  const teamPositions = positions().filter((position) => position.teamId === editingTeamId && editingTeamId !== 0);
  select.replaceChildren(...teamPositions.map((position) => new Option(position.name, String(position.id))));
  const named = teamPositions.find(
    (position) => position.name.toLowerCase() === newTeacherPositionName().toLowerCase(),
  );
  if (!named) {
    select.append(new Option(tText("New position: {{name}}", { name: newTeacherPositionName() }), "new"));
  }
  select.value = named ? String(named.id) : "new";

  const count = byId("team-form-import-count");
  if (count) {
    count.textContent = tText("Teachers to import: {{total}}", { total: linkable.teacherCount });
  }
}

/** The position a team's imported teachers get when the coordinator picks "New position". */
const newTeacherPositionName = (): string => tText("Teacher");

/** The D23 keys to send with a team save — none when the class did not change — and D28's choice. */
function teamClassLink(): TeamClassLink {
  const chosen = Number(byId<HTMLSelectElement>("team-form-class")?.value ?? teamClassOnOpen);
  if (chosen === teamClassOnOpen) {
    return {};
  }

  const asked = !byId("team-form-class-events")?.classList.contains("d-none");
  const checked = document.querySelector<HTMLInputElement>('input[name="team-form-class-events"]:checked')?.value;
  const classEvents: Pick<TeamClassLink, "classEvents"> =
    asked && (checked === "remove" || checked === "move") ? { classEvents: checked } : {};
  if (chosen === 0) {
    return { classGroupId: null, ...classEvents };
  }

  const importing = !byId("team-form-import")?.classList.contains("d-none");
  const position = byId<HTMLSelectElement>("team-form-import-position")?.value ?? "";
  if (!importing) {
    return { classGroupId: chosen, ...classEvents };
  }

  return position === "new"
    ? { classGroupId: chosen, importPositionName: newTeacherPositionName(), ...classEvents }
    : { classGroupId: chosen, importPositionId: Number(position), ...classEvents };
}

/**
 * Bring the team's scope rows into line with what the dialog was left holding.
 *
 * Only a manager reaches this: the field is read-only for everyone else, and the
 * `/api/ministries/scopes` endpoints refuse them anyway. The grant goes first so a
 * failure leaves the existing leader in place rather than a team with nobody.
 */
function syncTeamLeader(teamId: number, chosenPersonId: number): Promise<void> {
  if (!isManager) {
    return Promise.resolve();
  }

  const stale = teamLeadersOnOpen.filter((leader) => leader.personId !== chosenPersonId);
  const alreadyGranted = teamLeadersOnOpen.some((leader) => leader.personId === chosenPersonId);

  // Nothing was chosen and nothing was there: the commonest case does no work.
  if (chosenPersonId === 0 && stale.length === 0) {
    return Promise.resolve();
  }

  const granted =
    chosenPersonId !== 0 && !alreadyGranted
      ? grantScope(chosenPersonId, "team", teamId).then(() => undefined)
      : Promise.resolve();

  return granted.then(() => Promise.all(stale.map((leader) => revokeScope(leader.scopeId))).then(() => undefined));
}

function saveTeam(): void {
  const name = byId<HTMLInputElement>("team-form-name")?.value.trim() ?? "";
  const description = byId<HTMLInputElement>("team-form-description")?.value.trim() ?? "";
  const active = byId<HTMLInputElement>("team-form-active")?.checked ?? true;
  const leaderPersonId = isManager ? Number(teamLeaderPicker?.getInstance()?.getValue() ?? 0) || 0 : 0;

  if (name === "") {
    showModalError("team", i18next.t("Give the team a name"), notifyError);

    return;
  }

  // A newly created team is active by construction (§2.4 default), so the switch
  // only needs a follow-up write when the coordinator turned it off while adding.
  const classLink = teamClassLink();
  const saved: Promise<number> =
    editingTeamId === 0
      ? createTeam(ministryId, name, description, classLink).then((result) =>
          active ? result.team.id : updateTeam(result.team.id, { active: false }).then(() => result.team.id),
        )
      : updateTeam(editingTeamId, { name, description, active, ...classLink }).then(() => editingTeamId);

  // The scope call happens BEFORE the dialog closes, so a refused grant is
  // reported in the dialog it was asked for in rather than as a toast over a
  // screen that looks like it saved.
  saved
    .then((teamId) => syncTeamLeader(teamId, leaderPersonId))
    .then(() => {
      hideModal("teamModal");
      notifySuccess(editingTeamId === 0 ? i18next.t("Team added") : i18next.t("Team saved"));
      // A team change can add or rename a column of the qualification grid, and
      // linking a class can qualify its teachers, so the grid's cache is stale too.
      matrixGrid.invalidate();

      return load(true);
    })
    .catch((error: unknown) => {
      showModalError("team", errorMessage(error, i18next.t("The team could not be saved")), notifyError);
    });
}

// ─── Help wanted (D19, design §5.6) ──────────────────────────────────────────

/**
 * Fill the "Help wanted" card from the ministry document.
 *
 * The card is NOT inside a tab, so it is painted on every load rather than on tab
 * activation — and it is filled from the same cached document as the tabs, so it
 * costs no extra request.
 */
function renderHelpWanted(data: MinistryDetail): void {
  const toggle = byId<HTMLInputElement>("help-wanted-toggle");
  const text = byId<HTMLTextAreaElement>("help-wanted-text");
  if (toggle) {
    toggle.checked = Boolean(data.ministry.helpWanted);
  }
  if (text) {
    text.value = data.ministry.helpWantedText ?? "";
  }
}

function wireHelpWanted(): void {
  byId("help-wanted-save")?.addEventListener("click", () => {
    const toggle = byId<HTMLInputElement>("help-wanted-toggle");
    const text = byId<HTMLTextAreaElement>("help-wanted-text");
    show(byId("help-wanted-form-error"), false);

    updateMinistry(ministryId, {
      helpWanted: Boolean(toggle?.checked),
      helpWantedText: text?.value.trim() ?? "",
    })
      .then((result) => {
        notifySuccess(
          result.ministry.helpWanted
            ? i18next.t("Saved — this ministry is now asking for help")
            : i18next.t("Saved — this ministry is no longer asking for help"),
        );
        if (detail) {
          detail.ministry = result.ministry;
        }
      })
      .catch((error: unknown) => {
        showModalError("help-wanted", errorMessage(error, i18next.t("Help wanted could not be saved")), notifyError);
      });
  });
}

// ─── Edit ministry (D29) ──────────────────────────────────────────────────────

/**
 * Name, description and "Can this ministry provide teachers for Sunday School?". The switch is
 * a volunteer manager's: the view renders it disabled for everyone else and this sends it only
 * for a manager. Saving reloads the page, because the header, the sidebar and every dialog that
 * offers a class read what was saved.
 */
function wireMinistryEdit(): void {
  const button = byId<HTMLButtonElement>("ministry-edit-btn");
  const name = byId<HTMLInputElement>("ministry-edit-name");
  const description = byId<HTMLInputElement>("ministry-edit-description");
  const teaches = byId<HTMLInputElement>("ministry-edit-sunday-school");
  const save = byId<HTMLButtonElement>("ministry-edit-save");
  if (!button || !name || !description || !teaches || !save) {
    return;
  }

  wireModalFadeGuard("ministryEditModal");
  byId("ministryEditModal")?.addEventListener("shown.bs.modal", () => {
    name.focus();
  });

  button.addEventListener("click", () => {
    void ensureDetail().then(() => {
      if (detail === null) {
        notifyError(i18next.t("Could not load this ministry"));

        return;
      }
      show(byId("ministry-edit-form-error"), false);
      name.value = detail.ministry.name;
      description.value = detail.ministry.description ?? "";
      teaches.checked = detail.ministry.sundaySchool;
      modal("ministryEditModal")?.show();
    });
  });

  save.addEventListener("click", () => {
    if (name.value.trim() === "") {
      showModalError("ministry-edit", i18next.t("Give the ministry a name"), notifyError);

      return;
    }
    save.disabled = true;
    updateMinistry(ministryId, {
      name: name.value.trim(),
      description: description.value.trim(),
      ...(isManager ? { sundaySchool: teaches.checked } : {}),
    })
      .then(() => {
        window.location.reload();
      })
      .catch((error: unknown) => {
        save.disabled = false;
        showModalError("ministry-edit", errorMessage(error, i18next.t("The ministry could not be saved")), notifyError);
      });
  });
}

// ─── Lifecycle: Deactivate / Reactivate / Delete (design §4.6, §5.4) ─────────

/**
 * The header's lifecycle buttons (product-owner decision, 2026-09-17).
 *
 * Deactivate and Reactivate are one field on the ministry — `active` through the
 * ordinary update — followed by a full reload, because the sidebar (which heading
 * the ministry sits under), the Inactive badge and which buttons the header
 * shows are all server-rendered from that flag.
 *
 * Delete is rendered only on a deactivated ministry and only for a manager, but
 * the API decides: 403 for anyone else, 409 while the ministry is still active.
 * Deletion is total — service history included — so the dialog says how many
 * occurrences and assignments go with it, from the summary the page already
 * holds. On success the page no longer exists, so the browser goes to the
 * dashboard.
 */
function wireMinistryLifecycle(): void {
  const deactivate = byId<HTMLButtonElement>("ministry-deactivate-btn");
  const reactivate = byId<HTMLButtonElement>("ministry-reactivate-btn");
  const remove = byId<HTMLButtonElement>("ministry-delete-btn");

  const setActive = (button: HTMLButtonElement, active: boolean, failure: string): void => {
    button.disabled = true;
    updateMinistry(ministryId, { active })
      .then(() => {
        window.location.reload();
      })
      .catch((error: unknown) => {
        button.disabled = false;
        notifyError(errorMessage(error, failure));
      });
  };

  deactivate?.addEventListener("click", () => {
    const name = deactivate.dataset.ministryName ?? "";
    confirmDelete(
      i18next.t("Deactivate ministry"),
      i18next.t(
        "Deactivate {{name}}? It moves to Deactivated Ministries in the sidebar, where it can be reactivated or deleted. Nothing is removed.",
        { name },
      ),
      () => setActive(deactivate, false, i18next.t("The ministry could not be deactivated")),
    );
  });

  reactivate?.addEventListener("click", () => {
    setActive(reactivate, true, i18next.t("The ministry could not be reactivated"));
  });

  remove?.addEventListener("click", () => {
    const name = remove.dataset.ministryName ?? "";
    const occurrences = detail?.summary?.occurrenceCount ?? 0;
    const assignments = detail?.summary?.assignmentCount ?? 0;
    confirmDelete(
      i18next.t("Delete ministry"),
      i18next.t(
        "Delete {{name}} and everything in it? Its teams, positions, schedules, {{occurrences}} occurrences and {{assignments}} assignments — past service records included — plus its coordinator and team-leader grants, volunteer pool group and calendar are removed. This cannot be undone.",
        { name, occurrences, assignments },
      ),
      () => {
        remove.disabled = true;
        deleteMinistry(ministryId)
          .then(() => {
            window.location.href = `${window.CRM?.root ?? ""}/ministries/dashboard`;
          })
          .catch((error: unknown) => {
            remove.disabled = false;
            notifyError(errorMessage(error, i18next.t("The ministry could not be deleted")));
          });
      },
    );
  });
}

// ─── Wiring ──────────────────────────────────────────────────────────────────

function findTeam(id: number): VolunteerTeam | undefined {
  return detail?.teams.find((team) => team.id === id);
}

/**
 * The Team leader field inside the Add/Edit team dialog.
 *
 * The shared person selector (CR1/#9819) owns the modal lifecycle, the
 * body-mounted dropdown and the maxOptions fix, so all this does is pre-fill it
 * with whoever currently leads the team. The option is added by hand rather than
 * searched for: the picker's `load()` only runs on typing, and a pre-selected
 * value with no matching option renders as a blank control.
 *
 * Only a manager gets a picker at all — the markup renders a read-only field for
 * everybody else, so `#team-form-leader` is simply not in the document and
 * `attachToModal` has nothing to wrap.
 */
function wireTeamLeaderField(): void {
  const modalEl = byId("teamModal");
  if (!modalEl || !isManager) {
    return;
  }

  teamLeaderPicker = attachToModal(modalEl, "#team-form-leader", {
    onInit: (instance) => {
      const leader = teamLeadersOnOpen[0];
      if (!leader) {
        return;
      }
      instance.addOption({ objid: String(leader.personId), text: leader.personName });
      instance.setValue(String(leader.personId), true);
    },
  });

  // Clearing the field is how a team is left with no leader: an empty picker on
  // save revokes whatever grant the dialog opened with.
  byId("team-form-leader-clear")?.addEventListener("click", () => {
    teamLeaderPicker?.getInstance()?.clear();
  });
}

function wire(): void {
  for (const [navId, pane] of [
    ["nav-item-overview", "overview"],
    ["nav-item-positions", "positions"],
    ["nav-item-volunteers", "volunteers"],
    ["nav-item-schedules", "schedules"],
    ["nav-item-occurrences", "occurrences"],
    ["nav-item-calendar", "calendar"],
    ["nav-item-help-wanted", "help-wanted"],
  ] as Array<[string, TabName]>) {
    byId(navId)?.addEventListener("shown.bs.tab", () => activate(pane));
  }

  for (const button of document.querySelectorAll(".volunteer-retry")) {
    // The grid is a separate document, so its Retry must re-run its own load
    // rather than the ministry fetch — otherwise the button appears dead.
    const inMatrix = button.closest("#volunteers") !== null;
    const inOccurrences = button.closest("#occurrences") !== null;
    const inSchedules = button.closest("#schedules") !== null;
    const inCalendar = button.closest("#calendar") !== null;
    button.addEventListener("click", () => {
      if (inMatrix) {
        void matrixGrid.load(true);
      } else if (inOccurrences) {
        void occurrencesTable.load(true);
      } else if (inSchedules) {
        void schedulesTable.load(true);
      } else if (inCalendar) {
        void ministryEvents.load(true);
      } else {
        void load(true);
      }
    });
  }

  // Focus the first field once the modal has finished animating. Without it
  // Bootstrap's own `shown.bs.modal` handler moves focus to the dialog partway
  // through the 150 ms fade and swallows whatever was typed in the meantime.
  // The position and schedule dialogs do this inside their own components.
  byId("teamModal")?.addEventListener("shown.bs.modal", () => {
    byId<HTMLInputElement>("team-form-name")?.focus();
  });
  wireModalFadeGuard("teamModal");

  byId("team-add-btn")?.addEventListener("click", () => openTeamModal());
  byId("team-form-save")?.addEventListener("click", saveTeam);
  byId("team-form-class")?.addEventListener("change", () => {
    renderTeamImport();
    renderTeamClassEvents();
  });
  wireTeamLeaderField();
  wireHelpWanted();
  wireMinistryEdit();
  wireMinistryLifecycle();

  // Delegated: the rows are re-rendered on every load, so per-row listeners
  // would go stale. Positions, schedules and the grid wire their own.
  document.addEventListener("click", (event) => {
    const target = (event.target as HTMLElement | null)?.closest<HTMLElement>(
      ".volunteer-team-edit, .volunteer-team-delete",
    );
    if (!target) {
      return;
    }

    if (target.classList.contains("volunteer-team-edit")) {
      openTeamModal(findTeam(Number(target.dataset.teamId)));

      return;
    }

    confirmTeamDelete(Number(target.dataset.teamId), target.dataset.teamName ?? "");
  });
}

/**
 * The Delete team confirmation. For a team linked to a class whose events this ministry made,
 * it also asks what happens to those events (D28): keep them, remove the class from them, or
 * delete them through core — which the API refuses while another ministry has volunteers there.
 */
function confirmTeamDelete(teamId: number, name: string): void {
  const warning = i18next.t(
    "Delete {{name}} and everything in it? Its positions, qualifications, staffing needs, schedules, occurrences and assignments — past service records included — and its team-leader grants are removed. To keep the history, deactivate the team instead. This cannot be undone.",
    { name },
  );
  const ask = (counted: TeamClassEvents | null): void => {
    const question = counted === null || counted.total === 0 ? "" : classEventsQuestion(counted);
    confirmDelete(i18next.t("Delete team"), `<p>${warning}</p>${question}`, () => {
      const choice = document.querySelector<HTMLInputElement>('input[name="team-delete-class-events"]:checked')?.value;
      deleteTeam(teamId, choice === "remove" || choice === "delete" ? choice : "keep")
        .then(() => {
          notifySuccess(i18next.t("Team deleted"));
          matrixGrid.invalidate();
          ministryEvents.invalidate();

          return load(true);
        })
        .catch((error: unknown) => {
          notifyError(errorMessage(error, i18next.t("The team could not be deleted")));
        });
    });
  };

  if (!findTeam(teamId)?.classGroupId) {
    ask(null);

    return;
  }
  getTeamClassEvents(teamId)
    .then(ask)
    .catch(() => ask(null));
}

/** The radio group the Delete team confirmation carries (bootbox renders it as HTML). */
function classEventsQuestion(counted: TeamClassEvents): string {
  const className = counted.classGroupName ?? "";
  const option = (value: string, label: string, checked = false): string =>
    `<label class="form-check"><input class="form-check-input" type="radio" name="team-delete-class-events" value="${value}"${
      checked ? " checked" : ""
    }><span class="form-check-label">${label}</span></label>`;
  const staffed = counted.otherStaffing.filter((other) => other.assigned > 0).map((other) => other.ministryName);

  return `<fieldset id="team-delete-class-events">
      <legend class="form-label mb-1">${i18next.t(
        "{{ministry}} created {{total}} events for {{className}} ({{upcoming}} upcoming).",
        { ministry: counted.ministryName, total: counted.total, className, upcoming: counted.upcoming },
      )}</legend>
      ${option("keep", i18next.t("Keep them on {{className}}", { className }), true)}
      ${option("remove", i18next.t("Remove the class from them"))}
      ${option("delete", i18next.t("Delete those events"))}
      ${
        staffed.length > 0
          ? `<div class="form-text">${i18next.t("{{names}} also staff some of them.", { names: staffed.join(", ") })}</div>`
          : ""
      }
    </fieldset>`;
}

/** An occurrence page's Back button returns here with `?tab=occurrences` and the search it came from. */
function restoreOccurrenceSearch(): void {
  const params = new URLSearchParams(window.location.search);
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
  const config = (window.CRM?.volunteerMinistry ?? {
    ministryId: 0,
    isManager: false,
    isMinistryCoordinator: false,
    classUrlBase: null,
  }) as MinistryConfig;
  ministryId = config.ministryId;
  isManager = config.isManager;
  isMinistryCoordinator = config.isMinistryCoordinator;
  classUrlBase = config.classUrlBase ?? null;

  if (ministryId === 0) {
    return;
  }

  buildComponents();
  wire();
  void load().then(restoreOccurrenceSearch);
  // The coordinator/team-leader card (#9706) owns its own markup, state and
  // requests; this is the whole of its integration with the page.
  // A grant puts the person in the pool (2026-09-18), so the Volunteers grid is stale.
  initVolunteerScopes({ ...config, onChange: () => matrixGrid.invalidate() });
}

document.addEventListener("DOMContentLoaded", () => {
  if (window.CRM?.onLocalesReady) {
    window.CRM.onLocalesReady(init);
  } else {
    init();
  }
});
