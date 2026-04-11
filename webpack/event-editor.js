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

document.addEventListener("DOMContentLoaded", () => {
  const $ = window.$;
  if (!$) return;

  const cfg = window.CRM?.eventEditor || {};
  const t = window.i18next ? window.i18next.t.bind(window.i18next) : (s) => s;

  // ---------------------------------------------------------------------------
  // daterangepicker setup
  // ---------------------------------------------------------------------------
  if (cfg.startStr && cfg.endStr) {
    const startDate = window.moment(cfg.startStr, "YYYY-MM-DD H:mm").format("YYYY-MM-DD h:mm A");
    const endDate = window.moment(cfg.endStr, "YYYY-MM-DD H:mm").format("YYYY-MM-DD h:mm A");
    $("#EventDateRange").val(`${startDate} - ${endDate}`);
    $("#EventDateRange").daterangepicker({
      timePicker: true,
      timePickerIncrement: 30,
      linkedCalendars: true,
      showDropdowns: true,
      locale: { format: "YYYY-MM-DD h:mm A" },
      startDate,
      endDate,
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
    const range = $("#EventDateRange").val() || "";
    const parts = range.split(" - ");
    if (parts.length !== 2) return;
    const start = window.moment(parts[0], "YYYY-MM-DD h:mm A");
    const end = window.moment(parts[1], "YYYY-MM-DD h:mm A");
    if (start.isValid() && end.isValid() && end.isBefore(start)) {
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
