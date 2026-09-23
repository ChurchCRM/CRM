/**
 * map-neighbors.js — Find nearest-neighbor families to a selected family.
 *
 * Reads window.CRM.mapNeighborsConfig (set by people/views/map-neighbors-view.php)
 * and fetches results from GET /api/map/neighbors/{familyId}.
 *
 * Layout: full-width Leaflet map with a collapsible search panel on the right;
 * results render in a full-width table below the map. Markers:
 *   - church   → church icon pin
 *   - origin    → green circle marker (the selected family), drawn on top
 *   - neighbor  → circle markers coloured by distance band (see the map
 *                 legend); families sharing exact coordinates are fanned
 *                 into a small ring so each stays clickable
 *
 * Leaflet is loaded as a global from skin/external/leaflet/leaflet.js
 * (see webpack externals: { leaflet: 'L' }).
 */
import L from "leaflet";

$(document).ready(() => {
  window.CRM.onLocalesReady(() => {
    const cfg = window.CRM.mapNeighborsConfig;
    let listPeople = [];
    let map = null;
    let neighborLayer = null;
    let originMarker = null;
    let rowMarkers = [];
    let legendControl = null;

    // Distance bands (nearest → farthest), relative to the farthest result.
    const ORIGIN_COLOR = "#2fb344";
    const DISTANCE_BANDS = [
      { max: 0.25, color: "#206bc4", label: i18next.t("Closest") },
      { max: 0.5, color: "#f59f00", label: i18next.t("Near") },
      { max: 0.75, color: "#f76707", label: i18next.t("Farther") },
      { max: Number.POSITIVE_INFINITY, color: "#d63939", label: i18next.t("Farthest") },
    ];
    const bandFor = (fraction) => DISTANCE_BANDS.find((b) => fraction <= b.max) || DISTANCE_BANDS[0];

    // -- TomSelect on the family picker --------------------------------------
    $(".choiceSelectBox").each(function () {
      if (!this.tomselect) new TomSelect(this);
    });

    // -- Map init ----------------------------------------------------------------
    const startLat = cfg.churchLat || 0;
    const startLng = cfg.churchLng || 0;
    map = L.map("neighborsMap").setView([startLat, startLng], cfg.zoom || 10);
    L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution:
        '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors',
    }).addTo(map);
    neighborLayer = L.layerGroup().addTo(map);

    // Church marker (only when the church address is geocoded)
    if (cfg.hasLocation) {
      const churchIcon = L.icon({
        iconUrl: `${window.CRM.root}/skin/icons/church.png`,
        iconSize: [32, 32],
        iconAnchor: [16, 32],
        popupAnchor: [0, -34],
      });
      const churchPopup = document.createElement("strong");
      churchPopup.textContent = cfg.churchName || i18next.t("Church");
      L.marker([cfg.churchLat, cfg.churchLng], { icon: churchIcon }).bindPopup(churchPopup).addTo(map);
    }

    buildLegend(0);

    // -- Collapsible search panel ----------------------------------------------
    const $panel = $("#searchPanel");
    const $toggle = $("#toggleSearchPanel");

    function openPanel() {
      $panel.removeClass("collapsed");
      $toggle.addClass("d-none");
      window.setTimeout(() => map.invalidateSize(), 260);
    }
    function closePanel() {
      $panel.addClass("collapsed");
      $toggle.removeClass("d-none");
      window.setTimeout(() => map.invalidateSize(), 260);
    }
    $toggle.on("click", openPanel);
    $("#closeSearchPanel").on("click", closePanel);

    // -- Results --------------------------------------------------------------
    function setResultsVisible(hasResults) {
      $("#neighborsEmpty").toggleClass("d-none", hasResults);
      $("#neighborsTable").toggleClass("d-none", !hasResults);
      $("#addAllToCart, #removeAllFromCart").prop("disabled", !hasResults);
    }

    function clearMarkers() {
      neighborLayer.clearLayers();
      rowMarkers = [];
      if (originMarker) {
        map.removeLayer(originMarker);
        originMarker = null;
      }
    }

    // Build / refresh the map legend. Pass maxDistance 0 to show only the
    // fixed markers (before any search has run).
    function buildLegend(maxDistance) {
      const unit = cfg.distanceUnit || "";
      const el = L.DomUtil.create("div", "neighbors-legend");
      const row = (color, text, isIcon) => {
        const swatch = isIcon
          ? `<img src="${window.CRM.root}/skin/icons/church.png" width="14" height="14" alt="">`
          : `<span class="neighbors-legend-dot" style="background:${color}"></span>`;
        return `<div class="neighbors-legend-row">${swatch}<span>${text}</span></div>`;
      };

      let html = `<div class="neighbors-legend-title">${i18next.t("Legend")}</div>`;
      html += row(ORIGIN_COLOR, i18next.t("Selected family"));
      if (cfg.hasLocation) {
        html += row(null, cfg.churchName || i18next.t("Church"), true);
      }
      if (maxDistance > 0) {
        let lower = 0;
        DISTANCE_BANDS.forEach((band, i) => {
          const upper = i === DISTANCE_BANDS.length - 1 ? maxDistance : band.max * maxDistance;
          const range =
            i === DISTANCE_BANDS.length - 1
              ? `${lower.toFixed(1)}–${maxDistance.toFixed(1)} ${unit}`
              : `${lower.toFixed(1)}–${upper.toFixed(1)} ${unit}`;
          html += row(band.color, `${band.label} (${range})`);
          lower = upper;
        });
      }
      el.innerHTML = html;

      if (legendControl) {
        map.removeControl(legendControl);
      }
      legendControl = L.control({ position: "bottomright" });
      legendControl.onAdd = () => el;
      legendControl.addTo(map);
    }

    function renderResults(data) {
      const origin = data.origin || null;
      const families = data.neighbors || [];
      const $tbody = $("#neighborsTableBody").empty();
      listPeople = [];
      clearMarkers();

      const bounds = [];

      if (origin?.latitude) {
        bounds.push([origin.latitude, origin.longitude]);
      }

      // Families that share the exact same stored coordinates (common in
      // imported/demo data) would stack into a single dot — fan each group
      // out into a small ring so every family is individually visible.
      const coincidenceGroups = {};
      for (const f of families) {
        const key = `${f.latitude.toFixed(5)},${f.longitude.toFixed(5)}`;
        if (!coincidenceGroups[key]) {
          coincidenceGroups[key] = [];
        }
        coincidenceGroups[key].push(f);
      }
      // Farthest result drives the distance-band scale.
      const maxDistance = Math.max(...families.map((f) => Number.parseFloat(f.distance) || 0), 0.0001);

      const coincidenceSeen = {};
      const spreadLatLng = (family) => {
        const key = `${family.latitude.toFixed(5)},${family.longitude.toFixed(5)}`;
        const group = coincidenceGroups[key];
        if (!group || group.length < 2) {
          return [family.latitude, family.longitude];
        }
        const i = coincidenceSeen[key] || 0;
        coincidenceSeen[key] = i + 1;
        const ringRadius = 0.0003; // ~33 m
        const angle = (2 * Math.PI * i) / group.length;
        return [family.latitude + ringRadius * Math.cos(angle), family.longitude + ringRadius * Math.sin(angle)];
      };

      families.forEach((family, idx) => {
        for (const person of family.people) {
          listPeople.push(person.id);
        }

        const latlng = spreadLatLng(family);
        const bandColor = bandFor((Number.parseFloat(family.distance) || 0) / maxDistance).color;
        const marker = L.circleMarker(latlng, {
          radius: 8,
          color: "#ffffff",
          fillColor: bandColor,
          fillOpacity: 0.9,
          weight: 1.5,
        });
        marker.bindPopup(
          '<strong><a href="' +
            window.CRM.escapeHtml(family.profileUrl) +
            '">' +
            window.CRM.escapeHtml(family.name) +
            "</a></strong><br>" +
            window.CRM.escapeHtml(family.address) +
            "<br>" +
            window.CRM.escapeHtml(family.distanceText) +
            " " +
            window.CRM.escapeHtml(cfg.distanceUnit),
        );
        neighborLayer.addLayer(marker);
        rowMarkers[idx] = marker;
        bounds.push(latlng);

        const peopleHtml = family.people
          .map(
            (person) =>
              '<span class="badge bg-secondary-lt text-secondary me-1 mb-1">' +
              window.CRM.escapeHtml(person.name) +
              "</span>",
          )
          .join("");

        const $row = $("<tr>").attr("data-idx", idx);
        const sameSpot = Number.parseFloat(family.distance) === 0;
        const distanceLabel = sameSpot
          ? i18next.t("Same location")
          : `${window.CRM.escapeHtml(family.distanceText)} ${window.CRM.escapeHtml(cfg.distanceUnit || "")}`;
        $row.append(
          $("<td>").html(
            `<span class="neighbors-legend-dot me-2" style="background:${bandColor}"></span>${distanceLabel}`,
          ),
        );
        $row.append(
          $("<td>").html(
            (sameSpot ? "" : `${window.CRM.escapeHtml(family.bearing)} `) +
              '<a target="_blank" rel="noopener noreferrer" href="' +
              window.CRM.escapeHtml(family.directionsUrl) +
              '">' +
              i18next.t("Directions") +
              "</a>",
          ),
        );
        $row.append(
          $("<td>").html(
            '<a href="' +
              window.CRM.escapeHtml(family.profileUrl) +
              '"><strong>' +
              window.CRM.escapeHtml(family.name) +
              '</strong></a><br><span class="text-secondary">' +
              window.CRM.escapeHtml(family.address) +
              "</span>",
          ),
        );
        $row.append($("<td>").html(peopleHtml));
        $tbody.append($row);
      });

      // Origin family — distinct green marker, drawn last so it sits on top.
      if (origin?.latitude) {
        originMarker = L.circleMarker([origin.latitude, origin.longitude], {
          radius: 11,
          color: "#ffffff",
          fillColor: ORIGIN_COLOR,
          fillOpacity: 1,
          weight: 3,
        })
          .addTo(map)
          .bindPopup(
            `<strong>${window.CRM.escapeHtml(origin.name)}</strong><br>${window.CRM.escapeHtml(origin.address || "")}`,
          )
          .bringToFront();
      }

      buildLegend(families.length > 0 ? maxDistance : 0);

      $("#neighborsCount")
        .text(families.length)
        .toggleClass("d-none", families.length === 0);

      if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [48, 48], maxZoom: 16 });
      }

      setResultsVisible(families.length > 0);
    }

    // Clicking a result row focuses its marker
    $("#neighborsTableBody").on("click", "tr", function (e) {
      if (e.target.closest("a")) return;
      const idx = Number($(this).attr("data-idx"));
      const marker = rowMarkers[idx];
      if (!marker) return;
      map.setView(marker.getLatLng(), Math.max(map.getZoom(), 14));
      marker.openPopup();
      $("#neighborsTableBody tr").removeClass("table-active");
      $(this).addClass("table-active");
    });

    // -- Search --------------------------------------------------------------
    $("#neighborsForm").on("submit", (e) => {
      e.preventDefault();

      const familyId = $("#familySelect").val();
      if (!familyId) {
        window.CRM.notify(i18next.t("Please select a family."), { type: "warning" });
        return;
      }

      const classificationIds = $('input[name="classificationId"]:checked')
        .map(function () {
          return $(this).val();
        })
        .get()
        .join(",");

      const params = new URLSearchParams({
        maxNeighbors: $("#maxNeighbors").val() || 15,
        maxDistance: $("#maxDistance").val() || 10,
        classificationIds: classificationIds,
      });

      const $btn = $("#findNeighborsBtn").prop("disabled", true);

      fetch(`${cfg.apiUrl}/${encodeURIComponent(familyId)}?${params.toString()}`, {
        credentials: "same-origin",
      })
        .then((res) => {
          if (!res.ok) {
            throw new Error(`API error ${res.status}`);
          }
          return res.json();
        })
        .then((data) => {
          renderResults(data);
          closePanel();
        })
        .catch((err) => {
          console.error("Find Neighbors: failed to load results", err);
          window.CRM.notify(i18next.t("Failed to load neighbors."), { type: "danger" });
          listPeople = [];
          clearMarkers();
          setResultsVisible(false);
        })
        .finally(() => {
          $btn.prop("disabled", false);
        });
    });

    $("#addAllToCart").on("click", () => {
      window.CRM.cartManager.addPerson(listPeople, { showNotification: true });
    });

    $("#removeAllFromCart").on("click", () => {
      window.CRM.cartManager.removePerson(listPeople, { confirm: true, showNotification: true });
    });

    // Auto-run when the page is opened with ?familyId=N (deep link from a profile)
    if ($("#familySelect").val()) {
      $("#neighborsForm").trigger("submit");
    } else {
      openPanel();
    }
  });
});
