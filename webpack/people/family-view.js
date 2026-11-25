/**
 * Leaflet map initialisation for the family detail view (/people/family/:id).
 *
 * PHP injects window.CRM.familyMapConfig = { lat, lng } when the family
 * has stored geocoded coordinates. Leaflet comes from webpack/leaflet-global.js.
 *
 * Also handles the "Refresh Coordinates" button for families without coordinates.
 */
import L from "../leaflet-global";
import { initRefreshCoordinatesBtn } from "./geo-refresh";
import { initPropertyPopup } from "./property-popup";
import { initTimelineFilter } from "./timeline-filter";

function initTabLinks() {
  const showTab = (id) => {
    const trigger = document.querySelector(`.nav-link[href="#${id}"]`);
    if (!trigger) return false;
    window.bootstrap.Tab.getOrCreateInstance(trigger).show();
    return true;
  };
  for (const el of document.querySelectorAll("[data-open-tab]")) {
    el.addEventListener("click", (event) => {
      event.preventDefault();
      if (showTab(el.dataset.openTab)) {
        document.getElementById(el.dataset.openTab)?.scrollIntoView({ behavior: "smooth" });
      }
    });
  }
  if (window.location.hash) showTab(window.location.hash.slice(1));
  for (const el of document.querySelectorAll('.nav-link[data-bs-toggle="tab"]')) {
    el.addEventListener("shown.bs.tab", () => {
      window.$?.fn?.dataTable?.tables({ visible: true, api: true }).columns.adjust();
    });
  }
}

function initGivingSummary() {
  const summary = document.getElementById("family-giving-summary");
  if (!summary) return;
  window.CRM.APIRequest({ path: `payments/family/${window.CRM.currentFamily}/list` })
    .then((resp) => {
      const rows = (resp?.data ?? []).filter((row) => row.PledgeOrPayment === "Payment");
      if (!rows.length) return;
      const money = (amount) => window.CRM.currency.format(amount);
      const currentFY = window.CRM.currentFY || "";
      const ytd = rows
        .filter((row) => currentFY && row.FormattedFY === currentFY)
        .reduce((sum, row) => sum + (parseFloat(row.Amount) || 0), 0);
      const last = rows.reduce((a, b) => (b.Date > a.Date ? b : a));
      document.getElementById("family-giving-ytd").textContent = money(ytd);
      document.getElementById("family-giving-last").textContent =
        `${money(parseFloat(last.Amount))} — ${last.Date || ""}`;
      summary.classList.remove("d-none");
    })
    .catch(() => {});
}

document.addEventListener("DOMContentLoaded", () => {
  initTabLinks();
  initPropertyPopup({
    modalId: "add-family-property-modal",
    selectId: "input-family-properties",
    promptBoxId: "family-property-prompt-box",
    assignBtnId: "assign-family-property-btn",
    onSave: (propertyId, value) => {
      window.CRM.APIRequest({
        method: "POST",
        path: `people/properties/family/${window.CRM.currentFamily}/${propertyId}`,
        data: JSON.stringify({ value }),
      }).then(() => window.location.reload());
    },
  });
  initGivingSummary();
  document.querySelectorAll(".timeline-container").forEach(initTimelineFilter);
  // Initialize map if coordinates exist and the map container is in the DOM
  const config = window.CRM?.familyMapConfig;
  const mapEl = document.getElementById("map1");
  if (config && config.lat !== undefined && config.lng !== undefined && mapEl) {
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
