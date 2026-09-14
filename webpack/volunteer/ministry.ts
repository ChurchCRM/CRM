/**
 * S3 — ministry detail (#9715 and #9707, design §5.4 as amended).
 *
 * Six tabs: **Overview · Positions · Volunteers · Schedules · Occurrences · Help
 * Wanted**. Overview (its three counts, its description and its teams card) and
 * Positions are rendered from ONE `GET /api/volunteer/ministries/{id}` response
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
 * Adding a tab is: one `<li>` and one `.tab-pane` in the view, one entry in
 * `TAB_RENDERERS` (or one branch in `activate()`) here. Nothing else moves.
 *
 * Every state §5.8 requires is here and is driven by `renderState()`: the
 * loading block is re-shown at the start of **every** attempt, the error block
 * carries a Retry that genuinely re-runs the load, and the empty state is a
 * first-class Tabler `.empty` rather than a blank table. Toasts use
 * `window.CRM.notify` with `"danger"` — never `"error"`, which renders blue
 * (U5/E-7).
 *
 * Every user-visible string is `i18next.t()` in this file: the extractor scans
 * only `webpack/**` and `src/skin/js/**`, so the same call inside the .php view
 * would never be translated (design §5.10, F31).
 */

import { attachToModal } from "../common/person-select";
import {
  createPosition,
  createSchedule,
  createTeam,
  deletePosition,
  deleteSchedule,
  deleteTeam,
  errorMessage,
  generateOccurrences,
  getMinistry,
  getQualificationMatrix,
  grantQualification,
  grantScope,
  listOccurrences,
  listScheduleRequirements,
  listSchedules,
  type MinistryDetail,
  notifyError,
  notifySuccess,
  positionLabel,
  type QualificationMatrix,
  qualifyCart,
  removeVolunteerFromMinistry,
  revokeQualification,
  revokeScope,
  updateMinistry,
  updatePosition,
  updateSchedule,
  updateTeam,
  type VolunteerCandidatePosition,
  type VolunteerOccurrenceSummary,
  type VolunteerPoolPerson,
  type VolunteerPosition,
  type VolunteerRequirementRow,
  type VolunteerSchedule,
  type VolunteerTeam,
  type VolunteerTeamLeader,
} from "./api";
import { initVolunteerScopes } from "./scopes";
import { readStaffingNeeds, renderStaffingNeeds, validateStaffingNeeds } from "./staffing-needs";

interface MinistryConfig {
  ministryId: number;
  isManager: boolean;
  /**
   * Advisory: "the server believes you coordinate this ministry or better". It
   * decides whether "Remove Volunteer" is OFFERED and nothing else — the API
   * authorizes independently with the ministry-level entity middleware (D5).
   */
  isMinistryCoordinator: boolean;
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
type TabName = "overview" | "positions" | "volunteers" | "occurrences" | "schedules" | "help-wanted";

let ministryId = 0;
let isManager = false;
/** Advisory only — see `MinistryConfig.isMinistryCoordinator`. */
let isMinistryCoordinator = false;
let detail: MinistryDetail | null = null;
/**
 * The matrix is cached separately from `detail` because it is a different
 * document with a different filter (`?teamId=`) — but it is still ONE fetch for
 * the whole grid, never one per cell (§5.4).
 */
let matrix: QualificationMatrix | null = null;
/**
 * The team whose positions the Volunteers grid is showing. There is no "all
 * teams" answer any more: a ministry's teams own their own positions, two teams
 * may own a position of the same name, and a grid spanning them made the columns
 * ambiguous. Null only before the first team is known.
 */
let matrixTeamId: number | null = null;
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
/**
 * The ministry's upcoming occurrences with their derived gap counts (#9709).
 * Cached like the matrix so switching tabs does not re-fetch, and cleared on error
 * so Retry is a genuine retry (§5.8).
 */
let occurrences: VolunteerOccurrenceSummary[] | null = null;
/**
 * The ministry's recurring schedules (#9708's API, surfaced here by #9711). Cached
 * like the occurrence list and cleared on error so Retry is a genuine retry (§5.8).
 */
let schedules: VolunteerSchedule[] | null = null;
/** Calendar event types, fetched once for the schedule editor's select. */
let eventTypes: Array<{ id: number; name: string }> | null = null;
/** Which schedule a modal is editing; 0 means "new". */
let editingScheduleId = 0;
/**
 * The staffing plan of the schedule the modal is editing, as stored (§2.10). Empty for a
 * new schedule, which is what makes every position start checked.
 */
let scheduleRequirements: VolunteerRequirementRow[] = [];
/** Which record a modal is editing; 0 means "new". */
let editingTeamId = 0;
let editingPositionId = 0;

function byId<T extends HTMLElement>(id: string): T | null {
  return document.getElementById(id) as T | null;
}

function show(el: Element | null, visible: boolean): void {
  el?.classList.toggle("d-none", !visible);
}

/**
 * The §5.8 state machine for one pane, in one place so no pane can forget a
 * state. `content` is only shown in the `loaded` state, and `empty` replaces it
 * when there is genuinely nothing rather than leaving an empty table.
 */
function renderState(pane: TabName | PaneName, state: "loading" | "error" | "empty" | "loaded", message = ""): void {
  show(byId(`${pane}-loading`), state === "loading");
  show(byId(`${pane}-error`), state === "error");
  show(byId(`${pane}-empty`), state === "empty");

  if (pane === "overview") {
    show(byId("overview-content"), state === "loaded");
  } else {
    show(byId(`${pane}-table-wrapper`), state === "loaded");
  }

  if (state === "error") {
    const text = byId(`${pane}-error`)?.querySelector(".volunteer-error-text");
    if (text) {
      text.textContent = message;
    }
  }
}

/** Show an inline error inside an open modal, where the failed action happened. */
function showModalError(prefix: string, message: string): void {
  const box = byId(`${prefix}-form-error`);
  const text = box?.querySelector(".volunteer-error-text");
  if (text) {
    text.textContent = message;
  }
  show(box, true);
  notifyError(message);
}

function modal(id: string): { show(): void; hide(): void } | null {
  const el = byId(id);
  if (!el || !window.bootstrap?.Modal) {
    return null;
  }

  return window.bootstrap.Modal.getOrCreateInstance(el);
}

function statusBadge(active: boolean): string {
  return active
    ? `<span class="badge bg-green-lt text-green">${i18next.t("Active")}</span>`
    : `<span class="badge bg-secondary-lt">${i18next.t("Inactive")}</span>`;
}

/**
 * Row actions through the shared builder (U1/#9820) — it owns the scaffold and
 * every bit of escaping, so labels and `data-*` values are passed raw here.
 */
function actionMenu(items: Array<CRMActionMenuItem | false>): string {
  const build = window.CRM?.buildActionMenu;
  if (!build) {
    return "";
  }

  return build(items);
}

function escapeHtml(value: string): string {
  return window.CRM?.escapeHtml?.(value) ?? value;
}

/**
 * Tear down an existing DataTable **before** the `<tbody>` is rewritten.
 *
 * Order is load-bearing and easy to get backwards: `destroy()` puts the rows
 * DataTables cached at init time back into the DOM, so destroying *after*
 * writing new HTML silently restores the old rows and the update appears to have
 * been ignored.
 */
function destroyDataTable(tableId: string): void {
  if ($.fn.dataTable.isDataTable(`#${tableId}`)) {
    $(`#${tableId}`).DataTable().destroy();
  }
}

/** DataTables through the canonical `window.CRM.plugin.dataTable` merge idiom. */
function initDataTable(tableId: string): void {
  const table = $(`#${tableId}`);
  if (table.length === 0) {
    return;
  }

  // §5.8: a failed ajax must render the inline block, never a browser alert.
  $.fn.dataTable.ext.errMode = "none";

  table.DataTable({ ...(window.CRM?.plugin?.dataTable ?? {}) });
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
        `<a href="${root}/PersonView.php?PersonID=${leader.personId}">${escapeHtml(leader.personName)}</a>`,
    )
    .join(", ");
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
          <td class="fw-bold">${escapeHtml(team.name)}</td>
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

function renderPositions(data: MinistryDetail): void {
  const body = document.querySelector("#volunteerPositionsTable tbody");
  if (!body) {
    return;
  }

  destroyDataTable("volunteerPositionsTable");

  if (data.positions.length === 0) {
    body.innerHTML = "";
    renderState("positions", "empty");
    return;
  }

  body.innerHTML = data.positions
    .map((position: VolunteerPosition) => {
      const menu = actionMenu([
        {
          type: "button",
          icon: "fa-solid fa-pencil",
          label: i18next.t("Edit"),
          className: "volunteer-position-edit",
          data: { "position-id": position.id },
        },
        {
          type: "button",
          icon: position.active ? "fa-solid fa-toggle-off" : "fa-solid fa-toggle-on",
          // Deactivation is the answer once a position has history; deleting one
          // that is referenced is refused by the API with a 409 (§2.6).
          label: position.active ? i18next.t("Deactivate") : i18next.t("Activate"),
          className: "volunteer-position-toggle",
          data: { "position-id": position.id, "position-active": position.active ? "1" : "0" },
        },
        { type: "divider" },
        {
          type: "button",
          icon: "fa-solid fa-trash",
          label: i18next.t("Delete"),
          className: "volunteer-position-delete",
          danger: true,
          data: { "position-id": position.id, "position-name": position.name },
        },
      ]);

      return `<tr>
          <td class="text-center">${position.order}</td>
          <td class="fw-bold">${escapeHtml(position.name)}</td>
          <td>${position.description ? escapeHtml(position.description) : '<span class="text-body-secondary">—</span>'}</td>
          <td>${escapeHtml(position.teamName ?? "")}</td>
          <td class="text-center">${statusBadge(position.active)}</td>
          <td class="w-1">${menu}</td>
        </tr>`;
    })
    .join("");

  renderState("positions", "loaded");
  initDataTable("volunteerPositionsTable");
}

/**
 * The panes rendered straight from the cached ministry document. `teams` is not
 * here because it is not a tab any more — `renderOverview()` draws it.
 */
const TAB_RENDERERS: Record<"overview" | "positions", (data: MinistryDetail) => void> = {
  overview: renderOverview,
  positions: renderPositions,
};

// ─── The qualification matrix (#9707, design §5.4) ───────────────────────────

/**
 * People down the side, positions across the top, a checkbox in every cell.
 *
 * The grid is plain markup rather than a DataTable: the column set is
 * data-driven and every cell is an input, so DataTables' row cache would fight
 * the optimistic toggle below. The one DataTables feature this grid wants — a
 * filter box — is the `#qualification-filter` input, applied over
 * `data-person-name`.
 *
 * Every checkbox carries the qualification row id when one exists, so unticking
 * revokes that exact row without a lookup request.
 */
function renderMatrix(data: QualificationMatrix): void {
  const head = document.querySelector("#volunteerQualificationsTable thead tr");
  const body = document.querySelector("#volunteerQualificationsTable tbody");
  if (!head || !body) {
    return;
  }

  if (data.positions.length === 0 || data.people.length === 0) {
    head.innerHTML = `<th>${i18next.t("Volunteer")}</th>`;
    body.innerHTML = "";
    show(byId("volunteers-save-hint"), false);
    renderState("volunteers", "empty");
    return;
  }

  // "Remove Volunteer" is a ministry-level act, so it is offered to a ministry
  // coordinator and above and to nobody else. The flag is the server's opinion and
  // is advisory: the API refuses a team leader with 403 whatever this says (D5).
  const canRemove = isMinistryCoordinator;

  // "Elementary · Lead Teacher", not a bare "Lead Teacher", whenever the columns
  // come from more than one team — two teams under one ministry may own a position
  // of the same name, and the coordinator has to be able to tell the columns apart.
  // Once the filter names a single team the prefix is noise, so it is dropped.
  const matrixSpansTeams = new Set(data.positions.map((position: VolunteerPosition) => position.teamId)).size > 1;
  const columnLabel = (position: VolunteerPosition): string =>
    matrixSpansTeams ? positionLabel(position.teamName, position.name) : (position.name ?? "");

  head.innerHTML = [
    `<th>${i18next.t("Volunteer")}</th>`,
    ...data.positions.map(
      (position: VolunteerPosition) => `<th class="text-center">${escapeHtml(columnLabel(position))}</th>`,
    ),
    canRemove ? `<th class="text-center no-export w-1">${i18next.t("Actions")}</th>` : "",
  ].join("");

  body.innerHTML = data.people
    .map((person: VolunteerPoolPerson) => {
      const cells = data.positions
        .map((position: VolunteerPosition) => {
          const qualificationId = person.qualificationIds?.[String(position.id)] ?? 0;
          const checked = person.qualifications.includes(position.id) ? " checked" : "";

          // The status slot beside every box is where that box reports on itself —
          // a spinner while the write is in flight, then "Saved" or "Not saved".
          // It is a sibling of the input rather than a class on it, because a
          // checkbox cannot carry a spinner and there is no Save button to look at.
          return `<td class="text-center">
              <span class="volunteer-qual-cell">
                <input type="checkbox" class="form-check-input volunteer-qual-toggle"${checked}
                       data-person-id="${person.personId}"
                       data-position-id="${position.id}"
                       data-qualification-id="${qualificationId}"
                       aria-label="${window.CRM?.escapeAttribute?.(`${person.displayName} — ${columnLabel(position)}`) ?? ""}">
                <span class="volunteer-qual-status" aria-live="polite"></span>
              </span>
            </td>`;
        })
        .join("");

      // D19: the rows are the pool UNION the qualified, so a row can be here for
      // either reason and the screen has to say which. A pool member with no ticks
      // yet is the one a coordinator opened this screen to deal with; a qualified
      // non-member was taken out of the group and is still assignable.
      //
      // For a pool member the badge is always RENDERED and merely hidden when it
      // does not apply, because a tick falsifies it there and then: the first tick
      // must be able to take "not qualified yet" away, and the last untick must be
      // able to bring it back, without re-rendering the grid under the coordinator.
      const hint = person.inPool
        ? ` <span class="badge bg-secondary-lt text-secondary volunteer-pool-hint"${
            person.qualifications.length === 0 ? "" : " hidden"
          }>${escapeHtml(i18next.t("In the pool, not qualified yet"))}</span>`
        : ` <span class="badge bg-secondary-lt text-secondary volunteer-outside-pool-hint">${escapeHtml(
            i18next.t("Not in the pool"),
          )}</span>`;

      const menu = canRemove
        ? `<td class="text-center w-1">${actionMenu([
            {
              type: "button",
              icon: "fa-solid fa-user-minus",
              label: i18next.t("Remove Volunteer"),
              className: "volunteer-remove-volunteer",
              danger: true,
              data: { "person-id": person.personId, "person-name": person.displayName },
            },
          ])}</td>`
        : "";

      return `<tr data-person-name="${window.CRM?.escapeAttribute?.(person.displayName.toLowerCase()) ?? ""}">
          <td class="fw-bold">${escapeHtml(person.displayName)}${hint}</td>
          ${cells}
          ${menu}
        </tr>`;
    })
    .join("");

  renderState("volunteers", "loaded");
  // Only meaningful next to a grid that exists, so it is shown with the grid and
  // hidden with the empty and error states.
  show(byId("volunteers-save-hint"), true);
  applyMatrixFilter();
  fillPositionSelect("qualify-person-position");
  fillPositionSelect("qualify-cart-position");
}

/**
 * Write one tick back into the cached matrix.
 *
 * This is the whole of the "the ticks do not persist" defect. The grid is cached
 * in `matrix` and re-rendered from that cache whenever the tab is activated
 * again, so a save that only changed the checkbox's DOM was thrown away by the
 * next `renderMatrix(matrix)` — the write had reached the database and the screen
 * was drawing a document that predated it. Every save now updates the model the
 * renderer reads, in exactly the shape the server would have sent: an active
 * qualification is in `qualifications` and carries its row id in
 * `qualificationIds`, and a revoked one is in neither.
 */
function setCachedQualification(personId: number, positionId: number, qualificationId: number, active: boolean): void {
  const person = matrix?.people.find((row: VolunteerPoolPerson) => row.personId === personId);
  if (!person) {
    return;
  }

  const held = new Set(person.qualifications);
  if (active) {
    held.add(positionId);
    person.qualificationIds[String(positionId)] = qualificationId;
  } else {
    held.delete(positionId);
    delete person.qualificationIds[String(positionId)];
  }
  person.qualifications = [...held];
}

/**
 * "In the pool, not qualified yet" is a statement about the row that one tick
 * makes false and the last untick makes true again, so it is re-decided from the
 * cached model after every save rather than left until the next full render.
 */
function refreshPoolHint(input: HTMLInputElement, personId: number): void {
  const person = matrix?.people.find((row: VolunteerPoolPerson) => row.personId === personId);
  const hint = input.closest("tr")?.querySelector<HTMLElement>(".volunteer-pool-hint");
  if (!person || !hint) {
    return;
  }

  hint.hidden = person.qualifications.length > 0;
}

/** How long the green confirmation stays up before the cell goes quiet again. */
const SAVED_BADGE_MS = 1500;

/**
 * The three things one checkbox can be saying about itself.
 *
 * `null` clears the slot. The badge is taken out of the flow by
 * `.volunteer-qual-status` in the stylesheet, so a column does not jump wider for
 * a second and a half every time somebody ticks a box.
 */
function setQualificationStatus(input: HTMLInputElement, state: "saving" | "saved" | "failed" | null): void {
  const slot = input.parentElement?.querySelector<HTMLElement>(".volunteer-qual-status");
  if (!slot) {
    return;
  }

  if (state === "saving") {
    slot.innerHTML = `<span class="spinner-border spinner-border-sm text-secondary" role="status" aria-hidden="true"></span>`;
    return;
  }
  if (state === "saved") {
    slot.innerHTML = `<span class="badge bg-green-lt text-green"><i class="fa-solid fa-check me-1"></i>${escapeHtml(
      i18next.t("Saved"),
    )}</span>`;
    return;
  }
  if (state === "failed") {
    slot.innerHTML = `<span class="badge bg-red-lt text-red"><i class="fa-solid fa-triangle-exclamation me-1"></i>${escapeHtml(
      i18next.t("Not saved"),
    )}</span>`;
    return;
  }

  slot.textContent = "";
}

/** Hide the rows whose name does not contain what was typed. Pure client-side. */
function applyMatrixFilter(): void {
  const needle = byId<HTMLInputElement>("qualification-filter")?.value.trim().toLowerCase() ?? "";
  for (const row of document.querySelectorAll<HTMLTableRowElement>("#volunteerQualificationsTable tbody tr")) {
    const name = row.dataset.personName ?? "";
    row.hidden = needle !== "" && !name.includes(needle);
  }
}

/** The two modals offer the same columns the matrix is showing. */
function fillPositionSelect(id: string): void {
  const select = byId<HTMLSelectElement>(id);
  if (!select) {
    return;
  }

  const previous = select.value;
  select.textContent = "";
  const positions = matrix?.positions ?? [];
  const spansTeams = new Set(positions.map((position) => position.teamId)).size > 1;
  for (const position of positions) {
    const option = document.createElement("option");
    option.value = String(position.id);
    option.textContent = spansTeams ? positionLabel(position.teamName, position.name) : position.name;
    select.append(option);
  }
  select.value = previous;
}

/**
 * The team picker above the Volunteers grid.
 *
 * It has no "all teams" entry: positions belong to teams, two teams under one
 * ministry may own a position of the same name, and a grid spanning them made the
 * columns ambiguous however they were labelled. The grid therefore always shows
 * exactly one team, and it starts on the first — the same order the teams card
 * uses, which is the ministry document's order.
 */
function fillMatrixTeamFilter(): void {
  const select = byId<HTMLSelectElement>("qualification-team-filter");
  if (!select) {
    return;
  }

  const teams = detail?.teams ?? [];
  if (matrixTeamId === null || !teams.some((team) => team.id === matrixTeamId)) {
    matrixTeamId = teams[0]?.id ?? null;
  }

  select.textContent = "";
  for (const team of teams) {
    const option = document.createElement("option");
    option.value = String(team.id);
    option.textContent = team.name;
    select.append(option);
  }
  select.value = matrixTeamId === null ? "" : String(matrixTeamId);
}

async function loadMatrix(force = false): Promise<void> {
  if (matrix !== null && !force) {
    renderMatrix(matrix);
    return;
  }

  renderState("volunteers", "loading");
  show(byId("volunteers-save-hint"), false);
  try {
    // The ministry document names the teams, and the grid is always one team's —
    // so it has to be there before the first matrix request can name one.
    if (detail === null) {
      await load();
    }
    fillMatrixTeamFilter();
    matrix = await getQualificationMatrix(ministryId, matrixTeamId);
    renderMatrix(matrix);
  } catch (error) {
    // Clearing the cache is what makes Retry a genuine retry (§5.8).
    matrix = null;
    show(byId("volunteers-save-hint"), false);
    renderState("volunteers", "error", errorMessage(error, i18next.t("Could not load the volunteers")));
  }
}

// ─── Occurrences (#9709, design §5.5 entry point) ────────────────────────────

/**
 * The ministry's upcoming weeks with their derived gap counts, and a link into S4.
 *
 * Deliberately minimal — #9711's dashboard is the richer "what needs my attention"
 * answer. Its whole job is to make the staffing view reachable from the ministry page,
 * which #9708 and #9715 left no hook for. Every count comes from the API, which serves
 * them from `VolunteerAssignmentService::getGaps()`; nothing is re-derived here.
 */
/**
 * "1 Lead Teacher, 2 Helper" — what is missing, by name.
 *
 * A bare gap count tells a coordinator that something is short without telling them
 * what, which is one click of guessing per row. The names come from the server's gap
 * list; only the join happens here.
 */
function gapSummary(occurrence: VolunteerOccurrenceSummary): string {
  if (occurrence.gaps.length === 0) {
    return i18next.t("{{count}} still needed", { count: occurrence.gapCount });
  }

  // This table lists every schedule of the ministry, so two rows can be short of a
  // "Lead Teacher" that means two different teams' positions. The team is named on
  // each one, from the occurrence's own schedule.
  const teamName = detail?.teams.find((team) => team.id === occurrence.teamId)?.name ?? null;

  return occurrence.gaps.map((gap) => `${gap.gapCount} ${positionLabel(teamName, gap.positionName)}`.trim()).join(", ");
}

function renderOccurrences(rows: VolunteerOccurrenceSummary[]): void {
  const body = byId("volunteerOccurrencesTable")?.querySelector("tbody");
  if (!body) {
    return;
  }

  if (rows.length === 0) {
    destroyDataTable("volunteerOccurrencesTable");
    body.innerHTML = "";
    renderState("occurrences", "empty");

    return;
  }

  destroyDataTable("volunteerOccurrencesTable");

  const root = window.CRM?.root ?? "";
  body.innerHTML = rows
    .map((occurrence) => {
      const when = occurrence.start ?? occurrence.occurrenceDate ?? "";
      // An EMPTY plan is not "fully staffed" (§2.10). It has no gaps only because nobody
      // ever said what it needs, and reporting that in green is the whole of the defect
      // this change fixes — the badge links straight to where the needs are set.
      const gap =
        occurrence.requirementCount === 0
          ? `<a class="badge bg-secondary-lt text-secondary" href="${root}/volunteer/occurrences/${occurrence.id}">${escapeHtml(i18next.t("No staffing needs set"))}</a>`
          : occurrence.gapCount > 0
            ? `<span class="badge bg-red-lt text-red">${escapeHtml(gapSummary(occurrence))}</span>`
            : `<span class="badge bg-green-lt text-green">${escapeHtml(i18next.t("Fully staffed"))}</span>`;
      const filled =
        occurrence.requirementCount === 0
          ? `<span class="text-body-secondary">&mdash;</span>`
          : `${occurrence.liveCount} / ${occurrence.requiredCount}`;

      return `
        <tr>
          <td><a href="${root}/volunteer/occurrences/${occurrence.id}">${escapeHtml(when)}</a></td>
          <td>${escapeHtml(occurrence.scheduleName ?? "")}</td>
          <td class="text-center">${filled}</td>
          <td class="text-center">${gap}</td>
          <td class="text-center">
            ${actionMenu([
              {
                type: "link",
                href: `${root}/volunteer/occurrences/${occurrence.id}`,
                icon: "fa-solid fa-list-check",
                label: i18next.t("Staff this occurrence"),
              },
            ])}
          </td>
        </tr>`;
    })
    .join("");

  renderState("occurrences", "loaded");
  initDataTable("volunteerOccurrencesTable");
}

/** `YYYY-MM-DD`, `offsetDays` from today, in the browser's own calendar. */
function isoDate(offsetDays: number): string {
  const date = new Date();
  date.setHours(12, 0, 0, 0);
  date.setDate(date.getDate() + offsetDays);
  const pad = (n: number): string => String(n).padStart(2, "0");

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** How far forward the default "upcoming" window looks. */
const UPCOMING_DAYS = 90;
/** How far back "Filter by Date" proposes when it is opened with no range set. */
const PAST_DEFAULT_DAYS = 90;

/**
 * The window the Occurrences tab is showing, or `null` for the default.
 *
 * The default is **upcoming only** — from today forward — because the tab exists
 * to staff the weeks ahead, and a list that opens on things that already happened
 * buries them. Everything before today is one dialog away ("Filter by Date"), and
 * choosing a range puts it here; "Back to upcoming" sets it to null again.
 */
let occurrenceRange: { from: string; to: string } | null = null;

/** The window actually asked for, default included. `from`/`to` are mandatory (M9). */
function occurrenceWindow(): { from: string; to: string } {
  return occurrenceRange ?? { from: isoDate(0), to: isoDate(UPCOMING_DAYS) };
}

/** "Showing 2026-06-01 to 2026-09-13", with the way back, or nothing at all. */
function renderOccurrenceRangeNote(): void {
  const note = byId("occurrences-range-note");
  const text = byId("occurrences-range-text");
  if (text && occurrenceRange !== null) {
    text.textContent = i18next.t("Showing {{from}} to {{to}}", {
      from: occurrenceRange.from,
      to: occurrenceRange.to,
    });
  }
  show(note, occurrenceRange !== null);
}

async function loadOccurrences(force = false): Promise<void> {
  if (occurrences !== null && !force) {
    renderOccurrenceRangeNote();
    renderOccurrences(occurrences);

    return;
  }

  renderState("occurrences", "loading");
  renderOccurrenceRangeNote();

  try {
    const data = await listOccurrences({ ...occurrenceWindow(), ministryId });
    occurrences = data.occurrences;
    renderOccurrences(occurrences);
  } catch (error) {
    occurrences = null;
    renderState("occurrences", "error", errorMessage(error, i18next.t("Could not load the occurrences")));
  }
}

/**
 * The "Filter by Date" dialog: a From, a To, and a Show.
 *
 * It opens on the last ninety days up to yesterday — "what did we just do" — which
 * is the question a coordinator opens a past-occurrence list to ask. The list
 * endpoint already takes a mandatory `from`/`to` window (M9), so this asks nothing
 * new of the API; only the browser's idea of "the default" changes.
 */
function wireOccurrenceRange(): void {
  byId("occurrences-filter-btn")?.addEventListener("click", () => {
    show(byId("occurrence-range-form-error"), false);
    const from = byId<HTMLInputElement>("occurrence-range-from");
    const to = byId<HTMLInputElement>("occurrence-range-to");
    if (from) {
      from.value = occurrenceRange?.from ?? isoDate(-PAST_DEFAULT_DAYS);
    }
    if (to) {
      to.value = occurrenceRange?.to ?? isoDate(-1);
    }
    modal("occurrenceRangeModal")?.show();
  });

  byId("occurrence-range-show")?.addEventListener("click", () => {
    const from = byId<HTMLInputElement>("occurrence-range-from")?.value ?? "";
    const to = byId<HTMLInputElement>("occurrence-range-to")?.value ?? "";

    if (from === "" || to === "") {
      showModalError("occurrence-range", i18next.t("Choose a date to show from and a date to show to"));
      return;
    }
    // The server answers an inverted window with a 400; saying so here is faster
    // and says it where the mistake was made.
    if (to < from) {
      showModalError("occurrence-range", i18next.t("The window ends before it starts"));
      return;
    }

    occurrenceRange = { from, to };
    modal("occurrenceRangeModal")?.hide();
    void loadOccurrences(true);
  });

  byId("occurrences-range-reset")?.addEventListener("click", () => {
    occurrenceRange = null;
    void loadOccurrences(true);
  });
}

/**
 * The ministry's recurring schedules — #9708 built the API and the generator, and left
 * no screen; §5.4 lists Schedules as a tab of S3, so this is that tab.
 *
 * Per row: the pattern in one readable phrase, how many dates have been generated, and
 * the three actions that matter — generate more, edit, delete. Generation is idempotent
 * server-side (§2.9), so pressing Generate twice creates nothing the second time; the
 * toast reports what the server actually did rather than assuming.
 */
function renderSchedules(rows: VolunteerSchedule[]): void {
  const body = byId("volunteerSchedulesTable")?.querySelector("tbody");
  if (!body) {
    return;
  }

  if (rows.length === 0) {
    destroyDataTable("volunteerSchedulesTable");
    body.innerHTML = "";
    renderState("schedules", "empty");

    return;
  }

  destroyDataTable("volunteerSchedulesTable");

  body.innerHTML = rows
    .map((schedule) => {
      const pattern =
        schedule.linkMode === "event_type"
          ? i18next.t("Calendar event type: {{name}}", { name: schedule.eventTypeName ?? "" })
          : i18next.t("Every {{day}}", { day: schedule.recurDow ?? "" });
      const team = detail?.teams.find((candidate) => candidate.id === schedule.teamId);
      // Every schedule names a team; an empty cell here would mean the ministry
      // document is stale, not that the schedule is ministry-wide.

      return `
        <tr>
          <td>${escapeHtml(schedule.name)}</td>
          <td>${escapeHtml(pattern)}</td>
          <td>${escapeHtml(team?.name ?? "")}</td>
          <td class="text-center">${schedule.occurrenceCount}</td>
          <td class="text-center">${statusBadge(schedule.active)}</td>
          <td class="text-center">
            ${actionMenu([
              {
                type: "button",
                icon: "fa-solid fa-wand-magic-sparkles",
                label: i18next.t("Generate dates"),
                className: "volunteer-schedule-generate",
                data: { "schedule-id": schedule.id, "schedule-name": schedule.name },
              },
              {
                type: "button",
                icon: "fa-solid fa-pen",
                label: i18next.t("Edit"),
                className: "volunteer-schedule-edit",
                data: { "schedule-id": schedule.id },
              },
              {
                type: "button",
                icon: "fa-solid fa-trash",
                label: i18next.t("Delete"),
                className: "volunteer-schedule-delete",
                danger: true,
                data: { "schedule-id": schedule.id, "schedule-name": schedule.name },
              },
            ])}
          </td>
        </tr>`;
    })
    .join("");

  renderState("schedules", "loaded");
  initDataTable("volunteerSchedulesTable");
}

async function loadSchedules(force = false): Promise<void> {
  if (schedules !== null && !force) {
    renderSchedules(schedules);

    return;
  }

  renderState("schedules", "loading");

  try {
    const data = await listSchedules(ministryId);
    schedules = data.schedules;
    renderSchedules(schedules);
  } catch (error) {
    schedules = null;
    renderState("schedules", "error", errorMessage(error, i18next.t("Could not load the schedules")));
  }
}

/**
 * Calendar event types for the editor's select.
 *
 * A plain `fetch` rather than a call through `./api`: that module is the client for
 * `/api/volunteer/*` and this is a core calendar read, so routing it through the
 * volunteer prefix would be wrong. Failure is not fatal — the select is simply empty
 * and the standalone pattern still works.
 */
async function loadEventTypes(): Promise<void> {
  if (eventTypes !== null) {
    return;
  }

  try {
    const response = await fetch(`${window.CRM?.root ?? ""}/api/events/types`, {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    });
    const body: unknown = response.ok ? await response.json() : [];
    eventTypes = Array.isArray(body)
      ? body.map((row: Record<string, unknown>) => ({
          id: Number(row.Id ?? row.id ?? 0),
          name: String(row.Name ?? row.name ?? ""),
        }))
      : [];
  } catch {
    eventTypes = [];
  }
}

function fillScheduleSelects(): void {
  const teamSelect = byId<HTMLSelectElement>("schedule-form-team");
  if (teamSelect) {
    // No "no team" entry: a schedule always belongs to a team, and the ministry
    // always has at least one to offer.
    teamSelect.innerHTML = (detail?.teams ?? [])
      .map((team) => `<option value="${team.id}">${escapeHtml(team.name)}</option>`)
      .join("");
  }

  const typeSelect = byId<HTMLSelectElement>("schedule-form-event-type");
  if (typeSelect) {
    typeSelect.innerHTML = (eventTypes ?? [])
      .map((type) => `<option value="${type.id}">${escapeHtml(type.name)}</option>`)
      .join("");
  }
}

/** Show only the fields the chosen link mode actually uses (§2.8's invariants). */
function syncScheduleMode(): void {
  const mode = byId<HTMLSelectElement>("schedule-form-link-mode")?.value ?? "event_type";
  const linked = mode === "event_type";
  show(byId("schedule-form-event-type-row"), linked);
  show(byId("schedule-form-title-filter-row"), linked);
  show(byId("schedule-form-standalone-rows"), !linked);
}

/**
 * The positions a schedule's staffing plan may name: the chosen team's, and nothing
 * else. The "ministry's team-less positions, offered to every team" branch that used
 * to live here is gone with the positions themselves — it was what made one position
 * name appear twice in a ministry-wide list with no way to tell which team it meant.
 *
 * Mirrors `volunteerCandidatePositions()` in the API, which answers the same question
 * for the occurrence-level editor.
 */
function candidatePositionsForTeam(teamId: number): VolunteerCandidatePosition[] {
  return (detail?.positions ?? [])
    .filter((position) => position.active)
    .filter((position) => position.teamId === teamId)
    .map((position) => ({
      id: position.id,
      name: position.name,
      teamId: position.teamId,
      teamName: position.teamName,
      order: position.order,
    }));
}

/** Re-draw the needs rows for whichever team the form currently names. */
function renderScheduleNeeds(): void {
  const container = byId("schedule-form-needs");
  if (!container) {
    return;
  }

  const teamId = Number(byId<HTMLSelectElement>("schedule-form-team")?.value ?? 0);

  // A new schedule starts with every position checked — "I just made a team with one
  // position, of course I need one of them" — while an edit reflects the rows that exist,
  // so a position with no requirement shows unchecked, which is what its absence means.
  renderStaffingNeeds(container, candidatePositionsForTeam(teamId), scheduleRequirements, editingScheduleId === 0);
}

function openScheduleModal(schedule?: VolunteerSchedule): void {
  editingScheduleId = schedule?.id ?? 0;
  show(byId("schedule-form-error"), false);
  scheduleRequirements = [];

  // The schedule's stored plan, fetched alongside the event types so the modal opens
  // once, filled. A failure is not fatal: the rows fall back to the new-schedule
  // defaults and saving still writes a plan.
  const requirements =
    schedule === undefined
      ? Promise.resolve()
      : listScheduleRequirements(schedule.id)
          .then((data) => {
            scheduleRequirements = data.requirements;
          })
          .catch(() => {
            scheduleRequirements = [];
          });

  void Promise.all([loadEventTypes(), requirements]).then(() => {
    fillScheduleSelects();

    const set = (id: string, value: string): void => {
      const el = byId<HTMLInputElement | HTMLSelectElement>(id);
      if (el) {
        el.value = value;
      }
    };

    set("schedule-form-name", schedule?.name ?? "");
    // A new schedule starts on the ministry's first team rather than on nothing,
    // because "nothing" is no longer a storable answer.
    set("schedule-form-team", String(schedule?.teamId ?? detail?.teams[0]?.id ?? ""));
    set("schedule-form-link-mode", schedule?.linkMode ?? "event_type");
    set(
      "schedule-form-event-type",
      schedule?.eventTypeId === null || schedule === undefined ? "" : String(schedule.eventTypeId),
    );
    set("schedule-form-title-filter", schedule?.titleFilter ?? "");
    set("schedule-form-dow", schedule?.recurDow ?? "Sunday");
    set("schedule-form-start-time", (schedule?.startTime ?? "").slice(0, 5));
    set("schedule-form-end-time", (schedule?.endTime ?? "").slice(0, 5));
    set("schedule-form-window-start", schedule?.windowStart ?? isoDate(0));
    set("schedule-form-window-end", schedule?.windowEnd ?? "");

    const active = byId<HTMLInputElement>("schedule-form-active");
    if (active) {
      active.checked = schedule?.active ?? true;
    }

    const title = byId("scheduleModalTitle");
    if (title) {
      title.textContent = schedule ? i18next.t("Edit schedule") : i18next.t("Add schedule");
    }

    syncScheduleMode();
    renderScheduleNeeds();
    modal("scheduleModal")?.show();
  });
}

function scheduleFormPayload(): Record<string, unknown> {
  const value = (id: string): string => byId<HTMLInputElement | HTMLSelectElement>(id)?.value?.trim() ?? "";
  const linkMode = value("schedule-form-link-mode");
  const teamId = value("schedule-form-team");

  const payload: Record<string, unknown> = {
    name: value("schedule-form-name"),
    linkMode,
    teamId: Number(teamId),
    windowStart: value("schedule-form-window-start"),
    windowEnd: value("schedule-form-window-end") === "" ? null : value("schedule-form-window-end"),
    active: byId<HTMLInputElement>("schedule-form-active")?.checked ?? true,
  };

  if (linkMode === "event_type") {
    payload.eventTypeId = Number(value("schedule-form-event-type")) || null;
    payload.titleFilter = value("schedule-form-title-filter");
  } else {
    payload.recurType = "weekly";
    payload.recurDow = value("schedule-form-dow");
    payload.startTime = value("schedule-form-start-time");
    payload.endTime = value("schedule-form-end-time");
  }

  // The whole plan, in the same request as the schedule row: the server writes both in
  // one transaction, so a payload naming an unknown position leaves no half-made
  // schedule behind. An empty array is a real answer ("needs nobody") and is sent as one.
  const needs = byId("schedule-form-needs");
  if (needs) {
    payload.requirements = readStaffingNeeds(needs);
  }

  return payload;
}

function saveSchedule(): void {
  const needs = byId("schedule-form-needs");
  const invalid = needs === null ? null : validateStaffingNeeds(needs);
  if (invalid !== null) {
    showModalError("schedule", invalid);

    return;
  }

  const payload = scheduleFormPayload();
  const request =
    editingScheduleId === 0 ? createSchedule(ministryId, payload) : updateSchedule(editingScheduleId, payload);

  request
    .then(() => {
      modal("scheduleModal")?.hide();
      notifySuccess(editingScheduleId === 0 ? i18next.t("Schedule created") : i18next.t("Schedule saved"));

      return loadSchedules(true);
    })
    .catch((error: unknown) => {
      showModalError("schedule", errorMessage(error, i18next.t("The schedule could not be saved")));
    });
}

// ─── Loading ─────────────────────────────────────────────────────────────────

/** Panes that have been activated at least once, so a reload re-renders them. */
const activated = new Set<"overview" | "positions">(["overview"]);

/** The state blocks a pane owns — Overview owns the teams card's as well. */
function paneStates(pane: "overview" | "positions"): Array<TabName | PaneName> {
  return pane === "overview" ? ["overview", "teams"] : [pane];
}

async function load(force = false): Promise<void> {
  if (detail !== null && !force) {
    renderHelpWanted(detail);
    for (const pane of activated) {
      TAB_RENDERERS[pane](detail);
    }
    return;
  }

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
    void loadMatrix();
    return;
  }

  // So is the occurrence list: a date window, not a slice of the ministry detail.
  if (tab === "occurrences") {
    void loadOccurrences();
    return;
  }

  // And so is the schedule list: its own collection under the ministry.
  if (tab === "schedules") {
    void loadSchedules();
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

  modal("teamModal")?.show();
}

/**
 * Bring the team's scope rows into line with what the dialog was left holding.
 *
 * Only a manager reaches this: the field is read-only for everyone else, and the
 * `/api/volunteer/scopes` endpoints refuse them anyway. The grant goes first so a
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
    showModalError("team", i18next.t("Give the team a name"));
    return;
  }

  // A newly created team is active by construction (§2.4 default), so the switch
  // only needs a follow-up write when the coordinator turned it off while adding.
  const saved: Promise<number> =
    editingTeamId === 0
      ? createTeam(ministryId, name, description).then((result) =>
          active ? result.team.id : updateTeam(result.team.id, { active: false }).then(() => result.team.id),
        )
      : updateTeam(editingTeamId, { name, description, active }).then(() => editingTeamId);

  // The scope call happens BEFORE the dialog closes, so a refused grant is
  // reported in the dialog it was asked for in rather than as a toast over a
  // screen that looks like it saved.
  saved
    .then((teamId) => syncTeamLeader(teamId, leaderPersonId))
    .then(() => {
      modal("teamModal")?.hide();
      notifySuccess(editingTeamId === 0 ? i18next.t("Team added") : i18next.t("Team saved"));
      // A team change can add or rename a column of the qualification grid, so
      // the grid's own cached document is stale too.
      matrix = null;
      return load(true);
    })
    .catch((error: unknown) => {
      showModalError("team", errorMessage(error, i18next.t("The team could not be saved")));
    });
}

function openPositionModal(position?: VolunteerPosition): void {
  editingPositionId = position?.id ?? 0;
  show(byId("position-form-error"), false);

  const name = byId<HTMLInputElement>("position-form-name");
  const description = byId<HTMLInputElement>("position-form-description");
  const order = byId<HTMLInputElement>("position-form-order");
  const active = byId<HTMLInputElement>("position-form-active");
  const teamSelect = byId<HTMLSelectElement>("position-form-team");
  const title = byId("positionModalTitle");

  if (name) {
    name.value = position?.name ?? "";
  }
  if (description) {
    description.value = position?.description ?? "";
  }
  if (order) {
    order.value = String(position?.order ?? (detail?.positions.length ?? 0) + 1);
  }
  if (active) {
    active.checked = position?.active ?? true;
  }
  if (title) {
    title.textContent = position ? i18next.t("Edit position") : i18next.t("Add position");
  }

  if (teamSelect) {
    // No "no team" entry: a position always belongs to one, and a new position
    // starts on the ministry's first team.
    teamSelect.textContent = "";
    for (const team of detail?.teams ?? []) {
      const option = document.createElement("option");
      option.value = String(team.id);
      option.textContent = team.name;
      teamSelect.append(option);
    }
    teamSelect.value = String(position?.teamId ?? detail?.teams[0]?.id ?? "");
  }

  modal("positionModal")?.show();
}

function savePosition(): void {
  const name = byId<HTMLInputElement>("position-form-name")?.value.trim() ?? "";
  const description = byId<HTMLInputElement>("position-form-description")?.value.trim() ?? "";
  const orderValue = byId<HTMLInputElement>("position-form-order")?.value ?? "0";
  const active = byId<HTMLInputElement>("position-form-active")?.checked ?? true;
  const teamValue = byId<HTMLSelectElement>("position-form-team")?.value ?? "";
  const teamId = Number(teamValue);

  if (name === "") {
    showModalError("position", i18next.t("Give the position a name"));
    return;
  }
  if (!teamId) {
    showModalError("position", i18next.t("Choose the team this position serves on"));
    return;
  }

  const saved =
    editingPositionId === 0
      ? createPosition(ministryId, { name, description, teamId, order: Number(orderValue) || 0 })
      : updatePosition(editingPositionId, { name, description, teamId, order: Number(orderValue) || 0, active });

  saved
    .then(() => {
      modal("positionModal")?.hide();
      notifySuccess(editingPositionId === 0 ? i18next.t("Position added") : i18next.t("Position saved"));
      // A position IS a column of the qualification grid, so the grid's cached
      // document no longer describes the screen.
      matrix = null;
      return load(true);
    })
    .catch((error: unknown) => {
      showModalError("position", errorMessage(error, i18next.t("The position could not be saved")));
    });
}

/**
 * Destructive actions go behind a bootbox confirm (U3). The 409 the API raises
 * when the record is still referenced is surfaced verbatim, because its message
 * names the counts and tells the coordinator to deactivate instead.
 */
function confirmDelete(title: string, message: string, onConfirm: () => void): void {
  window.bootbox?.confirm({
    title,
    message,
    buttons: {
      confirm: { label: i18next.t("Yes"), className: "btn-danger" },
      cancel: { label: i18next.t("No"), className: "btn-default" },
    },
    callback: (result: boolean) => {
      if (result) {
        onConfirm();
      }
    },
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
        showModalError("help-wanted", errorMessage(error, i18next.t("Help wanted could not be saved")));
      });
  });
}

// ─── Team leaders, on the team's own row (#9706's scope API) ─────────────────

// ─── Wiring ──────────────────────────────────────────────────────────────────

function findTeam(id: number): VolunteerTeam | undefined {
  return detail?.teams.find((team) => team.id === id);
}

function findPosition(id: number): VolunteerPosition | undefined {
  return detail?.positions.find((position) => position.id === id);
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

/**
 * "Remove Volunteer" on a row of the Volunteers grid.
 *
 * One call, three effects, spelled out in the confirm because none of them is
 * guessable from the words "remove": the qualifications go, the upcoming
 * assignments are cancelled, and they leave the pool. The toast reports the
 * server's own counts rather than assuming what happened.
 */
function wireRemoveVolunteer(): void {
  document.addEventListener("click", (event) => {
    const target = (event.target as HTMLElement | null)?.closest<HTMLElement>(".volunteer-remove-volunteer");
    if (!target) {
      return;
    }

    const personId = Number(target.dataset.personId);
    const personName = target.dataset.personName ?? "";

    confirmDelete(
      i18next.t("Remove Volunteer"),
      i18next.t(
        "Remove {{name}} from {{ministry}}? This removes all their qualifications here, takes them off every future occurrence, and removes them from the volunteer pool.",
        { name: personName, ministry: detail?.ministry.name ?? "" },
      ),
      () => {
        removeVolunteerFromMinistry(ministryId, personId)
          .then((result) => {
            notifySuccess(
              i18next.t("Removed. {{qualifications}} qualifications, {{assignments}} upcoming assignments.", {
                qualifications: result.qualifications,
                assignments: result.assignments,
              }),
            );
            matrix = null;

            // The pool count on Overview moved, so the ministry document is stale too.
            return load(true).then(() => loadMatrix(true));
          })
          .catch((error: unknown) => {
            notifyError(errorMessage(error, i18next.t("They could not be removed from this ministry")));
          });
      },
    );
  });
}

function wireQualifications(): void {
  byId("qualification-filter")?.addEventListener("input", applyMatrixFilter);

  byId("qualification-team-filter")?.addEventListener("change", (event) => {
    // Every option is a team now, so there is no "" to translate back to null.
    matrixTeamId = Number((event.target as HTMLSelectElement).value) || null;
    void loadMatrix(true);
  });

  // The shared person selector (CR1/#9819) rather than a fourth hand-rolled
  // TomSelect: it owns the modal lifecycle, the body-mounted dropdown and the
  // maxOptions fix.
  const personModal = byId("qualifyPersonModal");
  const personPicker = personModal ? attachToModal(personModal, "#qualify-person-select") : null;

  byId("qualification-add-person")?.addEventListener("click", () => {
    show(byId("qualify-person-form-error"), false);
    fillPositionSelect("qualify-person-position");
    modal("qualifyPersonModal")?.show();
  });

  byId("qualify-person-save")?.addEventListener("click", () => {
    const personId = Number(personPicker?.getInstance()?.getValue() ?? 0);
    const positionId = Number(byId<HTMLSelectElement>("qualify-person-position")?.value ?? 0);

    if (!personId || !positionId) {
      showModalError("qualify-person", i18next.t("Choose a person and a position"));
      return;
    }

    grantQualification(positionId, personId)
      .then(() => {
        modal("qualifyPersonModal")?.hide();
        notifySuccess(i18next.t("Qualification granted"));
        return loadMatrix(true);
      })
      .catch((error: unknown) => {
        showModalError("qualify-person", errorMessage(error, i18next.t("The qualification could not be saved")));
      });
  });

  byId("qualification-cart-btn")?.addEventListener("click", () => {
    show(byId("qualify-cart-form-error"), false);
    fillPositionSelect("qualify-cart-position");
    modal("qualifyCartModal")?.show();
  });

  byId("qualify-cart-save")?.addEventListener("click", () => {
    const positionId = Number(byId<HTMLSelectElement>("qualify-cart-position")?.value ?? 0);
    if (!positionId) {
      showModalError("qualify-cart", i18next.t("Choose a position"));
      return;
    }

    qualifyCart(positionId)
      .then((result) => {
        modal("qualifyCartModal")?.hide();
        notifySuccess(i18next.t("{{count}} people qualified", { count: result.granted + result.reactivated }));
        return loadMatrix(true);
      })
      .catch((error: unknown) => {
        showModalError("qualify-cart", errorMessage(error, i18next.t("The cart could not be qualified")));
      });
  });

  /**
   * One cell. The tick is applied straight away and rolled back on failure with
   * a toast — the §5.4 "optimistic UI + toast on failure" rule. The grid is NOT
   * re-fetched on success: a coordinator ticking fifteen boxes should not pay
   * for fifteen reloads.
   *
   * What it DOES do on success is write the answer back into `matrix`, the cached
   * document the grid is re-rendered from. Without that the tick lived only in the
   * DOM and the next `renderMatrix(matrix)` — which happens every time the tab is
   * activated again — drew a document that predated the save, which is what the
   * product owner saw as "it is not persisting".
   *
   * Each box also reports on itself, because there is deliberately no Save
   * button: a spinner while the write is in flight, a green "Saved" for a second
   * and a half, and a red "Not saved" beside the rolled-back box on failure.
   */
  document.addEventListener("change", (event) => {
    const target = event.target as HTMLInputElement | null;
    if (!target?.classList.contains("volunteer-qual-toggle")) {
      return;
    }

    const personId = Number(target.dataset.personId);
    const positionId = Number(target.dataset.positionId);
    const qualificationId = Number(target.dataset.qualificationId ?? 0);
    const nowChecked = target.checked;
    target.disabled = true;
    setQualificationStatus(target, "saving");

    const confirmSaved = (): void => {
      target.disabled = false;
      setQualificationStatus(target, "saved");
      window.setTimeout(() => setQualificationStatus(target, null), SAVED_BADGE_MS);
    };

    const rollback = (error: unknown, fallback: string): void => {
      target.checked = !nowChecked;
      target.disabled = false;
      setQualificationStatus(target, "failed");
      notifyError(errorMessage(error, fallback));
    };

    if (nowChecked) {
      grantQualification(positionId, personId)
        .then((result) => {
          // Remember the row id so unticking can revoke it with no lookup — on the
          // element for this render, and in the cache for every render after it.
          const savedId = result.qualification.id;
          target.dataset.qualificationId = String(savedId);
          setCachedQualification(personId, positionId, savedId, true);
          refreshPoolHint(target, personId);
          confirmSaved();
        })
        .catch((error: unknown) => rollback(error, i18next.t("The qualification could not be saved")));
      return;
    }

    if (!qualificationId) {
      // Nothing to revoke — the box was never really on.
      target.disabled = false;
      setQualificationStatus(target, null);
      return;
    }

    revokeQualification(qualificationId)
      .then(() => {
        // Revocation is deactivation, so the row id stays valid: re-ticking
        // reactivates the same row rather than making a second one (§2.7). The
        // cache drops it, which is the shape the server would send — an inactive
        // qualification is in neither list.
        setCachedQualification(personId, positionId, qualificationId, false);
        target.dataset.qualificationId = "0";
        refreshPoolHint(target, personId);
        confirmSaved();
      })
      .catch((error: unknown) => rollback(error, i18next.t("The qualification could not be removed")));
  });
}

/**
 * Keep a row action menu usable inside a horizontally scrolling table.
 *
 * `.volunteer-scroll-x` sets `overflow-x: auto` so the qualification grid can be
 * wider than the page. Per the CSS Overflow spec that coerces `overflow-y` from
 * `visible` to `auto`, which clips any absolutely-positioned descendant — and a
 * Bootstrap dropdown is exactly that. `data-bs-display="static"` does not help:
 * it only turns Popper off, and the menu is still positioned inside the clipping
 * box.
 *
 * The fix is to take the OPEN menu out of that box altogether. A `position: fixed`
 * element is not clipped by an ancestor's overflow at all, so on `shown.bs.dropdown`
 * the menu is switched to fixed and anchored to the trigger's viewport rectangle,
 * and on `hide.bs.dropdown` it is handed back to Bootstrap untouched. Nothing is
 * moved in the DOM, so Bootstrap's own focus handling, the delegated row-action
 * click handlers and `dropdown-menu-end` alignment all keep working.
 *
 * Coordinates are re-derived on scroll and resize while the menu is open, because
 * a fixed element does not follow the row it belongs to.
 */
function wireUnclippedRowMenus(wrapperId: string): void {
  const wrapper = byId(wrapperId);
  if (!wrapper) {
    return;
  }

  let open: { menu: HTMLElement; toggle: HTMLElement } | null = null;

  const place = (): void => {
    if (!open) {
      return;
    }
    const rect = open.toggle.getBoundingClientRect();
    // `dropdown-menu-end` aligns the menu's right edge with the trigger's.
    open.menu.style.top = `${rect.bottom}px`;
    open.menu.style.left = `${Math.max(0, rect.right - open.menu.offsetWidth)}px`;
  };

  const release = (): void => {
    if (!open) {
      return;
    }
    open.menu.classList.remove("volunteer-menu-fixed");
    open.menu.style.top = "";
    open.menu.style.left = "";
    open = null;
    window.removeEventListener("resize", place);
    window.removeEventListener("scroll", place, true);
  };

  // `shown`, not `show`: a `.dropdown-menu` without `.show` is `display: none`,
  // so its width — which right-alignment needs — is zero until Bootstrap has
  // opened it. The handler runs before the browser paints, so there is no flash.
  wrapper.addEventListener("shown.bs.dropdown", (event) => {
    // Bootstrap fires this on the TOGGLE, not on the `.dropdown` wrapper — both
    // are accepted here so the handler survives either reading.
    const node = event.target as HTMLElement | null;
    const toggle = node?.matches("[data-bs-toggle='dropdown']")
      ? node
      : (node?.querySelector<HTMLElement>("[data-bs-toggle='dropdown']") ?? null);
    const menu = toggle?.parentElement?.querySelector<HTMLElement>(".dropdown-menu") ?? null;
    if (!menu || !toggle) {
      return;
    }

    release();
    open = { menu, toggle };
    menu.classList.add("volunteer-menu-fixed");
    place();
    window.addEventListener("resize", place);
    // Capture: the wrapper's own scroll does not bubble.
    window.addEventListener("scroll", place, true);
  });

  wrapper.addEventListener("hide.bs.dropdown", release);
}

function wire(): void {
  for (const [navId, pane] of [
    ["nav-item-overview", "overview"],
    ["nav-item-positions", "positions"],
    ["nav-item-volunteers", "volunteers"],
    ["nav-item-schedules", "schedules"],
    ["nav-item-occurrences", "occurrences"],
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
    button.addEventListener("click", () => {
      if (inMatrix) {
        void loadMatrix(true);
      } else if (inOccurrences) {
        void loadOccurrences(true);
      } else if (inSchedules) {
        void loadSchedules(true);
      } else {
        void load(true);
      }
    });
  }

  // Focus the first field once the modal has finished animating. Without it
  // Bootstrap's own `shown.bs.modal` handler moves focus to the dialog partway
  // through the 150 ms fade and swallows whatever was typed in the meantime.
  for (const [modalId, inputId] of [
    ["teamModal", "team-form-name"],
    ["positionModal", "position-form-name"],
    ["scheduleModal", "schedule-form-name"],
    ["occurrenceRangeModal", "occurrence-range-from"],
  ]) {
    byId(modalId)?.addEventListener("shown.bs.modal", () => {
      byId<HTMLInputElement>(inputId)?.focus();
    });
  }

  byId("team-add-btn")?.addEventListener("click", () => openTeamModal());
  byId("team-form-save")?.addEventListener("click", saveTeam);
  byId("position-add-btn")?.addEventListener("click", () => openPositionModal());
  byId("position-form-save")?.addEventListener("click", savePosition);
  byId("schedule-add-btn")?.addEventListener("click", () => openScheduleModal());
  byId("schedule-form-save")?.addEventListener("click", saveSchedule);
  byId("schedule-form-link-mode")?.addEventListener("change", syncScheduleMode);
  // Positions are team-scoped, so the list of things that can be needed changes with the
  // team. Re-rendering discards whatever was typed for the old team's positions, which is
  // correct: those rows are no longer part of this schedule's plan.
  byId("schedule-form-team")?.addEventListener("change", renderScheduleNeeds);
  wireTeamLeaderField();
  wireRemoveVolunteer();
  wireHelpWanted();
  wireQualifications();
  wireOccurrenceRange();
  // The qualification grid is the one table on this page that scrolls sideways.
  wireUnclippedRowMenus("volunteers-table-wrapper");

  // Delegated: the rows are re-rendered on every load, so per-row listeners
  // would go stale.
  document.addEventListener("click", (event) => {
    const target = (event.target as HTMLElement | null)?.closest<HTMLElement>(
      ".volunteer-team-edit, .volunteer-team-delete, .volunteer-position-edit, .volunteer-position-delete, .volunteer-position-toggle, .volunteer-schedule-edit, .volunteer-schedule-delete, .volunteer-schedule-generate",
    );
    if (!target) {
      return;
    }

    if (target.classList.contains("volunteer-schedule-edit")) {
      openScheduleModal((schedules ?? []).find((row) => row.id === Number(target.dataset.scheduleId)));
      return;
    }

    if (target.classList.contains("volunteer-schedule-generate")) {
      const scheduleId = Number(target.dataset.scheduleId);
      generateOccurrences(scheduleId)
        .then((result) => {
          // The server's own numbers, not an assumption: generation is idempotent,
          // so "created 0, 8 already there" is a perfectly good outcome to report.
          notifySuccess(
            i18next.t("{{created}} dates created, {{existing}} were already there", {
              created: result.created,
              existing: result.existing,
            }),
          );
          occurrences = null;

          return loadSchedules(true);
        })
        .catch((error: unknown) => {
          notifyError(errorMessage(error, i18next.t("The dates could not be generated")));
        });
      return;
    }

    if (target.classList.contains("volunteer-schedule-delete")) {
      const scheduleId = Number(target.dataset.scheduleId);
      confirmDelete(
        i18next.t("Delete schedule"),
        i18next.t("Delete {{name}}? This cannot be undone.", { name: target.dataset.scheduleName ?? "" }),
        () => {
          deleteSchedule(scheduleId)
            .then(() => {
              notifySuccess(i18next.t("Schedule deleted"));
              occurrences = null;

              return loadSchedules(true);
            })
            .catch((error: unknown) => {
              notifyError(errorMessage(error, i18next.t("The schedule could not be deleted")));
            });
        },
      );
      return;
    }

    if (target.classList.contains("volunteer-team-edit")) {
      openTeamModal(findTeam(Number(target.dataset.teamId)));
      return;
    }

    if (target.classList.contains("volunteer-team-delete")) {
      const teamId = Number(target.dataset.teamId);
      confirmDelete(
        i18next.t("Delete team"),
        i18next.t("Delete {{name}}? This cannot be undone.", { name: target.dataset.teamName ?? "" }),
        () => {
          deleteTeam(teamId)
            .then(() => {
              notifySuccess(i18next.t("Team deleted"));
              matrix = null;
              return load(true);
            })
            .catch((error: unknown) => {
              notifyError(errorMessage(error, i18next.t("The team could not be deleted")));
            });
        },
      );
      return;
    }

    if (target.classList.contains("volunteer-position-edit")) {
      openPositionModal(findPosition(Number(target.dataset.positionId)));
      return;
    }

    if (target.classList.contains("volunteer-position-toggle")) {
      const positionId = Number(target.dataset.positionId);
      const nowActive = target.dataset.positionActive !== "1";
      updatePosition(positionId, { active: nowActive })
        .then(() => {
          notifySuccess(nowActive ? i18next.t("Position activated") : i18next.t("Position deactivated"));
          // Only ACTIVE positions are columns (§2.6), so this adds or drops one.
          matrix = null;
          return load(true);
        })
        .catch((error: unknown) => {
          notifyError(errorMessage(error, i18next.t("The position could not be saved")));
        });
      return;
    }

    const positionId = Number(target.dataset.positionId);
    confirmDelete(
      i18next.t("Delete position"),
      i18next.t("Delete {{name}}? This cannot be undone.", { name: target.dataset.positionName ?? "" }),
      () => {
        deletePosition(positionId)
          .then(() => {
            notifySuccess(i18next.t("Position deleted"));
            matrix = null;
            return load(true);
          })
          .catch((error: unknown) => {
            notifyError(errorMessage(error, i18next.t("The position could not be deleted")));
          });
      },
    );
  });
}

function init(): void {
  const config = (window.CRM?.volunteerMinistry ?? {
    ministryId: 0,
    isManager: false,
    isMinistryCoordinator: false,
  }) as MinistryConfig;
  ministryId = config.ministryId;
  isManager = config.isManager;
  isMinistryCoordinator = config.isMinistryCoordinator;

  if (ministryId === 0) {
    return;
  }

  wire();
  void load();
  // The coordinator/team-leader card (#9706) owns its own markup, state and
  // requests; this is the whole of its integration with the page.
  initVolunteerScopes(config);
}

document.addEventListener("DOMContentLoaded", () => {
  if (window.CRM?.onLocalesReady) {
    window.CRM.onLocalesReady(init);
  } else {
    init();
  }
});
