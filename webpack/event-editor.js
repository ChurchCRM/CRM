/**
 * Event Editor — client-side behavior.
 *
 * Reads initialization data from `window.CRM.eventEditor` (set inline by view).
 * Handles:
 *  - daterangepicker initialization with start/end values
 *  - attendance-count auto-total
 *  - Quick-mode toggle (#8499)
 *  - Client-side date order validation (#6629)
 */

import flatpickr from "flatpickr";

document.addEventListener("DOMContentLoaded", () => {
  const $ = window.$;
  if (!$) return;

  const cfg = window.CRM?.eventEditor || {};
  const t = window.i18next ? window.i18next.t.bind(window.i18next) : (s) => s;

  // ---------------------------------------------------------------------------
  // flatpickr range picker setup (replaces daterangepicker)
  // ---------------------------------------------------------------------------
  if (cfg.startStr && cfg.endStr) {
    // Initialize input display (legacy formats are kept for now)
    try {
      document.querySelector("#EventDateRange").value = `${cfg.startStr} - ${cfg.endStr}`;
    } catch (e) {
      // ignore if element missing
    }

    // flatpickr will attempt to parse provided defaultDate strings according
    // to the given dateFormat. Using 24-hour parse for input values emitted
    // from the server (YYYY-MM-DD H:mm).
    flatpickr("#EventDateRange", {
      mode: "range",
      enableTime: true,
      time_24hr: false,
      dateFormat: "Y-m-d H:i",
      altInput: false,
      defaultDate: [cfg.startStr, cfg.endStr],
      minuteIncrement: 30,
    });
  }

  // ---------------------------------------------------------------------------
  // Attendance count auto-total
  // ---------------------------------------------------------------------------
  function updateRealTotal() {
    let total = 0;
    $(".attendance-count").each(function () {
      total += parseInt($(this).val(), 10) || 0;
    });
    $("#RealTotal").val(total);
  }
  $(".attendance-count").on("input change", updateRealTotal);
  updateRealTotal();

  // ---------------------------------------------------------------------------
  // Quick mode toggle (#8499) — show/hide advanced fields
  // ---------------------------------------------------------------------------
  let advancedShown = !!cfg.eventExists;
  $("#toggleAdvancedBtn").on("click", () => {
    advancedShown = !advancedShown;
    $(".event-editor-advanced").toggle(advancedShown);
    $("#toggleAdvancedIcon").toggleClass("ti-chevron-down", !advancedShown).toggleClass("ti-chevron-up", advancedShown);
    $("#toggleAdvancedLabel").text(advancedShown ? t("Hide Advanced Options") : t("Show More Options"));
  });

  // ---------------------------------------------------------------------------
  // Client-side date order validation (#6629) — block submit if end < start
  // ---------------------------------------------------------------------------
  $('form[name="EventsEditor"]').on("submit", (e) => {
    // Prefer reading parsed dates from flatpickr instance when available.
    const el = document.querySelector("#EventDateRange");
    let startDate = null;
    let endDate = null;
    if (el && el._flatpickr && Array.isArray(el._flatpickr.selectedDates) && el._flatpickr.selectedDates.length === 2) {
      startDate = el._flatpickr.selectedDates[0];
      endDate = el._flatpickr.selectedDates[1];
    } else {
      // Fallback: try to parse the input value as two parts (string parsing)
      const range = $("#EventDateRange").val() || "";
      const parts = range.split(" - ");
      if (parts.length === 2) {
        const s = Date.parse(parts[0]);
        const eDate = Date.parse(parts[1]);
        if (!isNaN(s) && !isNaN(eDate)) {
          startDate = new Date(s);
          endDate = new Date(eDate);
        }
      }
    }

    if (startDate && endDate && endDate < startDate) {
      e.preventDefault();
      const msg = t("Event end date/time must be on or after the start date/time.");
      if (window.CRM && window.CRM.notify) {
        window.CRM.notify(msg, { type: "danger", delay: 5000 });
      } else {
        alert(msg);
      }
      $("#EventDateRange").trigger("focus");
    }
  });
});
