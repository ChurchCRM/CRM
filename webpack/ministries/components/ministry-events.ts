/**
 * The ministry page's Calendar tab (D24, design §5.4): the events the ministry owns, with
 * each one's staffing per team and — for past events — its headcount total (D26), the
 * one dialog behind **New event** and **New recurring event**, and **Delete events** for
 * the ticked rows (D28).
 *
 * The events are core's. The dialog posts to `POST /ministries/{id}/events`, which creates
 * them through the calendar's own code with the ministry's id and, when "Staff these
 * events" is on, the schedule that staffs them in the same transaction. Editing one event
 * stays in the core event editor, which the row menu links to; Delete events goes through
 * core's delete path too, on the server.
 *
 * The staffing section reuses what the Schedules and Generate dialogs already are: the
 * staffing-needs editor, the two Volunteer times rows and the "Fill by default with" rows.
 */

import {
  createMinistryEvents,
  deleteMinistryEvents,
  errorMessage,
  listCalendars,
  listClasses,
  listEventTypes,
  listMinistryEvents,
  listPinnableCalendars,
  listPositionEligiblePeople,
  notifyError,
  notifySuccess,
  type VolunteerEligiblePerson,
  type VolunteerMinistryEvent,
  type VolunteerMinistryEventInput,
  type VolunteerMinistryEventStaffing,
  type VolunteerPosition,
  type VolunteerTeam,
} from "../api";
import { readStaffingNeeds, renderStaffingNeeds, validateStaffingNeeds } from "../staffing-needs";
import {
  destroyDefaultFillPickers,
  mountDefaultFillPickers,
  readDefaultFills,
  renderDefaultFillRow,
  restoreDefaultFills,
  wireDefaultFillRows,
} from "./default-fill";
import { readOffsets, renderOffsetFields, writeOffsets } from "./offsets";
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
  tText,
  wireModalFadeGuard,
  wireUnclippedRowMenus,
} from "./ui";

/** EventService::MAX_REPEAT_OCCURRENCES — the server refuses a larger series. */
const MAX_SERIES_EVENTS = 366;

/** The values the API takes for a weekly recurrence; the labels are the browser's. */
const WEEKDAYS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

export interface MinistryEventsOptions {
  ministryId(): number;
  /** D29: may the ministry's events have a class. */
  sundaySchool(): boolean;
  teams(): VolunteerTeam[];
  positions(): VolunteerPosition[];
  ensureContext(): Promise<void>;
  occurrenceUrl(occurrenceId: number): string;
  /** Open the Staff an event dialog with this event chosen. */
  staffEvent(event: { id: number; title: string; start: string }): void;
  /** New events may have come with a schedule and occurrences the other tabs cache. */
  invalidateStaffing(): void;
}

export interface MinistryEventsHandle {
  load(force?: boolean): Promise<void>;
  invalidate(): void;
}

type Recurrence = NonNullable<VolunteerMinistryEventInput["recurrence"]>;

export function createMinistryEventsTab(options: MinistryEventsOptions): MinistryEventsHandle {
  let events: VolunteerMinistryEvent[] | null = null;
  /** The lists the dialog's selects are filled from, fetched on first open. */
  let eventTypes: Array<{ id: number; name: string }> | null = null;
  let classes: Array<{ groupId: number; name: string }> | null = null;
  let calendars: Array<{ id: number; name: string; own: boolean }> | null = null;
  /** The class field was set by the staffing team's class (D23), not by hand. */
  let classFromTeam = false;
  const eligible = new Map<number, VolunteerEligiblePerson[]>();
  let defaultPickers: TomSelectInstance[] = [];
  let defaultsSequence = 0;

  const root = (): string => window.CRM?.root ?? "";
  const pastShown = (): boolean => byId<HTMLInputElement>("ministry-events-past")?.checked ?? false;

  // ── The table ─────────────────────────────────────────────────────────────

  function when(event: VolunteerMinistryEvent): string {
    const sameDay = event.start.slice(0, 10) === event.end.slice(0, 10);

    return `${event.start.slice(0, 16)} – ${sameDay ? event.end.slice(11, 16) : event.end.slice(0, 16)}`;
  }

  function staffingBadge(team: VolunteerMinistryEventStaffing): string {
    const look = {
      gap: {
        cls: "bg-red-lt text-red",
        tip: tText("{{total}} still needed", { total: team.gap }),
      },
      pending: {
        cls: "bg-yellow-lt text-yellow",
        tip: tText("Every position is assigned; {{total}} not yet confirmed", { total: team.pending }),
      },
      filled: { cls: "bg-green-lt text-green", tip: i18next.t("Every position is filled and confirmed") },
      unplanned: { cls: "bg-secondary-lt text-secondary", tip: i18next.t("No staffing needs set") },
    }[team.status];
    const label =
      team.status === "unplanned"
        ? tText("{{team}}: no staffing needs set", { team: team.teamName })
        : tText("{{team}}: {{filled}} of {{needed}}", {
            team: team.teamName,
            filled: team.filled,
            needed: team.needed,
          });

    return `<a class="badge ${look.cls} me-1 mb-1 ministry-event-staffing" data-team-id="${team.teamId}" data-status="${team.status}"
              href="${escapeAttribute(options.occurrenceUrl(team.occurrenceIds[0]))}" title="${escapeAttribute(look.tip)}">${escapeHtml(label)}</a>`;
  }

  function row(event: VolunteerMinistryEvent): string {
    const upcoming = event.start.slice(0, 10) >= isoDate(0);
    const badges = (items: Array<{ name: string }>, cls: string): string =>
      items.map((item) => `<span class="badge ${cls} me-1 mb-1">${escapeHtml(item.name)}</span>`).join("");

    const staffing =
      event.staffing.length === 0
        ? `<span class="text-body-secondary">${escapeHtml(i18next.t("Not staffed"))}</span>`
        : event.staffing.map(staffingBadge).join("");
    const headcount = upcoming
      ? ""
      : `<div class="small text-body-secondary ministry-event-headcount">${escapeHtml(
          event.headcount.recorded
            ? tText("Headcount: {{total}}", { total: event.headcount.total })
            : i18next.t("No headcount recorded yet"),
        )}</div>`;

    const menu = actionMenu([
      upcoming &&
        !event.inactive && {
          type: "button",
          icon: "fa-solid fa-user-plus",
          label: i18next.t("Staff this event"),
          className: "ministry-event-staff",
          data: { "event-id": event.id },
        },
      {
        type: "link",
        icon: "fa-solid fa-pen",
        label: i18next.t("Edit event"),
        href: `${root()}/event/editor/${event.id}`,
      },
      {
        type: "link",
        icon: "fa-solid fa-eye",
        label: i18next.t("View event"),
        href: `${root()}/event/view/${event.id}`,
      },
    ]);

    return `
      <tr data-event-id="${event.id}">
        <td class="w-1 no-export">
          <input type="checkbox" class="form-check-input ministry-event-select" data-event-id="${event.id}"
                 aria-label="${escapeAttribute(tText("Select {{event}}", { event: `${when(event)} ${event.title}` }))}">
        </td>
        <td data-order="${escapeAttribute(event.start)}">${escapeHtml(when(event))}</td>
        <td>
          <a class="fw-bold" href="${root()}/event/view/${event.id}">${escapeHtml(event.title)}</a>${
            event.inactive
              ? ` <span class="badge bg-secondary-lt text-secondary">${escapeHtml(i18next.t("Inactive"))}</span>`
              : ""
          }
          <div class="small text-body-secondary">${escapeHtml(event.eventTypeName ?? "")}</div>
        </td>
        <td>${badges(event.calendars, "bg-azure-lt text-azure")}</td>
        <td>${badges(event.linkedGroups, "bg-purple-lt text-purple")}</td>
        <td>${staffing}${headcount}</td>
        <td class="w-1">${menu}</td>
      </tr>`;
  }

  function render(rows: VolunteerMinistryEvent[]): void {
    const body = byId("volunteerMinistryEventsTable")?.querySelector("tbody");
    if (!body) {
      return;
    }

    destroyDataTable("volunteerMinistryEventsTable");

    if (rows.length === 0) {
      body.innerHTML = "";
      show(byId("ministry-events-empty-upcoming"), !pastShown());
      show(byId("ministry-events-empty-past"), pastShown());
      renderState("ministry-events", "empty");
      syncSelection();

      return;
    }

    body.innerHTML = rows.map(row).join("");
    renderState("ministry-events", "loaded");
    initDataTable("volunteerMinistryEventsTable", {
      order: [[1, pastShown() ? "desc" : "asc"]],
      // Sorted on the ISO start in `data-order`, left-aligned like the other text columns.
      columnDefs: [
        { targets: 0, orderable: false, searchable: false },
        { targets: 1, type: "string" },
        { targets: 6, orderable: false, searchable: false },
      ],
    });
    syncSelection();
  }

  // ── Selection and Delete events (D28) ─────────────────────────────────────

  function selectedIds(): number[] {
    return Array.from(
      document.querySelectorAll<HTMLInputElement>("#volunteerMinistryEventsTable .ministry-event-select:checked"),
    ).map((box) => Number(box.dataset.eventId));
  }

  /** The button wakes up while something is ticked; every copy of Select All mirrors the rows. */
  function syncSelection(): void {
    const boxes = Array.from(
      document.querySelectorAll<HTMLInputElement>("#volunteerMinistryEventsTable .ministry-event-select"),
    );
    const checked = boxes.filter((box) => box.checked).length;
    const button = byId<HTMLButtonElement>("ministry-events-delete-btn");
    if (button) {
      button.disabled = checked === 0;
      button.textContent = "";
      button.insertAdjacentHTML(
        "beforeend",
        `<i class="fa-solid fa-trash me-1" aria-hidden="true"></i>${escapeHtml(
          checked > 0 ? tText("Delete events ({{count}})", { count: checked }) : i18next.t("Delete events"),
        )}`,
      );
    }
    for (const all of document.querySelectorAll<HTMLInputElement>("#ministry-events-select-all")) {
      all.checked = boxes.length > 0 && checked === boxes.length;
      all.indeterminate = checked > 0 && checked < boxes.length;
      all.disabled = boxes.length === 0;
    }
  }

  /**
   * Confirms with how many events go and which other ministries staff them — the server refuses
   * while their volunteers are assigned, unless the user manages the calendar.
   */
  function deleteSelected(): void {
    const ids = selectedIds();
    if (ids.length === 0) {
      return;
    }
    const staffedBy = new Set<string>();
    for (const event of events ?? []) {
      if (ids.includes(event.id)) {
        for (const other of event.otherStaffing) {
          if (other.assigned > 0) {
            staffedBy.add(other.ministryName);
          }
        }
      }
    }

    const message = [
      i18next.t(
        "Delete {{count}} events? They leave every calendar, with their check-ins and headcounts, and this ministry's staffing of them keeps only its date. This cannot be undone.",
        { count: ids.length },
      ),
      staffedBy.size > 0
        ? i18next.t(
            "Volunteers of {{names}} are assigned to some of them. Unless you manage the calendar, the delete is refused until that staffing is removed.",
            { names: Array.from(staffedBy).join(", ") },
          )
        : "",
    ]
      .filter((part) => part !== "")
      .map((part) => `<p>${part}</p>`)
      .join("");

    confirmDelete(i18next.t("Delete events"), message, () => {
      const button = byId<HTMLButtonElement>("ministry-events-delete-btn");
      if (button) {
        button.disabled = true;
      }
      deleteMinistryEvents(options.ministryId(), ids)
        .then((result) => {
          notifySuccess(tText("{{count}} events deleted", { count: result.deleted }));
          options.invalidateStaffing();

          return load(true);
        })
        .catch((error: unknown) => {
          notifyError(errorMessage(error, i18next.t("The events could not be deleted")));
          syncSelection();
        });
    });
  }

  async function load(force = false): Promise<void> {
    if (events !== null && !force) {
      render(events);

      return;
    }

    renderState("ministry-events", "loading");

    try {
      await options.ensureContext();
      events = (await listMinistryEvents(options.ministryId(), pastShown())).events;
      render(events);
    } catch (error) {
      events = null;
      renderState("ministry-events", "error", errorMessage(error, i18next.t("Could not load this ministry's events")));
    }
  }

  function invalidate(): void {
    events = null;
  }

  // ── The dialog ────────────────────────────────────────────────────────────

  const field = <T extends HTMLElement = HTMLInputElement>(name: string): T | null =>
    byId<T>(`ministry-event-form-${name}`);
  const value = (name: string): string =>
    field<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>(name)?.value.trim() ?? "";
  const isSeries = (): boolean => field("series")?.checked ?? false;
  const staffing = (): boolean => field("staff-toggle")?.checked ?? false;

  async function loadChoices(): Promise<void> {
    const ministryId = options.ministryId();
    const [types, classList, calendarList] = await Promise.all([
      eventTypes === null ? listEventTypes().then((data) => data.eventTypes) : Promise.resolve(eventTypes),
      classes === null ? listClasses().then((data) => data.classes) : Promise.resolve(classes),
      calendars === null
        ? Promise.all([listCalendars(), listPinnableCalendars(ministryId)]).then(([all, pinnable]) =>
            all.Calendars.filter((calendar) => pinnable.calendarIds.includes(calendar.Id)).map((calendar) => ({
              id: calendar.Id,
              name: calendar.Name,
              own: calendar.MinistryId === ministryId,
            })),
          )
        : Promise.resolve(calendars),
    ]);
    eventTypes = types;
    classes = classList;
    calendars = calendarList;
  }

  function fillSelects(): void {
    const typeSelect = field<HTMLSelectElement>("type");
    if (typeSelect) {
      typeSelect.innerHTML = [
        `<option value="">${escapeHtml(i18next.t("Choose an event type"))}</option>`,
        ...(eventTypes ?? []).map((type) => `<option value="${type.id}">${escapeHtml(type.name)}</option>`),
      ].join("");
    }

    const classSelect = field<HTMLSelectElement>("class");
    if (classSelect) {
      classSelect.innerHTML = [
        `<option value="">${escapeHtml(i18next.t("No class"))}</option>`,
        ...(classes ?? []).map((row) => `<option value="${row.groupId}">${escapeHtml(row.name)}</option>`),
      ].join("");
    }

    const dow = field<HTMLSelectElement>("dow");
    if (dow && dow.options.length === 0) {
      // 2023-01-01 was a Sunday, so day `i` of that week names WEEKDAYS[i] in the reader's language.
      dow.innerHTML = WEEKDAYS.map(
        (day, index) =>
          `<option value="${day}">${escapeHtml(
            new Date(2023, 0, 1 + index).toLocaleDateString(undefined, { weekday: "long" }),
          )}</option>`,
      ).join("");
    }

    const month = field<HTMLSelectElement>("doy-month");
    if (month && month.options.length === 0) {
      month.innerHTML = Array.from(
        { length: 12 },
        (_, index) =>
          `<option value="${String(index + 1).padStart(2, "0")}">${escapeHtml(
            new Date(2023, index, 1).toLocaleDateString(undefined, { month: "long" }),
          )}</option>`,
      ).join("");
    }

    const list = field("calendars");
    if (list) {
      list.innerHTML =
        (calendars ?? []).length === 0
          ? `<div class="form-hint" id="ministry-event-form-no-calendars">${escapeHtml(
              i18next.t("No calendar is open to this ministry, so the events will not show on any calendar."),
            )}</div>`
          : (calendars ?? [])
              .map(
                (calendar) => `
                <label class="form-check form-check-inline">
                  <input class="form-check-input ministry-event-calendar" type="checkbox" value="${calendar.id}" ${
                    calendar.own ? "checked" : ""
                  }>
                  <span class="form-check-label">${escapeHtml(calendar.name)}</span>
                </label>`,
              )
              .join("");
    }

    const team = field<HTMLSelectElement>("team");
    if (team) {
      team.innerHTML = options
        .teams()
        .filter((candidate) => candidate.active)
        .map((candidate) => `<option value="${candidate.id}">${escapeHtml(candidate.name)}</option>`)
        .join("");
    }
  }

  function syncKind(): void {
    show(field("once-fields"), !isSeries());
    show(field("series-fields"), isSeries());
    const title = byId("ministryEventModalTitle");
    if (title) {
      title.textContent = isSeries() ? i18next.t("New recurring event") : i18next.t("New event");
    }
    syncRecurrence();
  }

  function syncRecurrence(): void {
    const type = value("recur-type");
    show(field("dow-row"), type === "weekly");
    show(field("dom-row"), type === "monthly");
    show(field("doy-row"), type === "yearly");
    renderPreview();
  }

  function recurrence(): Recurrence {
    const type = value("recur-type") as Recurrence["type"];
    if (type === "monthly") {
      return { type, dom: Number(value("dom")) };
    }
    if (type === "yearly") {
      return { type, doy: `${value("doy-month")}-${value("doy-day").padStart(2, "0")}` };
    }

    return { type: "weekly", dow: value("dow") };
  }

  /** The dates the server's recurrence engine (RecurrenceDateGenerator) will make, for the preview. */
  function seriesDates(rule: Recurrence, first: string, last: string): Date[] {
    const start = new Date(`${first}T12:00:00`);
    const end = new Date(`${last}T12:00:00`);
    const dates: Date[] = [];
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
      return dates;
    }

    if (rule.type === "weekly") {
      const day = new Date(start);
      day.setDate(day.getDate() + ((WEEKDAYS.indexOf(rule.dow ?? "Sunday") - day.getDay() + 7) % 7));
      for (; day <= end && dates.length <= MAX_SERIES_EVENTS; day.setDate(day.getDate() + 7)) {
        dates.push(new Date(day));
      }
    } else if (rule.type === "monthly") {
      for (
        const month = new Date(start.getFullYear(), start.getMonth(), 1, 12);
        month <= end && dates.length <= MAX_SERIES_EVENTS;
        month.setMonth(month.getMonth() + 1)
      ) {
        const lastDay = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
        const date = new Date(month.getFullYear(), month.getMonth(), Math.min(rule.dom ?? 1, lastDay), 12);
        if (date >= start && date <= end) {
          dates.push(date);
        }
      }
    } else {
      const [mm, dd] = (rule.doy ?? "01-01").split("-").map(Number);
      for (let year = start.getFullYear(); year <= end.getFullYear(); year++) {
        const date = new Date(year, mm - 1, dd, 12);
        if (date.getMonth() === mm - 1 && date >= start && date <= end) {
          dates.push(date);
        }
      }
    }

    return dates;
  }

  function renderPreview(): void {
    const preview = field("preview");
    if (!preview) {
      return;
    }
    const first = value("range-start");
    const last = value("range-end");
    if (!isSeries() || first === "" || last === "" || last < first) {
      show(preview, false);

      return;
    }

    const rule = recurrence();
    const dates = seriesDates(rule, first, last);
    const short = (date: Date): string => date.toLocaleDateString(undefined, { month: "short", day: "numeric" });
    let text: string;
    if (dates.length === 0) {
      text = i18next.t("No date between the first and last date matches the recurrence");
    } else if (dates.length > MAX_SERIES_EVENTS) {
      text = tText("That is more than {{max}} events. Choose a shorter range.", { max: MAX_SERIES_EVENTS });
    } else {
      const range = { total: dates.length, first: short(dates[0]), last: short(dates[dates.length - 1]) };
      if (rule.type === "weekly") {
        text = tText("Creates {{total}} events, every {{day}} from {{first}} to {{last}}", {
          ...range,
          day: dates[0].toLocaleDateString(undefined, { weekday: "long" }),
        });
      } else if (rule.type === "monthly") {
        text = tText("Creates {{total}} events, on day {{day}} of each month from {{first}} to {{last}}", {
          ...range,
          day: rule.dom,
        });
      } else {
        text = tText("Creates {{total}} events, every year on {{date}} from {{first}} to {{last}}", {
          ...range,
          date: short(dates[0]),
        });
      }
    }
    preview.textContent = text;
    show(preview, true);
  }

  // ── Staff these events ────────────────────────────────────────────────────

  function teamPositions(): VolunteerPosition[] {
    const teamId = Number(value("team"));

    return options.positions().filter((position) => position.active && position.teamId === teamId);
  }

  function renderNeeds(): void {
    const container = field("needs");
    if (container) {
      renderStaffingNeeds(
        container,
        teamPositions().map((position) => ({
          id: position.id,
          name: position.name,
          teamId: position.teamId,
          teamName: position.teamName,
          order: position.order,
        })),
        [],
        true,
      );
    }
    void renderDefaults();
  }

  /** One "Fill by default with" row per position the plan asks for, as the Generate dialog draws them. */
  async function renderDefaults(): Promise<void> {
    const container = field("defaults");
    const needs = field("needs");
    if (!container || !needs) {
      return;
    }
    const sequence = ++defaultsSequence;
    const wanted = readStaffingNeeds(needs).filter((need) => need.maxCount === null || (need.maxCount ?? 0) > 0);
    const positionsById = new Map(teamPositions().map((position) => [position.id, position]));

    const html: string[] = [];
    for (const need of wanted) {
      if (!eligible.has(need.positionId)) {
        try {
          eligible.set(need.positionId, (await listPositionEligiblePeople(need.positionId)).people);
        } catch {
          eligible.set(need.positionId, []);
        }
      }
      html.push(
        renderDefaultFillRow(
          "ministry-event-default",
          need.positionId,
          positionsById.get(need.positionId)?.name ?? "",
          need.minCount,
          need.maxCount ?? null,
          eligible.get(need.positionId) ?? [],
        ),
      );
    }
    if (sequence !== defaultsSequence) {
      return;
    }

    const previous = readDefaultFills(container);
    destroyDefaultFillPickers(defaultPickers);
    container.innerHTML = html.join("");
    defaultPickers = mountDefaultFillPickers(container);
    restoreDefaultFills(container, defaultPickers, previous);
  }

  /** D23 (d): a team linked to a class suggests that class, until the class is chosen by hand. */
  function applyTeamClass(): void {
    const select = field<HTMLSelectElement>("class");
    if (!select || !options.sundaySchool()) {
      return;
    }
    const team = options.teams().find((candidate) => candidate.id === Number(value("team")));
    if (team?.classGroupId && (select.value === "" || classFromTeam)) {
      if (!Array.from(select.options).some((option) => option.value === String(team.classGroupId))) {
        select.append(new Option(team.classGroupName ?? "", String(team.classGroupId)));
      }
      select.value = String(team.classGroupId);
      classFromTeam = true;
    } else if (!team?.classGroupId && classFromTeam) {
      select.value = "";
      classFromTeam = false;
    }
  }

  /** A class the staffing team suggested goes away with the staffing; one chosen by hand stays. */
  function syncStaffing(): void {
    show(field("staff"), staffing());
    if (staffing()) {
      applyTeamClass();
    } else if (classFromTeam) {
      const select = field<HTMLSelectElement>("class");
      if (select) {
        select.value = "";
      }
      classFromTeam = false;
    }
  }

  // ── Open and save ─────────────────────────────────────────────────────────

  async function openModal(series: boolean): Promise<void> {
    try {
      await options.ensureContext();
      await loadChoices();
    } catch (error) {
      notifyError(errorMessage(error, i18next.t("Could not load the event choices")));

      return;
    }

    // Qualifications may have changed on the Volunteers tab since the last open.
    eligible.clear();
    fillSelects();
    renderOffsetFields("ministry-event-form");
    writeOffsets("ministry-event-form", 0, 0);
    for (const name of ["title", "description", "date", "range-start", "range-end", "dom", "doy-day"]) {
      const input = field<HTMLInputElement>(name);
      if (input) {
        input.value = "";
      }
    }
    const set = (name: string, text: string): void => {
      const input = field<HTMLInputElement | HTMLSelectElement>(name);
      if (input) {
        input.value = text;
      }
    };
    set("date", isoDate(1));
    set("range-start", isoDate(1));
    set("start-time", "09:00");
    set("end-time", "10:00");
    set("recur-type", "weekly");
    set("dom", "1");
    set("doy-month", "01");
    set("doy-day", "1");

    show(field("class-row"), options.sundaySchool());

    const kind = field(series ? "series" : "once");
    if (kind) {
      (kind as HTMLInputElement).checked = true;
    }
    const toggle = field("staff-toggle");
    if (toggle) {
      toggle.checked = false;
    }
    classFromTeam = false;
    show(field("error"), false);

    syncKind();
    syncStaffing();
    renderNeeds();

    byId("ministryEventModal")?.addEventListener(
      "shown.bs.modal",
      () => {
        field("title")?.focus();
      },
      { once: true },
    );
    modal("ministryEventModal")?.show();
  }

  function payload(): VolunteerMinistryEventInput | string {
    const title = value("title");
    const eventTypeId = Number(value("type"));
    const startTime = value("start-time");
    const endTime = value("end-time");
    if (title === "") {
      return i18next.t("An event title is required");
    }
    if (!eventTypeId) {
      return i18next.t("Choose an existing event type");
    }
    if (startTime === "" || endTime === "" || endTime <= startTime) {
      return i18next.t("The event must end after it starts");
    }

    const body: VolunteerMinistryEventInput = {
      title,
      eventTypeId,
      description: value("description"),
      linkedGroupId: options.sundaySchool() ? Number(value("class")) || null : null,
      calendarIds: Array.from(
        document.querySelectorAll<HTMLInputElement>("#ministry-event-form-calendars .ministry-event-calendar:checked"),
      ).map((box) => Number(box.value)),
      startTime,
      endTime,
    };

    if (isSeries()) {
      if (value("range-start") === "" || value("range-end") === "") {
        return i18next.t("Choose the first and last date");
      }
      if (value("range-end") < value("range-start")) {
        return i18next.t("The last date is before the first date");
      }
      body.recurrence = recurrence();
      body.rangeStart = value("range-start");
      body.rangeEnd = value("range-end");
    } else {
      if (value("date") === "") {
        return i18next.t("Choose the date of the event");
      }
      body.date = value("date");
    }

    if (staffing()) {
      const needs = field("needs");
      const offsets = readOffsets("ministry-event-form");
      if (typeof offsets === "string") {
        return offsets;
      }
      const invalid = needs === null ? null : validateStaffingNeeds(needs);
      if (invalid !== null) {
        return invalid;
      }
      body.staff = {
        teamId: Number(value("team")),
        requirements: needs === null ? [] : readStaffingNeeds(needs),
        startOffsetMinutes: offsets.start,
        endOffsetMinutes: offsets.end,
        defaults: readDefaultFills(field("defaults")),
      };
    }

    return body;
  }

  function save(): void {
    const body = payload();
    if (typeof body === "string") {
      showModalError("ministry-event", body);

      return;
    }

    show(field("error"), false);
    const button = field<HTMLButtonElement>("save");
    if (button) {
      button.disabled = true;
    }

    createMinistryEvents(options.ministryId(), body)
      .then((result) => {
        hideModal("ministryEventModal");
        const parts = [tText("Events created: {{total}}", { total: result.events.length })];
        if ((result.assigned ?? 0) > 0) {
          parts.push(tText("Volunteers assigned: {{total}}", { total: result.assigned }));
        }
        notifySuccess(parts.join(". "));
        if (result.schedule) {
          options.invalidateStaffing();
        }

        return load(true);
      })
      .catch((error: unknown) => {
        showModalError("ministry-event", errorMessage(error, i18next.t("The events could not be created")));
      })
      .finally(() => {
        if (button) {
          button.disabled = false;
        }
      });
  }

  function wire(): void {
    wireUnclippedRowMenus("ministry-events-table-wrapper");
    wireModalFadeGuard("ministryEventModal");
    wireDefaultFillRows(field("defaults"));

    byId("ministry-events-past")?.addEventListener("change", () => {
      void load(true);
    });
    byId("ministry-event-add-btn")?.addEventListener("click", () => {
      void openModal(false);
    });
    byId("ministry-event-add-series-btn")?.addEventListener("click", () => {
      void openModal(true);
    });

    for (const name of ["once", "series"]) {
      field(name)?.addEventListener("change", syncKind);
    }
    field("recur-type")?.addEventListener("change", syncRecurrence);
    for (const name of ["dow", "dom", "doy-month", "doy-day", "range-start", "range-end"]) {
      field(name)?.addEventListener("input", renderPreview);
      field(name)?.addEventListener("change", renderPreview);
    }
    field("class")?.addEventListener("change", () => {
      classFromTeam = false;
    });
    field("staff-toggle")?.addEventListener("change", syncStaffing);
    field("team")?.addEventListener("change", () => {
      renderNeeds();
      applyTeamClass();
    });
    field("needs")?.addEventListener("change", () => {
      void renderDefaults();
    });
    field("save")?.addEventListener("click", save);
    byId("ministryEventModal")?.addEventListener("hidden.bs.modal", () => {
      destroyDefaultFillPickers(defaultPickers);
      defaultPickers = [];
    });

    // Delegated on the document: the rows are re-rendered on every load, and DataTables may
    // clone the header the Select All box lives in.
    document.addEventListener("change", (event) => {
      const target = event.target as HTMLInputElement | null;
      if (target?.classList.contains("ministry-event-select")) {
        syncSelection();
      } else if (target?.id === "ministry-events-select-all") {
        for (const box of document.querySelectorAll<HTMLInputElement>(
          "#volunteerMinistryEventsTable .ministry-event-select",
        )) {
          box.checked = target.checked;
        }
        syncSelection();
      }
    });
    byId("ministry-events-delete-btn")?.addEventListener("click", deleteSelected);

    // Delegated: the rows are re-rendered on every load.
    document.addEventListener("click", (event) => {
      const target = (event.target as HTMLElement | null)?.closest<HTMLElement>(".ministry-event-staff");
      if (!target) {
        return;
      }
      const chosen = (events ?? []).find((row) => row.id === Number(target.dataset.eventId));
      if (chosen) {
        options.staffEvent({ id: chosen.id, title: chosen.title, start: chosen.start });
      }
    });
  }

  wire();

  return { load, invalidate };
}
