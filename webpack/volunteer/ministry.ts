/**
 * S3 — ministry detail (#9715 and #9707, design §5.4).
 *
 * Four tabs. Overview, Teams & Pools and Positions are rendered from ONE
 * `GET /api/volunteer/ministries/{id}` response that is fetched on first use and
 * cached; Qualifications is one `GET .../qualification-matrix` because it has
 * its own `?teamId=` filter. Both obey the same rule, which is the point §5.4
 * actually makes: the matrix must handle 15–200 pool members "without
 * re-fetching per cell", so the whole grid — people, positions and every tick —
 * arrives in a single document and a checkbox writes exactly one row.
 *
 * The pool half is deliberately read-only about membership. A pool is a link to
 * a Group and the Group stays the roster (D1); every `/api/groups` write needs
 * the global Manage Groups flag and the ORM hooks demand it independently, so
 * the screen links into `/groups/view/{id}` and says which permission is needed
 * rather than proxying an edit that would fail deeper in (§4.6, Appendix D-1).
 *
 * Adding a tab (#9708/#9711's Schedules) is: one `<li>` and one `.tab-pane` in
 * the view, one entry in `TAB_RENDERERS` here. Nothing else moves.
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
  linkPool,
  listOccurrences,
  listScheduleRequirements,
  listSchedules,
  type MinistryDetail,
  notifyError,
  notifySuccess,
  type QualificationMatrix,
  qualifyCart,
  revokeQualification,
  unlinkPool,
  updatePosition,
  updateSchedule,
  updateTeam,
  type VolunteerCandidatePosition,
  type VolunteerOccurrenceSummary,
  type VolunteerPool,
  type VolunteerPoolPerson,
  type VolunteerPosition,
  type VolunteerRequirementRow,
  type VolunteerSchedule,
  type VolunteerTeam,
} from "./api";
import { initVolunteerScopes } from "./scopes";
import { readStaffingNeeds, renderStaffingNeeds, validateStaffingNeeds } from "./staffing-needs";

interface MinistryConfig {
  ministryId: number;
  isManager: boolean;
}

type PaneName = "overview" | "teams" | "positions";
/**
 * Every tab in the strip. The matrix and the occurrence list each have their own
 * fetch — a different document with a different filter — so they carry their own
 * names and load themselves rather than riding on the ministry detail.
 */
type TabName = PaneName | "qualifications" | "occurrences" | "schedules";

let ministryId = 0;
let detail: MinistryDetail | null = null;
/**
 * The matrix is cached separately from `detail` because it is a different
 * document with a different filter (`?teamId=`) — but it is still ONE fetch for
 * the whole grid, never one per cell (§5.4).
 */
let matrix: QualificationMatrix | null = null;
let matrixTeamId: number | null = null;
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
/** The pool owner the group picker is about to link to. */
let poolOwner: { type: "ministry" | "team"; id: number } = { type: "ministry", id: 0 };

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
function renderState(pane: TabName, state: "loading" | "error" | "empty" | "loaded", message = ""): void {
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

function renderOverview(data: MinistryDetail): void {
  const active = data.positions.filter((position) => position.active).length;
  const set = (id: string, value: string): void => {
    const el = byId(id);
    if (el) {
      el.textContent = value;
    }
  };

  set("overview-team-count", String(data.teams.length));
  set("overview-position-count", String(data.positions.length));
  set("overview-active-position-count", String(active));
  set("overview-description", data.ministry.description ?? "");
  renderState("overview", "loaded");
}

/**
 * The pool list inside the Teams & Pools tab (#9707).
 *
 * Rendered from the same cached ministry document as the teams table, so
 * opening the tab is still one fetch. Membership is READ-ONLY here: the count
 * is a link into `/groups/view/{id}`, because every `/api/groups` write needs
 * the global Manage Groups flag — and the ORM hooks demand it independently, so
 * proxying the edit would only fail deeper in (design §4.6, Appendix D-1).
 */
function renderPools(data: MinistryDetail): void {
  const body = document.querySelector("#volunteerPoolsTable tbody");
  if (!body) {
    return;
  }

  const pools = data.pools ?? [];
  destroyDataTable("volunteerPoolsTable");

  if (pools.length === 0) {
    body.innerHTML = "";
    show(byId("pools-empty"), true);
    show(byId("pools-table-wrapper"), false);
    return;
  }

  const root = window.CRM?.root ?? "";

  body.innerHTML = pools
    .map((pool: VolunteerPool) => {
      const menu = actionMenu([
        {
          type: "link",
          icon: "fa-solid fa-users",
          label: i18next.t("Open the group"),
          href: `${root}/groups/view/${pool.groupId}`,
        },
        { type: "divider" },
        {
          type: "button",
          icon: "fa-solid fa-link-slash",
          // "Unlink", never "Delete": the Group and everyone in it survive.
          label: i18next.t("Unlink"),
          className: "volunteer-pool-unlink",
          danger: true,
          data: { "pool-id": pool.id, "group-name": pool.groupName ?? "" },
        },
      ]);

      const serves =
        pool.ownerType === "ministry"
          ? `<span class="text-body-secondary">${i18next.t("Whole ministry")}</span>`
          : escapeHtml(pool.ownerName ?? "");

      return `<tr>
          <td class="fw-bold">${escapeHtml(pool.groupName ?? "")}</td>
          <td>${serves}</td>
          <td>${pool.label ? escapeHtml(pool.label) : '<span class="text-body-secondary">—</span>'}</td>
          <td class="text-center">
            <a href="${root}/groups/view/${pool.groupId}" class="badge bg-blue-lt text-decoration-none"
               title="${i18next.t("Membership is managed in Groups")}">${pool.memberCount}</a>
          </td>
          <td class="w-1">${menu}</td>
        </tr>`;
    })
    .join("");

  show(byId("pools-empty"), false);
  show(byId("pools-table-wrapper"), true);
  initDataTable("volunteerPoolsTable");
}

function renderTeams(data: MinistryDetail): void {
  renderPools(data);

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
        {
          type: "button",
          icon: "fa-solid fa-link",
          label: i18next.t("Link a Group"),
          className: "volunteer-team-link-pool",
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

      return `<tr>
          <td class="fw-bold">${escapeHtml(team.name)}</td>
          <td>${team.description ? escapeHtml(team.description) : '<span class="text-body-secondary">—</span>'}</td>
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
          <td>${position.teamName ? escapeHtml(position.teamName) : `<span class="text-body-secondary">${i18next.t("Whole ministry")}</span>`}</td>
          <td class="text-center">${statusBadge(position.active)}</td>
          <td class="w-1">${menu}</td>
        </tr>`;
    })
    .join("");

  renderState("positions", "loaded");
  initDataTable("volunteerPositionsTable");
}

const TAB_RENDERERS: Record<PaneName, (data: MinistryDetail) => void> = {
  overview: renderOverview,
  teams: renderTeams,
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
    renderState("qualifications", "empty");
    return;
  }

  head.innerHTML = [
    `<th>${i18next.t("Volunteer")}</th>`,
    ...data.positions.map((position: VolunteerPosition) => `<th class="text-center">${escapeHtml(position.name)}</th>`),
  ].join("");

  body.innerHTML = data.people
    .map((person: VolunteerPoolPerson) => {
      const cells = data.positions
        .map((position: VolunteerPosition) => {
          const qualificationId = person.qualificationIds?.[String(position.id)] ?? 0;
          const checked = person.qualifications.includes(position.id) ? " checked" : "";

          return `<td class="text-center">
              <input type="checkbox" class="form-check-input volunteer-qual-toggle"${checked}
                     data-person-id="${person.personId}"
                     data-position-id="${position.id}"
                     data-qualification-id="${qualificationId}"
                     aria-label="${window.CRM?.escapeAttribute?.(`${person.displayName} — ${position.name}`) ?? ""}">
            </td>`;
        })
        .join("");

      return `<tr data-person-name="${window.CRM?.escapeAttribute?.(person.displayName.toLowerCase()) ?? ""}">
          <td class="fw-bold">${escapeHtml(person.displayName)}</td>
          ${cells}
        </tr>`;
    })
    .join("");

  renderState("qualifications", "loaded");
  applyMatrixFilter();
  fillPositionSelect("qualify-person-position");
  fillPositionSelect("qualify-cart-position");
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
  for (const position of matrix?.positions ?? []) {
    const option = document.createElement("option");
    option.value = String(position.id);
    option.textContent = position.name;
    select.append(option);
  }
  select.value = previous;
}

/** The team picker above the matrix; "Whole ministry" is the default. */
function fillMatrixTeamFilter(): void {
  const select = byId<HTMLSelectElement>("qualification-team-filter");
  if (!select) {
    return;
  }

  select.textContent = "";
  const wide = document.createElement("option");
  wide.value = "";
  wide.textContent = i18next.t("Whole ministry");
  select.append(wide);
  for (const team of detail?.teams ?? []) {
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

  renderState("qualifications", "loading");
  try {
    matrix = await getQualificationMatrix(ministryId, matrixTeamId);
    fillMatrixTeamFilter();
    renderMatrix(matrix);
  } catch (error) {
    // Clearing the cache is what makes Retry a genuine retry (§5.8).
    matrix = null;
    renderState("qualifications", "error", errorMessage(error, i18next.t("Could not load the qualifications")));
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

  return occurrence.gaps.map((gap) => `${gap.gapCount} ${gap.positionName ?? ""}`.trim()).join(", ");
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

async function loadOccurrences(force = false): Promise<void> {
  if (occurrences !== null && !force) {
    renderOccurrences(occurrences);

    return;
  }

  renderState("occurrences", "loading");

  try {
    // A fixed forward window: `from`/`to` are mandatory server-side (M9), and a
    // coordinator staffing "the next while" wants roughly a quarter.
    const data = await listOccurrences({
      from: isoDate(-7),
      to: isoDate(90),
      ministryId,
    });
    occurrences = data.occurrences;
    renderOccurrences(occurrences);
  } catch (error) {
    occurrences = null;
    renderState("occurrences", "error", errorMessage(error, i18next.t("Could not load the occurrences")));
  }
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

      return `
        <tr>
          <td>${escapeHtml(schedule.name)}</td>
          <td>${escapeHtml(pattern)}</td>
          <td>${escapeHtml(team?.name ?? i18next.t("Whole ministry"))}</td>
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
    teamSelect.innerHTML =
      `<option value="">${escapeHtml(i18next.t("Whole ministry"))}</option>` +
      (detail?.teams ?? []).map((team) => `<option value="${team.id}">${escapeHtml(team.name)}</option>`).join("");
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
 * The positions a schedule's staffing plan may name: the chosen team's, plus the
 * ministry's team-less ones, which §2.6 says apply to every team under it. What it never
 * offers is another team's positions. A ministry-wide schedule (no team) offers them all.
 *
 * Mirrors `volunteerCandidatePositions()` in the API, which serves the same question for
 * the occurrence-level editor — that one has to, because a team leader editing one week
 * must not need the ministry-wide position list.
 */
function candidatePositionsForTeam(teamId: number | null): VolunteerCandidatePosition[] {
  return (detail?.positions ?? [])
    .filter((position) => position.active)
    .filter((position) => teamId === null || position.teamId === null || position.teamId === teamId)
    .map((position) => ({
      id: position.id,
      name: position.name,
      teamId: position.teamId,
      order: position.order,
    }));
}

/** Re-draw the needs rows for whichever team the form currently names. */
function renderScheduleNeeds(): void {
  const container = byId("schedule-form-needs");
  if (!container) {
    return;
  }

  const raw = byId<HTMLSelectElement>("schedule-form-team")?.value ?? "";
  const teamId = raw === "" ? null : Number(raw);

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
    set("schedule-form-team", schedule?.teamId === null || schedule === undefined ? "" : String(schedule.teamId));
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
    teamId: teamId === "" ? null : Number(teamId),
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
const activated = new Set<PaneName>(["overview"]);

async function load(force = false): Promise<void> {
  if (detail !== null && !force) {
    for (const pane of activated) {
      TAB_RENDERERS[pane](detail);
    }
    return;
  }

  for (const pane of activated) {
    renderState(pane, "loading");
  }

  try {
    detail = await getMinistry(ministryId);
    for (const pane of activated) {
      TAB_RENDERERS[pane](detail);
    }
  } catch (error) {
    // Clearing the cache is what makes Retry a genuine retry (§5.8).
    detail = null;
    const message = errorMessage(error, i18next.t("Could not load this ministry"));
    for (const pane of activated) {
      renderState(pane, "error", message);
    }
  }
}

function activate(tab: TabName): void {
  // The matrix is its own document with its own filter, so it loads itself.
  if (tab === "qualifications") {
    fillMatrixTeamFilter();
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

  activated.add(tab);
  if (detail === null) {
    void load();
    return;
  }
  TAB_RENDERERS[tab](detail);
}

// ─── Editors ─────────────────────────────────────────────────────────────────

function openTeamModal(team?: VolunteerTeam): void {
  editingTeamId = team?.id ?? 0;
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

  modal("teamModal")?.show();
}

function saveTeam(): void {
  const name = byId<HTMLInputElement>("team-form-name")?.value.trim() ?? "";
  const description = byId<HTMLInputElement>("team-form-description")?.value.trim() ?? "";
  const active = byId<HTMLInputElement>("team-form-active")?.checked ?? true;

  if (name === "") {
    showModalError("team", i18next.t("Give the team a name"));
    return;
  }

  // A newly created team is active by construction (§2.4 default), so the switch
  // only needs a follow-up write when the coordinator turned it off while adding.
  const saved =
    editingTeamId === 0
      ? createTeam(ministryId, name, description).then((result) =>
          active ? undefined : updateTeam(result.team.id, { active: false }).then(() => undefined),
        )
      : updateTeam(editingTeamId, { name, description, active }).then(() => undefined);

  saved
    .then(() => {
      modal("teamModal")?.hide();
      notifySuccess(editingTeamId === 0 ? i18next.t("Team added") : i18next.t("Team saved"));
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
    teamSelect.textContent = "";
    const wide = document.createElement("option");
    wide.value = "";
    wide.textContent = i18next.t("Whole ministry");
    teamSelect.append(wide);
    for (const team of detail?.teams ?? []) {
      const option = document.createElement("option");
      option.value = String(team.id);
      option.textContent = team.name;
      teamSelect.append(option);
    }
    teamSelect.value = position?.teamId ? String(position.teamId) : "";
  }

  modal("positionModal")?.show();
}

function savePosition(): void {
  const name = byId<HTMLInputElement>("position-form-name")?.value.trim() ?? "";
  const description = byId<HTMLInputElement>("position-form-description")?.value.trim() ?? "";
  const orderValue = byId<HTMLInputElement>("position-form-order")?.value ?? "0";
  const active = byId<HTMLInputElement>("position-form-active")?.checked ?? true;
  const teamValue = byId<HTMLSelectElement>("position-form-team")?.value ?? "";
  const teamId = teamValue === "" ? null : Number(teamValue);

  if (name === "") {
    showModalError("position", i18next.t("Give the position a name"));
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

// ─── Pools (#9707) ───────────────────────────────────────────────────────────

/**
 * Link a Group as a pool, through `window.CRM.groups.promptSelection()` (G4) —
 * the picker the Groups module already ships, which owns the modal lifecycle,
 * the TomSelect teardown and its own i18n. V2 builds no second group chooser.
 */
function openGroupPicker(owner: { type: "ministry" | "team"; id: number }): void {
  const groups = window.CRM?.groups;
  if (!groups?.promptSelection) {
    notifyError(i18next.t("The group picker is not available on this page"));
    return;
  }

  poolOwner = owner;
  groups.promptSelection({ Type: groups.selectTypes.Group }, (result) => {
    const groupId = Number(result.GroupID);
    if (!groupId) {
      return;
    }

    linkPool(poolOwner, groupId)
      .then(() => {
        notifySuccess(i18next.t("Group linked as a volunteer pool"));
        // The matrix rows come from the pool, so it is stale now.
        matrix = null;
        return load(true);
      })
      .catch((error: unknown) => {
        notifyError(errorMessage(error, i18next.t("The group could not be linked")));
      });
  });
}

// ─── Wiring ──────────────────────────────────────────────────────────────────

function findTeam(id: number): VolunteerTeam | undefined {
  return detail?.teams.find((team) => team.id === id);
}

function findPosition(id: number): VolunteerPosition | undefined {
  return detail?.positions.find((position) => position.id === id);
}

function wirePools(): void {
  byId("pool-add-btn")?.addEventListener("click", () => openGroupPicker({ type: "ministry", id: ministryId }));

  // Delegated: the pool and team rows are re-rendered on every load.
  document.addEventListener("click", (event) => {
    const target = (event.target as HTMLElement | null)?.closest<HTMLElement>(
      ".volunteer-pool-unlink, .volunteer-team-link-pool",
    );
    if (!target) {
      return;
    }

    if (target.classList.contains("volunteer-team-link-pool")) {
      openGroupPicker({ type: "team", id: Number(target.dataset.teamId) });
      return;
    }

    const poolId = Number(target.dataset.poolId);
    confirmDelete(
      i18next.t("Unlink this pool"),
      // Worth spelling out: "unlink" is not "delete the group", and a
      // coordinator who has just been told membership lives in Groups needs to
      // know this button does not touch it.
      i18next.t("Stop using {{name}} as a volunteer pool? The group and its members are not changed.", {
        name: target.dataset.groupName ?? "",
      }),
      () => {
        unlinkPool(poolId)
          .then(() => {
            notifySuccess(i18next.t("Volunteer pool unlinked"));
            matrix = null;
            return load(true);
          })
          .catch((error: unknown) => {
            notifyError(errorMessage(error, i18next.t("The pool could not be unlinked")));
          });
      },
    );
  });
}

function wireQualifications(): void {
  byId("qualification-filter")?.addEventListener("input", applyMatrixFilter);

  byId("qualification-team-filter")?.addEventListener("change", (event) => {
    const value = (event.target as HTMLSelectElement).value;
    matrixTeamId = value === "" ? null : Number(value);
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
   * for fifteen reloads, and the only state that changed is the one this
   * checkbox owns.
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

    const rollback = (error: unknown, fallback: string): void => {
      target.checked = !nowChecked;
      target.disabled = false;
      notifyError(errorMessage(error, fallback));
    };

    if (nowChecked) {
      grantQualification(positionId, personId)
        .then((result) => {
          // Remember the row id so unticking can revoke it with no lookup.
          target.dataset.qualificationId = String(result.qualification.id);
          target.disabled = false;
        })
        .catch((error: unknown) => rollback(error, i18next.t("The qualification could not be saved")));
      return;
    }

    if (!qualificationId) {
      // Nothing to revoke — the box was never really on.
      target.disabled = false;
      return;
    }

    revokeQualification(qualificationId)
      .then(() => {
        // Revocation is deactivation, so the row id stays valid: re-ticking
        // reactivates the same row rather than making a second one (§2.7).
        target.disabled = false;
      })
      .catch((error: unknown) => rollback(error, i18next.t("The qualification could not be removed")));
  });
}

function wire(): void {
  for (const [navId, pane] of [
    ["nav-item-overview", "overview"],
    ["nav-item-teams", "teams"],
    ["nav-item-positions", "positions"],
    ["nav-item-qualifications", "qualifications"],
    ["nav-item-occurrences", "occurrences"],
    ["nav-item-schedules", "schedules"],
  ] as Array<[string, TabName]>) {
    byId(navId)?.addEventListener("shown.bs.tab", () => activate(pane));
  }

  for (const button of document.querySelectorAll(".volunteer-retry")) {
    // The matrix is a separate document, so its Retry must re-run its own load
    // rather than the ministry fetch — otherwise the button appears dead.
    const inMatrix = button.closest("#qualifications") !== null;
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
  wirePools();
  wireQualifications();

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
  const config = (window.CRM?.volunteerMinistry ?? { ministryId: 0, isManager: false }) as MinistryConfig;
  ministryId = config.ministryId;

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
