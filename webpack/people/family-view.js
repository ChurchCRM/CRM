/**
 * Leaflet map initialisation for the family detail view (/v2/family/:id).
 *
 * PHP injects window.CRM.familyMapConfig = { lat, lng } when the family
 * has stored geocoded coordinates. Leaflet is loaded as a global from
 * skin/external/leaflet/leaflet.js (see webpack externals: { leaflet: 'L' }).
 *
 * Also handles the "Refresh Coordinates" button for families without coordinates.
 */
import L from "leaflet";
import { initRefreshCoordinatesBtn } from "./geo-refresh";

document.addEventListener("DOMContentLoaded", function () {
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
});
