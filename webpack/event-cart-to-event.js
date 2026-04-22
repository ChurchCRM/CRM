/**
 * Cart-to-Event page — auto-submit event type filter on change.
 */

document.addEventListener("DOMContentLoaded", () => {
  document.getElementById("typeId")?.addEventListener("change", () => {
    document.getElementById("eventTypeFilterForm")?.submit();
  });
});
