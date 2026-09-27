/**
 * "Fill by default with" — one row per position a staffing plan asks for, each with a
 * picker over the qualified people in rotation order (the pool first, then the rest, as
 * the Assign dialog groups them) and, once somebody is chosen, a "Set as Accepted" box.
 *
 * Shared by the Generate occurrences dialog (review, 2026-09-18) and the ministry
 * Calendar tab's new-event dialog (D24), which ask the same question of occurrences that
 * are about to be made. The rows render into a container the caller owns; `idPrefix`
 * keeps the two dialogs' select ids apart while the class names stay the same.
 */

import type { VolunteerEligiblePerson, VolunteerGenerateDefault } from "../api";
import { escapeAttribute, escapeHtml, show, tText } from "./ui";

function describeCandidate(person: VolunteerEligiblePerson): string {
  const served =
    person.lastServedDate === null
      ? i18next.t("has not served yet")
      : tText("last served {{date}}", { date: person.lastServedDate });

  return `${person.displayName} — ${served}`;
}

/** The picker's options: blank first, then the pool, then the rest — the Assign dialog's grouping. */
function candidateOptions(people: VolunteerEligiblePerson[]): string {
  const option = (person: VolunteerEligiblePerson): string =>
    `<option value="${person.personId}" data-in-pool="${person.inPool ? "1" : "0"}">${escapeHtml(
      describeCandidate(person),
    )}</option>`;
  const inPool = people.filter((person) => person.inPool);
  const outside = people.filter((person) => !person.inPool);

  return [
    `<option value="">${escapeHtml(i18next.t("Leave open"))}</option>`,
    inPool.length === 0
      ? ""
      : `<optgroup label="${escapeAttribute(i18next.t("In the volunteer pool"))}">${inPool.map(option).join("")}</optgroup>`,
    outside.length === 0
      ? ""
      : `<optgroup label="${escapeAttribute(i18next.t("Not in the pool"))}">${outside.map(option).join("")}</optgroup>`,
  ].join("");
}

function needsPhrase(min: number, max: number | null): string {
  if (max !== null && max > min) {
    return tText("{{min}} to {{max}} needed", { min, max });
  }

  return tText("{{count}} needed", { count: min });
}

/** The Accepted box only means something once a person is named, so it comes and goes with the choice. */
export function syncAcceptedBox(select: HTMLSelectElement): void {
  const row = select.closest<HTMLElement>(".generate-default-row");
  const wrap = row?.querySelector<HTMLElement>(".generate-default-accepted-wrap") ?? null;
  const chosen = select.value !== "";
  show(wrap, chosen);
  if (!chosen) {
    const box = row?.querySelector<HTMLInputElement>(".generate-default-accepted");
    if (box) {
      box.checked = false;
    }
  }
}

export function renderDefaultFillRow(
  idPrefix: string,
  positionId: number,
  positionName: string,
  min: number,
  max: number | null,
  people: VolunteerEligiblePerson[],
): string {
  const selectId = `${idPrefix}-${positionId}`;

  return `
      <div class="generate-default-row mb-3" data-position-id="${positionId}">
        <div class="fw-medium mb-1">
          ${escapeHtml(positionName)}
          <span class="text-body-secondary fw-normal">— ${escapeHtml(needsPhrase(min, max))}</span>
        </div>
        ${
          people.length === 0
            ? `<div class="form-hint generate-default-empty">${escapeHtml(
                i18next.t("Nobody is qualified for this position yet, so it stays open."),
              )}</div>`
            : `<div class="row g-2 align-items-end">
                <div class="col-12 col-md-8">
                  <label class="form-label mb-1" for="${selectId}">${escapeHtml(i18next.t("Fill by default with"))}:</label>
                  <select class="form-select generate-default-select" id="${selectId}" data-position-id="${positionId}">
                    ${candidateOptions(people)}
                  </select>
                </div>
                <div class="col-12 col-md-4 pb-md-2">
                  <label class="form-check d-none generate-default-accepted-wrap">
                    <input class="form-check-input generate-default-accepted" type="checkbox">
                    <span class="form-check-label">${escapeHtml(i18next.t("Set as Accepted"))}</span>
                  </label>
                </div>
              </div>`
        }
      </div>`;
}

/**
 * The same shared TomSelect every other picker uses, body-mounted so the dialog cannot clip
 * its dropdown, with no option cap (#9819). The caller destroys what it gets back.
 */
export function mountDefaultFillPickers(container: HTMLElement | null): TomSelectInstance[] {
  const instances: TomSelectInstance[] = [];
  if (!container || !window.TomSelect) {
    return instances;
  }
  for (const select of container.querySelectorAll<HTMLSelectElement>("select.generate-default-select")) {
    instances.push(
      new window.TomSelect(select, {
        dropdownParent: "body",
        maxOptions: null,
        onChange: () => syncAcceptedBox(select),
      }),
    );
  }

  return instances;
}

export function destroyDefaultFillPickers(instances: TomSelectInstance[]): void {
  for (const instance of instances) {
    try {
      instance.destroy();
    } catch (_e) {
      // The rows may already be gone; nothing left to tear down.
    }
  }
}

/** Keep each row's Accepted box in step with its picker. Delegated: the rows are re-rendered per open. */
export function wireDefaultFillRows(container: HTMLElement | null): void {
  container?.addEventListener("change", (event) => {
    const select = (event.target as HTMLElement | null)?.closest<HTMLSelectElement>("select.generate-default-select");
    if (select) {
      syncAcceptedBox(select);
    }
  });
}

export function readDefaultFills(container: HTMLElement | null): VolunteerGenerateDefault[] {
  const defaults: VolunteerGenerateDefault[] = [];
  for (const row of container?.querySelectorAll<HTMLElement>(".generate-default-row") ?? []) {
    const select = row.querySelector<HTMLSelectElement>("select.generate-default-select");
    const personId = Number(select?.value ?? 0);
    if (!select || personId <= 0) {
      continue;
    }
    defaults.push({
      positionId: Number(row.dataset.positionId),
      personId,
      accepted: row.querySelector<HTMLInputElement>(".generate-default-accepted")?.checked ?? false,
    });
  }

  return defaults;
}

/** Put earlier answers back after the rows were re-rendered (the plan changed underneath them). */
export function restoreDefaultFills(
  container: HTMLElement | null,
  pickers: TomSelectInstance[],
  previous: VolunteerGenerateDefault[],
): void {
  for (const answer of previous) {
    const row = container?.querySelector<HTMLElement>(`.generate-default-row[data-position-id="${answer.positionId}"]`);
    const select = row?.querySelector<HTMLSelectElement>("select.generate-default-select");
    if (!row || !select || !Array.from(select.options).some((option) => option.value === String(answer.personId))) {
      continue;
    }
    const picker = pickers.find((instance) => instance.input === select);
    if (picker) {
      picker.setValue(String(answer.personId), true);
    } else {
      select.value = String(answer.personId);
    }
    syncAcceptedBox(select);
    const accepted = row.querySelector<HTMLInputElement>(".generate-default-accepted");
    if (accepted) {
      accepted.checked = answer.accepted;
    }
  }
}
