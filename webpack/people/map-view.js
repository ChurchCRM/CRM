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

// Resolve i18next lazily on every call — this module can load before the
// global i18next is ready, so capturing t at module load would freeze it to a
// no-op that also drops the interpolation values.
const t = (key, opts) => (window.i18next ? window.i18next.t(key, opts) : key);

// Fired after a bulk geocode adds coordinates to families. The map block (when
// present) listens for this and repaints its markers in place — no page reload.
const FAMILY_DATA_CHANGED = "crm:familydata-changed";

// ---------------------------------------------------------------------------
// "Update All Coordinates" handler
// Registered at the top level (outside the cfg/#map guard) so the button
// in the page header always works, even when the church has no location set.
// ---------------------------------------------------------------------------
const geocodeAllBtn = document.getElementById("geocodeAllBtn");
if (geocodeAllBtn) {
  const resultsContainer = document.getElementById("geocodeAllResults");

  const reasonLabel = (reason) => {
    if (reason === "incomplete_address") {
      return t("Address is incomplete (missing city, state, and ZIP)");
    }
    if (reason === "no_result") {
      return t("No match found — check the street address, city, and ZIP");
    }
    return t("A geocoding error occurred — try again later");
  };

  // Persistent "working" panel shown near the results area for the whole run,
  // so the user has a visible signal beyond the small header-button spinner.
  const showWorkingPanel = () => {
    if (!resultsContainer) return;
    resultsContainer.innerHTML = "";

    const panel = document.createElement("div");
    panel.className = "alert alert-info d-flex align-items-center mb-0";
    panel.setAttribute("role", "status");

    const spinner = document.createElement("span");
    spinner.className = "spinner-border spinner-border-sm me-2";
    spinner.setAttribute("aria-hidden", "true");
    panel.appendChild(spinner);

    const text = document.createElement("span");
    text.textContent = t("Geocoding families… this can take up to a minute. You can keep this page open.");
    panel.appendChild(text);

    resultsContainer.appendChild(panel);
  };

  const clearResultsPanel = () => {
    if (resultsContainer) {
      resultsContainer.innerHTML = "";
    }
  };

  // Build one failure row: family name (links to the editor) with the address
  // on a muted second line and an edit affordance on the right. Kept compact so
  // long lists stay scannable.
  const buildFailureRow = (f) => {
    const row = document.createElement("a");
    // editUrl is fully server-constructed (/FamilyEditor.php?FamilyID=<int>)
    // with no user-injectable components — assign directly as a URL property.
    row.href = f.editUrl;
    // Open in a new tab so the admin keeps the failure list while fixing addresses.
    row.target = "_blank";
    row.rel = "noopener";
    row.className =
      "list-group-item list-group-item-action bg-transparent border-0 border-top px-0 py-2 " +
      "d-flex align-items-center gap-2";

    const textCol = document.createElement("div");
    textCol.className = "flex-fill min-w-0";

    const name = document.createElement("div");
    name.className = "fw-medium";
    name.textContent = f.name || t("Unknown family");
    textCol.appendChild(name);

    const addr = document.createElement("div");
    addr.className = "text-secondary small text-truncate";
    addr.textContent = f.address || t("No address on file");
    textCol.appendChild(addr);

    row.appendChild(textCol);

    const editIcon = document.createElement("i");
    editIcon.className = "fa-solid fa-pen-to-square text-secondary flex-shrink-0";
    editIcon.setAttribute("aria-hidden", "true");
    row.appendChild(editIcon);

    return row;
  };

  const renderFailurePanel = (data) => {
    if (!resultsContainer) return;
    resultsContainer.innerHTML = "";

    if (!(data.failed > 0 && Array.isArray(data.failures) && data.failures.length > 0)) {
      return;
    }

    // Tabler styles .alert as display:flex, so all rich content must live in a
    // single flex child (the ".d-flex > icon + body" pattern used across the app).
    const alertEl = document.createElement("div");
    alertEl.className = "alert alert-warning alert-dismissible mb-0";
    alertEl.setAttribute("role", "alert");

    const flex = document.createElement("div");
    flex.className = "d-flex";
    alertEl.appendChild(flex);

    const iconWrap = document.createElement("div");
    const icon = document.createElement("i");
    icon.className = "fa-solid fa-triangle-exclamation fs-3 me-2";
    iconWrap.appendChild(icon);
    flex.appendChild(iconWrap);

    const body = document.createElement("div");
    body.className = "flex-fill";
    flex.appendChild(body);

    const title = document.createElement("h4");
    title.className = "alert-title mb-1";
    title.textContent = t(`{{count}} families could not be geocoded`, { count: data.failed });
    body.appendChild(title);

    const hint = document.createElement("div");
    hint.className = "text-secondary small mb-2";
    hint.textContent = t("Open a family to fix its address, then run this again.");
    body.appendChild(hint);

    // Group failures by reason so the explanation appears once per reason
    // instead of being repeated on every row.
    const byReason = new Map();
    for (const f of data.failures) {
      if (!byReason.has(f.reason)) {
        byReason.set(f.reason, []);
      }
      byReason.get(f.reason).push(f);
    }

    // Scroll the whole failure list once it gets long, so the panel never
    // pushes the map far down the page regardless of how many families failed.
    // The border + rounding make the scroll region obvious.
    const scroller = document.createElement("div");
    const isScrolling = data.failures.length > 6;
    if (isScrolling) {
      scroller.className = "border rounded px-2 py-1 mt-1";
      scroller.style.maxHeight = "260px";
      scroller.style.overflowY = "auto";
    }
    body.appendChild(scroller);

    const showReasonHeadings = byReason.size > 1;
    for (const [reason, families] of byReason) {
      if (showReasonHeadings) {
        const reasonEl = document.createElement("div");
        reasonEl.className = "text-secondary text-uppercase fw-bold small mt-2 mb-1";
        reasonEl.textContent = reasonLabel(reason);
        scroller.appendChild(reasonEl);
      } else {
        // Single reason — state it once, plainly, above the list.
        const reasonEl = document.createElement("div");
        reasonEl.className = "fw-medium mb-1";
        reasonEl.textContent = reasonLabel(reason);
        body.insertBefore(reasonEl, scroller);
      }

      const list = document.createElement("div");
      list.className = "list-group list-group-flush";
      for (const f of families) {
        list.appendChild(buildFailureRow(f));
      }
      scroller.appendChild(list);
    }

    if (data.failuresTruncated) {
      const more = document.createElement("div");
      more.className = "text-secondary small mt-2";
      more.textContent = t(`…and {{count}} more. Run again to see the rest.`, {
        count: data.failed - data.failures.length,
      });
      body.appendChild(more);
    }

    // CSP-safe dismiss button (Bootstrap data-bs-dismiss, no onclick)
    const closeBtn = document.createElement("button");
    closeBtn.type = "button";
    closeBtn.className = "btn-close";
    closeBtn.setAttribute("data-bs-dismiss", "alert");
    closeBtn.setAttribute("aria-label", t("Close"));
    alertEl.appendChild(closeBtn);

    resultsContainer.appendChild(alertEl);
    resultsContainer.scrollIntoView({ behavior: "smooth", block: "nearest" });
  };

  geocodeAllBtn.addEventListener("click", () => {
    window.bootbox.confirm({
      title: t("Update All Family Coordinates"),
      message: t(
        "This finds map coordinates for every family that is missing them, using OpenStreetMap. " +
          "It processes up to 50 families per run; a full batch takes about a minute. " +
          "You can keep this page open while it runs. Continue?",
      ),
      buttons: {
        confirm: { label: t("Update Coordinates"), className: "btn-primary" },
        cancel: { label: t("Cancel"), className: "btn-secondary" },
      },
      callback: (result) => {
        if (!result) return;

        const originalHtml = geocodeAllBtn.innerHTML;
        geocodeAllBtn.disabled = true;
        geocodeAllBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-1"></i>${t("Geocoding…")}`;
        showWorkingPanel();

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

            // Refresh the button's "(N)" count so it reflects how many families
            // are still missing coordinates. originalHtml is stale — captured
            // before the run. data.remaining is `total - geocoded`, so it
            // already includes families that failed this run. Patch the count
            // in place rather than rebuilding so the icon/markup stay intact.
            if (typeof data.remaining === "number") {
              const withoutCount = originalHtml.replace(/\s*\(\d+\)(\s*)$/, "$1");
              geocodeAllBtn.innerHTML = data.remaining > 0 ? `${withoutCount} (${data.remaining})` : withoutCount;
              geocodeAllBtn.dataset.missingCount = String(data.remaining);
            } else {
              geocodeAllBtn.innerHTML = originalHtml;
            }

            // --- Summary toast --------------------------------------------
            // `remaining` covers batch overflow (families not reached because
            // of the 50-per-run cap) PLUS families that failed. Only prompt
            // "run again" when there is genuine overflow — re-running does
            // nothing for addresses that simply will not resolve.
            const overflow = data.remaining - data.failed;
            let msg;
            let toastType;
            if (data.total === 0) {
              msg = t("All families already have coordinates.");
              toastType = "info";
            } else if (overflow > 0) {
              msg = t(
                `Geocoded {{geocoded}} of {{total}} families. {{overflow}} not yet processed — run again to continue.`,
                { geocoded: data.geocoded, total: data.total, overflow },
              );
              toastType = "warning";
            } else if (data.failed > 0) {
              msg = t(`Geocoded {{geocoded}} families. {{failed}} could not be resolved — see details below.`, {
                geocoded: data.geocoded,
                failed: data.failed,
              });
              toastType = data.geocoded > 0 ? "success" : "warning";
            } else {
              msg = t(`Geocoded all {{geocoded}} families. The map is now up to date.`, {
                geocoded: data.geocoded,
              });
              toastType = "success";
            }

            window.CRM.notify(msg, { type: toastType, delay: 8000 });

            // --- Failure detail panel ------------------------------------
            renderFailurePanel(data);

            // --- Repaint the map in place (no reload) --------------------
            if (data.geocoded > 0) {
              document.dispatchEvent(new CustomEvent(FAMILY_DATA_CHANGED));
            }
          })
          .catch((err) => {
            geocodeAllBtn.disabled = false;
            geocodeAllBtn.innerHTML = originalHtml;
            clearResultsPanel();
            window.CRM.notify(t("Failed to update coordinates") + (err.message ? `: ${err.message}` : ""), {
              type: "danger",
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

  // Legend IDs the user has toggled off — reapplied after a marker reload so a
  // bulk geocode doesn't silently turn every hidden classification back on.
  const inactiveLegendIds = () => {
    const ids = new Set();
    for (const el of document.querySelectorAll(".legend-item.inactive")) {
      ids.add(Number.parseInt(el.dataset.legendId, 10));
    }
    return ids;
  };

  const loadFamilyMarkers = () => {
    // Drop existing family markers (the church marker is not tracked here).
    for (const cid of Object.keys(classMarkers)) {
      for (const m of classMarkers[cid]) {
        map.removeLayer(m);
      }
      delete classMarkers[cid];
    }

    return fetch(apiUrl, { credentials: "same-origin" })
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
        // Re-hide markers for classifications that were toggled off.
        const hidden = inactiveLegendIds();
        for (const cid of Object.keys(classMarkers)) {
          if (hidden.has(Number.parseInt(cid, 10))) {
            for (const m of classMarkers[cid]) {
              map.removeLayer(m);
            }
          }
        }
      })
      .catch((err) => {
        console.error("Map: failed to load family data", err);
      });
  };

  loadFamilyMarkers();

  // Repaint markers in place when a bulk geocode reports new coordinates.
  document.addEventListener(FAMILY_DATA_CHANGED, () => {
    loadFamilyMarkers();
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
