/**
 * Calendar Event Editor — Bootstrap 5 modal shell for viewing/editing/creating
 * calendar events. Uses a single modal instance with content swapping to
 * avoid Bootstrap transition race conditions. The form markup and widget
 * wiring live in `./event-form.js` so the full-page /event/editor/:id
 * surface can render the identical form inline.
 */

import { deleteEvent, renderEventEditor, renderEventViewer, saveEvent } from "./event-form.js";

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

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------

let currentModal = null;
let modalEl = null;
let formController = null;
let activeRequestId = 0;

// ---------------------------------------------------------------------------
// Teardown
// ---------------------------------------------------------------------------

function destroyForm() {
  if (formController) {
    formController.destroy();
    formController = null;
  }
}

function cleanup() {
  destroyForm();

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
// Viewer mode — read-only content swap
// ---------------------------------------------------------------------------

function showViewContent(event, calendars, eventTypes, groups = []) {
  if (!modalEl) return;
  destroyForm();

  const header = modalEl.querySelector("#eventModalHeader");
  const body = modalEl.querySelector("#eventModalBody");
  const footer = modalEl.querySelector("#eventModalFooter");

  header.innerHTML = `
    <h5 class="modal-title">${escapeHtml(event.Title || "")}</h5>
    <button type="button" class="btn-close" id="eventCloseXBtn" aria-label="${t("Close")}"></button>`;
  header.className = "modal-header";

  formController = renderEventViewer(body, event, calendars, eventTypes, { groups });
  body.className = "modal-body";
  body.removeAttribute("style");

  footer.innerHTML = `
    <button type="button" class="btn btn-ghost-danger" id="eventDeleteBtn">
      <i class="fas fa-trash me-1"></i>${t("Delete")}</button>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-secondary" id="eventCancelBtn">${t("Close")}</button>
      <a class="btn btn-outline-primary" id="eventCheckinBtn"
         href="${CRMRoot}/event/checkin/${event.Id}">
        <i class="fas fa-clipboard-check me-1"></i>${t("Check-in")}</a>
      <button type="button" class="btn btn-primary" id="eventEditBtn">
        <i class="fas fa-pencil me-1"></i>${t("Edit")}</button>
    </div>`;
  footer.className = "modal-footer d-flex justify-content-between";

  document.getElementById("eventEditBtn").addEventListener("click", () => {
    showEditContent(event, calendars, eventTypes, groups);
  });

  bindCloseHandlers();
  bindDeleteHandler(event);
}

// ---------------------------------------------------------------------------
// Editor mode — form content swap
// ---------------------------------------------------------------------------

function showEditContent(event, calendars, eventTypes, groups = []) {
  if (!modalEl) return;
  destroyForm();

  const header = modalEl.querySelector("#eventModalHeader");
  const body = modalEl.querySelector("#eventModalBody");
  const footer = modalEl.querySelector("#eventModalFooter");

  header.innerHTML = `<button type="button" class="btn-close ms-auto" id="eventCloseXBtn" aria-label="${t("Close")}"></button>`;
  header.className = "modal-header pb-0 border-bottom-0 flex-column align-items-stretch";

  body.className = "modal-body pt-3";
  body.style.overflow = "visible";

  // Prepend a header host inside the header so the title renders as the big
  // bold inline input (consistent with pre-refactor look).
  const titleHost = document.createElement("div");
  titleHost.id = "eventModalTitleHost";
  header.insertBefore(titleHost, header.firstChild);

  footer.innerHTML = `
    <button type="button" class="btn btn-ghost-danger" id="eventDeleteBtn">
      <i class="fas fa-trash me-1"></i>${t("Delete")}</button>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-secondary" id="eventCancelBtn">${t("Cancel")}</button>
      <button type="button" class="btn btn-primary" id="eventSaveBtn" disabled>
        <i class="fas fa-save me-1"></i>${t("Save")}</button>
    </div>`;
  footer.className = "modal-footer d-flex justify-content-between";

  formController = renderEventEditor(body, event, calendars, eventTypes, {
    titleHost,
    groups,
    onValidityChange: (valid) => {
      const saveBtn = document.getElementById("eventSaveBtn");
      if (saveBtn) saveBtn.disabled = !valid;
    },
  });

  document.getElementById("eventSaveBtn").addEventListener("click", () => {
    saveEvent(formController.getEvent(), CRMRoot)
      .then(() => closeModal())
      .catch(() => {
        if (window.CRM?.notify) window.CRM.notify(t("Failed to save event. Please try again."), { type: "danger" });
      });
  });

  bindCloseHandlers();
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
// Delete handler (confirmed via bootbox, shared across view + edit footers)
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
    window.bootbox.confirm({
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
        deleteEvent(event.Id, CRMRoot)
          .then(() => closeModal())
          .catch(() => {
            if (window.CRM?.notify) {
              window.CRM.notify(t("Failed to delete event. Please try again."), { type: "danger" });
            }
          });
      },
    });
  });
}

// ---------------------------------------------------------------------------
// Error display inside modal
// ---------------------------------------------------------------------------

function showErrorContent(message) {
  if (!modalEl) return;
  destroyForm();

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
    fetchJSON(`${CRMRoot}/api/groups/`).catch(() => []),
  ])
    .then(([eventData, calData, typeData, groups]) => {
      if (thisRequest !== activeRequestId) return;
      const event = eventData;
      if (event.Start) event.Start = new Date(event.Start);
      if (event.End) event.End = new Date(event.End);
      showViewContent(event, calData.Calendars, typeData.EventTypes, Array.isArray(groups) ? groups : []);
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

  Promise.all([
    fetchJSON(`${CRMRoot}/api/calendars`),
    fetchJSON(`${CRMRoot}/api/events/types`),
    fetchJSON(`${CRMRoot}/api/groups/`).catch(() => []),
  ])
    .then(([calData, typeData, groups]) => {
      if (thisRequest !== activeRequestId) return;
      // No EventType has Id=0, so the initial Type:0 matches nothing and the
      // rendered <select> has no `selected` option — the browser shows the
      // first option but TomSelect's change never fires, so the payload stays
      // at 0 and the API rejects it. Seed with the first type's Id so the
      // visible default and the payload agree.
      if (!event.Type && typeData.EventTypes.length > 0) {
        event.Type = typeData.EventTypes[0].Id;
      }
      showEditContent(event, calData.Calendars, typeData.EventTypes, Array.isArray(groups) ? groups : []);
    })
    .catch((err) => {
      if (thisRequest !== activeRequestId) return;
      showErrorContent(t("Failed to load calendar data. Please try again."));
      console.error("showNewEventForm error:", err);
    });
};
