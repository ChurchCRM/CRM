/**
 * map-view.js — Congregation map powered by Leaflet + OpenStreetMap
 *
 * Reads window.CRM.mapConfig (set by people/views/map-view.php) and
 * fetches family/person data from GET /api/map/families[?groupId=N].
 *
 * Leaflet is loaded as a global from skin/external/leaflet/leaflet.js
 * (see webpack externals: { leaflet: 'L' }). No Google Maps API key required.
 */
import L from "leaflet";
import { buildAPIUrl } from "../api-utils";

// ---------------------------------------------------------------------------
// "Update All Coordinates" handler
// Registered at the top level (outside the cfg/#map guard) so the button
// in the page header always works, even when the church has no location set.
// ---------------------------------------------------------------------------
const geocodeAllBtn = document.getElementById("geocodeAllBtn");
if (geocodeAllBtn) {
  geocodeAllBtn.addEventListener("click", () => {
    const t = window.i18next ? window.i18next.t.bind(window.i18next) : (s) => s;

    window.bootbox.confirm({
      title: t("Update All Family Coordinates"),
      message: t(
        "This will geocode all families missing coordinates using Nominatim (OpenStreetMap). " +
          "It processes up to 50 families per run at ~1 request/second. " +
          "Continue?",
      ),
      buttons: {
        confirm: { label: t("Update Coordinates"), className: "btn-primary" },
        cancel: { label: t("Cancel"), className: "btn-secondary" },
      },
      callback: (result) => {
        if (!result) return;

        const originalHtml = geocodeAllBtn.innerHTML;
        geocodeAllBtn.disabled = true;
        geocodeAllBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-1"></i>${t("Geocoding...")} `;

        fetch(buildAPIUrl("map/geocode-all"), {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json" },
        })
          .then((res) => {
            if (!res.ok) {
              return res.json().then((body) => {
                throw new Error(body.message || res.statusText);
              });
            }
            return res.json();
          })
          .then((data) => {
            geocodeAllBtn.disabled = false;
            geocodeAllBtn.innerHTML = originalHtml;

            const msg =
              data.remaining > 0
                ? t(
                    `Geocoded {{geocoded}} of {{total}} families. {{remaining}} still missing — run again to continue.`,
                    { geocoded: data.geocoded, total: data.total, remaining: data.remaining },
                  )
                : t(`Geocoded {{geocoded}} families ({{failed}} could not be resolved).`, {
                    geocoded: data.geocoded,
                    failed: data.failed,
                  });

            window.CRM.notify(msg, { type: data.geocoded > 0 ? "success" : "warning", delay: 8000 });

            // Reload after a brief pause so the map refreshes with new markers
            if (data.geocoded > 0) {
              setTimeout(() => window.location.reload(), 2000);
            }
          })
          .catch((err) => {
            geocodeAllBtn.disabled = false;
            geocodeAllBtn.innerHTML = originalHtml;
            window.CRM.notify(t("Failed to update coordinates") + (err.message ? `: ${err.message}` : ""), {
              type: "error",
            });
          });
      },
    });
  });
}

const cfg = window.CRM.mapConfig;

if (cfg && document.getElementById("map")) {
  // -- Map init ---------------------------------------------------------------
  const map = L.map("map").setView([cfg.churchLat, cfg.churchLng], cfg.zoom);

  L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors',
  }).addTo(map);

  // Church marker using the existing church icon
  const churchIcon = L.icon({
    iconUrl: `${window.CRM.root}/skin/icons/church.png`,
    iconSize: [32, 32],
    iconAnchor: [16, 32],
    popupAnchor: [0, -34],
  });
  const churchPopup = document.createElement("strong");
  churchPopup.textContent = cfg.churchName;
  L.marker([cfg.churchLat, cfg.churchLng], { icon: churchIcon }).bindPopup(churchPopup).addTo(map);

  // -- Legend control (desktop, bottom-right) ---------------------------------
  const legendControl = L.control({ position: "bottomright" });
  legendControl.onAdd = () => document.getElementById("map-legend");
  legendControl.addTo(map);

  // -- Colour lookup (keyed by legend item id) --------------------------------
  // Build from cfg.legendItems so marker colors always match the legend exactly,
  // regardless of whether ids are sequential or sparse database OptionIds.
  const legendColorMap = {};
  for (const item of cfg.legendItems || []) {
    legendColorMap[item.id] = item.color;
  }

  const colorFor = (id) => legendColorMap[id] || cfg.markerColors[id % cfg.markerColors.length] || "#6c757d";

  // -- Legend key: roleId in group mode, classificationId otherwise -----------
  const legendIdFor = (item) => (cfg.legendType === "roles" ? item.roleId || 0 : item.classificationId || 0);

  // -- Marker tracking (keyed by legend ID) -----------------------------------
  const classMarkers = {};

  const addMarker = (item) => {
    const color = colorFor(legendIdFor(item));
    const marker = L.circleMarker([item.latitude, item.longitude], {
      radius: 8,
      color,
      fillColor: color,
      fillOpacity: 0.85,
      weight: 2,
    });

    marker.bindPopup(() => {
      let html = `<strong><a href="${item.profileUrl}">${item.salutation}</a></strong><br>${item.address}`;
      if (item.phone) {
        html += `<br><a href="tel:${encodeURIComponent(item.phone)}">${window.CRM.escapeHtml(item.phone)}</a>`;
      }
      if (item.directionsUrl) {
        html +=
          `<br><a href="${window.CRM.escapeHtml(item.directionsUrl)}" target="_blank" rel="noopener noreferrer" ` +
          'class="btn btn-sm btn-outline-primary mt-1">' +
          '<i class="fa-solid fa-diamond-turn-right me-1"></i>Get Directions</a>';
      }
      return html;
    });
    marker.addTo(map);

    const cid = legendIdFor(item);
    if (!classMarkers[cid]) {
      classMarkers[cid] = [];
    }
    classMarkers[cid].push(marker);
  };

  // -- Fetch family/person data from the REST API -----------------------------
  let apiUrl = cfg.apiUrl;
  if (cfg.groupId !== null && cfg.groupId !== undefined) {
    apiUrl += `?groupId=${cfg.groupId}`;
  }

  fetch(apiUrl, { credentials: "same-origin" })
    .then((res) => {
      if (!res.ok) {
        throw new Error(`API error ${res.status}`);
      }
      return res.json();
    })
    .then((items) => {
      for (const item of items) {
        addMarker(item);
      }
    })
    .catch((err) => {
      console.error("Map: failed to load family data", err);
    });

  // -- Legend item click/keyboard interaction ---------------------------------
  // .legend-item elements replace raw checkboxes; toggle .inactive class.
  // Desktop and mobile share the same legendId so both stay in sync.
  // aria-pressed is kept in sync so screen readers report the toggled state.
  const toggleLegendItem = (item) => {
    const legendId = Number.parseInt(item.dataset.legendId, 10);
    const isActive = !item.classList.contains("inactive");

    // Toggle all items with the same legendId (desktop + mobile)
    for (const sibling of document.querySelectorAll(`.legend-item[data-legend-id="${legendId}"]`)) {
      sibling.classList.toggle("inactive", isActive);
      sibling.setAttribute("aria-pressed", isActive ? "false" : "true");
    }

    // Show / hide matching map markers
    for (const m of classMarkers[legendId] || []) {
      if (isActive) {
        map.removeLayer(m);
      } else {
        m.addTo(map);
      }
    }
  };

  for (const item of document.querySelectorAll(".legend-item")) {
    item.addEventListener("click", () => toggleLegendItem(item));
    item.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        toggleLegendItem(item);
      }
    });
  }
}
