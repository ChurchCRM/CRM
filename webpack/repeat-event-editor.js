/**
 * Repeat Event Editor page – client-side logic.
 *
 * Handles:
 *  - Enabling / disabling recurrence-type controls (weekly / monthly / yearly)
 *  - Auto-populating the event title from the selected type
 *  - Client-side form validation (time ordering, date-range ordering)
 */

import "./repeat-event-editor.css";

document.addEventListener("DOMContentLoaded", () => {
  const t = window.i18next ? window.i18next.t.bind(window.i18next) : (s) => s;

  // ---------------------------------------------------------------------------
  // Recurrence-type radio group: enable/disable associated controls
  // ---------------------------------------------------------------------------
  function updateRecurrenceInputs() {
    const selected = document.querySelector('input[name="RecurType"]:checked');
    const type = selected ? selected.value : null;

    const recurDow = document.getElementById("RecurDOW");
    const recurDom = document.getElementById("RecurDOM");
    const recurDoy = document.getElementById("RecurDOY");

    if (recurDow) recurDow.disabled = type !== "weekly";
    if (recurDom) recurDom.disabled = type !== "monthly";
    if (recurDoy) recurDoy.disabled = type !== "yearly";
  }

  document.querySelectorAll('input[name="RecurType"]').forEach((radio) => {
    radio.addEventListener("change", updateRecurrenceInputs);
  });
  updateRecurrenceInputs();

  // ---------------------------------------------------------------------------
  // Auto-populate the title field when an event type is chosen
  // ---------------------------------------------------------------------------
  const eventTypeSelect = document.getElementById("EventTypeID");
  if (eventTypeSelect) {
    eventTypeSelect.addEventListener("change", function () {
      const selectedOption = this.options[this.selectedIndex];
      const name = selectedOption ? selectedOption.dataset.name || "" : "";
      const titleInput = document.getElementById("EventTitle");
      if (!titleInput) return;

      // Only auto-fill when empty or previously auto-filled
      if (titleInput.value === "" || titleInput.dataset.autoFilled === "true") {
        titleInput.value = name;
        titleInput.dataset.autoFilled = name !== "" ? "true" : "false";
      }
    });
  }

  // Track manual edits so auto-fill never overwrites deliberate input
  const titleInput = document.getElementById("EventTitle");
  if (titleInput) {
    titleInput.addEventListener("input", function () {
      this.dataset.autoFilled = "false";
    });
  }

  // ---------------------------------------------------------------------------
  // Form validation: time ordering + date-range ordering
  // ---------------------------------------------------------------------------
  const form = document.querySelector('form[name="RepeatEventsForm"]');
  if (form) {
    form.addEventListener("submit", (e) => {
      const startTime = document.getElementById("StartTime");
      const endTime = document.getElementById("EndTime");
      if (startTime && endTime && startTime.value && endTime.value && endTime.value <= startTime.value) {
        e.preventDefault();
        window.CRM.notify(t("End time must be after start time."), { type: "warning", delay: 5000 });
        return;
      }

      const rangeStart = document.getElementById("RangeStart");
      const rangeEnd = document.getElementById("RangeEnd");
      if (rangeStart && rangeEnd && rangeStart.value && rangeEnd.value && rangeEnd.value < rangeStart.value) {
        e.preventDefault();
        window.CRM.notify(t("Range end date must be on or after range start date."), { type: "warning", delay: 5000 });
      }
    });
  }
});
