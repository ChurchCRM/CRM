/**
 * The ministry page's Calendar tab (D24, design §5.4): the events the ministry owns, with
 * each one's staffing per team and — for past events — its headcount total (D26), the
 * one dialog behind **New event** and **New recurring event**, and **Delete events** for
 * the ticked rows (D28).
 *
 * The events are core's. The dialog posts to `POST /ministries/{id}/events`, which creates
 * them through the calendar's own code with the ministry's id — and only creates them (D33).
 * Then it asks whether to staff them: one event opens Staff an event with it chosen; a series
 * goes to the schedules that already follow it (`POST …/events/staff`, the server decides the
 * match) and the Occurrences tab shows what they made, or — when none follows it — Add
 * schedule opens for it. Editing one event stays in the core event editor, which the row menu
 * links to; Delete events goes through core's delete path too, on the server.
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
  notifyError,
  notifyInfo,
  notifySuccess,
  staffMinistryEvents,
  type VolunteerMinistryEvent,
  type VolunteerMinistryEventInput,
  type VolunteerMinistryEventStaffing,
  type VolunteerTeam,
} from "../api";
import type { MinistryEventPrefill, SchedulePrefill } from "./schedules-table";
import { rollupStaffing, STAFFING_BADGE_CLASS, staffingTitle } from "./staffing-label";
import {
  actionMenu,
  appLocale,
  byId,
  confirmDelete,
  destroyDataTable,
  escapeAttribute,
  escapeHtml,
  formatIsoDate,
  hideModal,
  initDataTable,
  isoDate,
  modal,
  renderState,
  shortDate,
  shortDateTimeRange,
  show,
  showModalError,
  tText,
  wireModalFadeGuard,
  wireUnclippedRowMenus,
} from "./ui";

/** EventService::MAX_REPEAT_OCCURRENCES — the server refuses a larger series. */
const MAX_SERIES_EVENTS = 366;

/** The values the API takes for a weekly recurrence; the labels are in ChurchCRM's locale. */
const WEEKDAYS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

export interface MinistryEventsOptions {
  ministryId(): number;
  /** D29: may the ministry's events have a class. */
  sundaySchool(): boolean;
  teams(): VolunteerTeam[];
  ensureContext(): Promise<void>;
  occurrenceUrl(occurrenceId: number): string;
  /** Open the Staff an event dialog with this event chosen. */
  staffEvent(event: { id: number; title: string; start: string }): void;
  /** Staffing new events made occurrences the other tabs cache. */
  invalidateStaffing(): void;
  /** Switch to the Occurrences tab, narrowed to these (D33). */
  showOccurrences(filter: { text: string }): void;
  /** Switch to the Schedules tab and open Add schedule pre-filled (D33). */
  addSchedule(prefill: SchedulePrefill): void;
}

export interface MinistryEventsHandle {
  load(force?: boolean): Promise<void>;
  invalidate(): void;
  /** Open New event / New recurring event, optionally pre-filled for a schedule that found nothing (D30). */
  openNew(series: boolean, prefill?: MinistryEventPrefill): Promise<void>;
}

type Recurrence = NonNullable<VolunteerMinistryEventInput["recurrence"]>;

export function createMinistryEventsTab(options: MinistryEventsOptions): MinistryEventsHandle {
  let events: VolunteerMinistryEvent[] | null = null;
  /** The lists the dialog's selects are filled from, fetched on first open. */
  let eventTypes: Array<{ id: number; name: string }> | null = null;
  /** The type a new event starts with (D31): Ministry Settings' choice, else "Other". */
  let defaultEventTypeId: number | null = null;
  let classes: Array<{ groupId: number; name: string }> | null = null;
  let calendars: Array<{ id: number; name: string; own: boolean }> | null = null;
  /** The Last date a series stops on, restored when the field is cleared (D33). */
  let lastRangeEnd = "";
  /** The Last date was chosen by hand, so moving the first date no longer moves it. */
  let rangeEndChosen = false;

  const root = (): string => window.CRM?.root ?? "";
  const pastShown = (): boolean => byId<HTMLInputElement>("ministry-events-past")?.checked ?? false;

  // ── The table ─────────────────────────────────────────────────────────────

  function staffingBadge(team: VolunteerMinistryEventStaffing): string {
    const counts =
      team.status === "unplanned"
        ? null
        : rollupStaffing({
            requiredCount: team.needed,
            capacity: team.capacity,
            liveCount: team.filled,
            gapCount: team.gap,
            openCount: team.openCount,
            pendingCount: team.pending,
          });
    const tone = counts?.tone ?? "secondary";
    const label =
      counts === null
        ? tText("{{team}}: no staffing needs set", { team: team.teamName })
        : tText("{{team}}: {{staffing}}", { team: team.teamName, staffing: counts.label });
    const tip = counts === null ? i18next.t("No staffing needs set") : staffingTitle(counts);

    return `<a class="badge ${STAFFING_BADGE_CLASS[tone]} me-1 mb-1 ministry-event-staffing" data-team-id="${team.teamId}" data-status="${team.status}" data-tone="${tone}"
              href="${escapeAttribute(options.occurrenceUrl(team.occurrenceIds[0]))}" title="${escapeAttribute(tip)}">${escapeHtml(label)}</a>`;
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
                 aria-label="${escapeAttribute(tText("Select {{event}}", { event: `${shortDateTimeRange(event.start, event.end)} ${event.title}` }))}">
        </td>
        <td data-order="${escapeAttribute(event.start)}">${escapeHtml(shortDateTimeRange(event.start, event.end))}</td>
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
      i18next.t("Events to delete: {{total}}", { total: ids.length }),
      i18next.t(
        "Deleted events leave every calendar, with their check-ins and headcounts, and this ministry's staffing of them keeps only its date. This cannot be undone.",
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
          notifySuccess(i18next.t("Events deleted: {{total}}", { total: result.deleted }));
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

  async function loadChoices(): Promise<void> {
    const ministryId = options.ministryId();
    const [types, classList, calendarList] = await Promise.all([
      eventTypes === null
        ? listEventTypes().then((data) => {
            defaultEventTypeId = data.defaultEventTypeId;

            return data.eventTypes;
          })
        : Promise.resolve(eventTypes),
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
            new Date(2023, 0, 1 + index).toLocaleDateString(appLocale(), { weekday: "long" }),
          )}</option>`,
      ).join("");
    }

    const month = field<HTMLSelectElement>("doy-month");
    if (month && month.options.length === 0) {
      month.innerHTML = Array.from(
        { length: 12 },
        (_, index) =>
          `<option value="${String(index + 1).padStart(2, "0")}">${escapeHtml(
            new Date(2023, index, 1).toLocaleDateString(appLocale(), { month: "long" }),
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
    const short = (date: Date): string => shortDate(formatIsoDate(date));
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
          day: dates[0].toLocaleDateString(appLocale(), { weekday: "long" }),
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

  // ── The Last date (D33) ───────────────────────────────────────────────────

  function oneYearAfter(iso: string): string {
    const date = new Date(`${iso}T12:00:00`);
    date.setFullYear(date.getFullYear() + 1);

    return formatIsoDate(date);
  }

  /** Until the Last date is chosen by hand, it stays a year after the first date. */
  function syncRangeStart(): void {
    const first = value("range-start");
    const last = field("range-end");
    if (!rangeEndChosen && first !== "" && last) {
      last.value = oneYearAfter(first);
      lastRangeEnd = last.value;
    }
    renderPreview();
  }

  /** A series always has a last date: a cleared field is put back, and says so. */
  function syncRangeEnd(): void {
    const last = field("range-end");
    const note = field("range-end-note");
    if (!last) {
      return;
    }
    if (last.value === "") {
      const first = value("range-start");
      last.value = lastRangeEnd !== "" ? lastRangeEnd : first !== "" ? oneYearAfter(first) : "";
      if (note) {
        note.textContent = tText("A recurring event needs a last date, so it is back to {{date}}.", {
          date: shortDate(last.value),
        });
      }
      show(note, true);
    } else if (last.value !== lastRangeEnd) {
      lastRangeEnd = last.value;
      rangeEndChosen = true;
      show(note, false);
    }
    renderPreview();
  }

  // ── Open and save ─────────────────────────────────────────────────────────

  async function openModal(series: boolean, prefill?: MinistryEventPrefill): Promise<void> {
    try {
      await options.ensureContext();
      await loadChoices();
    } catch (error) {
      notifyError(errorMessage(error, i18next.t("Could not load the event choices")));

      return;
    }

    fillSelects();
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
    if (defaultEventTypeId !== null && (eventTypes ?? []).some((type) => type.id === defaultEventTypeId)) {
      set("type", String(defaultEventTypeId));
    }
    set("date", isoDate(1));
    set("range-start", isoDate(1));
    rangeEndChosen = false;
    lastRangeEnd = "";
    syncRangeStart();
    show(field("range-end-note"), false);
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
    show(field("error"), false);

    if (prefill) {
      set("title", prefill.title ?? prefill.groupName ?? "");
      const classSelect = field<HTMLSelectElement>("class");
      if (prefill.groupId !== null && classSelect && options.sundaySchool()) {
        if (!Array.from(classSelect.options).some((option) => option.value === String(prefill.groupId))) {
          classSelect.append(new Option(prefill.groupName ?? "", String(prefill.groupId)));
        }
        classSelect.value = String(prefill.groupId);
      }
    }

    syncKind();

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
    const created = { series: isSeries(), linkedGroupId: body.linkedGroupId ?? null };

    createMinistryEvents(options.ministryId(), body)
      .then((result) => {
        hideModal("ministryEventModal");
        // After the dialog has gone, so the question is not stacked on a closing modal.
        byId("ministryEventModal")?.addEventListener("hidden.bs.modal", () => askToStaff(result.events, created), {
          once: true,
        });

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

  // ── Staff them? (D33) ─────────────────────────────────────────────────────

  type CreatedEvent = { id: number; title: string; start: string; end: string };

  /** Creating events and staffing them are separate steps: the question comes once the events exist. */
  function askToStaff(made: CreatedEvent[], created: { series: boolean; linkedGroupId: number | null }): void {
    if (made.length === 0) {
      return;
    }
    const first = made[0];
    const message =
      made.length === 1
        ? i18next.t("{{title}} on {{date}} created. Staff it now?", {
            title: first.title,
            date: shortDate(first.start),
          })
        : i18next.t("{{count}} {{title}} events created. Staff them now?", { count: made.length, title: first.title });

    let staffThem = false;
    let shown = false;
    let closeWhenShown = false;
    window.bootbox?.confirm({
      message,
      className: "ministry-event-staff-prompt",
      buttons: {
        confirm: { label: i18next.t("Staff them"), className: "btn-primary" },
        cancel: { label: i18next.t("Not now"), className: "btn-outline-secondary" },
      },
      callback: (yes: boolean) => {
        staffThem = yes;
        if (shown) {
          return true;
        }
        // Bootstrap drops a hide asked for while the dialog is still fading in (see hideModal()).
        closeWhenShown = true;

        return false;
      },
    });

    const prompt = document.querySelector(".ministry-event-staff-prompt");
    if (!prompt) {
      return;
    }
    prompt.addEventListener(
      "shown.bs.modal",
      () => {
        shown = true;
        if (closeWhenShown) {
          window.bootstrap.Modal.getOrCreateInstance(prompt).hide();
        }
      },
      { once: true },
    );
    // The next dialog opens once this one has gone, so the two never share a backdrop.
    prompt.addEventListener(
      "hidden.bs.modal",
      () => {
        if (!staffThem) {
          return;
        }
        if (created.series) {
          void staffSeries(made, created.linkedGroupId);
        } else {
          options.staffEvent({ id: first.id, title: first.title, start: first.start });
        }
      },
      { once: true },
    );
  }

  /**
   * A new series goes to every schedule that already follows it — the server finds them by
   * class or exact title (core events have no series id). With none, Add schedule opens for it:
   * on the class when one was chosen, else this ministry's events with exactly this title, which
   * always finds the series because the ministry owns it.
   */
  async function staffSeries(made: CreatedEvent[], linkedGroupId: number | null): Promise<void> {
    const title = made[0].title;
    let runs: Awaited<ReturnType<typeof staffMinistryEvents>>["schedules"];
    try {
      runs = (
        await staffMinistryEvents(
          options.ministryId(),
          made.map((event) => event.id),
        )
      ).schedules;
    } catch (error) {
      notifyError(errorMessage(error, i18next.t("The events could not be staffed")));

      return;
    }

    if (runs.length === 0) {
      const classTeam =
        linkedGroupId === null ? undefined : options.teams().find((team) => team.classGroupId === linkedGroupId);
      const groupName = classes?.find((row) => row.groupId === linkedGroupId)?.name ?? null;
      options.addSchedule({
        name: title,
        teamId: classTeam?.id ?? null,
        linkMode: linkedGroupId === null ? "ministry" : "class",
        groupId: linkedGroupId,
        groupName,
        titleFilter: linkedGroupId === null ? title : null,
      });

      return;
    }

    invalidate();
    options.invalidateStaffing();
    if (runs.every((run) => run.created === 0)) {
      notifyInfo(
        i18next.t(
          "{{names}} will staff these events. Their occurrences are made as they come within the next {{count}} weeks.",
          {
            names: runs.map((run) => `"${run.schedule.name}"`).join(", "),
            count: runs[0].schedule.horizonWeeks,
          },
        ),
      );

      return;
    }

    const parts = runs.map((run) =>
      i18next.t('"{{name}}": {{count}} occurrences created', { name: run.schedule.name, count: run.created }),
    );
    const assigned = runs.reduce((total, run) => total + run.assigned, 0);
    const unqualified = runs.reduce((total, run) => total + run.unqualified, 0);
    if (assigned > 0) {
      parts.push(i18next.t("Volunteers assigned: {{total}}", { total: assigned }));
    }
    if (unqualified > 0) {
      parts.push(i18next.t("{{count}} left open because the default is no longer qualified", { count: unqualified }));
    }
    notifySuccess(parts.join(". "));
    options.showOccurrences({ text: title });
  }

  function wire(): void {
    wireUnclippedRowMenus("ministry-events-table-wrapper");
    wireModalFadeGuard("ministryEventModal");

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
    for (const name of ["dow", "dom", "doy-month", "doy-day"]) {
      field(name)?.addEventListener("input", renderPreview);
      field(name)?.addEventListener("change", renderPreview);
    }
    field("range-start")?.addEventListener("input", syncRangeStart);
    field("range-start")?.addEventListener("change", syncRangeStart);
    // Typed dates arrive as `input`, and a blank mid-typing is not a refusal yet.
    field("range-end")?.addEventListener("input", () => {
      if (value("range-end") !== "") {
        syncRangeEnd();
      }
    });
    field("range-end")?.addEventListener("change", syncRangeEnd);
    field("range-end")?.addEventListener("blur", syncRangeEnd);
    field("save")?.addEventListener("click", save);

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

  return { load, invalidate, openNew: openModal };
}
