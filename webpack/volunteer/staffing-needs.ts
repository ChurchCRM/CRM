/**
 * The staffing-needs editor — one position per row, a checkbox, Min and Max (§2.10).
 *
 * Shared on purpose. The schedule form (S3's Schedules tab) and the occurrence page's
 * "Edit staffing needs" modal ask the identical question — *how many of which position
 * does this need?* — of two different parents, and the only differences are which rows
 * come pre-checked and where the answer is POSTed. Two copies of a number-pair validator
 * would drift, and the drift would be silent: a Max below Min accepted on one screen and
 * refused on the other.
 *
 * **Why this module exists at all.** A staffing requirement is a separate entity from a
 * position, and until now no screen created one. A schedule therefore had none, so every
 * occurrence it generated needed nobody, had no gaps, and reported itself "Fully staffed"
 * with `0/0` filled — technically true of an empty plan and useless to a coordinator.
 * The fix is this editor plus the wording rule that an EMPTY plan says "No staffing needs
 * set" and never "Fully staffed".
 *
 * Nothing here writes to the server or reads a global: it renders into a container the
 * caller owns, and hands back plain rows. The caller decides what request they become.
 *
 * Every user-visible string is `i18next.t()` in this file — the extractor scans
 * `webpack/**`, never a `.php` view's `i18next` call (design §5.10, F31).
 */

import type { VolunteerCandidatePosition, VolunteerRequirementInput, VolunteerRequirementRow } from "./api";

/** Hard ceiling on a count, matching the `number` inputs' own `max`. */
const MAX_COUNT = 99;

function escapeHtml(value: string): string {
  return window.CRM?.escapeHtml?.(value) ?? value;
}

function escapeAttribute(value: string): string {
  return window.CRM?.escapeAttribute?.(value) ?? value;
}

/**
 * Draw the editor into `container`.
 *
 * `current` is the plan as it stands — for a NEW schedule it is empty and `checkByDefault`
 * makes every position start checked, which is the "I just made a team with one position,
 * of course I need one of them" case the defect report was written about. On an edit, a
 * position with no requirement row renders unchecked, because that is exactly what the
 * absence of the row means.
 */
export function renderStaffingNeeds(
  container: HTMLElement,
  positions: VolunteerCandidatePosition[],
  current: VolunteerRequirementRow[],
  checkByDefault: boolean,
): void {
  if (positions.length === 0) {
    container.innerHTML = `
      <div class="empty py-3" data-role="no-positions">
        <div class="empty-icon"><i class="fa-solid fa-clipboard-list fa-2x text-muted"></i></div>
        <p class="empty-title mb-0">${escapeHtml(i18next.t("This team has no positions yet"))}</p>
        <p class="empty-subtitle text-body-secondary mb-0">
          ${escapeHtml(i18next.t("Add a position first, then come back and say how many of them are needed."))}
        </p>
      </div>`;

    return;
  }

  const byPosition = new Map(current.map((row) => [row.positionId, row]));

  container.innerHTML = positions
    .map((position) => {
      const existing = byPosition.get(position.id);
      // A stored Min 0 / Max 0 is not a need, it is an occurrence saying "not this week"
      // about a position its schedule asks for (§2.10's union means that suppression has
      // to be a row, not the absence of one). It renders as the unchecked box it means.
      const suppressed = existing !== undefined && existing.minCount === 0 && existing.maxCount === 0;
      const checked = (existing !== undefined && !suppressed) || (checkByDefault && current.length === 0);
      const min = suppressed ? 1 : (existing?.minCount ?? 1);
      // A stored NULL max means "same as min" (§2.10); the input shows the real number so
      // the coordinator is never editing an invisible default.
      const max = suppressed ? 1 : (existing?.maxCount ?? min);
      const rowId = `staffing-need-${position.id}`;

      return `
        <div class="row g-2 align-items-end py-2 border-bottom volunteer-need-row" data-position-id="${position.id}">
          <div class="col-12 col-sm-6">
            <label class="form-check mb-0" for="${rowId}-check">
              <input class="form-check-input volunteer-need-check" type="checkbox"
                     id="${rowId}-check" ${checked ? "checked" : ""}>
              <span class="form-check-label">${escapeHtml(position.name)}</span>
            </label>
          </div>
          <div class="col-6 col-sm-3">
            <label class="form-label small mb-1" for="${rowId}-min">${escapeHtml(i18next.t("Min"))}</label>
            <input type="number" class="form-control form-control-sm volunteer-need-min"
                   id="${rowId}-min" min="0" max="${MAX_COUNT}" step="1" value="${min}"
                   aria-label="${escapeAttribute(i18next.t("Minimum needed for {{position}}", { position: position.name }))}"
                   ${checked ? "" : "disabled"}>
          </div>
          <div class="col-6 col-sm-3">
            <label class="form-label small mb-1" for="${rowId}-max">${escapeHtml(i18next.t("Max"))}</label>
            <input type="number" class="form-control form-control-sm volunteer-need-max"
                   id="${rowId}-max" min="0" max="${MAX_COUNT}" step="1" value="${max}"
                   aria-label="${escapeAttribute(i18next.t("Maximum allowed for {{position}}", { position: position.name }))}"
                   ${checked ? "" : "disabled"}>
          </div>
        </div>`;
    })
    .join("");

  wire(container);
  refreshWarning(container);
}

/**
 * One delegated listener per container, attached on every render.
 *
 * `renderStaffingNeeds()` replaces `innerHTML` wholesale, which throws away the listeners
 * on the rows but NOT one bound to the container itself — so the flag below keeps a
 * re-render from stacking a second identical handler.
 */
function wire(container: HTMLElement): void {
  if (container.dataset.needsWired === "1") {
    return;
  }
  container.dataset.needsWired = "1";

  container.addEventListener("change", (event) => {
    const target = event.target as HTMLElement | null;
    if (target?.classList.contains("volunteer-need-check")) {
      const row = target.closest<HTMLElement>(".volunteer-need-row");
      const checked = (target as HTMLInputElement).checked;
      for (const input of row?.querySelectorAll<HTMLInputElement>(".volunteer-need-min, .volunteer-need-max") ?? []) {
        input.disabled = !checked;
      }
      refreshWarning(container);
    }
  });

  // `input`, not `change`: the Max-below-Min message has to appear while the number is
  // being typed, not only after the field is left.
  container.addEventListener("input", () => {
    refreshWarning(container);
  });
}

/** Every checked row, as the API's `requirements` array. */
export function readStaffingNeeds(container: HTMLElement): VolunteerRequirementInput[] {
  const rows: VolunteerRequirementInput[] = [];

  for (const row of container.querySelectorAll<HTMLElement>(".volunteer-need-row")) {
    const check = row.querySelector<HTMLInputElement>(".volunteer-need-check");
    if (!check?.checked) {
      continue;
    }

    const min = numberIn(row, ".volunteer-need-min");
    const max = numberIn(row, ".volunteer-need-max");

    rows.push({
      positionId: Number(row.dataset.positionId ?? 0),
      minCount: min,
      maxCount: max,
    });
  }

  return rows;
}

function numberIn(row: HTMLElement, selector: string): number {
  const raw = row.querySelector<HTMLInputElement>(selector)?.value ?? "";
  const parsed = Number.parseInt(raw, 10);

  return Number.isNaN(parsed) ? 0 : parsed;
}

/**
 * The client half of §2.10's count rules, returning the message to show or `null`.
 *
 * The server checks the same two things and is the authority — this exists so a
 * coordinator is told before the round trip, not so the server can trust the client.
 */
export function validateStaffingNeeds(container: HTMLElement): string | null {
  for (const row of container.querySelectorAll<HTMLElement>(".volunteer-need-row")) {
    const check = row.querySelector<HTMLInputElement>(".volunteer-need-check");
    if (!check?.checked) {
      continue;
    }

    const name = row.querySelector(".form-check-label")?.textContent?.trim() ?? "";
    const min = numberIn(row, ".volunteer-need-min");
    const max = numberIn(row, ".volunteer-need-max");

    if (min < 0 || min > MAX_COUNT || max < 0 || max > MAX_COUNT) {
      return i18next.t("{{position}}: the counts must be between 0 and {{max}}.", { position: name, max: MAX_COUNT });
    }
    if (max < min) {
      return i18next.t("{{position}}: the maximum cannot be below the minimum.", { position: name });
    }
  }

  return null;
}

/** How many rows are checked — the caller's "may I warn about an empty plan?" test. */
export function countCheckedNeeds(container: HTMLElement): number {
  return container.querySelectorAll<HTMLInputElement>(".volunteer-need-check:checked").length;
}

/**
 * The two inline notices the editor owns: the empty-plan warning and the count error.
 *
 * A plan with nothing checked is SAVEABLE — a coordinator may genuinely not know yet —
 * but it is never silent, because "generated occurrences will need nobody" is the exact
 * surprise this whole change exists to remove.
 */
function refreshWarning(container: HTMLElement): void {
  const notice = noticeElement(container);
  if (!notice) {
    return;
  }

  const error = validateStaffingNeeds(container);
  if (error !== null) {
    notice.className = "alert alert-danger py-2 px-3 mt-2 mb-0 volunteer-needs-notice";
    notice.textContent = error;
    notice.classList.remove("d-none");

    return;
  }

  if (countCheckedNeeds(container) === 0 && container.querySelector(".volunteer-need-row") !== null) {
    notice.className = "alert alert-warning py-2 px-3 mt-2 mb-0 volunteer-needs-notice";
    notice.textContent = i18next.t("No staffing needs: generated occurrences will need nobody.");
    notice.classList.remove("d-none");

    return;
  }

  notice.classList.add("d-none");
  notice.textContent = "";
}

/**
 * The notice lives as a sibling AFTER the container, so a re-render of the rows cannot
 * take it with it. Created on first use rather than in the `.php` view, because both
 * callers would otherwise have to remember to add the same empty `<div>`.
 */
function noticeElement(container: HTMLElement): HTMLElement | null {
  const parent = container.parentElement;
  if (!parent) {
    return null;
  }

  let notice = parent.querySelector<HTMLElement>(":scope > .volunteer-needs-notice");
  if (!notice) {
    notice = document.createElement("div");
    notice.className = "alert alert-warning py-2 px-3 mt-2 mb-0 volunteer-needs-notice d-none";
    notice.setAttribute("role", "alert");
    container.insertAdjacentElement("afterend", notice);
  }

  return notice;
}
