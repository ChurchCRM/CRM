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
import { fetchAPIJSON } from "../api-utils";

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
  const refreshBtn = document.getElementById("refresh-coordinates-btn");
  if (!refreshBtn) return;

  const familyId = parseInt(refreshBtn.dataset.familyId || "0");
  if (familyId <= 0) return;

  const t = window.i18next ? i18next.t.bind(i18next) : (s) => s;

  refreshBtn.addEventListener("click", async function () {
    const btn = this;
    const originalText = btn.innerHTML;

    try {
      btn.disabled = true;
      btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i>${t("Refreshing...")}`;

      const result = await fetchAPIJSON(`family/${familyId}/geocode`, {
        method: "POST",
      });

      if (result.success) {
        btn.classList.remove("btn-outline-success");
        btn.classList.add("btn-outline-primary");
        btn.innerHTML = `<i class="fa-solid fa-check mr-1"></i>${t("Coordinates Updated")}`;
        // Reload page to show the new map
        setTimeout(() => location.reload(), 1500);
      } else {
        btn.classList.remove("btn-outline-success");
        btn.classList.add("btn-outline-danger");
        btn.innerHTML = `<i class="fa-solid fa-exclamation-triangle mr-1"></i>${t("Failed to geocode")}`;
        btn.disabled = false;
        setTimeout(() => {
          btn.classList.remove("btn-outline-danger");
          btn.classList.add("btn-outline-success");
          btn.innerHTML = originalText;
        }, 3000);
      }
    } catch (error) {
      btn.classList.remove("btn-outline-success");
      btn.classList.add("btn-outline-danger");
      btn.innerHTML = `<i class="fa-solid fa-network-wired"></i> ${t("Error")}`;
      btn.disabled = false;
      console.error("Geocoding error:", error);
      setTimeout(() => {
        btn.classList.remove("btn-outline-danger");
        btn.classList.add("btn-outline-success");
        btn.innerHTML = originalText;
      }, 3000);
    }
  });
});
