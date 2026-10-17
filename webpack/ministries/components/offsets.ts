/**
 * The two "volunteers start / finish relative to the event" rows (D21), shared by the
 * schedule dialog and the Staff an event dialog.
 *
 * The rows are drawn here rather than in the views so the ministry page and the
 * portal's team page carry one implementation, and so every label is an `i18next.t()`
 * the extractor can see (§5.10). The server stores signed minutes: the volunteers'
 * start is the event's start plus `startOffsetMinutes`, their end the event's end plus
 * `endOffsetMinutes`.
 */

import { byId, escapeAttribute, escapeHtml, tText } from "./ui";

/** Same bound as `VolunteerSchedule::MAX_OFFSET_MINUTES`. */
export const MAX_OFFSET_MINUTES = 720;

function row(prefix: string, which: "start" | "end"): string {
  const id = `${prefix}-${which}-offset`;
  const label = which === "start" ? i18next.t("Volunteers start") : i18next.t("Volunteers finish");
  const earlier =
    which === "start" ? i18next.t("minutes before the event starts") : i18next.t("minutes before the event ends");
  const later =
    which === "start" ? i18next.t("minutes after the event starts") : i18next.t("minutes after the event ends");

  return `
    <div class="row g-2 align-items-center mb-2">
      <div class="col-12 col-md-3">
        <label class="col-form-label" for="${id}">${escapeHtml(label)}</label>
      </div>
      <div class="col-4 col-md-2">
        <input type="number" class="form-control" id="${id}" min="0" max="${MAX_OFFSET_MINUTES}" step="5" value="0">
      </div>
      <div class="col-8 col-md-7">
        <select class="form-select" id="${id}-direction" aria-label="${escapeAttribute(label)}">
          <option value="before">${escapeHtml(earlier)}</option>
          <option value="after">${escapeHtml(later)}</option>
        </select>
      </div>
    </div>`;
}

/** Draw both rows into `#{prefix}-offsets` once; later calls leave them alone. */
export function renderOffsetFields(prefix: string): void {
  const container = byId(`${prefix}-offsets`);
  if (!container || container.childElementCount > 0) {
    return;
  }
  container.innerHTML = `
    <div class="form-label mb-1">${escapeHtml(i18next.t("Volunteer times"))}</div>
    ${row(prefix, "start")}
    ${row(prefix, "end")}
    <div class="form-text mt-0">${escapeHtml(
      i18next.t("Leave both at 0 to serve exactly when the event happens. Moving the event moves the volunteers too."),
    )}</div>`;
}

function write(prefix: string, which: "start" | "end", minutes: number): void {
  const input = byId<HTMLInputElement>(`${prefix}-${which}-offset`);
  const direction = byId<HTMLSelectElement>(`${prefix}-${which}-offset-direction`);
  if (input) {
    input.value = String(Math.abs(minutes));
  }
  if (direction) {
    // A zero reads most naturally as "before the start" and "after the end".
    const earlier = minutes < 0 || (minutes === 0 && which === "start");
    direction.value = earlier ? "before" : "after";
  }
}

/** Fill both rows from stored signed minutes. */
export function writeOffsets(prefix: string, startMinutes: number, endMinutes: number): void {
  write(prefix, "start", startMinutes);
  write(prefix, "end", endMinutes);
}

function read(prefix: string, which: "start" | "end"): number {
  const raw = byId<HTMLInputElement>(`${prefix}-${which}-offset`)?.value.trim() ?? "";
  const minutes = raw === "" ? 0 : Number(raw);
  const earlier = byId<HTMLSelectElement>(`${prefix}-${which}-offset-direction`)?.value === "before";

  return earlier ? -minutes : minutes;
}

/** Both rows as signed minutes, or an error message when a box holds something else. */
export function readOffsets(prefix: string): { start: number; end: number } | string {
  const start = read(prefix, "start");
  const end = read(prefix, "end");
  for (const minutes of [start, end]) {
    if (!Number.isInteger(minutes) || Math.abs(minutes) > MAX_OFFSET_MINUTES) {
      return tText("Volunteer times must be whole minutes, at most {{max}} from the event", {
        max: MAX_OFFSET_MINUTES,
      });
    }
  }

  return { start, end };
}

/** "Volunteers start 45 minutes before the event starts." and its partner, or "" when both are 0. */
export function offsetSummary(startMinutes: number, endMinutes: number): string {
  const parts: string[] = [];
  if (startMinutes !== 0) {
    parts.push(
      startMinutes < 0
        ? tText("Volunteers start {{count}} minutes before the event starts.", { count: -startMinutes })
        : tText("Volunteers start {{count}} minutes after the event starts.", { count: startMinutes }),
    );
  }
  if (endMinutes !== 0) {
    parts.push(
      endMinutes > 0
        ? tText("They finish {{count}} minutes after the event ends.", { count: endMinutes })
        : tText("They finish {{count}} minutes before the event ends.", { count: -endMinutes }),
    );
  }

  return parts.join(" ");
}
