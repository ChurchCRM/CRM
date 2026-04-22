/**
 * Calendar Event Editor
 *
 * Bootstrap 5 modal for viewing/editing/creating calendar events.
 * Uses a single modal instance with content swapping to avoid Bootstrap
 * transition race conditions. TomSelect for dropdowns, native datetime
 * inputs, and the shared Quill editor for rich text.
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
  const dateOnly = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (dateOnly) {
    return new Date(Number(dateOnly[1]), Number(dateOnly[2]) - 1, Number(dateOnly[3]));
  }
  return new Date(value);
}

function formatDateForInput(date, allDay) {
  if (!date) return "";
  const d = new Date(date);
  if (allDay) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
  }
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
let modalEl = null;
let quillDesc = null;
let quillText = null;
let tsEventType = null;
let tsCalendars = null;
let activeRequestId = 0;

// ---------------------------------------------------------------------------
// Widget cleanup (TomSelect, Quill) — called before content swap
// ---------------------------------------------------------------------------

function destroyWidgets() {
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
}

// ---------------------------------------------------------------------------
// Full cleanup — destroys modal instance and clears container
// ---------------------------------------------------------------------------

function cleanup() {
  destroyWidgets();

  if (currentModal) {
    // Remove fade class to prevent Bootstrap transition callbacks from firing
    // after dispose() nulls the internal element reference.
    if (modalEl) {
      modalEl.classList.remove("fade", "show");
      modalEl.removeAttribute("role");
    }
    try {
      currentModal.dispose();
    } catch (_e) {
      // dispose() can throw if called during a show/hide transition
    }
    currentModal = null;
  }

  modalEl = null;

  // Remove any leftover backdrop and body overrides
  document.querySelectorAll(".modal-backdrop").forEach((el) => el.remove());
  document.body.classList.remove("modal-open");
  document.body.style.removeProperty("overflow");
  document.body.style.removeProperty("padding-right");

  const container = document.getElementById("calendar-event-app");
  if (container) container.innerHTML = "";
}

// ---------------------------------------------------------------------------
// Modal shell — created once per interaction, content swapped in place
// ---------------------------------------------------------------------------

function createAndShowModal() {
  const container = document.getElementById("calendar-event-app");
  if (!container) return;

  container.innerHTML = `
    <div class="modal fade" id="eventEditorModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header" id="eventModalHeader">
            <h5 class="modal-title">
              <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
              ${t("Loading...")}
            </h5>
            <button type="button" class="btn-close" id="eventCloseXBtn" aria-label="${t("Close")}"></button>
          </div>
          <div class="modal-body" id="eventModalBody"></div>
          <div class="modal-footer d-flex justify-content-between d-none" id="eventModalFooter"></div>
        </div>
      </div>
    </div>`;

  modalEl = document.getElementById("eventEditorModal");
  currentModal = new window.bootstrap.Modal(modalEl, { backdrop: "static" });

  modalEl.addEventListener("hidden.bs.modal", () => {
    cleanup();
    window.CRM.refreshAllFullCalendarSources();
  });

  currentModal.show();
  bindCloseHandlers();
}

// ---------------------------------------------------------------------------
// Viewer (read-only body content)
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
// Editor (edit-mode body content)
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
      <div id="quill-Desc" class="quill-editor-container" data-editor-size="compact"></div>
    </div>

    <div class="mb-3">
      <label class="form-label" for="quill-Text">${t("Additional Information")}</label>
      <div id="quill-Text" class="quill-editor-container" data-editor-size="compact"></div>
    </div>
  `;
}

// ---------------------------------------------------------------------------
// Content swap — viewer mode
// ---------------------------------------------------------------------------

function showViewContent(event, calendars, eventTypes) {
  if (!modalEl) return;
  destroyWidgets();

  const header = modalEl.querySelector("#eventModalHeader");
  const body = modalEl.querySelector("#eventModalBody");
  const footer = modalEl.querySelector("#eventModalFooter");

  header.innerHTML = `
    <h5 class="modal-title">${escapeHtml(event.Title || "")}</h5>
    <button type="button" class="btn-close" id="eventCloseXBtn" aria-label="${t("Close")}"></button>`;
  header.className = "modal-header";

  body.innerHTML = renderViewer(event, calendars, eventTypes);
  body.className = "modal-body";
  body.removeAttribute("style");

  footer.innerHTML = `
    <button type="button" class="btn btn-ghost-danger" id="eventDeleteBtn">
      <i class="fas fa-trash me-1"></i>${t("Delete")}</button>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-secondary" id="eventCancelBtn">${t("Close")}</button>
      <a class="btn btn-outline-primary" id="eventCheckinBtn"
         href="${(window.CRM && window.CRM.root) || ""}/event/checkin/${event.Id}">
        <i class="fas fa-clipboard-check me-1"></i>${t("Check-in")}</a>
      <button type="button" class="btn btn-primary" id="eventEditBtn">
        <i class="fas fa-pencil me-1"></i>${t("Edit")}</button>
    </div>`;
  footer.className = "modal-footer d-flex justify-content-between";

  // Edit button switches to edit mode (same modal, content swap)
  document.getElementById("eventEditBtn").addEventListener("click", () => {
    showEditContent(event, calendars, eventTypes);
  });

  bindCloseHandlers();
  bindDeleteHandler(event);
}

// ---------------------------------------------------------------------------
// Content swap — editor mode
// ---------------------------------------------------------------------------

function showEditContent(event, calendars, eventTypes) {
  if (!modalEl) return;
  destroyWidgets();

  const allDay = isAllDay(event);
  const header = modalEl.querySelector("#eventModalHeader");
  const body = modalEl.querySelector("#eventModalBody");
  const footer = modalEl.querySelector("#eventModalFooter");

  header.innerHTML = `
    <div class="w-100 me-3 pt-1">
      <label class="form-label text-muted small mb-1" for="event-title-input">${t("Event Title")}</label>
      <input id="event-title-input" name="Title" value="${escapeHtml(event.Title || "")}"
        placeholder="${t("e.g. Sunday Service")}"
        class="form-control form-control-lg fw-bold border-0 border-bottom rounded-0 px-0" style="box-shadow:none">
      <div class="invalid-feedback" id="titleFeedback"><i class="fas fa-exclamation-circle me-1"></i>${t("This field is required")}</div>
    </div>
    <button type="button" class="btn-close" id="eventCloseXBtn" aria-label="${t("Close")}"></button>`;
  header.className = "modal-header pb-0 border-bottom-0";

  body.innerHTML = renderEditor(event, calendars, eventTypes, allDay);
  body.className = "modal-body pt-3";
  body.style.overflow = "visible";

  footer.innerHTML = `
    <button type="button" class="btn btn-ghost-danger" id="eventDeleteBtn">
      <i class="fas fa-trash me-1"></i>${t("Delete")}</button>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-secondary" id="eventCancelBtn">${t("Cancel")}</button>
      <button type="button" class="btn btn-primary" id="eventSaveBtn" disabled>
        <i class="fas fa-save me-1"></i>${t("Save")}</button>
    </div>`;
  footer.className = "modal-footer d-flex justify-content-between";

  bindCloseHandlers();
  initEditMode(event);
  bindDeleteHandler(event);
}

// ---------------------------------------------------------------------------
// Close modal — bypasses Bootstrap's hide animation (which doesn't reliably
// complete on dynamically swapped content) and removes the element directly.
// ---------------------------------------------------------------------------

function closeModal() {
  cleanup();
  window.CRM.refreshAllFullCalendarSources();
}

function bindCloseHandlers() {
  const xBtn = document.getElementById("eventCloseXBtn");
  if (xBtn) xBtn.addEventListener("click", closeModal);
  const cancelBtn = document.getElementById("eventCancelBtn");
  if (cancelBtn) cancelBtn.addEventListener("click", closeModal);
}

// ---------------------------------------------------------------------------
// Shared delete handler
// ---------------------------------------------------------------------------

function bindDeleteHandler(event) {
  const deleteBtn = document.getElementById("eventDeleteBtn");
  if (!deleteBtn) return;

  // Hide delete button for new (unsaved) events
  if (event.Id === 0) {
    deleteBtn.classList.add("d-none");
    return;
  }

  deleteBtn.addEventListener("click", () => {
    bootbox.confirm({
      title: t("Delete this event?"),
      message:
        t("Deleting this event will also delete all attendance records. This cannot be undone.") +
        ` <strong>${escapeHtml(event.Title || "")}</strong>`,
      buttons: {
        cancel: { label: '<i class="ti ti-x"></i> ' + t("Cancel") },
        confirm: { label: '<i class="ti ti-trash"></i> ' + t("Delete"), className: "btn-danger" },
      },
      callback: (confirmed) => {
        if (!confirmed) return;
        fetch(`${CRMRoot}/api/events/${event.Id}`, {
          credentials: "include",
          method: "DELETE",
          headers: { Accept: "application/json", "Content-Type": "application/json" },
        })
          .then((r) => {
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            closeModal();
          })
          .catch(() => {
            if (window.CRM?.notify)
              window.CRM.notify(t("Failed to delete event. Please try again."), { type: "danger" });
          });
      },
    });
  });
}

// ---------------------------------------------------------------------------
// Edit mode — wire up all form handlers
// ---------------------------------------------------------------------------

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
        closeModal();
      })
      .catch(() => {
        if (window.CRM?.notify) window.CRM.notify(t("Failed to save event. Please try again."), { type: "danger" });
      });
  });

  // Initial validation
  validateForm(event);
}

// ---------------------------------------------------------------------------
// Form validation
// ---------------------------------------------------------------------------

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

  if (titleInput && titleFb) {
    if (event.Title !== undefined && event.Title.length === 0) {
      titleFb.style.display = "block";
    } else {
      titleFb.style.display = "none";
    }
  }
}

// ---------------------------------------------------------------------------
// Error display inside modal
// ---------------------------------------------------------------------------

function showErrorContent(message) {
  if (!modalEl) return;
  destroyWidgets();

  const header = modalEl.querySelector("#eventModalHeader");
  const body = modalEl.querySelector("#eventModalBody");
  const footer = modalEl.querySelector("#eventModalFooter");

  header.innerHTML = `
    <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>${t("Error")}</h5>
    <button type="button" class="btn-close" id="eventCloseXBtn" aria-label="${t("Close")}"></button>`;
  header.className = "modal-header";

  body.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(message)}</div>`;
  body.className = "modal-body";
  body.removeAttribute("style");

  footer.innerHTML = `
    <div></div>
    <button type="button" class="btn btn-secondary" id="eventCancelBtn">${t("Close")}</button>`;
  footer.className = "modal-footer d-flex justify-content-between";

  bindCloseHandlers();
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

window.showEventForm = (eventArg) => {
  cleanup();
  createAndShowModal();

  const thisRequest = ++activeRequestId;

  Promise.all([
    fetchJSON(`${CRMRoot}/api/events/${eventArg.id}`),
    fetchJSON(`${CRMRoot}/api/calendars`),
    fetchJSON(`${CRMRoot}/api/events/types`),
  ])
    .then(([eventData, calData, typeData]) => {
      if (thisRequest !== activeRequestId) return;
      const event = eventData;
      if (event.Start) event.Start = new Date(event.Start);
      if (event.End) event.End = new Date(event.End);
      showViewContent(event, calData.Calendars, typeData.EventTypes);
    })
    .catch((err) => {
      if (thisRequest !== activeRequestId) return;
      showErrorContent(t("Failed to load event. Please try again."));
      console.error("showEventForm error:", err);
    });
};

window.showNewEventForm = (info) => {
  cleanup();
  createAndShowModal();

  const thisRequest = ++activeRequestId;

  const event = {
    Id: 0,
    Title: "",
    Type: 0,
    PinnedCalendars: [],
    Start: info.start,
    End: info.end,
  };

  Promise.all([fetchJSON(`${CRMRoot}/api/calendars`), fetchJSON(`${CRMRoot}/api/events/types`)])
    .then(([calData, typeData]) => {
      if (thisRequest !== activeRequestId) return;
      // No EventType has Id=0, so the initial Type:0 matches nothing and the
      // rendered <select> has no `selected` option — the browser shows the
      // first option but TomSelect's change never fires, so the payload stays
      // at 0 and the API rejects it. Seed with the first type's Id so the
      // visible default and the payload agree.
      if (!event.Type && typeData.EventTypes.length > 0) {
        event.Type = typeData.EventTypes[0].Id;
      }
      showEditContent(event, calData.Calendars, typeData.EventTypes);
    })
    .catch((err) => {
      if (thisRequest !== activeRequestId) return;
      showErrorContent(t("Failed to load calendar data. Please try again."));
      console.error("showNewEventForm error:", err);
    });
};
