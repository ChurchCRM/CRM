/**
 * The Schedules table, its Add / Edit dialog (with the staffing-needs editor) and
 * the Generate occurrences action (#9708's API, surfaced by #9711, design §5.4).
 *
 * Extracted verbatim from `webpack/ministries/ministry.ts` for #9868 so the Member
 * Portal's My Teams page gives a team leader the same table and the same dialog
 * for their own team.
 *
 * Per row: where its dates come from in one readable phrase (D22), how many
 * occurrences have been generated, and the three actions that matter — generate
 * more, edit, delete.
 * Generation is idempotent server-side (§2.9), so pressing Generate twice creates
 * nothing the second time; the toast reports what the server actually did rather
 * than assuming.
 *
 * Generate opens a dialog first (review, 2026-09-18): one row per position the
 * schedule's plan asks for, each with a "Fill by default with" picker over the same
 * rotation-ordered list the Assign dialog uses, and — once somebody is chosen — a
 * "Set as Accepted" box. The chosen people are assigned on every occurrence the run
 * creates, never on ones an earlier run made.
 *
 * D32: those defaults belong to the schedule. The schedule dialog's staffing needs show and
 * edit one per position, the Generate dialog opens on them and saves what it runs with, and
 * the daily top-up assigns them on the occurrences it creates.
 *
 * D30: a run that finds no events keeps the dialog open with a warning saying what it
 * looked for, and — where the viewer may create the ministry's events — a button to the
 * New recurring event dialog; a run whose occurrences all existed is an info toast.
 *
 * D31: a schedule follows events that already exist. The dialog offers only titles with
 * upcoming events and shows a class with none disabled ("add its meetings first"); there
 * is no "any event" choice, and the server refuses a schedule that would follow nothing.
 * Generation reaches the church-wide scheduling horizon, in weeks.
 *
 * D33: a NEW schedule generates on Save — the server runs it with the saved defaults — and the
 * page lands on the Occurrences tab with what it made, or says why nothing came (D30's
 * warning). Editing a schedule never generates.
 */

import {
  createSchedule,
  deleteSchedule,
  errorMessage,
  generateOccurrences,
  listClasses,
  listEventSeries,
  listEventTypes,
  listPositionEligiblePeople,
  listScheduleEligiblePeople,
  listScheduleRequirements,
  notifyError,
  notifyInfo,
  notifySuccess,
  notifyWarning,
  updateSchedule,
  type VolunteerCandidatePosition,
  type VolunteerClassGroup,
  type VolunteerEligiblePerson,
  type VolunteerGenerateResult,
  type VolunteerPosition,
  type VolunteerRequirementRow,
  type VolunteerSchedule,
  type VolunteerTeam,
} from "../api";
import { readStaffingNeeds, renderStaffingNeeds, validateStaffingNeeds } from "../staffing-needs";
import {
  destroyDefaultFillPickers,
  mountDefaultFillPickers,
  readDefaultFills,
  renderDefaultFillRow,
  savedDefaultOf,
  wireDefaultFillRows,
} from "./default-fill";
import { offsetSummary, readOffsets, renderOffsetFields, writeOffsets } from "./offsets";
import {
  actionMenu,
  byId,
  confirmDelete,
  destroyDataTable,
  escapeAttribute,
  escapeHtml,
  hideModal,
  initDataTable,
  isoDate,
  modal,
  renderState,
  shortDate,
  show,
  showModalError,
  statusBadge,
  tText,
  wireModalFadeGuard,
} from "./ui";

/** What the ministry's New recurring event dialog starts with when a schedule found nothing (D30). */
export interface MinistryEventPrefill {
  groupId: number | null;
  groupName: string | null;
  title: string | null;
}

/** What Add schedule starts with for a new series no schedule follows yet (D33). */
export interface SchedulePrefill {
  name: string;
  /** Null: the first team, as a new schedule starts. */
  teamId: number | null;
  linkMode: "class" | "ministry";
  groupId: number | null;
  groupName: string | null;
  titleFilter: string | null;
}

export interface SchedulesTableOptions {
  /** The ministry a new schedule is created under. */
  ministryId(): number;
  /** The teams the dialog's Team select offers, first one first. */
  teams(): VolunteerTeam[];
  /** Every position the caller knows about; the needs editor filters it by team. */
  positions(): VolunteerPosition[];
  /** Fetch the schedules this table lists. */
  fetch(): Promise<{ schedules: VolunteerSchedule[] }>;
  /** Generating or deleting moves the occurrence list, which the caller caches. */
  invalidateOccurrences(): void;
  /**
   * D29: may a schedule follow a class's meetings. Absent means yes; when no, "A class's
   * meetings" is offered only to a schedule that already follows a class.
   */
  classesAllowed?(): boolean;
  /**
   * D30: open the ministry's New recurring event dialog, pre-filled for a schedule that found
   * no events. Absent where the viewer may not create the ministry's events (the portal, a
   * team leader), and the warnings then say who adds them instead.
   */
  addEvents?(prefill: MinistryEventPrefill): void;
  /** D33: after a new schedule's Save made occurrences, show them on the Occurrences tab. */
  showOccurrences?(filter: { text: string; teamId: number | null }): void;
}

export interface SchedulesTableHandle {
  load(force?: boolean): Promise<void>;
  invalidate(): void;
  /** Open Add schedule pre-filled (D33). */
  openNew(prefill: SchedulePrefill): void;
}

export function createSchedulesTable(options: SchedulesTableOptions): SchedulesTableHandle {
  /**
   * The schedules, cached like the other lists and cleared on error so Retry is a
   * genuine retry (§5.8).
   */
  let schedules: VolunteerSchedule[] | null = null;
  /** Calendar event types, fetched once for the schedule editor's select. */
  let eventTypes: Array<{ id: number; name: string }> | null = null;
  /** The groups a class schedule may follow, fetched once per page. */
  let classes: VolunteerClassGroup[] | null = null;
  /** Which schedule the modal is editing; 0 means "new". */
  let editingScheduleId = 0;
  /**
   * The staffing plan of the schedule the modal is editing, as stored (§2.10). Empty
   * for a new schedule, which is what makes every position start checked.
   */
  let scheduleRequirements: VolunteerRequirementRow[] = [];
  /** The link mode of the open new-schedule dialog was set by its team's class (D23), not by hand. */
  let modeFromTeamClass = false;
  /** "A class's meetings", kept while it is taken out of the select (D29). */
  let classModeOption: HTMLOptionElement | null = null;
  /** The upcoming count of every title the Event picker offers, for its warning (D30). */
  let seriesCounts = new Map<string, number>();
  let seriesRequest = 0;
  /** Who may be each position's default (D32), fetched once per position per open. */
  let eligibleByPosition = new Map<number, VolunteerEligiblePerson[]>();
  let needsSequence = 0;

  function render(rows: VolunteerSchedule[]): void {
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
        const offsets = offsetSummary(schedule.startOffsetMinutes, schedule.endOffsetMinutes);
        const team = options.teams().find((candidate) => candidate.id === schedule.teamId);
        // Every schedule names a team; an empty cell here would mean the caller's
        // document is stale, not that the schedule is ministry-wide.

        return `
        <tr>
          <td>${escapeHtml(schedule.name)}</td>
          <td>${escapeHtml(describeSource(schedule))}${
            offsets === "" ? "" : `<div class="small text-body-secondary">${escapeHtml(offsets)}</div>`
          }</td>
          <td>${escapeHtml(team?.name ?? "")}</td>
          <td class="text-center">${schedule.occurrenceCount}</td>
          <td class="text-center">${statusBadge(schedule.active)}</td>
          <td class="text-center">
            ${actionMenu([
              {
                type: "button",
                icon: "fa-solid fa-wand-magic-sparkles",
                label: i18next.t("Generate occurrences"),
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
                icon: schedule.active ? "fa-solid fa-box-archive" : "fa-solid fa-rotate-left",
                label: schedule.active ? i18next.t("Deactivate") : i18next.t("Reactivate"),
                className: "volunteer-schedule-toggle-active",
                data: {
                  "schedule-id": schedule.id,
                  "schedule-name": schedule.name,
                  active: schedule.active ? "1" : "0",
                },
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

  /** Where a schedule's dates come from, in words (D22). */
  function describeSource(schedule: VolunteerSchedule): string {
    switch (schedule.linkMode) {
      case "class":
        if (schedule.groupName === null) {
          return i18next.t("A class that no longer exists");
        }

        return schedule.groupSundaySchool
          ? tText("Sunday School: {{name}}", { name: schedule.groupName })
          : tText("Meetings of {{name}}", { name: schedule.groupName });
      case "ministry":
        return schedule.titleFilter
          ? tText("This ministry's events titled {{title}}", { title: schedule.titleFilter })
          : i18next.t("This ministry's events (no event chosen yet)");
      case "event":
        return tText("One event: {{title}}", { title: schedule.eventTitle ?? "" });
      default:
        if (schedule.eventTypeName === null) {
          return i18next.t("An event type that no longer exists");
        }

        return schedule.titleFilter
          ? tText("{{type}} events titled {{title}}", { type: schedule.eventTypeName, title: schedule.titleFilter })
          : tText("{{type}} events (no event chosen yet)", { type: schedule.eventTypeName });
    }
  }

  async function load(force = false): Promise<void> {
    if (schedules !== null && !force) {
      render(schedules);

      return;
    }

    renderState("schedules", "loading");

    try {
      const data = await options.fetch();
      schedules = data.schedules;
      render(schedules);
    } catch (error) {
      schedules = null;
      renderState("schedules", "error", errorMessage(error, i18next.t("Could not load the schedules")));
    }
  }

  function invalidate(): void {
    schedules = null;
  }

  /**
   * Calendar event types for the editor's select, from the ministries surface: a
   * portal team leader cannot reach the core `/api/events/types`. Failure is not
   * fatal — the select is simply empty and the other two sources still work.
   */
  async function loadEventTypes(): Promise<void> {
    if (eventTypes !== null) {
      return;
    }

    try {
      eventTypes = (await listEventTypes()).eventTypes;
    } catch {
      eventTypes = [];
    }
  }

  async function loadClasses(): Promise<void> {
    if (classes !== null) {
      return;
    }

    try {
      classes = (await listClasses()).classes;
    } catch {
      classes = [];
    }
  }

  function fillSelects(schedule?: VolunteerSchedule): void {
    const teamSelect = byId<HTMLSelectElement>("schedule-form-team");
    if (teamSelect) {
      // No "no team" entry: a schedule always belongs to a team, and there is
      // always at least one to offer.
      teamSelect.innerHTML = options
        .teams()
        .map((team) => `<option value="${team.id}">${escapeHtml(team.name)}</option>`)
        .join("");
    }

    const typeSelect = byId<HTMLSelectElement>("schedule-form-event-type");
    if (typeSelect) {
      typeSelect.innerHTML = (eventTypes ?? [])
        .map((type) => `<option value="${type.id}">${escapeHtml(type.name)}</option>`)
        .join("");
    }

    // The class picker, keeping a stored class the list no longer offers (it lost its
    // upcoming events, or stopped being a Sunday School class) rather than dropping it.
    // A class with no meetings to come cannot be chosen (D31) — except the stored one, so
    // an edit of anything else still saves.
    const groupSelect = byId<HTMLSelectElement>("schedule-form-group");
    if (groupSelect) {
      const rows = [...(classes ?? [])];
      if (schedule?.groupId && schedule.groupName && !rows.some((row) => row.groupId === schedule.groupId)) {
        rows.unshift({
          groupId: schedule.groupId,
          name: schedule.groupName,
          sundaySchool: schedule.groupSundaySchool,
          upcomingCount: 0,
          nextStart: null,
        });
      }
      const selectable = (row: VolunteerClassGroup): boolean =>
        row.upcomingCount > 0 || (schedule?.linkMode === "class" && row.groupId === schedule.groupId);
      groupSelect.innerHTML = [
        `<option value="">${escapeHtml(i18next.t("Choose a class"))}</option>`,
        ...rows.map(
          (row) =>
            `<option value="${row.groupId}"${selectable(row) ? "" : " disabled"}>${escapeHtml(
              row.upcomingCount > 0
                ? tText("{{name}} ({{count}} upcoming)", { name: row.name, count: row.upcomingCount })
                : selectable(row)
                  ? tText("{{name}} (no upcoming meetings)", { name: row.name })
                  : tText("{{name}} (no upcoming meetings — add its meetings first)", { name: row.name }),
            )}</option>`,
        ),
      ].join("");

      const hint = byId("schedule-form-group-hint");
      const anyDisabled = rows.some((row) => !selectable(row));
      if (hint && anyDisabled) {
        hint.innerHTML = options.addEvents
          ? `${escapeHtml(i18next.t("A class with no meetings on the calendar cannot be chosen yet."))} <a href="#" id="schedule-form-add-meetings">${escapeHtml(
              i18next.t("Add its meetings first with New recurring event on the Calendar tab"),
            )}</a>`
          : escapeHtml(
              i18next.t(
                "A class with no meetings on the calendar cannot be chosen yet. A coordinator of the ministry adds its meetings on the ministry's Calendar tab.",
              ),
            );
      }
      show(hint, anyDisabled);
    }
  }

  /**
   * The Event picker: the distinct upcoming titles of the chosen type's events, or of
   * this ministry's, so a schedule follows ONE event series (review, 2026-09-18 — a type
   * alone matched every event of that type on a Sunday and made an occurrence for
   * each). There is no "any event" choice (D31): a title is required. The current value
   * is kept, and an unlisted one (an edit of a schedule whose events have passed) is
   * offered as its own option rather than silently dropped.
   */
  async function fillEventSeries(keep?: string): Promise<void> {
    const select = byId<HTMLSelectElement>("schedule-form-title-filter");
    if (!select) {
      return;
    }
    const byMinistry = byId<HTMLSelectElement>("schedule-form-link-mode")?.value === "ministry";
    const typeId = Number(byId<HTMLSelectElement>("schedule-form-event-type")?.value ?? 0);
    const from = byId<HTMLInputElement>("schedule-form-window-start")?.value || undefined;
    const wanted = keep ?? select.value;
    const request = ++seriesRequest;
    // The old list belongs to the previous source; a pick from it before the new one
    // arrives would follow the wrong events.
    select.innerHTML =
      wanted === ""
        ? `<option value="" disabled>${escapeHtml(i18next.t("Loading..."))}</option>`
        : `<option value="${escapeAttribute(wanted)}">${escapeHtml(wanted)}</option>`;
    select.value = wanted;
    select.disabled = true;
    show(byId("schedule-form-title-warning"), false);
    syncDetails();
    let series: Array<{ title: string; count: number }> = [];
    if (byMinistry || typeId > 0) {
      try {
        series = (
          await listEventSeries(byMinistry ? { ministryId: options.ministryId() } : { eventTypeId: typeId }, from)
        ).series;
      } catch {
        series = [];
      }
    }
    if (request !== seriesRequest) {
      return;
    }
    if (wanted !== "" && !series.some((row) => row.title === wanted)) {
      series = [{ title: wanted, count: 0 }, ...series];
    }
    seriesCounts = new Map(series.map((row) => [row.title, row.count]));
    select.disabled = false;
    select.innerHTML = [
      `<option value="" disabled>${escapeHtml(
        series.length === 0 ? i18next.t("No upcoming events to choose from") : i18next.t("Choose an event"),
      )}</option>`,
      ...series.map(
        (row) =>
          `<option value="${escapeAttribute(row.title)}">${escapeHtml(
            row.count > 0 ? tText("{{title}} ({{count}} upcoming)", { title: row.title, count: row.count }) : row.title,
          )}</option>`,
      ),
    ].join("");
    select.value = wanted;
    if (select.value !== wanted) {
      select.value = "";
    }
    syncTitleWarning();
  }

  /** Where the viewer's events come from, for the D30 warnings: the Calendar tab, or a coordinator. */
  function whereToAddEvents(className?: string): string {
    if (!options.addEvents) {
      return i18next.t("A coordinator of the ministry can add them on the ministry's Calendar tab.");
    }

    return className
      ? tText("Add them on the Calendar tab as a recurring event with {{name}} as its class.", { name: className })
      : i18next.t("Add them on the Calendar tab.");
  }

  /** D30: the chosen class has no meetings on the calendar from today on, so the schedule would find nothing. */
  function syncClassWarning(): void {
    const warning = byId("schedule-form-group-warning");
    const select = byId<HTMLSelectElement>("schedule-form-group");
    const groupId = Number(select?.value ?? 0);
    const chosen = (classes ?? []).find((row) => row.groupId === groupId);
    const empty =
      byId<HTMLSelectElement>("schedule-form-link-mode")?.value === "class" &&
      groupId > 0 &&
      (chosen?.upcomingCount ?? 0) === 0;
    if (warning && empty) {
      const name = chosen?.name ?? select?.selectedOptions[0]?.text ?? "";
      warning.textContent = `${tText(
        "{{name}} has no upcoming meetings on the calendar, so this schedule has nothing to staff.",
        { name },
      )} ${whereToAddEvents(name)}`;
    }
    show(warning, empty);
    syncDetails();
  }

  /** D30: the Event picker's choice has no upcoming events, or there are none to choose from. */
  function syncTitleWarning(): void {
    const warning = byId("schedule-form-title-warning");
    const mode = byId<HTMLSelectElement>("schedule-form-link-mode")?.value;
    const title = byId<HTMLSelectElement>("schedule-form-title-filter")?.value ?? "";
    let text = "";
    if (mode === "event_type" || mode === "ministry") {
      if (title !== "" && (seriesCounts.get(title) ?? 0) === 0) {
        text = tText("No upcoming events titled {{title}} are on the calendar.", { title });
      } else if (!Array.from(seriesCounts.values()).some((count) => count > 0)) {
        text =
          mode === "ministry"
            ? i18next.t("This ministry has no upcoming events on the calendar.")
            : i18next.t("No upcoming events of this type are on the calendar.");
      }
      if (text !== "" && mode === "ministry") {
        text = `${text} ${whereToAddEvents()}`;
      }
    }
    if (warning) {
      warning.textContent = text;
    }
    show(warning, text !== "");
    syncDetails();
  }

  /** Nothing below the event choice is shown, and nothing can be saved, until an event (or class) is chosen. */
  function syncDetails(): void {
    const mode = byId<HTMLSelectElement>("schedule-form-link-mode")?.value;
    const chosen =
      mode === "class"
        ? (byId<HTMLSelectElement>("schedule-form-group")?.value ?? "") !== ""
        : (byId<HTMLSelectElement>("schedule-form-title-filter")?.value ?? "") !== "";
    show(byId("schedule-form-details"), chosen);
    show(byId("schedule-form-save"), chosen);
  }

  /**
   * D23 (d): a NEW schedule for a team linked to a Sunday School class starts on that class's
   * meetings. Picking another team undoes only a mode this default chose; a mode the user
   * picked stays theirs, and an edit is never touched. Answers whether the mode changed.
   */
  function applyTeamClassDefault(): boolean {
    const mode = byId<HTMLSelectElement>("schedule-form-link-mode");
    const group = byId<HTMLSelectElement>("schedule-form-group");
    if (editingScheduleId !== 0 || !mode || !group) {
      return false;
    }
    const before = mode.value;

    const teamId = Number(byId<HTMLSelectElement>("schedule-form-team")?.value ?? 0);
    const team = options.teams().find((candidate) => candidate.id === teamId);
    if (team?.classGroupId) {
      if (!Array.from(group.options).some((option) => option.value === String(team.classGroupId))) {
        group.append(new Option(team.classGroupName ?? "", String(team.classGroupId)));
      }
      mode.value = "class";
      const upcoming = (classes ?? []).find((row) => row.groupId === team.classGroupId)?.upcomingCount ?? 0;
      group.value = upcoming > 0 ? String(team.classGroupId) : "";
      modeFromTeamClass = true;
    } else if (modeFromTeamClass) {
      mode.value = "event_type";
      group.value = "";
      modeFromTeamClass = false;
    }
    syncMode();

    return mode.value !== before;
  }

  /** D29: offer "A class's meetings" only to a ministry that may use classes, or to a schedule already on one. */
  function syncClassMode(schedule?: VolunteerSchedule): void {
    const mode = byId<HTMLSelectElement>("schedule-form-link-mode");
    classModeOption ??= mode?.querySelector<HTMLOptionElement>('option[value="class"]') ?? null;
    if (!mode || !classModeOption) {
      return;
    }

    const allowed = (options.classesAllowed?.() ?? true) || schedule?.linkMode === "class";
    if (!allowed) {
      classModeOption.remove();
    } else if (classModeOption.parentElement !== mode) {
      mode.insertBefore(classModeOption, mode.querySelector('option[value="ministry"]'));
    }
  }

  /** Show only the fields the chosen link mode actually uses (§2.8's invariants). */
  function syncMode(): void {
    const mode = byId<HTMLSelectElement>("schedule-form-link-mode")?.value ?? "event_type";
    show(byId("schedule-form-event-type-row"), mode === "event_type");
    show(byId("schedule-form-group-row"), mode === "class");
    show(byId("schedule-form-title-filter-row"), mode === "event_type" || mode === "ministry");
    syncClassWarning();
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
    return options
      .positions()
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

  async function loadEligible(positions: VolunteerCandidatePosition[]): Promise<void> {
    await Promise.all(
      positions
        .filter((position) => !eligibleByPosition.has(position.id))
        .map(async (position) => {
          try {
            eligibleByPosition.set(position.id, (await listPositionEligiblePeople(position.id)).people);
          } catch {
            eligibleByPosition.set(position.id, []);
          }
        }),
    );
  }

  /** Re-draw the needs rows for whichever team the form currently names. */
  async function renderNeeds(): Promise<void> {
    const container = byId("schedule-form-needs");
    if (!container) {
      return;
    }

    const teamId = Number(byId<HTMLSelectElement>("schedule-form-team")?.value ?? 0);
    const positions = candidatePositionsForTeam(teamId);
    const sequence = ++needsSequence;
    await loadEligible(positions);
    if (sequence !== needsSequence) {
      return;
    }

    // A new schedule starts with every position checked — "I just made a team with one
    // position, of course I need one of them" — while an edit reflects the rows that exist,
    // so a position with no requirement shows unchecked, which is what its absence means.
    renderStaffingNeeds(container, positions, scheduleRequirements, editingScheduleId === 0, eligibleByPosition);
  }

  function openModal(schedule?: VolunteerSchedule, prefill?: SchedulePrefill): void {
    editingScheduleId = schedule?.id ?? 0;
    modeFromTeamClass = false;
    show(byId("schedule-form-error"), false);
    scheduleRequirements = [];
    // Refetched on every open: a class's meeting count changes as events are added (D30).
    classes = null;
    seriesCounts = new Map();
    eligibleByPosition = new Map();
    show(byId("schedule-form-title-warning"), false);

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

    const firstTeamId = schedule?.teamId ?? prefill?.teamId ?? options.teams()[0]?.id ?? 0;
    const eligible = loadEligible(candidatePositionsForTeam(firstTeamId));

    void Promise.all([loadEventTypes(), loadClasses(), requirements, eligible]).then(async () => {
      fillSelects(schedule);
      syncClassMode(schedule);
      renderOffsetFields("schedule-form");

      const set = (id: string, value: string): void => {
        const el = byId<HTMLInputElement | HTMLSelectElement>(id);
        if (el) {
          el.value = value;
        }
      };

      set("schedule-form-name", schedule?.name ?? prefill?.name ?? "");
      // A new schedule starts on the first team offered rather than on nothing,
      // because "nothing" is no longer a storable answer.
      set("schedule-form-team", String(schedule?.teamId ?? prefill?.teamId ?? options.teams()[0]?.id ?? ""));
      set("schedule-form-link-mode", schedule?.linkMode ?? prefill?.linkMode ?? "event_type");
      if (schedule?.eventTypeId) {
        set("schedule-form-event-type", String(schedule.eventTypeId));
      }
      set("schedule-form-group", schedule?.groupId ? String(schedule.groupId) : "");
      writeOffsets("schedule-form", schedule?.startOffsetMinutes ?? 0, schedule?.endOffsetMinutes ?? 0);
      set("schedule-form-window-start", schedule?.windowStart ?? isoDate(0));
      set("schedule-form-window-end", schedule?.windowEnd ?? "");

      const title = byId("scheduleModalTitle");
      if (title) {
        title.textContent = schedule ? i18next.t("Edit schedule") : i18next.t("Add schedule");
      }

      if (prefill) {
        applyPrefillClass(prefill);
        syncMode();
      } else {
        syncMode();
        applyTeamClassDefault();
      }
      void fillEventSeries(schedule?.titleFilter ?? prefill?.titleFilter ?? "");
      await renderNeeds();
      modal("scheduleModal")?.show();
    });
  }

  /** The class a pre-filled schedule follows (D33), offered even when the picker did not list it. */
  function applyPrefillClass(prefill: SchedulePrefill): void {
    const group = byId<HTMLSelectElement>("schedule-form-group");
    if (!group || prefill.groupId === null) {
      return;
    }
    if (!Array.from(group.options).some((option) => option.value === String(prefill.groupId))) {
      group.append(new Option(prefill.groupName ?? "", String(prefill.groupId)));
    }
    group.value = String(prefill.groupId);
  }

  function formPayload(): Record<string, unknown> {
    const value = (id: string): string => byId<HTMLInputElement | HTMLSelectElement>(id)?.value?.trim() ?? "";
    const linkMode = value("schedule-form-link-mode");
    const teamId = value("schedule-form-team");

    const payload: Record<string, unknown> = {
      name: value("schedule-form-name"),
      linkMode,
      teamId: Number(teamId),
      windowStart: value("schedule-form-window-start"),
      windowEnd: value("schedule-form-window-end") === "" ? null : value("schedule-form-window-end"),
      ...(editingScheduleId === 0 ? { active: true } : {}),
    };

    if (linkMode === "event_type") {
      payload.eventTypeId = Number(value("schedule-form-event-type")) || null;
    }
    if (linkMode === "event_type" || linkMode === "ministry") {
      payload.titleFilter = value("schedule-form-title-filter");
    }
    if (linkMode === "class") {
      payload.groupId = Number(value("schedule-form-group")) || null;
    }

    const offsets = readOffsets("schedule-form");
    if (typeof offsets !== "string") {
      payload.startOffsetMinutes = offsets.start;
      payload.endOffsetMinutes = offsets.end;
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

  /** D31: every schedule names the events it follows; the server says the same with a 400. */
  function missingSource(): string | null {
    const mode = byId<HTMLSelectElement>("schedule-form-link-mode")?.value;
    if (mode === "class") {
      return (byId<HTMLSelectElement>("schedule-form-group")?.value ?? "") === "" ? i18next.t("Choose a class") : null;
    }

    return (byId<HTMLSelectElement>("schedule-form-title-filter")?.value ?? "") === ""
      ? i18next.t("Choose the event this schedule follows")
      : null;
  }

  function save(): void {
    const needs = byId("schedule-form-needs");
    const offsets = readOffsets("schedule-form");
    const invalid =
      missingSource() ?? (typeof offsets === "string" ? offsets : needs === null ? null : validateStaffingNeeds(needs));
    if (invalid !== null) {
      showModalError("schedule", invalid, notifyError);

      return;
    }

    const payload = formPayload();
    const request: Promise<void> =
      editingScheduleId === 0
        ? createSchedule(options.ministryId(), payload).then((result) => {
            hideModal("scheduleModal");
            reportNewSchedule(result.schedule, result.generated);
          })
        : updateSchedule(editingScheduleId, payload).then(() => {
            hideModal("scheduleModal");
            notifySuccess(i18next.t("Schedule saved"));
          });

    request
      .then(() => load(true))
      .catch((error: unknown) => {
        showModalError("schedule", errorMessage(error, i18next.t("The schedule could not be saved")), notifyError);
      });
  }

  /**
   * D33: a new schedule's Save generated it. What it made lands on the Occurrences tab with the
   * run's numbers; a run that found nothing says why, in D30's words, and stays here.
   */
  function reportNewSchedule(schedule: VolunteerSchedule, run: VolunteerGenerateResult | null): void {
    options.invalidateOccurrences();
    if (run === null) {
      notifySuccess(
        i18next.t('Schedule "{{name}}" created. It is inactive, so it makes no occurrences until it is active.', {
          name: schedule.name,
        }),
      );

      return;
    }
    if (run.noEvents) {
      const found = describeNothingFound(run, schedule);
      notifyWarning(
        [
          i18next.t('Schedule "{{name}}" created.', { name: schedule.name }),
          escapeHtml(found.text),
          escapeHtml(found.hint),
        ]
          .filter((part) => part !== "")
          .join(" "),
      );

      return;
    }

    notifySuccess(
      [
        i18next.t('Schedule "{{name}}" created: {{count}} occurrences', { name: schedule.name, count: run.created }),
        ...defaultCounts(run),
      ].join(". "),
    );
    options.showOccurrences?.({ text: schedule.name, teamId: schedule.teamId });
  }

  /** What a run's default volunteers came to (D32), for its toast. */
  function defaultCounts(run: VolunteerGenerateResult): string[] {
    const parts: string[] = [];
    if (run.assigned > 0) {
      parts.push(i18next.t("{{count}} volunteers assigned", { count: run.assigned }));
    }
    if (run.skipped > 0) {
      parts.push(i18next.t("{{count}} could not be assigned", { count: run.skipped }));
    }
    if (run.unqualified > 0) {
      parts.push(
        i18next.t("{{count}} left open because the default is no longer qualified", { count: run.unqualified }),
      );
    }

    return parts;
  }

  // ── Generate occurrences (2026-09-18) ───────────────────────────────────

  /** The schedule the Generate dialog is open for; 0 when it is closed. */
  let generatingScheduleId = 0;
  let generatingSchedule: VolunteerSchedule | null = null;
  /** What the warning's New recurring event button opens with, after a run found nothing (D30). */
  let nothingFoundPrefill: MinistryEventPrefill | null = null;
  /** One TomSelect per position row, torn down when the dialog closes. */
  let generateSelects: TomSelectInstance[] = [];

  function destroyGenerateSelects(): void {
    destroyDefaultFillPickers(generateSelects);
    generateSelects = [];
  }

  function openGenerateModal(schedule: VolunteerSchedule): void {
    generatingScheduleId = schedule.id;
    generatingSchedule = schedule;
    nothingFoundPrefill = null;
    show(byId("generate-form-warning"), false);
    destroyGenerateSelects();

    const intro = byId("generate-form-intro");
    if (intro) {
      intro.textContent = generateIntro(schedule);
    }
    const rows = byId("generate-form-rows");
    if (rows) {
      rows.innerHTML = "";
    }
    show(byId("generate-form-error"), false);
    show(byId("generate-form-empty"), false);
    show(byId("generate-form-loading"), true);
    const save = byId<HTMLButtonElement>("generate-form-save");
    if (save) {
      save.disabled = true;
    }

    modal("generateOccurrencesModal")?.show();

    listScheduleRequirements(schedule.id)
      .then(async (data) => {
        // Only the positions the plan actually asks for: a Max of 0 is "not on this
        // schedule", and offering a default for it would assign into a slot no
        // occurrence has.
        const wanted = data.requirements.filter((row) => row.maxCount === null || row.maxCount > 0);
        const html: string[] = [];
        for (const row of wanted) {
          const position = options.positions().find((candidate) => candidate.id === row.positionId);
          const name = row.positionName ?? position?.name ?? "";
          let people: VolunteerEligiblePerson[] = [];
          try {
            people = (await listScheduleEligiblePeople(schedule.id, row.positionId)).people;
          } catch {
            people = [];
          }
          html.push(
            renderDefaultFillRow(
              "generate-default",
              row.positionId,
              name,
              row.minCount,
              row.maxCount,
              people,
              savedDefaultOf(row),
            ),
          );
        }

        if (generatingScheduleId !== schedule.id) {
          return;
        }
        if (rows) {
          rows.innerHTML = html.join("");
        }
        show(byId("generate-form-empty"), wanted.length === 0);

        generateSelects = mountDefaultFillPickers(rows);
      })
      .catch((error: unknown) => {
        showModalError(
          "generate",
          errorMessage(error, i18next.t("The schedule's staffing needs could not be loaded")),
          notifyError,
        );
      })
      .finally(() => {
        show(byId("generate-form-loading"), false);
        if (save) {
          save.disabled = false;
        }
      });
  }

  /** D31: how far this run reaches, in the horizon's weeks, or the schedule's own last date. */
  function generateIntro(schedule: VolunteerSchedule): string {
    const through = shortDate(schedule.generateThrough);
    if (schedule.windowEnd !== null && schedule.windowEnd <= schedule.generateThrough) {
      return tText("Occurrences are created for events through {{date}}, when this schedule ends.", { date: through });
    }

    if (schedule.horizonWeeks === 1) {
      return tText("Occurrences are created for events in the next week (through {{date}}).", { date: through });
    }

    return tText("Occurrences are created for events in the next {{count}} weeks (through {{date}}).", {
      count: schedule.horizonWeeks,
      date: through,
    });
  }

  function runGenerate(): void {
    const scheduleId = generatingScheduleId;
    if (scheduleId === 0) {
      return;
    }
    const save = byId<HTMLButtonElement>("generate-form-save");
    if (save) {
      save.disabled = true;
    }

    const defaults = readDefaultFills(byId("generate-form-rows"));
    const anyDefault = defaults.some((row) => row.personId !== null);
    generateOccurrences(scheduleId, { defaults })
      .then((result) => {
        // Nothing to anchor to is not a success: the dialog stays open and says why (D30).
        if (result.noEvents) {
          showNothingFound(result);

          return;
        }

        hideModal("generateOccurrencesModal");
        // The server's own numbers, not an assumption: generation is idempotent, so a
        // run whose occurrences were all there already is reported as such, neutrally.
        if (result.created === 0) {
          const parts = [
            i18next.t("No new occurrences. All {{count}} events through {{through}} already have one.", {
              count: result.existing,
              through: shortDate(result.through),
            }),
          ];
          if (anyDefault) {
            parts.push(
              i18next.t("The default volunteers are saved on the schedule and go on the occurrences made from now on."),
            );
          }
          notifyInfo(parts.join(" "));

          return;
        }

        const parts = [
          i18next.t("{{created}} occurrences created, {{existing}} were already there", {
            created: result.created,
            existing: result.existing,
          }),
          ...defaultCounts(result),
        ];
        notifySuccess(parts.join(". "));
        options.invalidateOccurrences();

        return load(true);
      })
      .catch((error: unknown) => {
        showModalError(
          "generate",
          errorMessage(error, i18next.t("The occurrences could not be generated")),
          notifyError,
        );
      })
      .finally(() => {
        if (save) {
          save.disabled = false;
        }
      });
  }

  /** D30: the warning that replaces the success toast when a run found no events at all. */
  function showNothingFound(result: VolunteerGenerateResult): void {
    const found = describeNothingFound(result, generatingSchedule);
    const text = byId("generate-form-warning-text");
    if (text) {
      text.textContent = found.text;
    }
    const hint = byId("generate-form-warning-hint");
    if (hint) {
      hint.textContent = found.hint;
    }
    show(hint, found.hint !== "");

    nothingFoundPrefill = options.addEvents ? found.prefill : null;
    const button = byId<HTMLButtonElement>("generate-form-add-events");
    if (button) {
      button.textContent = "";
      button.insertAdjacentHTML(
        "beforeend",
        `<i class="fa-solid fa-repeat me-1" aria-hidden="true"></i>${escapeHtml(found.button)}`,
      );
    }
    show(button, nothingFoundPrefill !== null);

    const box = byId("generate-form-warning");
    show(box, true);
    box?.scrollIntoView({ block: "nearest" });
  }

  /** Why a run found nothing, in the schedule's own terms, and what would fix it. */
  function describeNothingFound(
    result: VolunteerGenerateResult,
    schedule: VolunteerSchedule | null,
  ): {
    text: string;
    hint: string;
    button: string;
    prefill: MinistryEventPrefill | null;
  } {
    const name = schedule?.name ?? "";
    const from = shortDate(result.from);
    const to = shortDate(result.through);
    const source = result.searched;
    const noButton = { button: "", prefill: null };

    if (result.from > result.through) {
      const windowEnd = schedule?.windowEnd ?? null;
      if (windowEnd !== null && windowEnd < result.from) {
        return {
          ...noButton,
          text: tText("{{name}} ended on {{date}}, so there are no dates left to generate.", {
            name,
            date: shortDate(windowEnd),
          }),
          hint: i18next.t("Edit the schedule and move its last date to follow more events."),
        };
      }

      return {
        ...noButton,
        text: tText("{{name}} starts on {{date}}, after the dates generated now (through {{through}}).", {
          name,
          date: shortDate(schedule?.windowStart ?? result.from),
          through: to,
        }),
        hint: i18next.t("Generate again closer to its first date."),
      };
    }

    const coordinatorAdds = i18next.t(
      "A coordinator of the ministry can add them on the ministry's Calendar tab. Then generate again.",
    );

    // Saved before D31 without a title: it follows nothing until one is chosen.
    if ((source.linkMode === "event_type" || source.linkMode === "ministry") && !source.titleFilter) {
      return {
        ...noButton,
        text: tText("{{name}} does not name the event it follows, so it has nothing to staff.", { name }),
        hint: i18next.t("Edit the schedule and choose the event it follows."),
      };
    }

    switch (source.linkMode) {
      case "class": {
        if (source.groupName === null) {
          return {
            ...noButton,
            text: i18next.t("The class this schedule follows no longer exists."),
            hint: i18next.t("Edit the schedule and choose another class."),
          };
        }
        const className = source.groupName;

        return {
          text: tText("No events on the calendar use {{name}} as their class between {{from}} and {{to}}.", {
            name: className,
            from,
            to,
          }),
          hint: options.addEvents
            ? tText("Add the class's meetings as a recurring event with {{name}} as its class, then generate again.", {
                name: className,
              })
            : coordinatorAdds,
          button: tText("New recurring event for {{name}}", { name: className }),
          prefill: { groupId: source.groupId, groupName: className, title: null },
        };
      }
      case "ministry": {
        const ministry = source.ministryName ?? "";
        const title = source.titleFilter;

        return {
          text: title
            ? tText("{{ministry}} has no events titled {{title}} between {{from}} and {{to}}.", {
                ministry,
                title,
                from,
                to,
              })
            : tText("{{ministry}} has no events between {{from}} and {{to}}.", { ministry, from, to }),
          hint: options.addEvents ? i18next.t("Add them on the Calendar tab, then generate again.") : coordinatorAdds,
          button: title ? tText("New recurring event titled {{title}}", { title }) : i18next.t("New recurring event"),
          prefill: { groupId: null, groupName: null, title },
        };
      }
      case "event_type": {
        if (source.eventTypeName === null) {
          return {
            ...noButton,
            text: i18next.t("The event type this schedule follows no longer exists."),
            hint: i18next.t("Edit the schedule and choose another event type."),
          };
        }
        const type = source.eventTypeName;

        return {
          ...noButton,
          text: source.titleFilter
            ? tText("No {{type}} events titled {{title}} between {{from}} and {{to}}.", {
                type,
                title: source.titleFilter,
                from,
                to,
              })
            : tText("No {{type}} events between {{from}} and {{to}}.", { type, from, to }),
          hint: i18next.t(
            "Church events of a type are added on the church calendar. Check the schedule's event type, title and dates.",
          ),
        };
      }
      default:
        return {
          ...noButton,
          text: tText("No event was found between {{from}} and {{to}}.", { from, to }),
          hint: "",
        };
    }
  }

  function wire(): void {
    wireModalFadeGuard("scheduleModal");
    wireModalFadeGuard("generateOccurrencesModal");
    byId("generate-form-save")?.addEventListener("click", runGenerate);
    byId("generate-form-add-events")?.addEventListener("click", () => {
      const prefill = nothingFoundPrefill;
      if (prefill === null || !options.addEvents) {
        return;
      }
      byId("generateOccurrencesModal")?.addEventListener("hidden.bs.modal", () => options.addEvents?.(prefill), {
        once: true,
      });
      hideModal("generateOccurrencesModal");
    });
    byId("generateOccurrencesModal")?.addEventListener("hidden.bs.modal", destroyGenerateSelects);
    wireDefaultFillRows(byId("generate-form-rows"));

    // Focus the first field once the modal has finished animating. Without it
    // Bootstrap's own `shown.bs.modal` handler moves focus to the dialog partway
    // through the 150 ms fade and swallows whatever was typed in the meantime.
    byId("scheduleModal")?.addEventListener("shown.bs.modal", () => {
      byId<HTMLInputElement>("schedule-form-name")?.focus();
    });

    byId("schedule-add-btn")?.addEventListener("click", () => openModal());
    document.addEventListener("click", (event) => {
      const link = (event.target as HTMLElement | null)?.closest("#schedule-form-add-meetings");
      if (!link || !options.addEvents) {
        return;
      }
      event.preventDefault();
      const team = options
        .teams()
        .find((candidate) => candidate.id === Number(byId<HTMLSelectElement>("schedule-form-team")?.value ?? 0));
      const empty =
        team?.classGroupId &&
        !(classes ?? []).some((row) => row.groupId === team.classGroupId && row.upcomingCount > 0);
      const prefill = empty
        ? { groupId: team.classGroupId ?? null, groupName: team.classGroupName ?? null, title: null }
        : { groupId: null, groupName: null, title: null };
      byId("scheduleModal")?.addEventListener("hidden.bs.modal", () => options.addEvents?.(prefill), { once: true });
      hideModal("scheduleModal");
    });
    byId("schedule-form-save")?.addEventListener("click", save);
    byId("schedule-form-link-mode")?.addEventListener("change", () => {
      modeFromTeamClass = false;
      syncMode();
      void fillEventSeries("");
    });
    byId("schedule-form-event-type")?.addEventListener("change", () => {
      void fillEventSeries("");
    });
    byId("schedule-form-group")?.addEventListener("change", syncClassWarning);
    byId("schedule-form-title-filter")?.addEventListener("change", syncTitleWarning);
    // Positions are team-scoped, so the list of things that can be needed changes with the
    // team. Re-rendering discards whatever was typed for the old team's positions, which is
    // correct: those rows are no longer part of this schedule's plan.
    byId("schedule-form-team")?.addEventListener("change", () => {
      void renderNeeds();
      if (applyTeamClassDefault()) {
        void fillEventSeries("");
      }
    });

    // Delegated: the rows are re-rendered on every load, so per-row listeners
    // would go stale.
    document.addEventListener("click", (event) => {
      const target = (event.target as HTMLElement | null)?.closest<HTMLElement>(
        ".volunteer-schedule-edit, .volunteer-schedule-delete, .volunteer-schedule-generate, .volunteer-schedule-toggle-active",
      );
      if (!target) {
        return;
      }

      if (target.classList.contains("volunteer-schedule-edit")) {
        openModal((schedules ?? []).find((row) => row.id === Number(target.dataset.scheduleId)));

        return;
      }

      if (target.classList.contains("volunteer-schedule-toggle-active")) {
        const scheduleId = Number(target.dataset.scheduleId);
        const activate = target.dataset.active !== "1";
        const run = (): void => {
          updateSchedule(scheduleId, { active: activate })
            .then(() => {
              notifySuccess(activate ? i18next.t("Schedule reactivated") : i18next.t("Schedule deactivated"));

              return load(true);
            })
            .catch((error: unknown) => {
              notifyError(
                errorMessage(
                  error,
                  activate
                    ? i18next.t("The schedule could not be reactivated")
                    : i18next.t("The schedule could not be deactivated"),
                ),
              );
            });
        };
        if (activate) {
          run();
        } else {
          confirmDelete(
            i18next.t("Deactivate schedule"),
            i18next.t(
              "Deactivate {{name}}? No new occurrences are made for it, by Generate or the daily top-up. Its existing occurrences and assignments stay, and it can be reactivated.",
              { name: target.dataset.scheduleName ?? "" },
            ),
            run,
          );
        }

        return;
      }

      if (target.classList.contains("volunteer-schedule-generate")) {
        const schedule = (schedules ?? []).find((row) => row.id === Number(target.dataset.scheduleId));
        if (schedule) {
          openGenerateModal(schedule);
        }

        return;
      }

      const scheduleId = Number(target.dataset.scheduleId);
      confirmDelete(
        i18next.t("Delete schedule"),
        i18next.t("Delete {{name}}? This cannot be undone.", { name: target.dataset.scheduleName ?? "" }),
        () => {
          deleteSchedule(scheduleId)
            .then(() => {
              notifySuccess(i18next.t("Schedule deleted"));
              options.invalidateOccurrences();

              return load(true);
            })
            .catch((error: unknown) => {
              notifyError(errorMessage(error, i18next.t("The schedule could not be deleted")));
            });
        },
      );
    });
  }

  wire();

  return { load, invalidate, openNew: (prefill) => openModal(undefined, prefill) };
}
