/**
 * Leaflet map initialisation for the person detail view (PersonView.php).
 *
 * PHP injects window.CRM.personMapConfig with one of two shapes:
 *   { lat, lng }   — family has stored geocoded coordinates (used directly)
 *   { address }    — unaffiliated person with own address (Nominatim geocodes client-side)
 *
 * Leaflet is loaded as a global from skin/external/leaflet/leaflet.js
 * (see webpack externals: { leaflet: 'L' }).
 */
import L from "leaflet";

document.addEventListener("DOMContentLoaded", function () {
  const config = window.CRM && window.CRM.personMapConfig;
  if (!config) {
    return;
  }

  function initMap(lat, lng) {
    const map = L.map("person-map", {
      scrollWheelZoom: false,
      dragging: false,
      zoomControl: false,
    }).setView([lat, lng], 14);

    L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution:
        '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors',
    }).addTo(map);

    L.marker([lat, lng]).addTo(map);
  }

  if (config.lat !== undefined && config.lng !== undefined) {
    // Stored coordinates from family — render immediately, no API call needed
    initMap(config.lat, config.lng);
  } else if (config.address) {
    // Unaffiliated person with own address — geocode client-side via Nominatim (free, no key)
    fetch(
      "https://nominatim.openstreetmap.org/search?q=" + encodeURIComponent(config.address) + "&format=json&limit=1",
      { headers: { "Accept-Language": "en", Accept: "application/json" } },
    )
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data.length) {
          return;
        }
        initMap(parseFloat(data[0].lat), parseFloat(data[0].lon));
      });
  }
});
