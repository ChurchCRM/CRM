/**
 * Client-side filter for the timeline UI.
 * Shared by both person-view and family-view webpack bundles.
 *
 * @param {Element} container - A .timeline-container element
 */
export function initTimelineFilter(container) {
  if (!container) return;

  const chips = container.querySelectorAll(".timeline-filter-chip");
  const allChip = container.querySelector(".timeline-filter-all");
  const events = container.querySelectorAll(".timeline-event[data-timeline-category]");
  const years = container.querySelectorAll(".timeline-year");
  const emptyNotice = container.querySelector(".timeline-empty-notice");

  if (chips.length === 0 || events.length === 0) return;

  let active = new Set(["notes"]);

  function applyFilter() {
    const showAll = active.has("all");
    const visibleYears = {};
    let anyVisible = false;

    events.forEach((el) => {
      const cat = el.getAttribute("data-timeline-category") || "notes";
      const visible = showAll || active.has(cat);
      el.style.display = visible ? "" : "none";
      if (visible) {
        anyVisible = true;
        visibleYears[el.getAttribute("data-timeline-year") || ""] = true;
      }
    });

    years.forEach((y) => {
      y.style.display = visibleYears[y.getAttribute("data-timeline-year") || ""] ? "" : "none";
    });

    if (emptyNotice) {
      emptyNotice.style.display = anyVisible ? "none" : "";
    }

    chips.forEach((chip) => {
      const f = chip.getAttribute("data-filter");
      const on = showAll || active.has(f);
      chip.classList.toggle("btn-primary", on);
      chip.classList.toggle("active", on);
      chip.classList.toggle("btn-outline-secondary", !on);
    });

    if (allChip) {
      allChip.classList.toggle("active", showAll);
    }
  }

  chips.forEach((chip) => {
    chip.addEventListener("click", () => {
      const f = chip.getAttribute("data-filter");
      if (!f) return;
      active.delete("all");
      if (active.has(f)) active.delete(f);
      else active.add(f);
      if (active.size === 0) active.add("notes");
      applyFilter();
    });
  });

  if (allChip) {
    allChip.addEventListener("click", () => {
      active = new Set(["all"]);
      applyFilter();
    });
  }

  applyFilter();
}
