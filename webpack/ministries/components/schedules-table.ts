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
 */

import {
  createSchedule,
  deleteSchedule,
  errorMessage,
  generateOccurrences,
  listClasses,
  listEventSeries,
  listEventTypes,
  listScheduleEligiblePeople,
  listScheduleRequirements,
  notifyError,
  notifySuccess,
  updateSchedule,
  type VolunteerCandidatePosition,
  type VolunteerClassGroup,
  type VolunteerEligiblePerson,
  type VolunteerGenerateDefault,
  type VolunteerPosition,
  type VolunteerRequirementRow,
  type VolunteerSchedule,
  type VolunteerTeam,
} from "../api";
import { readStaffingNeeds, renderStaffingNeeds, validateStaffingNeeds } from "../staffing-needs";
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
  show,
  showModalError,
  statusBadge,
  tText,
  wireModalFadeGuard,
} from "./ui";

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
}

export interface SchedulesTableHandle {
  load(force?: boolean): Promise<void>;
  invalidate(): void;
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
          : i18next.t("All of this ministry's events");
      case "event":
        return tText("One event: {{title}}", { title: schedule.eventTitle ?? "" });
      default:
        if (schedule.eventTypeName === null) {
          return i18next.t("An event type that no longer exists");
        }

        return schedule.titleFilter
          ? tText("{{type}} events titled {{title}}", { type: schedule.eventTypeName, title: schedule.titleFilter })
          : tText("All {{type}} events", { type: schedule.eventTypeName });
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
      groupSelect.innerHTML = [
        `<option value="">${escapeHtml(i18next.t("Choose a class"))}</option>`,
        ...rows.map(
          (row) =>
            `<option value="${row.groupId}">${escapeHtml(
              row.upcomingCount > 0
                ? tText("{{name}} ({{count}} upcoming)", { name: row.name, count: row.upcomingCount })
                : row.name,
            )}</option>`,
        ),
      ].join("");
    }
  }

  /**
   * The Event picker: the distinct upcoming titles of the chosen type's events, or of
   * this ministry's, so a schedule follows ONE event series (review, 2026-09-18 — a type
   * alone matched every event of that type on a Sunday and made an occurrence for
   * each). The current value is kept, and an unlisted one (an edit of a schedule whose
   * events have passed) is offered as its own option rather than silently dropped.
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
    if (wanted !== "" && !series.some((row) => row.title === wanted)) {
      series = [{ title: wanted, count: 0 }, ...series];
    }
    select.innerHTML = [
      `<option value="">${escapeHtml(
        byMinistry ? i18next.t("Any of this ministry's events") : i18next.t("Any event of this type"),
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
  }

  /** Show only the fields the chosen link mode actually uses (§2.8's invariants). */
  function syncMode(): void {
    const mode = byId<HTMLSelectElement>("schedule-form-link-mode")?.value ?? "event_type";
    show(byId("schedule-form-event-type-row"), mode === "event_type");
    show(byId("schedule-form-group-row"), mode === "class");
    show(byId("schedule-form-title-filter-row"), mode === "event_type" || mode === "ministry");
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

  /** Re-draw the needs rows for whichever team the form currently names. */
  function renderNeeds(): void {
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

  function openModal(schedule?: VolunteerSchedule): void {
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

    void Promise.all([loadEventTypes(), loadClasses(), requirements]).then(() => {
      fillSelects(schedule);
      renderOffsetFields("schedule-form");

      const set = (id: string, value: string): void => {
        const el = byId<HTMLInputElement | HTMLSelectElement>(id);
        if (el) {
          el.value = value;
        }
      };

      set("schedule-form-name", schedule?.name ?? "");
      // A new schedule starts on the first team offered rather than on nothing,
      // because "nothing" is no longer a storable answer.
      set("schedule-form-team", String(schedule?.teamId ?? options.teams()[0]?.id ?? ""));
      set("schedule-form-link-mode", schedule?.linkMode ?? "event_type");
      if (schedule?.eventTypeId) {
        set("schedule-form-event-type", String(schedule.eventTypeId));
      }
      set("schedule-form-group", schedule?.groupId ? String(schedule.groupId) : "");
      writeOffsets("schedule-form", schedule?.startOffsetMinutes ?? 0, schedule?.endOffsetMinutes ?? 0);
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

      syncMode();
      void fillEventSeries(schedule?.titleFilter ?? "");
      renderNeeds();
      modal("scheduleModal")?.show();
    });
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
      active: byId<HTMLInputElement>("schedule-form-active")?.checked ?? true,
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

  function save(): void {
    const needs = byId("schedule-form-needs");
    const offsets = readOffsets("schedule-form");
    const invalid = typeof offsets === "string" ? offsets : needs === null ? null : validateStaffingNeeds(needs);
    if (invalid !== null) {
      showModalError("schedule", invalid, notifyError);

      return;
    }

    const payload = formPayload();
    const request =
      editingScheduleId === 0
        ? createSchedule(options.ministryId(), payload)
        : updateSchedule(editingScheduleId, payload);

    request
      .then(() => {
        hideModal("scheduleModal");
        notifySuccess(editingScheduleId === 0 ? i18next.t("Schedule created") : i18next.t("Schedule saved"));

        return load(true);
      })
      .catch((error: unknown) => {
        showModalError("schedule", errorMessage(error, i18next.t("The schedule could not be saved")), notifyError);
      });
  }

  // ── Generate occurrences (2026-09-18) ───────────────────────────────────

  /** The schedule the Generate dialog is open for; 0 when it is closed. */
  let generatingScheduleId = 0;
  /** One TomSelect per position row, torn down when the dialog closes. */
  let generateSelects: TomSelectInstance[] = [];

  function destroyGenerateSelects(): void {
    for (const instance of generateSelects) {
      try {
        instance.destroy();
      } catch (_e) {
        // The rows may already be gone; nothing left to tear down.
      }
    }
    generateSelects = [];
  }

  function describeCandidate(person: VolunteerEligiblePerson): string {
    const served =
      person.lastServedDate === null
        ? i18next.t("has not served yet")
        : tText("last served {{date}}", { date: person.lastServedDate });

    return `${person.displayName} — ${served}`;
  }

  /** The picker's options: blank first, then the pool, then the rest — the Assign dialog's grouping. */
  function candidateOptions(people: VolunteerEligiblePerson[]): string {
    const option = (person: VolunteerEligiblePerson): string =>
      `<option value="${person.personId}" data-in-pool="${person.inPool ? "1" : "0"}">${escapeHtml(
        describeCandidate(person),
      )}</option>`;
    const inPool = people.filter((person) => person.inPool);
    const outside = people.filter((person) => !person.inPool);

    return [
      `<option value="">${escapeHtml(i18next.t("Leave open"))}</option>`,
      inPool.length === 0
        ? ""
        : `<optgroup label="${escapeAttribute(i18next.t("In the volunteer pool"))}">${inPool.map(option).join("")}</optgroup>`,
      outside.length === 0
        ? ""
        : `<optgroup label="${escapeAttribute(i18next.t("Not in the pool"))}">${outside.map(option).join("")}</optgroup>`,
    ].join("");
  }

  function needsPhrase(min: number, max: number | null): string {
    if (max !== null && max > min) {
      return tText("{{min}} to {{max}} needed", { min, max });
    }

    return tText("{{count}} needed", { count: min });
  }

  function syncAcceptedBox(select: HTMLSelectElement): void {
    const row = select.closest<HTMLElement>(".generate-default-row");
    const wrap = row?.querySelector<HTMLElement>(".generate-default-accepted-wrap") ?? null;
    const chosen = select.value !== "";
    show(wrap, chosen);
    if (!chosen) {
      const box = row?.querySelector<HTMLInputElement>(".generate-default-accepted");
      if (box) {
        box.checked = false;
      }
    }
  }

  function renderGenerateRow(
    positionId: number,
    positionName: string,
    min: number,
    max: number | null,
    people: VolunteerEligiblePerson[],
  ): string {
    const selectId = `generate-default-${positionId}`;

    return `
      <div class="generate-default-row mb-3" data-position-id="${positionId}">
        <div class="fw-medium mb-1">
          ${escapeHtml(positionName)}
          <span class="text-body-secondary fw-normal">— ${escapeHtml(needsPhrase(min, max))}</span>
        </div>
        ${
          people.length === 0
            ? `<div class="form-hint generate-default-empty">${escapeHtml(
                i18next.t("Nobody is qualified for this position yet, so it stays open."),
              )}</div>`
            : `<div class="row g-2 align-items-end">
                <div class="col-12 col-md-8">
                  <label class="form-label mb-1" for="${selectId}">${escapeHtml(i18next.t("Fill by default with"))}:</label>
                  <select class="form-select generate-default-select" id="${selectId}" data-position-id="${positionId}">
                    ${candidateOptions(people)}
                  </select>
                </div>
                <div class="col-12 col-md-4 pb-md-2">
                  <label class="form-check d-none generate-default-accepted-wrap">
                    <input class="form-check-input generate-default-accepted" type="checkbox">
                    <span class="form-check-label">${escapeHtml(i18next.t("Set as Accepted"))}</span>
                  </label>
                </div>
              </div>`
        }
      </div>`;
  }

  function openGenerateModal(schedule: VolunteerSchedule): void {
    generatingScheduleId = schedule.id;
    destroyGenerateSelects();

    const intro = byId("generate-form-intro");
    if (intro) {
      intro.textContent = tText("{{name}}: the occurrences are built for the next {{days}} days.", {
        name: schedule.name,
        days: schedule.generateAheadDays,
      });
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
          html.push(renderGenerateRow(row.positionId, name, row.minCount, row.maxCount, people));
        }

        if (generatingScheduleId !== schedule.id) {
          return;
        }
        if (rows) {
          rows.innerHTML = html.join("");
        }
        show(byId("generate-form-empty"), wanted.length === 0);

        // The same shared TomSelect every other picker uses, body-mounted so the
        // dialog cannot clip its dropdown, with no option cap (#9819).
        if (window.TomSelect) {
          for (const select of rows?.querySelectorAll<HTMLSelectElement>("select.generate-default-select") ?? []) {
            generateSelects.push(
              new window.TomSelect(select, {
                dropdownParent: "body",
                maxOptions: null,
                onChange: () => syncAcceptedBox(select),
              }),
            );
          }
        }
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

  function readGenerateDefaults(): VolunteerGenerateDefault[] {
    const defaults: VolunteerGenerateDefault[] = [];
    for (const row of byId("generate-form-rows")?.querySelectorAll<HTMLElement>(".generate-default-row") ?? []) {
      const select = row.querySelector<HTMLSelectElement>("select.generate-default-select");
      const personId = Number(select?.value ?? 0);
      if (!select || personId <= 0) {
        continue;
      }
      defaults.push({
        positionId: Number(row.dataset.positionId),
        personId,
        accepted: row.querySelector<HTMLInputElement>(".generate-default-accepted")?.checked ?? false,
      });
    }

    return defaults;
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

    generateOccurrences(scheduleId, { defaults: readGenerateDefaults() })
      .then((result) => {
        hideModal("generateOccurrencesModal");
        // The server's own numbers, not an assumption: generation is idempotent,
        // so "created 0, 8 already there" is a perfectly good outcome to report.
        const parts = [
          i18next.t("{{created}} occurrences created, {{existing}} were already there", {
            created: result.created,
            existing: result.existing,
          }),
        ];
        if (result.assigned > 0) {
          parts.push(i18next.t("{{count}} volunteers assigned", { count: result.assigned }));
        }
        if (result.skipped > 0) {
          parts.push(i18next.t("{{count}} could not be assigned", { count: result.skipped }));
        }
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

  function wire(): void {
    wireModalFadeGuard("scheduleModal");
    wireModalFadeGuard("generateOccurrencesModal");
    byId("generate-form-save")?.addEventListener("click", runGenerate);
    byId("generateOccurrencesModal")?.addEventListener("hidden.bs.modal", destroyGenerateSelects);
    // The Accepted box only means something once a person is named, so it appears
    // with the choice and goes away with it. Delegated: the rows are re-rendered per
    // open, and TomSelect fires a native `change` on the wrapped select as well.
    byId("generate-form-rows")?.addEventListener("change", (event) => {
      const select = (event.target as HTMLElement | null)?.closest<HTMLSelectElement>("select.generate-default-select");
      if (select) {
        syncAcceptedBox(select);
      }
    });

    // Focus the first field once the modal has finished animating. Without it
    // Bootstrap's own `shown.bs.modal` handler moves focus to the dialog partway
    // through the 150 ms fade and swallows whatever was typed in the meantime.
    byId("scheduleModal")?.addEventListener("shown.bs.modal", () => {
      byId<HTMLInputElement>("schedule-form-name")?.focus();
    });

    byId("schedule-add-btn")?.addEventListener("click", () => openModal());
    byId("schedule-form-save")?.addEventListener("click", save);
    byId("schedule-form-link-mode")?.addEventListener("change", () => {
      syncMode();
      void fillEventSeries("");
    });
    byId("schedule-form-event-type")?.addEventListener("change", () => {
      void fillEventSeries("");
    });
    // Positions are team-scoped, so the list of things that can be needed changes with the
    // team. Re-rendering discards whatever was typed for the old team's positions, which is
    // correct: those rows are no longer part of this schedule's plan.
    byId("schedule-form-team")?.addEventListener("change", renderNeeds);

    // Delegated: the rows are re-rendered on every load, so per-row listeners
    // would go stale.
    document.addEventListener("click", (event) => {
      const target = (event.target as HTMLElement | null)?.closest<HTMLElement>(
        ".volunteer-schedule-edit, .volunteer-schedule-delete, .volunteer-schedule-generate",
      );
      if (!target) {
        return;
      }

      if (target.classList.contains("volunteer-schedule-edit")) {
        openModal((schedules ?? []).find((row) => row.id === Number(target.dataset.scheduleId)));

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

  return { load, invalidate };
}
