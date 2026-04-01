/**
 * Church Information Page - Country/State dropdown, map, and copy-defaults handler.
 *
 * Follows the same API-driven pattern as DropdownManager.js:
 * - Countries: GET /api/public/data/countries
 * - States:    GET /api/public/data/countries/{code}/states
 */

document.addEventListener("DOMContentLoaded", function () {
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
    const blankLabel = window.i18next ? i18next.t("— Select State —") : "— Select State —";
    $select.append(new Option(blankLabel, ""));

    $.each(states, function (code, name) {
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
      return;
    }

    $.ajax({
      type: "GET",
      url: window.CRM.root + "/api/public/data/countries/" + countryCode.toLowerCase() + "/states",
    })
      .done(function (data) {
        container.innerHTML = "";
        if (data && Object.keys(data).length > 0) {
          const $select = buildStateSelect(fieldId, fieldName, data, selectedValue);
          container.appendChild($select[0]);
          initTomSelect($select[0]);
        } else {
          container.appendChild(buildStateInput(fieldId, fieldName, selectedValue)[0]);
        }
      })
      .fail(function () {
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
        url: window.CRM.root + "/api/public/data/countries",
      });
    }
    return countriesPromise;
  }

  function populateCountrySelect($selectEl, selectedValue, callback) {
    fetchCountries().done(function (data) {
      $selectEl.empty();
      const blankLabel = window.i18next ? i18next.t("— Select Country —") : "— Select Country —";
      $selectEl.append(new Option(blankLabel, ""));

      $.each(data, function (idx, country) {
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

  populateCountrySelect($countrySelect, userSelectedCountry, function (preselected) {
    if (preselected) {
      updateStateField(stateContainer, "sChurchState", "sChurchState", preselected, userSelectedState);
    } else {
      stateContainer.innerHTML = "";
      stateContainer.appendChild(buildStateInput("sChurchState", "sChurchState", userSelectedState)[0]);
    }
  });

  $countrySelect.on("change", function () {
    updateStateField(stateContainer, "sChurchState", "sChurchState", this.value, "");
  });

  // ── Default country → default state ─────────────────────────────────────

  if (defaultCountrySelect && defaultStateContainer) {
    const $defaultCountrySelect = $(defaultCountrySelect);
    const userSelectedDefaultCountry = $defaultCountrySelect.data("user-selected") || "";

    populateCountrySelect($defaultCountrySelect, userSelectedDefaultCountry, function (preselected) {
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
    copyBtn.addEventListener("click", function () {
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
      if (churchCountryEl && defaultCountryEl && defaultCountryEl.tomselect) {
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

  if (window.L && window.CRM && window.CRM.churchMapConfig) {
    const cfg = window.CRM.churchMapConfig;

    function initChurchMap() {
      const mapContainer = document.getElementById("church-location-map");
      if (!mapContainer) {
        return;
      }

      if (mapContainer._leaflet_id !== undefined) {
        return;
      }

      try {
        const churchMap = window.L.map("church-location-map", {
          scrollWheelZoom: false,
          zoomControl: true,
        }).setView([cfg.lat, cfg.lng], 15);

        window.L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
          maxZoom: 19,
          attribution:
            '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors',
        }).addTo(churchMap);

        window.L.marker([cfg.lat, cfg.lng])
          .bindPopup("<strong>" + cfg.name + "</strong>")
          .addTo(churchMap);

        setTimeout(() => {
          churchMap.invalidateSize();
        }, 100);
      } catch (e) {
        console.error("Error initializing map:", e);
      }
    }

    // Map is always visible (no tabs), init after a short delay for layout
    setTimeout(initChurchMap, 300);
  }
});
