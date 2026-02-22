/**
 * map-view.js — Congregation map powered by Leaflet + OpenStreetMap
 *
 * Reads window.CRM.mapConfig (set by v2/templates/map/map-view.php) and
 * fetches family/person data from GET /api/map/families[?groupId=N].
 *
 * No Google Maps API key required.
 */
(function () {
  "use strict";

  var cfg = window.CRM.mapConfig;

  // -- Map init ---------------------------------------------------------------
  var map = L.map("map").setView([cfg.churchLat, cfg.churchLng], cfg.zoom);

  L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors',
  }).addTo(map);

  // Church marker using the existing church icon
  var churchIcon = L.icon({
    iconUrl: window.CRM.root + "/skin/icons/church.png",
    iconSize: [32, 32],
    iconAnchor: [16, 32],
    popupAnchor: [0, -34],
  });
  L.marker([cfg.churchLat, cfg.churchLng], { icon: churchIcon })
    .bindPopup("<strong>" + cfg.churchName + "</strong>")
    .addTo(map);

  // -- Legend control (desktop, bottom-right) ---------------------------------
  var legendControl = L.control({ position: "bottomright" });
  legendControl.onAdd = function () {
    return document.getElementById("map-legend");
  };
  legendControl.addTo(map);

  // -- Colour helper ----------------------------------------------------------
  function colorFor(clsId) {
    return cfg.markerColors[clsId % cfg.markerColors.length] || "#6c757d";
  }

  // -- Marker tracking (keyed by classificationId) ----------------------------
  var classMarkers = {};

  function addMarker(item) {
    var color = colorFor(item.classificationId);
    var marker = L.circleMarker([item.latitude, item.longitude], {
      radius: 8,
      color: color,
      fillColor: color,
      fillOpacity: 0.85,
      weight: 2,
    });

    marker.bindPopup(
      '<strong><a href="' + item.profileUrl + '">' + item.salutation + "</a></strong>" + "<br>" + item.address,
    );
    marker.addTo(map);

    var cid = item.classificationId;
    if (!classMarkers[cid]) {
      classMarkers[cid] = [];
    }
    classMarkers[cid].push(marker);
  }

  // -- Fetch family/person data from the REST API -----------------------------
  var apiUrl = cfg.apiUrl;
  if (cfg.groupId !== null && cfg.groupId !== undefined) {
    apiUrl += "?groupId=" + cfg.groupId;
  }

  fetch(apiUrl, { credentials: "same-origin" })
    .then(function (res) {
      if (!res.ok) {
        throw new Error("API error " + res.status);
      }
      return res.json();
    })
    .then(function (items) {
      items.forEach(addMarker);
    })
    .catch(function (err) {
      console.error("Map: failed to load family data", err);
    });

  // -- Legend checkbox interaction ---------------------------------------------
  document.querySelectorAll(".legend-cb").forEach(function (cb) {
    cb.addEventListener("change", function () {
      var row = cb.closest(".legend-row");
      var clsId = parseInt(row.dataset.classification, 10);
      var show = cb.checked;

      // Sync the paired checkbox (desktop ↔ mobile legends share same clsId)
      document
        .querySelectorAll('.legend-row[data-classification="' + clsId + '"] .legend-cb')
        .forEach(function (sibling) {
          sibling.checked = show;
        });

      (classMarkers[clsId] || []).forEach(function (m) {
        if (show) {
          m.addTo(map);
        } else {
          map.removeLayer(m);
        }
      });
    });
  });

  // Toggle on legend row click (not just the checkbox)
  document.querySelectorAll(".legend-row").forEach(function (row) {
    row.addEventListener("click", function (e) {
      if (e.target.tagName !== "INPUT") {
        var cb = row.querySelector(".legend-cb");
        cb.checked = !cb.checked;
        cb.dispatchEvent(new Event("change"));
      }
    });
  });
})();
