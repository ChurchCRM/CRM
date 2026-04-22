/**
 * Event Editor page — boots the shared `event-form.js` renderer on the
 * full-page /event/editor/:id surface. The same renderer powers the
 * calendar modal, so field set, validation, and save payload stay
 * identical between the two entry points.
 */

import { deleteEvent, renderEventEditor, saveEvent } from "./event-form.js";

const CRMRoot = window.CRM.root;
const t = (key) => (window.i18next ? window.i18next.t(key) : key);
const cfg = window.CRM.eventEditorPage || {};

function fetchJSON(url, fallback = null) {
  return fetch(url, { credentials: "include" })
    .then((r) => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.status === 204 ? {} : r.json();
    })
    .catch(() => fallback);
}

function escapeHtml(str) {
  if (!str) return "";
  const div = document.createElement("div");
  div.textContent = str;
  return div.innerHTML.replace(/"/g, "&quot;").replace(/'/g, "&#39;");
}

function showError(message) {
  const mount = document.getElementById("event-editor-mount");
  if (!mount) return;
  mount.innerHTML = `<div class="alert alert-danger mb-0"><i class="ti ti-alert-triangle me-1"></i>${escapeHtml(message)}</div>`;
}

document.addEventListener("DOMContentLoaded", () => {
  const mount = document.getElementById("event-editor-mount");
  const titleHost = document.getElementById("event-editor-title-host");
  const actions = document.getElementById("event-editor-actions");
  const saveBtn = document.getElementById("event-editor-save");
  const deleteBtn = document.getElementById("event-editor-delete");
  if (!mount || !titleHost) return;

  const fetches = [
    fetchJSON(`${CRMRoot}/api/calendars`, { Calendars: [] }),
    fetchJSON(`${CRMRoot}/api/events/types`, { EventTypes: [] }),
    fetchJSON(`${CRMRoot}/api/groups/`, []),
  ];
  if (cfg.eventId > 0) {
    fetches.unshift(fetchJSON(`${CRMRoot}/api/events/${cfg.eventId}`, null));
  }

  Promise.all(fetches)
    .then((results) => {
      let eventData = null;
      let calData;
      let typeData;
      let groups;
      if (cfg.eventId > 0) {
        [eventData, calData, typeData, groups] = results;
        if (eventData === null) throw new Error("Failed to load event");
      } else {
        [calData, typeData, groups] = results;
      }

      const event = eventData || {
        Id: 0,
        Title: "",
        Type: cfg.typeId || 0,
        PinnedCalendars: [],
        Start: null,
        End: null,
        InActive: 0,
        LinkedGroupId: 0,
        AttendanceCounts: [],
      };
      if (event.Start) event.Start = new Date(event.Start);
      if (event.End) event.End = new Date(event.End);

      // Seed Type for brand-new events: the first available type is the
      // sensible default and ensures the save payload never submits Type:0.
      if (!event.Type && typeData.EventTypes.length > 0) {
        event.Type = typeData.EventTypes[0].Id;
      }

      // Clear the "Loading…" spinner before mounting the form.
      mount.innerHTML = "";

      const controller = renderEventEditor(mount, event, calData.Calendars, typeData.EventTypes, {
        titleHost,
        groups: Array.isArray(groups) ? groups : [],
        onValidityChange: (valid) => {
          if (saveBtn) saveBtn.disabled = !valid;
        },
      });

      if (actions) actions.classList.remove("d-none");

      if (saveBtn) {
        saveBtn.addEventListener("click", () => {
          saveBtn.disabled = true;
          saveEvent(controller.getEvent(), CRMRoot)
            .then(() => {
              window.location.href = cfg.redirectUrl;
            })
            .catch(() => {
              saveBtn.disabled = false;
              if (window.CRM?.notify) {
                window.CRM.notify(t("Failed to save event. Please try again."), { type: "danger" });
              }
            });
        });
      }

      if (deleteBtn && cfg.eventId > 0) {
        deleteBtn.addEventListener("click", () => {
          window.bootbox.confirm({
            title: t("Delete this event?"),
            message:
              t("Deleting this event will also delete all attendance records. This cannot be undone.") +
              ` <strong>${escapeHtml(event.Title || "")}</strong>`,
            buttons: {
              cancel: { label: `<i class="ti ti-x"></i> ${t("Cancel")}` },
              confirm: { label: `<i class="ti ti-trash"></i> ${t("Delete")}`, className: "btn-danger" },
            },
            callback: (confirmed) => {
              if (!confirmed) return;
              deleteEvent(cfg.eventId, CRMRoot)
                .then(() => {
                  window.location.href = cfg.redirectUrl;
                })
                .catch(() => {
                  if (window.CRM?.notify) {
                    window.CRM.notify(t("Failed to delete event. Please try again."), { type: "danger" });
                  }
                });
            },
          });
        });
      }
    })
    .catch((err) => {
      console.error("event-editor boot error:", err);
      showError(t("Failed to load event editor. Please refresh and try again."));
    });
});
