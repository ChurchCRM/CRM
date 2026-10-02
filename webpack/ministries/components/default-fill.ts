/**
 * Default volunteers (D32, D35): who a schedule assigns to a position on every occurrence it
 * creates. A staffing need names several people, so it gets one picker per slot over the
 * qualified people in rotation order (the pool first, then the rest, as the Assign dialog
 * groups them) and, once somebody is chosen, a "Set as Accepted" box.
 *
 * Shared by the schedule dialog's staffing needs (`staffing-needs.ts`) and the Generate
 * occurrences dialog's "Fill by default with" rows, which ask the same question. The slots
 * render into a container the caller owns; `prefix` keeps the two dialogs' classes apart.
 *
 * Slots: one per place up to Max — every one of them up to ALL_SLOTS_UP_TO, past that (or with
 * no Max) one more than are chosen — and never fewer than are chosen: a choice past Max stays
 * on screen, marked, and the caller refuses to save it. A person chosen in one picker is not
 * offered in the others. A saved default whose qualification was revoked stays offered,
 * marked "no longer qualified" (the server keeps a stored default named again).
 */

import type {
  VolunteerDefaultInput,
  VolunteerEligiblePerson,
  VolunteerGenerateDefaults,
  VolunteerRequirementRow,
} from "../api";
import { escapeAttribute, escapeHtml, shortDate, show, tText } from "./ui";

/** The server's ceiling on one need's defaults, whatever its Max. */
export const MAX_DEFAULTS = 50;

/** Up to this Max every slot is drawn; past it the slots grow one at a time. */
const ALL_SLOTS_UP_TO = 10;

/** A schedule's saved default for one position (D32), as its staffing needs carry it. */
export interface SavedDefault {
  personId: number;
  displayName: string;
  accepted: boolean;
  qualified: boolean;
}

export function savedDefaultsOf(row: VolunteerRequirementRow | undefined): SavedDefault[] {
  return (row?.defaults ?? []).map((saved) => ({
    personId: saved.personId,
    displayName: saved.name ?? "",
    accepted: saved.accepted,
    qualified: saved.qualified,
  }));
}

interface SlotSource {
  positionName: string;
  people: VolunteerEligiblePerson[];
  saved: SavedDefault[];
  blankLabel: string;
  prefix: string;
  small: boolean;
  /** The saved defaults the freshly rendered pickers still have to show; emptied by the first sync. */
  pending: SavedDefault[];
}

/** What each slots container offers, by its id: the pickers are rebuilt from it on every change. */
const sources = new Map<string, SlotSource>();

/** How many pickers a need shows for a Max (`null`: none set) with this many people chosen. */
export function slotCount(limit: number | null, chosen: number): number {
  const shown =
    limit !== null && limit <= ALL_SLOTS_UP_TO
      ? Math.max(limit, 0)
      : Math.min(chosen + 1, limit ?? MAX_DEFAULTS, MAX_DEFAULTS);

  return Math.max(shown, chosen);
}

function describeCandidate(person: VolunteerEligiblePerson): string {
  const served =
    person.lastServedDate === null
      ? i18next.t("has not served yet")
      : tText("last served {{date}}", { date: shortDate(person.lastServedDate) });

  return `${person.displayName} — ${served}`;
}

/** Blank first, then saved defaults the list no longer offers, then the pool, then the rest. */
function optionsHtml(source: SlotSource, exclude: Set<number>, selected: number): string {
  const option = (personId: number, label: string, inPool: boolean | null): string =>
    `<option value="${personId}"${inPool === null ? "" : ` data-in-pool="${inPool ? "1" : "0"}"`}${
      personId === selected ? " selected" : ""
    }>${escapeHtml(label)}</option>`;
  const offered = (personId: number): boolean => !exclude.has(personId);
  const listed = new Set(source.people.map((person) => person.personId));

  const unlisted = source.saved
    .filter((saved) => !listed.has(saved.personId) && offered(saved.personId))
    .map((saved) => option(saved.personId, tText("{{name}} (no longer qualified)", { name: saved.displayName }), null));
  const inPool = source.people.filter((person) => person.inPool && offered(person.personId));
  const outside = source.people.filter((person) => !person.inPool && offered(person.personId));

  return [
    `<option value="">${escapeHtml(source.blankLabel)}</option>`,
    ...unlisted,
    inPool.length === 0
      ? ""
      : `<optgroup label="${escapeAttribute(i18next.t("In the volunteer pool"))}">${inPool
          .map((person) => option(person.personId, describeCandidate(person), true))
          .join("")}</optgroup>`,
    outside.length === 0
      ? ""
      : `<optgroup label="${escapeAttribute(i18next.t("Not in the pool"))}">${outside
          .map((person) => option(person.personId, describeCandidate(person), false))
          .join("")}</optgroup>`,
  ].join("");
}

function slotHtml(containerId: string, serial: number, source: SlotSource): string {
  const p = source.prefix;
  const sm = source.small ? " form-select-sm" : "";

  return `
      <div class="row g-2 align-items-start mb-1 volunteer-default-slot ${p}-slot">
        <div class="col-12 col-sm-8">
          <select class="form-select${sm} volunteer-default-select ${p}-select" id="${containerId}-${serial}"></select>
          <div class="form-hint text-warning d-none volunteer-default-unqualified ${p}-unqualified"></div>
          <div class="invalid-feedback volunteer-default-over ${p}-over">${escapeHtml(
            i18next.t("Over Max: remove this one or raise Max."),
          )}</div>
        </div>
        <div class="col-12 col-sm-4 pt-sm-1">
          <label class="form-check mb-0 d-none volunteer-default-accepted-wrap ${p}-accepted-wrap">
            <input class="form-check-input volunteer-default-accepted ${p}-accepted" type="checkbox">
            <span class="form-check-label">${escapeHtml(i18next.t("Set as Accepted"))}</span>
          </label>
        </div>
      </div>`;
}

/**
 * The slots of one need, already holding `saved`. `id` must be unique on the page; `limit` is
 * the need's Max (null: none set). Call `syncDefaultSlots()` on the container once it is in
 * the document.
 */
export function renderDefaultSlots(options: {
  id: string;
  prefix: string;
  heading: string;
  positionName: string;
  people: VolunteerEligiblePerson[];
  saved: SavedDefault[];
  blankLabel: string;
  limit: number | null;
  small?: boolean;
  disabled?: boolean;
}): string {
  const source: SlotSource = {
    positionName: options.positionName,
    people: options.people,
    saved: options.saved,
    blankLabel: options.blankLabel,
    prefix: options.prefix,
    small: options.small ?? false,
    pending: options.saved,
  };
  sources.set(options.id, source);

  const count = slotCount(options.limit, options.saved.length);
  const slots = Array.from({ length: count }, (_, i) => slotHtml(options.id, i, source)).join("");

  return `
      <div class="volunteer-default-slots ${options.prefix}-slots" id="${options.id}" role="group"
           aria-label="${escapeAttribute(tText("Default volunteers for {{position}}", { position: options.positionName }))}"
           data-limit="${options.limit ?? ""}" data-next-slot="${count}" data-disabled="${options.disabled ? "1" : "0"}">
        <label class="form-label${options.small ? " small" : ""} mb-1 volunteer-default-heading" for="${options.id}-0">${escapeHtml(
          options.heading,
        )}</label>
        ${slots}
      </div>`;
}

function slotsOf(container: HTMLElement): HTMLElement[] {
  return Array.from(container.querySelectorAll<HTMLElement>(":scope > .volunteer-default-slot"));
}

function selectOf(slot: HTMLElement): HTMLSelectElement {
  return slot.querySelector<HTMLSelectElement>("select.volunteer-default-select") as HTMLSelectElement;
}

function limitOf(container: HTMLElement): number | null {
  const parsed = Number.parseInt(container.dataset.limit ?? "", 10);

  return Number.isNaN(parsed) ? null : parsed;
}

/** Put the saved defaults in a freshly rendered container's pickers, once. */
function applyPending(container: HTMLElement, source: SlotSource): void {
  const slots = slotsOf(container);
  source.pending.forEach((saved, i) => {
    const slot = slots[i];
    if (!slot) {
      return;
    }
    selectOf(slot).innerHTML = optionsHtml(source, new Set(), saved.personId);
    selectOf(slot).value = String(saved.personId);
    const box = slot.querySelector<HTMLInputElement>(".volunteer-default-accepted");
    if (box) {
      box.checked = saved.accepted;
    }
  });
  source.pending = [];
}

/**
 * Bring a need's pickers in line with its Max and its choices: empty slots added or dropped
 * (never one holding somebody), every other picker's list rebuilt without the people chosen
 * elsewhere, the Accepted boxes and the marks refreshed.
 */
export function syncDefaultSlots(container: HTMLElement, active: HTMLSelectElement | null = null): void {
  const source = sources.get(container.id);
  if (!source) {
    return;
  }
  applyPending(container, source);

  const limit = limitOf(container);
  const slots = slotsOf(container);
  const chosen = slots.filter((slot) => selectOf(slot).value !== "").length;
  const wanted = slotCount(limit, chosen);

  // Empty pickers past what is wanted go, the last first — the one just changed only when no
  // other empty one is left.
  for (const spareActive of [false, true]) {
    for (let i = slots.length - 1; i >= 0 && slots.length > wanted; i--) {
      const select = selectOf(slots[i]);
      if (select.value === "" && (select !== active || spareActive)) {
        slots[i].remove();
        slots.splice(i, 1);
      }
    }
  }
  while (slots.length < wanted) {
    const serial = Number(container.dataset.nextSlot ?? slots.length);
    container.dataset.nextSlot = String(serial + 1);
    container.insertAdjacentHTML("beforeend", slotHtml(container.id, serial, source));
    slots.push(slotsOf(container)[slots.length]);
  }

  const values = slots.map((slot) => Number(selectOf(slot).value || 0));
  const disabled = container.dataset.disabled === "1";
  let rank = 0;
  slots.forEach((slot, i) => {
    const select = selectOf(slot);
    if (select !== active || select.options.length === 0) {
      const exclude = new Set(values.filter((value, j) => j !== i && value > 0));
      select.innerHTML = optionsHtml(source, exclude, values[i]);
      select.value = values[i] > 0 ? String(values[i]) : "";
    }
    select.disabled = disabled;
    select.setAttribute(
      "aria-label",
      tText("Default volunteer {{number}} for {{position}}", { number: i + 1, position: source.positionName }),
    );

    const isChosen = values[i] > 0;
    if (isChosen) {
      rank++;
    }
    const over = isChosen && limit !== null && rank > limit;
    select.classList.toggle("is-invalid", over);

    const saved = source.saved.find((row) => row.personId === values[i]);
    const unqualified = slot.querySelector<HTMLElement>(".volunteer-default-unqualified");
    if (unqualified) {
      unqualified.textContent =
        saved && !saved.qualified
          ? tText("{{name}} is no longer qualified, so this place is left open on new occurrences.", {
              name: saved.displayName,
            })
          : "";
      show(unqualified, saved !== undefined && !saved.qualified);
    }

    const wrap = slot.querySelector<HTMLElement>(".volunteer-default-accepted-wrap");
    const box = slot.querySelector<HTMLInputElement>(".volunteer-default-accepted");
    show(wrap, isChosen);
    if (box) {
      box.disabled = disabled;
      if (!isChosen) {
        box.checked = false;
      }
    }
  });

  const heading = container.querySelector<HTMLLabelElement>(":scope > .volunteer-default-heading");
  if (heading && slots[0]) {
    heading.htmlFor = selectOf(slots[0]).id;
  }
  show(container, slots.length > 0);
}

/** Every slots container under `root`, synced — call once after rendering them. */
export function syncAllDefaultSlots(root: HTMLElement | null): void {
  for (const container of root?.querySelectorAll<HTMLElement>(".volunteer-default-slots") ?? []) {
    syncDefaultSlots(container);
  }
}

/** A changed picker re-syncs its need; returns the need's container, or null for any other target. */
export function handleDefaultSlotChange(target: EventTarget | null): HTMLElement | null {
  const select = (target as HTMLElement | null)?.closest<HTMLSelectElement>("select.volunteer-default-select");
  const container = select?.closest<HTMLElement>(".volunteer-default-slots") ?? null;
  if (select && container) {
    syncDefaultSlots(container, select);
  }

  return container;
}

/** A new Max for the need (`null`: none set): slots follow it, choices past it are marked, never dropped. */
export function setDefaultSlotsLimit(container: HTMLElement, limit: number | null): void {
  container.dataset.limit = limit === null ? "" : String(limit);
  syncDefaultSlots(container);
}

export function setDefaultSlotsDisabled(container: HTMLElement, disabled: boolean): void {
  container.dataset.disabled = disabled ? "1" : "0";
  syncDefaultSlots(container);
}

/** The chosen people in slot order — the order they are assigned in. */
export function readDefaultSlots(container: HTMLElement): VolunteerDefaultInput[] {
  const defaults: VolunteerDefaultInput[] = [];
  for (const slot of slotsOf(container)) {
    const personId = Number(selectOf(slot).value || 0);
    if (personId > 0) {
      defaults.push({
        personId,
        accepted: slot.querySelector<HTMLInputElement>(".volunteer-default-accepted")?.checked ?? false,
      });
    }
  }

  return defaults;
}

/** Why the need cannot be saved as it stands — more people chosen than its Max allows — or null. */
export function defaultSlotsProblem(container: HTMLElement): string | null {
  const limit = limitOf(container);
  const chosen = readDefaultSlots(container).length;
  if (limit === null || chosen <= limit) {
    return null;
  }

  return tText("{{position}}: Max is {{max}} but {{chosen}} default volunteers are chosen. Remove one or raise Max.", {
    position: sources.get(container.id)?.positionName ?? "",
    max: limit,
    chosen,
  });
}

// ── The Generate dialog's "Fill by default with" rows ─────────────────────

function needsPhrase(min: number, max: number | null): string {
  if (max !== null && max > min) {
    return tText("{{min}} to {{max}} needed", { min, max });
  }

  return tText("{{count}} needed", { count: min });
}

export function renderDefaultFillRow(
  idPrefix: string,
  positionId: number,
  positionName: string,
  min: number,
  max: number | null,
  people: VolunteerEligiblePerson[],
  saved: SavedDefault[] = [],
): string {
  return `
      <div class="generate-default-row mb-3" data-position-id="${positionId}">
        <div class="fw-medium mb-1">
          ${escapeHtml(positionName)}
          <span class="text-body-secondary fw-normal">— ${escapeHtml(needsPhrase(min, max))}</span>
        </div>
        ${
          people.length === 0 && saved.length === 0
            ? `<div class="form-hint generate-default-empty">${escapeHtml(
                i18next.t("Nobody is qualified for this position yet, so it stays open."),
              )}</div>`
            : renderDefaultSlots({
                id: `${idPrefix}-${positionId}`,
                prefix: "generate-default",
                heading: `${i18next.t("Fill by default with")}:`,
                positionName,
                people,
                saved,
                blankLabel: i18next.t("Leave open"),
                // The room an occurrence has for the position: its Max, its Min when Max is blank (§2.10).
                limit: max ?? min,
              })
        }
      </div>`;
}

/** Keep each row's pickers in step. Delegated: the rows are re-rendered per open. */
export function wireDefaultFillRows(container: HTMLElement | null): void {
  container?.addEventListener("change", (event) => {
    handleDefaultSlotChange(event.target);
  });
}

/** Every row with pickers, as the whole list per position — an empty one clears it (D32). */
export function readDefaultFills(container: HTMLElement | null): VolunteerGenerateDefaults[] {
  const answers: VolunteerGenerateDefaults[] = [];
  for (const row of container?.querySelectorAll<HTMLElement>(".generate-default-row") ?? []) {
    const slots = row.querySelector<HTMLElement>(".volunteer-default-slots");
    if (slots) {
      answers.push({ positionId: Number(row.dataset.positionId), defaults: readDefaultSlots(slots) });
    }
  }

  return answers;
}

export function defaultFillsProblem(container: HTMLElement | null): string | null {
  for (const slots of container?.querySelectorAll<HTMLElement>(".volunteer-default-slots") ?? []) {
    const problem = defaultSlotsProblem(slots);
    if (problem !== null) {
      return problem;
    }
  }

  return null;
}
