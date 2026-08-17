/**
 * Church Information Page - Country/State dropdown, map, preview, and copy-defaults handler.
 *
 * Follows the same API-driven pattern as DropdownManager.js:
 * - Countries: GET /api/public/data/countries
 * - States:    GET /api/public/data/countries/{code}/states
 */

// Holds the active Leaflet map instance so it can be torn down and recreated
// when coordinates are regenerated (Leaflet does not support re-centering a
// destroyed/re-initialized container without a fresh L.map() call).
let currentChurchMap = null;

function initChurchMap() {
  const mapContainer = document.getElementById("church-location-map");
  if (!mapContainer || !window.L || !window.CRM?.churchMapConfig) {
    return;
  }

  if (mapContainer._leaflet_id !== undefined) {
    return;
  }

  const cfg = window.CRM.churchMapConfig;

  try {
    currentChurchMap = window.L.map("church-location-map", {
      scrollWheelZoom: false,
      zoomControl: true,
    }).setView([cfg.lat, cfg.lng], 15);

    window.L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution:
        '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors',
    }).addTo(currentChurchMap);

    window.L.marker([cfg.lat, cfg.lng])
      .bindPopup(`<strong>${window.CRM.escapeHtml(cfg.name)}</strong>`)
      .addTo(currentChurchMap);

    setTimeout(() => {
      currentChurchMap.invalidateSize();
    }, 100);
  } catch (e) {
    console.error("Error initializing map:", e);
  }
}

// Destroys any existing map instance and re-initializes it, used after a
// successful "Generate Coordinates" so the marker actually moves instead of
// initChurchMap() silently no-op'ing on its already-initialized guard.
function reinitChurchMap() {
  const mapContainer = document.getElementById("church-location-map");
  if (currentChurchMap) {
    currentChurchMap.remove();
    currentChurchMap = null;
  }
  if (mapContainer) {
    delete mapContainer._leaflet_id;
  }
  initChurchMap();
}

document.addEventListener("DOMContentLoaded", () => {
  const countrySelect = document.getElementById("sChurchCountry");
  const stateContainer = document.getElementById("sChurchStateContainer");
  const defaultCountrySelect = document.getElementById("sDefaultCountry");
  const defaultStateContainer = document.getElementById("sDefaultStateContainer");

  if (!countrySelect || !stateContainer || !window.TomSelect) {
    return;
  }

  const $countrySelect = $(countrySelect);
  const userSelectedCountry = $countrySelect.data("user-selected") || "";
  const userSelectedState = stateContainer.dataset.userSelectedState || "";

  const userSelectedDefaultState = defaultStateContainer ? defaultStateContainer.dataset.userSelectedState || "" : "";

  // ── Helpers ──────────────────────────────────────────────────────────────

  function initTomSelect(el) {
    if (el.tomselect) {
      el.tomselect.destroy();
    }
    new TomSelect(el, {
      allowEmptyOption: true,
      placeholder: window.i18next ? i18next.t("Search or select...") : "Search or select...",
    });
  }

  // ── Generic state field builder ─────────────────────────────────────────
  // Reused for both church state and default state containers.

  function buildStateSelect(fieldId, fieldName, states, selectedValue) {
    const $select = $(`<select id="${fieldId}" name="${fieldName}" class="form-control" style="width:100%"></select>`);
    const blankLabel = window.i18next ? `— ${i18next.t("Select State")} —` : "— Select State —";
    $select.append(new Option(blankLabel, ""));

    $.each(states, (code, name) => {
      const selected = selectedValue === code || selectedValue === name;
      $select.append(new Option(name, code, selected, selected));
    });

    return $select;
  }

  function buildStateInput(fieldId, fieldName, currentValue) {
    const $input = $(
      `<input type="text" id="${fieldId}" name="${fieldName}" class="form-control" style="width:100%" maxlength="100">`,
    );
    $input.val(currentValue);
    return $input;
  }

  function updateStateField(container, fieldId, fieldName, countryCode, selectedValue) {
    if (!countryCode) {
      container.innerHTML = "";
      container.appendChild(buildStateInput(fieldId, fieldName, selectedValue)[0]);
      // Return a resolved deferred so callers can uniformly chain .always().
      return $.Deferred().resolve().promise();
    }

    return $.ajax({
      type: "GET",
      url: `${window.CRM.root}/api/public/data/countries/${countryCode.toLowerCase()}/states`,
    })
      .done((data) => {
        container.innerHTML = "";
        if (data && Object.keys(data).length > 0) {
          const $select = buildStateSelect(fieldId, fieldName, data, selectedValue);
          container.appendChild($select[0]);
          initTomSelect($select[0]);
        } else {
          container.appendChild(buildStateInput(fieldId, fieldName, selectedValue)[0]);
        }
      })
      .fail(() => {
        container.innerHTML = "";
        container.appendChild(buildStateInput(fieldId, fieldName, selectedValue)[0]);
      });
  }

  // ── Country dropdown ──────────────────────────────────────────────────────

  // Cache the countries API response so both church + default dropdowns share one request.
  let countriesPromise = null;

  function fetchCountries() {
    if (!countriesPromise) {
      countriesPromise = $.ajax({
        type: "GET",
        url: `${window.CRM.root}/api/public/data/countries`,
      });
      countriesPromise.fail(() => {
        countriesPromise = null;
      });
    }
    return countriesPromise;
  }

  function populateCountrySelect($selectEl, selectedValue, callback) {
    fetchCountries().done((data) => {
      $selectEl.empty();
      const blankLabel = window.i18next ? `— ${i18next.t("Select Country")} —` : "— Select Country —";
      $selectEl.append(new Option(blankLabel, ""));

      $.each(data, (_idx, country) => {
        const selected = selectedValue === country.code || selectedValue === country.name;
        $selectEl.append(new Option(country.name, country.code, selected, selected));
      });

      initTomSelect($selectEl[0]);

      if (callback) {
        callback($selectEl.val());
      }
    });
  }

  // ── Church country → church state ───────────────────────────────────────

  populateCountrySelect($countrySelect, userSelectedCountry, (preselected) => {
    let stateReady;
    if (preselected) {
      stateReady = updateStateField(stateContainer, "sChurchState", "sChurchState", preselected, userSelectedState);
    } else {
      stateContainer.innerHTML = "";
      stateContainer.appendChild(buildStateInput("sChurchState", "sChurchState", userSelectedState)[0]);
      stateReady = $.Deferred().resolve().promise();
    }
    // Sync the snapshot after both country and state fields are populated (async).
    // sChurchCountry options are loaded async by populateCountrySelect(); without
    // re-syncing here, initialAddressSnapshot.sChurchCountry stays "" while the
    // field holds e.g. "US", causing a false-positive stale banner on every page load.
    stateReady.always(() => {
      initialAddressSnapshot.sChurchCountry = document.getElementById("sChurchCountry")?.value || "";
      initialAddressSnapshot.sChurchState = document.getElementById("sChurchState")?.value || "";
      updateStaleCoordinatesState();
    });
  });

  $countrySelect.on("change", function () {
    updateStateField(stateContainer, "sChurchState", "sChurchState", this.value, "").always(() => {
      // Do NOT mutate initialAddressSnapshot here. The country mismatch between
      // the snapshot (geocoded country) and the new value already keeps the
      // stale-coordinates banner alive for A→B. On A→B→A the snapshot's
      // original sChurchState (e.g. "IL") is preserved, so the state mismatch
      // keeps the banner alive even after country returns to A. The banner
      // only clears when the user re-geocodes (resetStaleCoordinatesSnapshot).
      updateStaleCoordinatesState();
    });
  });

  // ── Default country → default state ─────────────────────────────────────

  if (defaultCountrySelect && defaultStateContainer) {
    const $defaultCountrySelect = $(defaultCountrySelect);
    const userSelectedDefaultCountry = $defaultCountrySelect.data("user-selected") || "";

    populateCountrySelect($defaultCountrySelect, userSelectedDefaultCountry, (preselected) => {
      if (preselected) {
        updateStateField(
          defaultStateContainer,
          "sDefaultState",
          "sDefaultState",
          preselected,
          userSelectedDefaultState,
        );
      } else {
        defaultStateContainer.innerHTML = "";
        defaultStateContainer.appendChild(
          buildStateInput("sDefaultState", "sDefaultState", userSelectedDefaultState)[0],
        );
      }
    });

    $defaultCountrySelect.on("change", function () {
      updateStateField(defaultStateContainer, "sDefaultState", "sDefaultState", this.value, "");
    });
  }

  // ── Other TomSelect dropdowns (language, timezone) ──────────────────────────

  $(".auto-tomselect").each(function () {
    if (this.id !== "sChurchCountry" && this.id !== "sDefaultCountry") {
      if (!this.tomselect) {
        initTomSelect(this);
      }
    }
  });

  // ── Copy from church address ────────────────────────────────────────────────

  const copyBtn = document.getElementById("copy-church-address");
  if (copyBtn) {
    copyBtn.addEventListener("click", () => {
      // Copy city
      const cityVal = document.getElementById("sChurchCity");
      const defaultCity = document.getElementById("sDefaultCity");
      if (cityVal && defaultCity) {
        defaultCity.value = cityVal.value;
      }

      // Copy zip
      const zipVal = document.getElementById("sChurchZip");
      const defaultZip = document.getElementById("sDefaultZip");
      if (zipVal && defaultZip) {
        defaultZip.value = zipVal.value;
      }

      // Copy country — set via TomSelect API, then update default state
      const churchCountryEl = document.getElementById("sChurchCountry");
      const defaultCountryEl = document.getElementById("sDefaultCountry");
      if (churchCountryEl && defaultCountryEl?.tomselect) {
        const countryCode = churchCountryEl.value;
        defaultCountryEl.tomselect.setValue(countryCode);

        // Reuse shared helper so fetching, UI rebuild, and error handling stay consistent
        const churchStateEl = document.getElementById("sChurchState");
        const stateValue = churchStateEl ? churchStateEl.value : "";
        if (defaultStateContainer) {
          updateStateField(defaultStateContainer, "sDefaultState", "sDefaultState", countryCode, stateValue);
        }
      }
    });
  }

  // ── Map initialization ────────────────────────────────────────────────────

  // Map is always visible (no tabs), init after a short delay for layout.
  // Only auto-runs if coordinates were already saved at page load — otherwise
  // the map stays hidden until "Generate Coordinates" populates it (see below).
  if (window.CRM?.churchMapConfig?.hasCoords) {
    setTimeout(initChurchMap, 300);
  }

  // ── Generate Coordinates button ─────────────────────────────────────────

  const generateBtn = document.getElementById("generate-coordinates-btn");
  const generateHelp = document.getElementById("generate-coordinates-help");
  const defaultGenerateHelpText = generateHelp ? generateHelp.textContent : "";

  function buildAddressForGeocoding() {
    const parts = [
      document.getElementById("sChurchAddress")?.value.trim(),
      document.getElementById("sChurchCity")?.value.trim(),
      document.getElementById("sChurchState")?.value.trim(),
      document.getElementById("sChurchZip")?.value.trim(),
    ];

    const countrySelectEl = document.getElementById("sChurchCountry");
    if (countrySelectEl?.value) {
      // Use the display name (option text), not the country code, since
      // Nominatim's free-text query works better with a readable name.
      parts.push(countrySelectEl.selectedOptions?.[0]?.text || "");
    }

    return parts.filter(Boolean).join(", ");
  }

  // ── "Stale coordinates" indicator ───────────────────────────────────────
  // Watches the address fields for edits after page load and flags the
  // saved coordinates as possibly out of date, since nothing re-geocodes
  // automatically until the user saves the form or clicks "Generate Coordinates".

  const addressFieldIds = ["sChurchAddress", "sChurchCity", "sChurchZip", "sChurchCountry"];
  const initialAddressSnapshot = {};
  addressFieldIds.forEach((id) => {
    initialAddressSnapshot[id] = document.getElementById(id)?.value || "";
  });
  initialAddressSnapshot.sChurchState = document.getElementById("sChurchState")?.value || "";

  function updateStaleCoordinatesState() {
    if (!generateHelp) {
      return;
    }

    const latEl = document.getElementById("iChurchLatitude");
    const lngEl = document.getElementById("iChurchLongitude");
    const hasCoords = Boolean(latEl?.value) || Boolean(lngEl?.value);

    const currentState = document.getElementById("sChurchState")?.value || "";
    const addressChanged =
      addressFieldIds.some((id) => (document.getElementById(id)?.value || "") !== initialAddressSnapshot[id]) ||
      currentState !== initialAddressSnapshot.sChurchState;

    if (hasCoords && addressChanged) {
      generateHelp.classList.add("text-warning");
      generateHelp.classList.remove("text-body-secondary");
      // Use DOM methods instead of innerHTML to avoid CodeQL js/xss-through-dom;
      // i18next output goes through createTextNode which never interprets HTML.
      const icon = document.createElement("i");
      icon.className = "fa-solid fa-triangle-exclamation me-1";
      const msg = window.i18next
        ? i18next.t("Address changed since coordinates were last set.")
        : "Address changed since coordinates were last set.";
      generateHelp.textContent = "";
      generateHelp.appendChild(icon);
      generateHelp.appendChild(document.createTextNode(msg));
    } else {
      generateHelp.classList.remove("text-warning");
      generateHelp.classList.add("text-body-secondary");
      generateHelp.textContent = defaultGenerateHelpText;
    }
  }

  function resetStaleCoordinatesSnapshot() {
    addressFieldIds.forEach((id) => {
      initialAddressSnapshot[id] = document.getElementById(id)?.value || "";
    });
    initialAddressSnapshot.sChurchState = document.getElementById("sChurchState")?.value || "";
    updateStaleCoordinatesState();
  }

  if (generateHelp) {
    ["sChurchAddress", "sChurchCity", "sChurchZip", "sChurchCountry"].forEach((id) => {
      document.getElementById(id)?.addEventListener("input", updateStaleCoordinatesState);
      document.getElementById(id)?.addEventListener("change", updateStaleCoordinatesState);
    });
    // State field's DOM node is replaced on country change — delegate from
    // the stable parent container instead of the (possibly stale) child.
    stateContainer.addEventListener("input", updateStaleCoordinatesState);
    stateContainer.addEventListener("change", updateStaleCoordinatesState);
  }

  if (generateBtn) {
    generateBtn.addEventListener("click", () => {
      const address = buildAddressForGeocoding();
      const t = (key) => (window.i18next ? i18next.t(key) : key);

      if (!address) {
        window.CRM?.notify?.(t("Enter a street address first."), { type: "warning", delay: 4000 });
        return;
      }

      // Capture child nodes now (before the loading state overwrites them) so
      // .finally() can restore them without an innerHTML read→write round-trip.
      const originalChildren = [...generateBtn.childNodes].map((n) => n.cloneNode(true));
      generateBtn.disabled = true;
      // Use DOM methods (same pattern as generateHelp) so i18next output is
      // never parsed as HTML.
      const spinner = document.createElement("i");
      spinner.className = "fa-solid fa-spinner fa-spin me-1";
      generateBtn.textContent = "";
      generateBtn.appendChild(spinner);
      generateBtn.appendChild(document.createTextNode(` ${t("Generating...")}`));
      fetch(`${window.CRM.root}/api/geocoder/address`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ address }),
      })
        .then((r) => {
          if (!r.ok) throw new Error(`HTTP ${r.status}`);
          return r.json();
        })
        .then((data) => {
          if (!data || (data.Latitude === 0 && data.Longitude === 0)) {
            window.CRM?.notify?.(t("Could not find coordinates for that address. Try entering them manually."), {
              type: "warning",
              delay: 6000,
            });
            return;
          }

          const latEl = document.getElementById("iChurchLatitude");
          const lngEl = document.getElementById("iChurchLongitude");
          if (!latEl || !lngEl) throw new Error("Coordinate inputs not found"); // unexpected — let .catch() handle it
          latEl.value = data.Latitude;
          lngEl.value = data.Longitude;
          latEl.dispatchEvent(new Event("input", { bubbles: true }));
          latEl.dispatchEvent(new Event("change", { bubbles: true }));
          lngEl.dispatchEvent(new Event("input", { bubbles: true }));
          lngEl.dispatchEvent(new Event("change", { bubbles: true }));

          window.CRM.churchMapConfig = {
            ...(window.CRM.churchMapConfig || {}),
            lat: data.Latitude,
            lng: data.Longitude,
            name: document.getElementById("sChurchName")?.value || "",
            hasCoords: true,
          };

          document.getElementById("church-location-map")?.classList.remove("d-none");
          document.getElementById("no-coords-alert")?.classList.add("d-none");
          reinitChurchMap();

          window.CRM?.notify?.(t("Coordinates Updated"), { type: "success", delay: 3000 });
          resetStaleCoordinatesSnapshot();
        })
        .catch(() => {
          window.CRM?.notify?.(t("Geocoding request failed. Please try again."), { type: "error", delay: 6000 });
        })
        .finally(() => {
          generateBtn.disabled = false;
          generateBtn.replaceChildren(...originalChildren);
        });
    });
  }

  // ── Live Display Preview ────────────────────────────────────────────────

  initChurchInfoPreview();
});

function initChurchInfoPreview() {
  const textFieldIds = [
    "sChurchName",
    "sChurchAddress",
    "sChurchCity",
    "sChurchZip",
    "sChurchPhone",
    "sChurchEmail",
    "sChurchWebSite",
  ];

  function toggleLine(lineId, show) {
    document.getElementById(lineId)?.classList.toggle("d-none", !show);
  }

  function render() {
    const name = document.getElementById("sChurchName")?.value.trim() || "";
    toggleLine("preview-name-line", !!name);
    const previewName = document.getElementById("preview-name");
    if (previewName) {
      previewName.textContent = name;
    }
    document.getElementById("preview-name-required-alert")?.classList.toggle("d-none", !!name);

    const address = document.getElementById("sChurchAddress")?.value.trim() || "";
    toggleLine("preview-address-line", !!address);
    const previewAddress = document.getElementById("preview-address");
    if (previewAddress) {
      previewAddress.textContent = address;
    }

    const city = document.getElementById("sChurchCity")?.value.trim() || "";
    const state = document.getElementById("sChurchState")?.value.trim() || "";
    const zip = document.getElementById("sChurchZip")?.value.trim() || "";
    const cityStateStr = [city, state].filter(Boolean).join(", ");
    const cityLine = [cityStateStr, zip].filter(Boolean).join(" ").trim();
    toggleLine("preview-citystate-line", !!cityLine);
    const previewCityState = document.getElementById("preview-citystate");
    if (previewCityState) {
      previewCityState.textContent = cityLine;
    }

    const countrySelectEl = document.getElementById("sChurchCountry");
    const countryName = countrySelectEl?.value ? countrySelectEl.selectedOptions?.[0]?.text || "" : "";
    toggleLine("preview-country-line", !!countryName);
    const previewCountry = document.getElementById("preview-country");
    if (previewCountry) {
      previewCountry.textContent = countryName;
    }

    const phone = document.getElementById("sChurchPhone")?.value.trim() || "";
    toggleLine("preview-phone-line", !!phone);
    const previewPhone = document.getElementById("preview-phone");
    if (previewPhone) {
      previewPhone.textContent = phone;
    }

    const email = document.getElementById("sChurchEmail")?.value.trim() || "";
    toggleLine("preview-email-line", !!email);
    const previewEmail = document.getElementById("preview-email");
    if (previewEmail) {
      previewEmail.textContent = email;
      // Only create a mailto: link when the value looks like a real email address;
      // reject anything that could be a javascript: or data: scheme injection.
      // The regex already blocks scheme injection; encodeURI (unlike encodeURIComponent)
      // does not encode '@', so the mailto: address remains RFC-compliant and
      // is decoded correctly by all mail clients including embedded webviews.
      const safeEmailHref =
        email && /^[^\s<>"'\\]+@[^\s<>"'\\]+\.[^\s<>"'\\]+$/.test(email) ? `mailto:${encodeURI(email)}` : "#";
      previewEmail.href = safeEmailHref;
    }

    const website = document.getElementById("sChurchWebSite")?.value.trim() || "";
    toggleLine("preview-website-line", !!website);
    const previewWebsite = document.getElementById("preview-website");
    if (previewWebsite) {
      previewWebsite.textContent = website;
      // Only allow http:// and https:// URLs; encodeURI is a CodeQL-recognised sanitiser
      // that breaks the taint path while preserving valid URL structure.
      const safeWebsiteHref = /^https?:\/\//i.test(website) ? encodeURI(website) : "#";
      previewWebsite.href = safeWebsiteHref;
    }
  }

  textFieldIds.forEach((id) => {
    const el = document.getElementById(id);
    el?.addEventListener("input", render);
    el?.addEventListener("change", render);
  });

  // Country <select> exists synchronously in the DOM even before TomSelect
  // wraps it (after the async countries fetch resolves), and TomSelect
  // proxies selections back onto this native element with native "change"
  // events — so attaching here works regardless of TomSelect's init timing.
  document.getElementById("sChurchCountry")?.addEventListener("change", render);

  // State field's DOM node is replaced on country change — delegate from
  // the stable parent container instead of the (possibly stale) child.
  document.getElementById("sChurchStateContainer")?.addEventListener("input", render);
  document.getElementById("sChurchStateContainer")?.addEventListener("change", render);

  // Deliberately do NOT call render() here. The country <select>'s options
  // (and its display name) are only populated once the async
  // /api/public/data/countries fetch resolves via populateCountrySelect().
  // Rendering immediately would flash the country preview line blank before
  // that resolves. The page's server-rendered preview is already correct
  // for the initial state, so we leave it untouched until the user edits a
  // field, at which point render() takes over.
}
