/**
 * Calendar Event Editor
 *
 * Bootstrap 5 modal for viewing/editing/creating calendar events.
 * Uses TomSelect, native datetime inputs, and the shared Quill editor.
 */

import DOMPurify from "dompurify";
import { initializeQuillEditor } from "./quill-editor.js";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const CRMRoot = window.CRM.root;
const t = (key) => (window.i18next ? window.i18next.t(key) : key);

function fetchJSON(url, opts = {}) {
  return fetch(url, { credentials: "include", ...opts }).then((r) => {
    if (!r.ok) throw new Error(`HTTP ${r.status}`);
    if (r.status === 204) return {};
    return r.json();
  });
}

function escapeHtml(str) {
  if (!str) return "";
  const div = document.createElement("div");
  div.textContent = str;
  return div.innerHTML.replace(/"/g, "&quot;").replace(/'/g, "&#39;");
}

/** Parse an input value as a local date. date-only "YYYY-MM-DD" is parsed as
 *  local (not UTC) to avoid off-by-one day shifts in non-UTC timezones. */
function parseInputDate(value) {
  if (!value) return undefined;
  // date-only: "YYYY-MM-DD" — construct as local
  const dateOnly = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (dateOnly) {
    return new Date(Number(dateOnly[1]), Number(dateOnly[2]) - 1, Number(dateOnly[3]));
  }
  // datetime-local: "YYYY-MM-DDTHH:mm" — already parsed as local by Date()
  return new Date(value);
}

function formatDateForInput(date, allDay) {
  if (!date) return "";
  const d = new Date(date);
  if (allDay) {
    // yyyy-MM-dd
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
  }
  // yyyy-MM-ddTHH:mm (for datetime-local)
  const y = d.getFullYear();
  const mo = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  const h = String(d.getHours()).padStart(2, "0");
  const mi = String(d.getMinutes()).padStart(2, "0");
  return `${y}-${mo}-${day}T${h}:${mi}`;
}

function formatDateForDisplay(date, allDay) {
  if (!date) return "N/A";
  const d = new Date(date);
  if (allDay) return d.toLocaleDateString(undefined, { year: "numeric", month: "long", day: "numeric" });
  return d.toLocaleString(undefined, {
    year: "numeric",
    month: "long",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function isAllDay(event) {
  if (!event.Start) return true;
  const s = new Date(event.Start);
  if (s.getHours() !== 0 || s.getMinutes() !== 0) return false;
  if (event.End) {
    const e = new Date(event.End);
    if (e.getHours() !== 0 || e.getMinutes() !== 0) return false;
  }
  return true;
}

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------

let currentModal = null;
let quillDesc = null;
let quillText = null;
let tsEventType = null;
let tsCalendars = null;

function cleanup() {
  if (currentModal) {
    // Remove the modal element from DOM first to prevent transition callbacks
    // from accessing null properties during dispose
    const modalEl = document.getElementById("eventEditorModal");
    if (modalEl) {
      modalEl.classList.remove("show");
      modalEl.removeAttribute("role");
    }
    try {
      currentModal.dispose();
    } catch (_e) {
      // dispose() can throw if called during a show/hide transition
    }
    currentModal = null;
    // Remove any leftover backdrop
    document.querySelectorAll(".modal-backdrop").forEach((el) => el.remove());
    document.body.classList.remove("modal-open");
    document.body.style.removeProperty("overflow");
    document.body.style.removeProperty("padding-right");
  }
  // Destroy TomSelect instances
  if (tsEventType) {
    tsEventType.destroy();
    tsEventType = null;
  }
  if (tsCalendars) {
    tsCalendars.destroy();
    tsCalendars = null;
  }
  // Clean up Quill instances from global registry
  if (window.quillEditors) {
    delete window.quillEditors["quill-Desc"];
    delete window.quillEditors["quill-Text"];
  }
  quillDesc = null;
  quillText = null;

  const container = document.getElementById("calendar-event-app");
  if (container) container.innerHTML = "";
}

// ---------------------------------------------------------------------------
// Viewer (read-only mode)
// ---------------------------------------------------------------------------

function renderViewer(event, calendars, eventTypes) {
  const allDay = isAllDay(event);
  const matchedType = eventTypes.find((et) => event.Type != null && event.Type === et.Id);
  const pinnedCals = calendars.filter((c) => event.PinnedCalendars?.includes(c.Id));
  const sanitizedDesc = DOMPurify.sanitize(event.Desc || "");
  const sanitizedText = DOMPurify.sanitize(event.Text || "");

  let calBadges = "";
  for (const cal of pinnedCals) {
    calBadges += `<span class="badge border me-1" style="background-color:#${cal.BackgroundColor};color:#${cal.ForegroundColor};border-color:#${cal.BackgroundColor}">
      <span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background-color:#${cal.ForegroundColor};opacity:0.7"></span>
      ${escapeHtml(cal.Name)}</span>`;
  }

  let metaRows = "";
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
    ${sanitizedDesc.trim() ? `<div class="mb-4"><h4 class="subheader">${t("Description")}</h4><div class="prose">${sanitizedDesc}</div></div>` : ""}
    ${sanitizedText.trim() ? `<div class="mb-2"><h4 class="subheader">${t("Additional Information")}</h4><div class="prose">${sanitizedText}</div></div>` : ""}
  `;
}

// ---------------------------------------------------------------------------
// Editor (edit mode)
// ---------------------------------------------------------------------------

function renderEditor(event, calendars, eventTypes, allDay) {
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

  return `
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label" for="eventTypeSelect">${t("Event Type")}</label>
        <select id="eventTypeSelect" class="form-select">${typeOptions}</select>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="pinnedCalendarsSelect">${t("Pinned Calendars")}<span class="text-danger ms-1">*</span></label>
        <select id="pinnedCalendarsSelect" class="form-select" multiple>${calOptions}</select>
        <div class="invalid-feedback" id="calendarsFeedback"><i class="fas fa-exclamation-circle me-1"></i>${t("This field is required")}</div>
      </div>
    </div>

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
      <div id="quill-Desc" style="min-height:150px;border:1px solid #ccc;border-radius:4px"></div>
    </div>

    <div class="mb-3">
      <label class="form-label" for="quill-Text">${t("Additional Information")}</label>
      <div id="quill-Text" style="min-height:150px;border:1px solid #ccc;border-radius:4px"></div>
    </div>
  `;
}

// ---------------------------------------------------------------------------
// Modal orchestration
// ---------------------------------------------------------------------------

function buildModal(event, calendars, eventTypes, isEditMode) {
  const container = document.getElementById("calendar-event-app");
  if (!container) return;

  const allDay = isAllDay(event);

  // Build header
  let headerContent;
  if (isEditMode) {
    headerContent = `
      <div class="w-100 me-3 pt-1">
        <label class="form-label text-muted small mb-1" for="event-title-input">${t("Event Title")}</label>
        <input id="event-title-input" name="Title" value="${escapeHtml(event.Title || "")}"
          placeholder="${t("e.g. Sunday Service")}"
          class="form-control form-control-lg fw-bold border-0 border-bottom rounded-0 px-0" style="box-shadow:none">
        <div class="invalid-feedback" id="titleFeedback"><i class="fas fa-exclamation-circle me-1"></i>${t("This field is required")}</div>
      </div>`;
  } else {
    headerContent = `<h5 class="modal-title">${escapeHtml(event.Title || "")}</h5>`;
  }

  // Build body
  const bodyContent = isEditMode
    ? renderEditor(event, calendars, eventTypes, allDay)
    : renderViewer(event, calendars, eventTypes);

  // Build footer
  const deleteBtn = `<button type="button" class="btn btn-ghost-danger" id="eventDeleteBtn">
    <i class="fas fa-trash me-1"></i>${t("Delete")}</button>`;

  let rightBtns;
  if (isEditMode) {
    rightBtns = `<div class="d-flex gap-2">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${t("Cancel")}</button>
      <button type="button" class="btn btn-primary" id="eventSaveBtn" disabled>
        <i class="fas fa-save me-1"></i>${t("Save")}</button>
    </div>`;
  } else {
    rightBtns = `<div class="d-flex gap-2">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${t("Close")}</button>
      <button type="button" class="btn btn-primary" id="eventEditBtn">
        <i class="fas fa-pencil me-1"></i>${t("Edit")}</button>
    </div>`;
  }

  container.innerHTML = `
    <div class="modal fade" id="eventEditorModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header ${isEditMode ? "pb-0 border-bottom-0" : ""}">
            ${headerContent}
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="${t("Close")}"></button>
          </div>
          <div class="modal-body ${isEditMode ? "pt-3" : ""}" ${isEditMode ? 'style="overflow:visible"' : ""}>
            ${bodyContent}
          </div>
          <div class="modal-footer d-flex justify-content-between">
            ${deleteBtn}
            ${rightBtns}
          </div>
        </div>
      </div>
    </div>`;

  const modalEl = document.getElementById("eventEditorModal");
  currentModal = new window.bootstrap.Modal(modalEl, { backdrop: "static" });

  // On hidden, clean up
  modalEl.addEventListener("hidden.bs.modal", () => {
    cleanup();
    window.CRM.refreshAllFullCalendarSources();
  });

  // Delete handler
  const deleteBtnEl = document.getElementById("eventDeleteBtn");
  if (deleteBtnEl) {
    deleteBtnEl.addEventListener("click", () => {
      if (!window.confirm(t("Are you sure you want to delete this event?"))) return;
      fetch(`${CRMRoot}/api/events/${event.Id}`, {
        credentials: "include",
        method: "DELETE",
        headers: { Accept: "application/json", "Content-Type": "application/json" },
      })
        .then((r) => {
          if (!r.ok) throw new Error(`HTTP ${r.status}`);
          currentModal.hide();
        })
        .catch(() => {
          if (window.CRM?.notify) window.CRM.notify(t("Failed to delete event. Please try again."), { type: "danger" });
        });
    });
  }

  if (isEditMode) {
    initEditMode(event);
  } else {
    document.getElementById("eventEditBtn").addEventListener("click", () => {
      cleanup();
      buildModal(event, calendars, eventTypes, true);
    });
  }

  currentModal.show();
}

function initEditMode(event) {
  // Title input
  const titleInput = document.getElementById("event-title-input");
  titleInput.addEventListener("input", () => {
    event.Title = titleInput.value;
    validateForm(event);
  });

  // TomSelect for event type
  const eventTypeEl = document.getElementById("eventTypeSelect");
  tsEventType = new window.TomSelect(eventTypeEl, {
    placeholder: t("Select event type..."),
    allowEmptyOption: true,
  });
  tsEventType.on("change", (value) => {
    event.Type = value ? Number.parseInt(value, 10) : undefined;
  });

  // TomSelect for pinned calendars (multi)
  const calEl = document.getElementById("pinnedCalendarsSelect");
  tsCalendars = new window.TomSelect(calEl, {
    placeholder: t("Select calendars..."),
    plugins: ["remove_button"],
  });
  tsCalendars.on("change", () => {
    event.PinnedCalendars = tsCalendars.getValue().map((v) => Number.parseInt(v, 10));
    validateForm(event);
    // Show/hide validation
    const fb = document.getElementById("calendarsFeedback");
    if (fb) {
      fb.style.display = event.PinnedCalendars.length === 0 ? "block" : "none";
    }
  });

  // All-day toggle
  const dayTypeRadios = document.querySelectorAll('input[name="eventDayType"]');
  for (const radio of dayTypeRadios) {
    radio.addEventListener("change", () => {
      const nowAllDay = radio.value === "allday";
      const startInput = document.getElementById("eventStartDate");
      const endInput = document.getElementById("eventEndDate");

      if (nowAllDay) {
        const s = event.Start ? new Date(event.Start) : new Date();
        s.setHours(0, 0, 0, 0);
        event.Start = s;
        const e = event.End ? new Date(event.End) : new Date(s);
        e.setHours(0, 0, 0, 0);
        event.End = e;
      } else {
        const now = new Date();
        const s = event.Start ? new Date(event.Start) : new Date();
        s.setHours(now.getHours(), 0, 0, 0);
        event.Start = s;
        const e = event.End ? new Date(event.End) : new Date(s);
        e.setHours(now.getHours() + 1, 0, 0, 0);
        event.End = e;
      }

      startInput.type = nowAllDay ? "date" : "datetime-local";
      endInput.type = nowAllDay ? "date" : "datetime-local";
      startInput.value = formatDateForInput(event.Start, nowAllDay);
      endInput.value = formatDateForInput(event.End, nowAllDay);
      endInput.min = startInput.value || "";
      validateForm(event);
    });
  }

  // Date inputs
  const startInput = document.getElementById("eventStartDate");
  const endInput = document.getElementById("eventEndDate");
  startInput.addEventListener("change", () => {
    event.Start = startInput.value ? parseInputDate(startInput.value) : undefined;
    endInput.min = startInput.value || "";
    validateForm(event);
  });
  endInput.addEventListener("change", () => {
    event.End = endInput.value ? parseInputDate(endInput.value) : undefined;
    validateForm(event);
  });

  // Quill editors
  quillDesc = initializeQuillEditor("#quill-Desc", { placeholder: t("Enter text here...") });
  if (quillDesc && event.Desc) {
    quillDesc.root.innerHTML = event.Desc;
  }
  if (quillDesc) {
    quillDesc.on("text-change", () => {
      event.Desc = quillDesc.root.innerHTML;
    });
  }

  quillText = initializeQuillEditor("#quill-Text", { placeholder: t("Enter text here...") });
  if (quillText && event.Text) {
    quillText.root.innerHTML = event.Text;
  }
  if (quillText) {
    quillText.on("text-change", () => {
      event.Text = quillText.root.innerHTML;
    });
  }

  // Save handler
  document.getElementById("eventSaveBtn").addEventListener("click", () => {
    const url = `${CRMRoot}/api/events${event.Id !== 0 ? `/${event.Id}` : ""}`;
    const body = JSON.stringify(event, (_key, value) => {
      if (value instanceof Date) {
        return window.moment ? window.moment(value).format() : value.toISOString();
      }
      return value;
    });
    fetch(url, {
      credentials: "include",
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body,
    })
      .then((r) => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        currentModal.hide();
      })
      .catch(() => {
        if (window.CRM?.notify) window.CRM.notify(t("Failed to save event. Please try again."), { type: "danger" });
      });
  });

  // Initial validation
  validateForm(event);
}

function validateForm(event) {
  const saveBtn = document.getElementById("eventSaveBtn");
  if (!saveBtn) return;

  const titleInput = document.getElementById("event-title-input");
  const titleFb = document.getElementById("titleFeedback");
  const valid =
    event.Title &&
    event.Title.length > 0 &&
    event.PinnedCalendars &&
    event.PinnedCalendars.length > 0 &&
    event.Start != null &&
    event.End != null;

  saveBtn.disabled = !valid;

  // Show title validation feedback
  if (titleInput && titleFb) {
    if (event.Title !== undefined && event.Title.length === 0) {
      titleFb.style.display = "block";
    } else {
      titleFb.style.display = "none";
    }
  }
}

// ---------------------------------------------------------------------------
// Loading modal
// ---------------------------------------------------------------------------

function showLoadingModal() {
  const container = document.getElementById("calendar-event-app");
  if (!container) return;

  container.innerHTML = `
    <div class="modal fade" id="eventEditorModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
              ${t("Loading...")}
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="${t("Close")}"></button>
          </div>
        </div>
      </div>
    </div>`;

  const modalEl = document.getElementById("eventEditorModal");
  currentModal = new window.bootstrap.Modal(modalEl, { backdrop: "static" });
  modalEl.addEventListener("hidden.bs.modal", () => {
    cleanup();
    window.CRM.refreshAllFullCalendarSources();
  });
  currentModal.show();
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

window.showEventForm = (eventArg) => {
  cleanup();
  showLoadingModal();

  Promise.all([
    fetchJSON(`${CRMRoot}/api/events/${eventArg.id}`),
    fetchJSON(`${CRMRoot}/api/calendars`),
    fetchJSON(`${CRMRoot}/api/events/types`),
  ]).then(([eventData, calData, typeData]) => {
    const event = eventData;
    if (event.Start) event.Start = new Date(event.Start);
    if (event.End) event.End = new Date(event.End);

    cleanup();
    buildModal(event, calData.Calendars, typeData.EventTypes, false);
  });
};

window.showNewEventForm = (info) => {
  cleanup();
  showLoadingModal();

  const event = {
    Id: 0,
    Title: "",
    Type: 0,
    PinnedCalendars: [],
    Start: info.start,
    End: info.end,
  };

  Promise.all([fetchJSON(`${CRMRoot}/api/calendars`), fetchJSON(`${CRMRoot}/api/events/types`)]).then(
    ([calData, typeData]) => {
      cleanup();
      buildModal(event, calData.Calendars, typeData.EventTypes, true);
    },
  );
};
