/**
 * Leaflet map initialisation for the family detail view (/people/family/:id).
 *
 * PHP injects window.CRM.familyMapConfig = { lat, lng } when the family
 * has stored geocoded coordinates. Leaflet is loaded as a global from
 * skin/external/leaflet/leaflet.js (see webpack externals: { leaflet: 'L' }).
 *
 * Also handles the "Refresh Coordinates" button for families without coordinates.
 */
import L from "leaflet";
import { initRefreshCoordinatesBtn } from "./geo-refresh";

document.addEventListener("DOMContentLoaded", () => {
  // Initialize map if coordinates exist
  const config = window.CRM && window.CRM.familyMapConfig;
  if (config && config.lat !== undefined && config.lng !== undefined) {
    const map = L.map("map1", {
      scrollWheelZoom: false,
      dragging: false,
      zoomControl: false,
    }).setView([config.lat, config.lng], 14);

    L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution:
        '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors',
    }).addTo(map);

    L.marker([config.lat, config.lng]).addTo(map);
  }

  // Initialize refresh coordinates button
  initRefreshCoordinatesBtn();

  // Family Check-In modal (#6838) — check in all family members to one event
  initFamilyCheckin();
});

/**
 * Initialize the "Check In Family" modal handler.
 * Reads `window.CRM.familyCheckin = { familyPersonIds, activeEvents }` set by the view.
 */
function initFamilyCheckin() {
  const cfg = window.CRM?.familyCheckin;
  if (!cfg) return;

  const $ = window.$;
  if (!$) return;
  const t = window.i18next ? window.i18next.t.bind(window.i18next) : (s) => s;

  const $select = $("#familyCheckinEventSelect");
  const $submit = $("#familyCheckinSubmit");
  const $noEvents = $("#familyCheckinNoEvents");

  // Populate the event selector from the inline config
  if (Array.isArray(cfg.activeEvents) && cfg.activeEvents.length > 0) {
    cfg.activeEvents.forEach((evt) => {
      $("<option>").attr("value", evt.id).text(`${evt.title} — ${evt.date}`).appendTo($select);
    });
  } else {
    $select.prop("disabled", true);
    $noEvents.removeClass("d-none");
  }

  // Enable submit only when a valid event is selected
  $select.on("change", function () {
    $submit.prop("disabled", !this.value);
  });

  // POST to /api/events/{id}/checkin-people with all family member IDs
  $submit.on("click", () => {
    const eventId = $select.val();
    if (!eventId || !cfg.familyPersonIds?.length) return;

    $submit
      .prop("disabled", true)
      .html(`<span class="spinner-border spinner-border-sm me-1"></span>${t("Working...")}`);

    fetch(`${window.CRM.root}/api/events/${eventId}/checkin-people`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ personIds: cfg.familyPersonIds }),
    })
      .then((res) => {
        if (!res.ok) return res.json().then((d) => Promise.reject(d));
        return res.json();
      })
      .then((data) => {
        window.bootstrap.Modal.getInstance(document.getElementById("familyCheckinModal"))?.hide();
        const msg = t("Checked in {{count}} people.").replace(
          "{{count}}",
          data.checkedIn || cfg.familyPersonIds.length,
        );
        window.CRM.notify(msg, { type: "success", delay: 4000 });
      })
      .catch((err) => {
        const msg = err?.message || t("Family check-in failed. Please try again.");
        window.CRM.notify(msg, { type: "danger", delay: 5000 });
        $submit.prop("disabled", false).html(`<i class="fa-solid fa-check me-1"></i>${t("Check In")}`);
      });
  });
}
