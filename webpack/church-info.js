/**
 * Church Information Page - Country/State dropdown and map initialization.
 *
 * Follows the same API-driven pattern as DropdownManager.js:
 * - Countries: GET /api/public/data/countries
 * - States:    GET /api/public/data/countries/{code}/states
 */

document.addEventListener("DOMContentLoaded", function () {
  const countrySelect = document.getElementById("sChurchCountry");
  const stateContainer = document.getElementById("sChurchStateContainer");

  if (!countrySelect || !stateContainer || !window.TomSelect) {
    return;
  }

  const $countrySelect = $(countrySelect);
  const userSelectedCountry = $countrySelect.data("user-selected") || "";
  const userSelectedState = stateContainer.dataset.userSelectedState || "";

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

  // ── State field ───────────────────────────────────────────────────────────

  function buildStateSelect(states) {
    const $select = $(
      `<select id="sChurchState" name="sChurchState" class="form-control" style="width:100%"></select>`,
    );
    const blankLabel = window.i18next ? i18next.t("— Select State —") : "— Select State —";
    $select.append(new Option(blankLabel, ""));

    $.each(states, function (code, name) {
      const selected = userSelectedState === code || userSelectedState === name;
      $select.append(new Option(name, code, selected, selected));
    });

    return $select;
  }

  function buildStateInput() {
    return $(
      `<input type="text" id="sChurchState" name="sChurchState" class="form-control" style="width:100%" maxlength="100" value="${userSelectedState}">`,
    );
  }

  function updateStateField(countryCode) {
    if (!countryCode) {
      stateContainer.innerHTML = "";
      const $input = buildStateInput();
      stateContainer.appendChild($input[0]);
      return;
    }

    $.ajax({
      type: "GET",
      url: window.CRM.root + "/api/public/data/countries/" + countryCode.toLowerCase() + "/states",
    })
      .done(function (data) {
        stateContainer.innerHTML = "";
        if (data && Object.keys(data).length > 0) {
          const $select = buildStateSelect(data);
          stateContainer.appendChild($select[0]);
          initTomSelect($select[0]);
        } else {
          stateContainer.appendChild(buildStateInput()[0]);
        }
      })
      .fail(function () {
        stateContainer.innerHTML = "";
        stateContainer.appendChild(buildStateInput()[0]);
      });
  }

  // ── Country dropdown ──────────────────────────────────────────────────────

  $.ajax({
    type: "GET",
    url: window.CRM.root + "/api/public/data/countries",
  }).done(function (data) {
    $countrySelect.empty();
    $countrySelect.append(new Option(window.i18next ? i18next.t("— Select Country —") : "— Select Country —", ""));

    $.each(data, function (idx, country) {
      const selected = userSelectedCountry === country.code || userSelectedCountry === country.name;
      $countrySelect.append(new Option(country.name, country.code, selected, selected));
    });

    initTomSelect(countrySelect);

    // Trigger initial state load for the pre-selected country
    const preselected = $countrySelect.val();
    if (preselected) {
      updateStateField(preselected);
    } else {
      stateContainer.innerHTML = "";
      stateContainer.appendChild(buildStateInput()[0]);
    }
  });

  // Update state field whenever country changes
  $countrySelect.on("change", function () {
    updateStateField(this.value);
  });

  // ── Other TomSelect dropdowns (language, timezone) ──────────────────────────

  $(".auto-tomselect").each(function () {
    if (this.id !== "sChurchCountry") {
      if (!this.tomselect) {
        initTomSelect(this);
      }
    }
  });

  // ── Tab persistence ────────────────────────────────────────────────────────

  const TAB_KEY = "churchInfoActiveTab";

  // Restore last active tab from localStorage
  const savedTab = localStorage.getItem(TAB_KEY);
  const targetTab = savedTab ? document.getElementById(savedTab) : null;
  if (targetTab) {
    $(targetTab).tab("show");
  } else {
    // Default to first tab
    $("#basic-tab").tab("show");
  }

  // Save active tab whenever it changes
  $("#church-info-tabs a[data-toggle='tab']").on("shown.bs.tab", function () {
    localStorage.setItem(TAB_KEY, this.id);
  });

  // ── Map initialization ────────────────────────────────────────────────────

  if (window.L && window.CRM && window.CRM.churchMapConfig) {
    const cfg = window.CRM.churchMapConfig;
    let churchMap = null;

    function initChurchMap() {
      const mapContainer = document.getElementById("church-location-map");
      if (!mapContainer) {
        return;
      }

      if (mapContainer._leaflet_id !== undefined) {
        if (churchMap) {
          churchMap.invalidateSize();
        }
        return;
      }

      try {
        churchMap = window.L.map("church-location-map", {
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
          if (churchMap) {
            churchMap.invalidateSize();
          }
        }, 100);
      } catch (e) {
        console.error("Error initializing map:", e);
      }
    }

    const locationTab = document.getElementById("location-tab");
    if (locationTab) {
      locationTab.addEventListener("click", function () {
        setTimeout(initChurchMap, 300);
      });

      if (window.$) {
        $(locationTab).on("shown.bs.tab", function () {
          setTimeout(initChurchMap, 100);
        });
      }

      if (locationTab.classList.contains("active")) {
        setTimeout(initChurchMap, 500);
      }
    }
  }
});
