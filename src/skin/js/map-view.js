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

  // -- Colour lookup (keyed by legend item id) --------------------------------
  // Build from cfg.legendItems so marker colors always match the legend exactly,
  // regardless of whether ids are sequential or sparse database OptionIds.
  var legendColorMap = {};
  (cfg.legendItems || []).forEach(function (item) {
    legendColorMap[item.id] = item.color;
  });

  function colorFor(id) {
    return legendColorMap[id] || cfg.markerColors[id % cfg.markerColors.length] || "#6c757d";
  }

  // -- Legend key: roleId in group mode, classificationId otherwise -----------
  function legendIdFor(item) {
    return cfg.legendType === "roles" ? item.roleId || 0 : item.classificationId || 0;
  }

  // -- Marker tracking (keyed by legend ID) -----------------------------------
  var classMarkers = {};

  function addMarker(item) {
    var color = colorFor(legendIdFor(item));
    var marker = L.circleMarker([item.latitude, item.longitude], {
      radius: 8,
      color: color,
      fillColor: color,
      fillOpacity: 0.85,
      weight: 2,
    });

    marker.bindPopup(function () {
      var html =
        '<strong><a href="' + item.profileUrl + '">' + item.salutation + "</a></strong>" + "<br>" + item.address;
      if (item.phone) {
        html +=
          '<br><a href="tel:' + encodeURIComponent(item.phone) + '">' + window.CRM.escapeHtml(item.phone) + "</a>";
      }
      if (item.directionsUrl) {
        html +=
          '<br><a href="' +
          window.CRM.escapeHtml(item.directionsUrl) +
          '" target="_blank" rel="noopener noreferrer" ' +
          'class="btn btn-sm btn-outline-primary mt-1">' +
          '<i class="fa-solid fa-diamond-turn-right mr-1"></i>Get Directions</a>';
      }
      return html;
    });
    marker.addTo(map);

    var cid = legendIdFor(item);
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

  // -- Legend item click interaction ------------------------------------------
  // .legend-item elements replace raw checkboxes; toggle .inactive class.
  // Desktop and mobile share the same legendId so both stay in sync.
  document.querySelectorAll(".legend-item").forEach(function (item) {
    item.addEventListener("click", function () {
      var legendId = parseInt(item.dataset.legendId, 10);
      var isActive = !item.classList.contains("inactive");

      // Toggle all items with the same legendId (desktop + mobile)
      document.querySelectorAll('.legend-item[data-legend-id="' + legendId + '"]').forEach(function (sibling) {
        sibling.classList.toggle("inactive", isActive);
      });

      // Show / hide matching map markers
      (classMarkers[legendId] || []).forEach(function (m) {
        if (isActive) {
          map.removeLayer(m);
        } else {
          m.addTo(map);
        }
      });
    });
  });
})();
