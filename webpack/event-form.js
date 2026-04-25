/**
 * Event Form — shared renderer and controller for the ChurchCRM event
 * editor + viewer used from multiple surfaces (calendar modal, full-page
 * event editor, cart-to-event). Keeps the field set, validation rules,
 * and widget wiring in one place so the UI stays identical wherever
 * events are created or edited.
 *
 * Public API:
 *   renderEventEditor(container, event, calendars, eventTypes, options)
 *   renderEventViewer(container, event, calendars, eventTypes)
 *   saveEvent(event, apiRoot)  // POSTs to /api/events(/:id); returns a Promise
 *
 * Both render functions mutate `event` in place and return a controller
 * { getEvent, validate, destroy } so callers can plug save / cancel /
 * delete buttons into their own chrome (modal footer vs. page footer).
 */

import DOMPurify from "dompurify";
import { initializeQuillEditor } from "./quill-editor.js";

const t = (key) => (window.i18next ? window.i18next.t(key) : key);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function escapeHtml(str) {
  if (!str) return "";
  const div = document.createElement("div");
  div.textContent = str;
  return div.innerHTML.replace(/"/g, "&quot;").replace(/'/g, "&#39;");
}

// Architecture: event.Start / event.End are CHURCH wall-clock STRINGS in
// `YYYY-MM-DDTHH:mm:ss` (timed) or `YYYY-MM-DD` (all-day). Never JS Date
// objects in date logic. This avoids browser-tz contamination — `new Date()`,
// `getHours()`, `getDate()` etc. all interpret in the user's local tz, which
// silently shifts wall-clock numbers when browser tz ≠ church tz.
//
// All format conversions happen at boundaries:
//   - API response → toWallClockString  (strips space/Z/offset)
//   - FullCalendar Date → formatJsDateInChurchTz  (Intl with timeZone)
//   - User input field → already a string, used directly
//   - Send to API → string passes through JSON.stringify

function getChurchTz() {
  return window.CRM?.calendarJSArgs?.sTimeZone || "UTC";
}

// Format a JS Date as "YYYY-MM-DDTHH:mm:ss" wall-clock numbers in the given tz.
// Uses Intl with `timeZone` so the components reflect the church's tz, not the
// browser's. en-CA gives ISO-friendly numeric output.
function formatJsDateInChurchTz(date, tz, dateOnly) {
  const opts = {
    timeZone: tz,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour12: false,
    ...(dateOnly ? {} : { hour: "2-digit", minute: "2-digit", second: "2-digit" }),
  };
  const parts = new Intl.DateTimeFormat("en-CA", opts).formatToParts(date);
  const get = (type) => parts.find((p) => p.type === type)?.value || "00";
  const ymd = `${get("year")}-${get("month")}-${get("day")}`;
  if (dateOnly) return ymd;
  // en-CA returns "24" for midnight in some locales — normalize to "00".
  const hh = get("hour") === "24" ? "00" : get("hour");
  return `${ymd}T${hh}:${get("minute")}:${get("second")}`;
}

// Coerce any input (Date, API string, naive ISO, date-only) to a wall-clock
// string in the church's tz. Returns "" for empty input.
//   - API space format "2026-04-24 20:00:00.000000"      → "2026-04-24T20:00:00"
//   - ISO with offset  "2026-04-24T20:00:00-04:00"        → "2026-04-24T20:00:00"
//   - ISO with Z       "2026-04-25T00:00:00.000Z"         → reinterpret in tz
//   - Date-only        "2026-04-24"                        → "2026-04-24"
//   - JS Date                                              → format in church tz
export function toWallClockString(value) {
  if (!value) return "";
  if (value instanceof Date) {
    return formatJsDateInChurchTz(value, getChurchTz(), false);
  }
  if (typeof value !== "string") return "";
  // ISO with explicit Z or +HH:MM: parse as instant, format in church tz so
  // the wall-clock numbers reflect the configured display zone (handles
  // legacy UTC-stored events from the brief broken iteration).
  if (/Z$/.test(value) || /[+-]\d{2}:\d{2}$/.test(value)) {
    const parsed = new Date(value);
    if (!Number.isNaN(parsed.getTime())) {
      return formatJsDateInChurchTz(parsed, getChurchTz(), false);
    }
  }
  // Naive: assume already wall-clock in church tz. Strip space/microseconds.
  return value.replace(" ", "T").replace(/\.\d+/, "").substring(0, 19);
}

// Date-only variant: returns "YYYY-MM-DD".
function toWallClockDate(value) {
  if (!value) return "";
  if (value instanceof Date) {
    return formatJsDateInChurchTz(value, getChurchTz(), true);
  }
  if (typeof value !== "string") return "";
  if (/Z$/.test(value) || /[+-]\d{2}:\d{2}$/.test(value)) {
    const parsed = new Date(value);
    if (!Number.isNaN(parsed.getTime())) {
      return formatJsDateInChurchTz(parsed, getChurchTz(), true);
    }
  }
  return value.replace(" ", "T").substring(0, 10);
}

// Datetime-local input format ("YYYY-MM-DDTHH:mm") — no seconds.
function formatDateForInput(value, allDay) {
  if (allDay) return toWallClockDate(value);
  return toWallClockString(value).substring(0, 16);
}

// Human-readable display: "April 24, 2026" or "April 24, 2026 at 08:00 PM".
function formatDateForDisplay(value, allDay) {
  const s = allDay ? toWallClockDate(value) : toWallClockString(value);
  if (!s) return "N/A";
  const m = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?/);
  if (!m) return s;
  const [, y, mo, d, hh, mi] = m;
  const months = [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December",
  ];
  const dateStr = `${months[parseInt(mo, 10) - 1]} ${parseInt(d, 10)}, ${y}`;
  if (allDay || hh === undefined) return dateStr;
  let h = parseInt(hh, 10);
  const am = h < 12 ? "AM" : "PM";
  h = h % 12 || 12;
  return `${dateStr} at ${String(h).padStart(2, "0")}:${mi} ${am}`;
}

export function isAllDay(event) {
  if (!event.Start) return true;
  const s = toWallClockString(event.Start);
  // Date-only string (length 10) is implicitly all-day.
  if (s.length <= 10) return true;
  const m = s.match(/T(\d{2}):(\d{2})/);
  if (!m || m[1] !== "00" || m[2] !== "00") return false;
  if (event.End) {
    const e = toWallClockString(event.End);
    if (e.length > 10) {
      const me = e.match(/T(\d{2}):(\d{2})/);
      if (me && (me[1] !== "00" || me[2] !== "00")) return false;
    }
  }
  return true;
}

// ---------------------------------------------------------------------------
// Editor markup
// ---------------------------------------------------------------------------

function renderTitleFieldInline(event) {
  return `
    <div class="mb-3">
      <label class="form-label" for="event-title-input">${t("Event Title")}<span class="text-danger ms-1">*</span></label>
      <input id="event-title-input" name="Title" value="${escapeHtml(event.Title || "")}"
        placeholder="${t("e.g. Sunday Service")}" class="form-control" required>
      <div class="invalid-feedback" id="titleFeedback"><i class="fas fa-exclamation-circle me-1"></i>${t("This field is required")}</div>
    </div>`;
}

function renderTitleFieldInHeader(event) {
  return `
    <div class="w-100 me-3 pt-1">
      <label class="form-label text-muted small mb-1" for="event-title-input">${t("Event Title")}</label>
      <input id="event-title-input" name="Title" value="${escapeHtml(event.Title || "")}"
        placeholder="${t("e.g. Sunday Service")}"
        class="form-control form-control-lg fw-bold border-0 border-bottom rounded-0 px-0" style="box-shadow:none">
      <div class="invalid-feedback" id="titleFeedback"><i class="fas fa-exclamation-circle me-1"></i>${t("This field is required")}</div>
    </div>`;
}

function renderAdvancedSection(event, groups) {
  const inactive = Number(event.InActive || 0) === 1;
  const linkedGroupId = Number(event.LinkedGroupId || 0);

  const groupOptions = groups
    .map(
      (g) =>
        `<option value="${g.groupID ?? g.Id}" ${(g.groupID ?? g.Id) === linkedGroupId ? "selected" : ""}>${escapeHtml(g.name ?? g.Name)}</option>`,
    )
    .join("");

  // Attendance Counts rows render only for saved events (event.Id > 0 and the
  // event has at least one count category recorded). For brand-new events the
  // section hides with a short note — counts get filled in after the event is
  // saved, which matches how volunteers actually use this feature anyway.
  let countsMarkup = "";
  const hasCounts = Array.isArray(event.AttendanceCounts) && event.AttendanceCounts.length > 0;
  if (event.Id && hasCounts) {
    const rows = event.AttendanceCounts.map(
      (c, idx) => `
      <div class="col-sm-6 col-md-4 mb-2">
        <label class="form-label" for="attCount_${idx}">${escapeHtml(c.name)}</label>
        <input type="number" id="attCount_${idx}" class="form-control attendance-count"
               data-count-id="${c.id}" data-count-name="${escapeHtml(c.name)}"
               min="0" value="${Number(c.count) || 0}">
      </div>`,
    ).join("");
    countsMarkup = `
      <div class="mb-3">
        <label class="form-label">${t("Attendance Counts")}</label>
        <div class="row g-2">${rows}</div>
        <small class="form-text text-secondary">${t("Volunteer-entered headcounts, broken down by category.")}</small>
      </div>`;
  } else if (!event.Id) {
    countsMarkup = `
      <div class="mb-3">
        <label class="form-label text-muted">${t("Attendance Counts")}</label>
        <div class="text-muted small"><i class="ti ti-info-circle me-1"></i>${t("Available after the event is saved.")}</div>
      </div>`;
  }

  return `
    <div class="my-3">
      <button type="button" class="btn btn-link p-0 text-decoration-none"
              data-bs-toggle="collapse" data-bs-target="#eventAdvancedFields"
              aria-expanded="false" aria-controls="eventAdvancedFields">
        <i class="ti ti-chevron-down me-1" id="eventAdvancedChevron"></i>
        <span id="eventAdvancedLabel">${t("Show more options")}</span>
      </button>
    </div>
    <div class="collapse" id="eventAdvancedFields">
      <div class="card card-sm bg-muted-lt border-0 mb-3">
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">${t("Event Status")}<span class="text-danger ms-1">*</span></label>
            <div class="form-selectgroup form-selectgroup-pills">
              <label class="form-selectgroup-item">
                <input type="radio" name="eventInActive" value="0" class="form-selectgroup-input" ${!inactive ? "checked" : ""}>
                <span class="form-selectgroup-label"><i class="ti ti-check me-1"></i>${t("Active")}</span>
              </label>
              <label class="form-selectgroup-item">
                <input type="radio" name="eventInActive" value="1" class="form-selectgroup-input" ${inactive ? "checked" : ""}>
                <span class="form-selectgroup-label"><i class="ti ti-ban me-1"></i>${t("Inactive")}</span>
              </label>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="linkedGroupSelect">${t("Linked Group")}</label>
            <select id="linkedGroupSelect" class="form-select">
              <option value="0">${t("No group")}</option>
              ${groupOptions}
            </select>
            <small class="form-text text-secondary">${t("Ties this event to a class or ministry roster — used by the Kiosk check-in flow.")}</small>
          </div>

          ${countsMarkup}
        </div>
      </div>
    </div>`;
}

function renderEditorFields(event, calendars, eventTypes, groups, allDay) {
  const calOptions = calendars
    .map(
      (c) =>
        `<option value="${c.Id}" ${event.PinnedCalendars?.includes(c.Id) ? "selected" : ""}>${escapeHtml(c.Name)}</option>`,
    )
    .join("");
  const typeOptions = eventTypes
    .map((et) => `<option value="${et.Id}" ${event.Type === et.Id ? "selected" : ""}>${escapeHtml(et.Name)}</option>`)
    .join("");

  const inputType = allDay ? "date" : "datetime-local";
  const startVal = formatDateForInput(event.Start, allDay);
  const endVal = formatDateForInput(event.End, allDay);

  // Show a banner when the user's browser tz differs from the church's
  // configured sTimeZone, so admins editing from a different region know that
  // the times they enter are interpreted as the church's wall-clock — not
  // their browser's local time.
  let tzNoticeMarkup = "";
  const churchTz = window.CRM?.calendarJSArgs?.sTimeZone;
  if (churchTz && !allDay) {
    let browserTz = "";
    try {
      browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone || "";
    } catch (_e) {
      browserTz = "";
    }
    if (browserTz && browserTz !== churchTz) {
      tzNoticeMarkup = `
    <div class="alert alert-info py-2 mb-3" role="status">
      <i class="ti ti-clock me-1"></i>
      ${t("Times are interpreted as")} <strong>${escapeHtml(churchTz)}</strong>
      (${t("the church's configured timezone")}).
      ${t("Your browser is in")} <strong>${escapeHtml(browserTz)}</strong> — ${t("the times you enter here will be saved as the church's wall-clock time, not your local time.")}
    </div>`;
    }
  }

  return `
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label" for="eventTypeSelect">${t("Event Type")}</label>
        <select id="eventTypeSelect" class="form-select">${typeOptions}</select>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="pinnedCalendarsSelect">${t("Pinned Calendars")}</label>
        <select id="pinnedCalendarsSelect" class="form-select" multiple>${calOptions}</select>
        <div class="form-text text-warning d-none" id="calendarsEmptyHint">
          <i class="ti ti-info-circle me-1"></i>${t("No calendar selected — this event will be saved but won't appear on any calendar view.")}
        </div>
      </div>
    </div>
    ${tzNoticeMarkup}
    <div class="mb-2">
      <div class="form-selectgroup form-selectgroup-pills">
        <label class="form-selectgroup-item">
          <input type="radio" name="eventDayType" value="timed" class="form-selectgroup-input" ${!allDay ? "checked" : ""}>
          <span class="form-selectgroup-label"><i class="fa-regular fa-clock me-1"></i>${t("Timed")}</span>
        </label>
        <label class="form-selectgroup-item">
          <input type="radio" name="eventDayType" value="allday" class="form-selectgroup-input" ${allDay ? "checked" : ""}>
          <span class="form-selectgroup-label"><i class="fa-regular fa-sun me-1"></i>${t("All Day")}</span>
        </label>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label" for="eventStartDate">${t("Start Date")}<span class="text-danger ms-1">*</span></label>
        <input type="${inputType}" id="eventStartDate" class="form-control" value="${startVal}" autocomplete="off">
      </div>
      <div class="col-md-6">
        <label class="form-label" for="eventEndDate">${t("End Date")}<span class="text-danger ms-1">*</span></label>
        <input type="${inputType}" id="eventEndDate" class="form-control" value="${endVal}" autocomplete="off" ${startVal ? `min="${startVal}"` : ""}>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label" for="quill-Desc">${t("Description")}</label>
      <div id="quill-Desc" class="quill-editor-container" data-editor-size="compact"></div>
    </div>

    <div class="mb-3">
      <label class="form-label" for="quill-Text">${t("Additional Information")}</label>
      <div id="quill-Text" class="quill-editor-container" data-editor-size="compact"></div>
    </div>

    ${renderAdvancedSection(event, groups)}
  `;
}

// ---------------------------------------------------------------------------
// Viewer markup
// ---------------------------------------------------------------------------

function renderViewerMarkup(event, calendars, eventTypes, groups = []) {
  const allDay = isAllDay(event);
  const matchedType = eventTypes.find((et) => event.Type != null && event.Type === et.Id);
  const pinnedCals = calendars.filter((c) => event.PinnedCalendars?.includes(c.Id));
  const sanitizedDesc = DOMPurify.sanitize(event.Desc || "");
  const sanitizedText = DOMPurify.sanitize(event.Text || "");
  const inactive = Number(event.InActive || 0) === 1;
  const linkedGroupId = Number(event.LinkedGroupId || 0);
  const matchedGroup = linkedGroupId ? groups.find((g) => Number(g.groupID ?? g.Id) === linkedGroupId) : null;
  const attendanceCounts = Array.isArray(event.AttendanceCounts) ? event.AttendanceCounts : [];
  const hasCounts = attendanceCounts.some((c) => Number(c.count) > 0);

  let calBadges = "";
  for (const cal of pinnedCals) {
    calBadges += `<span class="badge border me-1" style="background-color:#${cal.BackgroundColor};color:#${cal.ForegroundColor};border-color:#${cal.BackgroundColor}">
      <span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background-color:#${cal.ForegroundColor};opacity:0.7"></span>
      ${escapeHtml(cal.Name)}</span>`;
  }

  let metaRows = "";
  // Status is always shown — Active/Inactive is a core attribute of the event.
  metaRows += `<dt class="col-sm-3 text-muted">${t("Status")}</dt>
    <dd class="col-sm-9">${
      inactive
        ? `<span class="badge bg-secondary-lt"><i class="ti ti-ban me-1"></i>${t("Inactive")}</span>`
        : `<span class="badge bg-green-lt text-green"><i class="ti ti-check me-1"></i>${t("Active")}</span>`
    }</dd>`;
  if (matchedType) {
    metaRows += `<dt class="col-sm-3 text-muted">${t("Event Type")}</dt>
      <dd class="col-sm-9"><span class="badge bg-blue-lt text-blue">${escapeHtml(matchedType.Name)}</span></dd>`;
  }
  if (allDay) {
    metaRows += `<dt class="col-sm-3 text-muted">${t("Duration")}</dt>
      <dd class="col-sm-9"><span class="badge bg-green-lt text-green">${t("All Day")}</span></dd>`;
  }
  if (pinnedCals.length > 0) {
    metaRows += `<dt class="col-sm-3 text-muted">${t("Calendars")}</dt>
      <dd class="col-sm-9"><div class="d-flex flex-wrap gap-2">${calBadges}</div></dd>`;
  } else if (pinnedCals.length === 0 && calendars.length > 0) {
    metaRows += `<dt class="col-sm-3 text-muted">${t("Calendars")}</dt>
      <dd class="col-sm-9"><span class="text-muted small"><i class="ti ti-info-circle me-1"></i>${t("Not pinned to any calendar")}</span></dd>`;
  }
  if (matchedGroup) {
    const groupName = matchedGroup.name ?? matchedGroup.Name;
    metaRows += `<dt class="col-sm-3 text-muted">${t("Linked Group")}</dt>
      <dd class="col-sm-9"><span class="badge bg-purple-lt text-purple"><i class="ti ti-users me-1"></i>${escapeHtml(groupName)}</span></dd>`;
  }

  // Attendance counts render as a compact pill list below the meta rows.
  // Only categories with a count > 0 appear in the viewer so empty events
  // aren't noisy; the editor still shows every category for data entry.
  let countsSection = "";
  if (hasCounts) {
    const pills = attendanceCounts
      .filter((c) => Number(c.count) > 0)
      .map(
        (c) =>
          `<span class="badge bg-azure-lt text-azure me-2 mb-1">
             <span class="fw-semibold">${Number(c.count)}</span>
             <span class="ms-1">${escapeHtml(c.name)}</span>
           </span>`,
      )
      .join("");
    const total = attendanceCounts.reduce((sum, c) => sum + (Number(c.count) || 0), 0);
    countsSection = `
      <div class="mb-4">
        <h4 class="subheader">${t("Attendance Counts")}</h4>
        <div class="d-flex flex-wrap align-items-center">
          ${pills}
          <span class="ms-auto text-muted small">${t("Total")}: <span class="fw-bold">${total}</span></span>
        </div>
      </div>`;
  }

  return `
    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <div class="card card-sm border-0 bg-blue-lt">
          <div class="card-body py-3">
            <div class="d-flex align-items-center gap-3">
              <i class="fa-regular fa-calendar-check fa-xl text-blue flex-shrink-0"></i>
              <div>
                <div class="text-blue small fw-medium">${t("Start Date")}</div>
                <div class="fw-bold">${formatDateForDisplay(event.Start, allDay)}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card card-sm border-0 bg-red-lt">
          <div class="card-body py-3">
            <div class="d-flex align-items-center gap-3">
              <i class="fa-regular fa-calendar-xmark fa-xl text-red flex-shrink-0"></i>
              <div>
                <div class="text-red small fw-medium">${t("End Date")}</div>
                <div class="fw-bold">${formatDateForDisplay(event.End, allDay)}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <dl class="row mb-3">${metaRows}</dl>
    ${countsSection}
    ${sanitizedDesc.trim() ? `<div class="mb-4"><h4 class="subheader">${t("Description")}</h4><div class="prose">${sanitizedDesc}</div></div>` : ""}
    ${sanitizedText.trim() ? `<div class="mb-2"><h4 class="subheader">${t("Additional Information")}</h4><div class="prose">${sanitizedText}</div></div>` : ""}
  `;
}

// ---------------------------------------------------------------------------
// Public: renderEventViewer
// ---------------------------------------------------------------------------

export function renderEventViewer(container, event, calendars, eventTypes, options = {}) {
  const { groups = [] } = options;
  container.innerHTML = renderViewerMarkup(event, calendars, eventTypes, groups);
  return {
    getEvent: () => event,
    destroy: () => {
      // Viewer has no stateful widgets to tear down.
    },
  };
}

// ---------------------------------------------------------------------------
// Public: renderEventEditor
// ---------------------------------------------------------------------------

export function renderEventEditor(container, event, calendars, eventTypes, options = {}) {
  const { titleHost = null, onValidityChange = null, groups = [] } = options;

  const allDay = isAllDay(event);
  const fieldsMarkup = renderEditorFields(event, calendars, eventTypes, groups, allDay);

  if (titleHost) {
    titleHost.innerHTML = renderTitleFieldInHeader(event);
    container.innerHTML = fieldsMarkup;
  } else {
    container.innerHTML = renderTitleFieldInline(event) + fieldsMarkup;
  }

  // --- widget state (captured in closure for teardown) ---
  let tsEventType = null;
  let tsCalendars = null;
  let quillDesc = null;
  let quillText = null;

  // Suppress the "title required" message until the user has actually
  // interacted with the title input (or attempted a save). Showing it on
  // initial mount for a new event creates visual noise before the user has
  // had a chance to type anything.
  let titleTouched = Boolean(event.Title && event.Title.length > 0);

  // --- validity tracking ---
  // Pinned Calendars is intentionally NOT part of validate(): the data model
  // supports events without a pinned calendar (they just don't appear on any
  // calendar view). The empty state surfaces as a warning hint below the
  // select, not as a block on save.
  function validate() {
    return Boolean(event.Title && event.Title.length > 0 && event.Start != null && event.End != null);
  }

  function updateCalendarsEmptyHint() {
    const hint = document.getElementById("calendarsEmptyHint");
    if (!hint) return;
    const empty = !event.PinnedCalendars || event.PinnedCalendars.length === 0;
    hint.classList.toggle("d-none", !empty);
  }

  function updateTitleFeedback() {
    const titleInput = document.getElementById("event-title-input");
    const titleFb = document.getElementById("titleFeedback");
    if (!titleInput || !titleFb) return;
    const showError = titleTouched && event.Title !== undefined && event.Title.length === 0;
    titleFb.style.display = showError ? "block" : "none";
  }

  function fireValidity() {
    updateTitleFeedback();
    if (onValidityChange) onValidityChange(validate());
  }

  // --- Title ---
  const titleInput = document.getElementById("event-title-input");
  if (titleInput) {
    titleInput.addEventListener("input", () => {
      event.Title = titleInput.value;
      titleTouched = true;
      fireValidity();
    });
    titleInput.addEventListener("blur", () => {
      titleTouched = true;
      updateTitleFeedback();
    });
  }

  // --- Event Type (TomSelect) ---
  const eventTypeEl = document.getElementById("eventTypeSelect");
  tsEventType = new window.TomSelect(eventTypeEl, {
    placeholder: t("Select event type..."),
    allowEmptyOption: true,
  });
  tsEventType.on("change", (value) => {
    event.Type = value ? Number.parseInt(value, 10) : undefined;
  });

  // --- Pinned Calendars (TomSelect multi) ---
  const calEl = document.getElementById("pinnedCalendarsSelect");
  tsCalendars = new window.TomSelect(calEl, {
    placeholder: t("Select calendars..."),
    plugins: ["remove_button"],
  });
  tsCalendars.on("change", () => {
    event.PinnedCalendars = tsCalendars.getValue().map((v) => Number.parseInt(v, 10));
    updateCalendarsEmptyHint();
    fireValidity();
  });

  // Initial hint state reflects whatever the event came in with.
  updateCalendarsEmptyHint();

  // --- Timed / All-Day toggle ---
  const dayTypeRadios = document.querySelectorAll('input[name="eventDayType"]');
  for (const radio of dayTypeRadios) {
    radio.addEventListener("change", () => {
      const nowAllDay = radio.value === "allday";
      const startInput = document.getElementById("eventStartDate");
      const endInput = document.getElementById("eventEndDate");

      // String math: extract date parts from existing wall-clock strings (or
      // "today in church tz" if absent); re-assemble with new time portion.
      const churchToday = formatJsDateInChurchTz(new Date(), getChurchTz(), true);
      const startDate = toWallClockDate(event.Start) || churchToday;
      const endDate = toWallClockDate(event.End) || startDate;
      if (nowAllDay) {
        event.Start = startDate;
        event.End = endDate;
      } else {
        // Default time-of-day for a fresh timed event. Church events are
        // overwhelmingly morning/midday (services, meetings, classes), so
        // 9:00 AM is a far better default than "current church-time hour"
        // — which produced confusing 1 AM defaults during after-hours edits.
        // If the existing time portion is non-zero, preserve it.
        const existingStart = toWallClockString(event.Start);
        const existingEnd = toWallClockString(event.End);
        const startTimePart = /T(?!00:00)/.test(existingStart) ? existingStart.substring(11, 19) : "09:00:00";
        const endTimePart = /T(?!00:00)/.test(existingEnd) ? existingEnd.substring(11, 19) : "10:00:00";
        event.Start = `${startDate}T${startTimePart}`;
        event.End = `${endDate}T${endTimePart}`;
      }

      startInput.type = nowAllDay ? "date" : "datetime-local";
      endInput.type = nowAllDay ? "date" : "datetime-local";
      startInput.value = formatDateForInput(event.Start, nowAllDay);
      endInput.value = formatDateForInput(event.End, nowAllDay);
      endInput.min = startInput.value || "";
      fireValidity();
    });
  }

  // --- Date inputs ---
  const startInput = document.getElementById("eventStartDate");
  const endInput = document.getElementById("eventEndDate");
  if (startInput) {
    startInput.addEventListener("change", () => {
      // Input value is already a wall-clock string ("YYYY-MM-DDTHH:mm" or
      // "YYYY-MM-DD"). Pad seconds for timed entries so the wire format is
      // consistent. No `new Date()` — we don't want browser-tz interpretation.
      const v = startInput.value;
      if (!v) {
        event.Start = undefined;
      } else if (v.length === 16) {
        event.Start = `${v}:00`;
      } else {
        event.Start = v;
      }
      if (endInput) endInput.min = v || "";
      fireValidity();
    });
  }
  if (endInput) {
    endInput.addEventListener("change", () => {
      const v = endInput.value;
      if (!v) {
        event.End = undefined;
      } else if (v.length === 16) {
        event.End = `${v}:00`;
      } else {
        event.End = v;
      }
      fireValidity();
    });
  }

  // --- Quill editors ---
  quillDesc = initializeQuillEditor("#quill-Desc", { placeholder: t("Enter text here...") });
  if (quillDesc && event.Desc) quillDesc.root.innerHTML = event.Desc;
  if (quillDesc) {
    quillDesc.on("text-change", () => {
      event.Desc = quillDesc.root.innerHTML;
    });
  }

  quillText = initializeQuillEditor("#quill-Text", { placeholder: t("Enter text here...") });
  if (quillText && event.Text) quillText.root.innerHTML = event.Text;
  if (quillText) {
    quillText.on("text-change", () => {
      event.Text = quillText.root.innerHTML;
    });
  }

  // --- Advanced section: Active/Inactive, Linked Group, Attendance Counts ---
  event.InActive = Number(event.InActive || 0);
  const inactiveRadios = document.querySelectorAll('input[name="eventInActive"]');
  for (const radio of inactiveRadios) {
    radio.addEventListener("change", () => {
      event.InActive = Number.parseInt(radio.value, 10) || 0;
    });
  }

  event.LinkedGroupId = Number(event.LinkedGroupId || 0);
  const linkedGroupEl = document.getElementById("linkedGroupSelect");
  if (linkedGroupEl) {
    linkedGroupEl.addEventListener("change", () => {
      event.LinkedGroupId = Number.parseInt(linkedGroupEl.value, 10) || 0;
    });
  }

  // Attendance counts live on `event.AttendanceCounts[]` — each input
  // rewrites the matching row by data-count-id when changed.
  if (!Array.isArray(event.AttendanceCounts)) event.AttendanceCounts = [];
  const countInputs = document.querySelectorAll(".attendance-count");
  for (const input of countInputs) {
    input.addEventListener("input", () => {
      const countId = Number.parseInt(input.dataset.countId, 10);
      const row = event.AttendanceCounts.find((r) => Number(r.id) === countId);
      if (row) row.count = Number.parseInt(input.value, 10) || 0;
    });
  }

  // Toggle the chevron + label text when the collapse opens/closes.
  const advancedCollapse = document.getElementById("eventAdvancedFields");
  if (advancedCollapse) {
    advancedCollapse.addEventListener("show.bs.collapse", () => {
      const chevron = document.getElementById("eventAdvancedChevron");
      const label = document.getElementById("eventAdvancedLabel");
      if (chevron) chevron.classList.replace("ti-chevron-down", "ti-chevron-up");
      if (label) label.textContent = t("Hide advanced options");
    });
    advancedCollapse.addEventListener("hide.bs.collapse", () => {
      const chevron = document.getElementById("eventAdvancedChevron");
      const label = document.getElementById("eventAdvancedLabel");
      if (chevron) chevron.classList.replace("ti-chevron-up", "ti-chevron-down");
      if (label) label.textContent = t("Show more options");
    });
  }

  // Initial validity fire so callers can set the save button's disabled state.
  fireValidity();

  return {
    getEvent: () => event,
    validate,
    destroy: () => {
      if (tsEventType) {
        tsEventType.destroy();
        tsEventType = null;
      }
      if (tsCalendars) {
        tsCalendars.destroy();
        tsCalendars = null;
      }
      if (window.quillEditors) {
        delete window.quillEditors["quill-Desc"];
        delete window.quillEditors["quill-Text"];
      }
      quillDesc = null;
      quillText = null;
    },
  };
}

// ---------------------------------------------------------------------------
// Public: saveEvent — shared POST to /api/events (new) or /api/events/:id
// ---------------------------------------------------------------------------

export function saveEvent(event, apiRoot) {
  const url = `${apiRoot}/api/events${event.Id !== 0 ? `/${event.Id}` : ""}`;
  const body = JSON.stringify(event, (_key, value) => {
    // event.Start / event.End should already be wall-clock strings by this
    // point (see toWallClockString boundaries). Any leftover JS Date —
    // typically from a code path that hasn't been refactored — is converted
    // to church-tz wall-clock so we never send browser-tz components or UTC.
    if (value instanceof Date) {
      return formatJsDateInChurchTz(value, getChurchTz(), false);
    }
    return value;
  });
  return fetch(url, {
    credentials: "include",
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body,
  }).then((r) => {
    if (!r.ok) throw new Error(`HTTP ${r.status}`);
    return r;
  });
}

// ---------------------------------------------------------------------------
// Public: deleteEvent — shared DELETE to /api/events/:id
// ---------------------------------------------------------------------------

export function deleteEvent(eventId, apiRoot) {
  return fetch(`${apiRoot}/api/events/${eventId}`, {
    credentials: "include",
    method: "DELETE",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
  }).then((r) => {
    if (!r.ok) throw new Error(`HTTP ${r.status}`);
    return r;
  });
}
