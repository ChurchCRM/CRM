/**
 * The three things S5 and S6 both need (#9712, design §5.6/§5.8).
 *
 * Not a component library and not a framework — #9709 and §5.7 are explicit that V2
 * introduces neither. It is the small amount of code that would otherwise be copied
 * verbatim between two pages that are deliberately the same shape: the §5.8 state
 * machine, the "when" line every card starts with, and the escaping helper.
 */

import { formatWallClock } from "../ministries/components/ui";

/**
 * The §5.8 state machine for one pane.
 *
 * The loading block is re-shown at the start of EVERY attempt and the error block's
 * Retry genuinely re-runs the load — both are requirements, not conveniences, and
 * putting the switch here means neither page can forget a state.
 */
export function renderState(pane: string, state: "loading" | "error" | "empty" | "loaded", message = ""): void {
  show(document.getElementById(`${pane}-loading`), state === "loading");
  show(document.getElementById(`${pane}-error`), state === "error");
  show(document.getElementById(`${pane}-empty`), state === "empty");
  show(document.getElementById(`${pane}-content`), state === "loaded");

  if (state === "error") {
    const text = document.getElementById(`${pane}-error`)?.querySelector(".volunteer-error-text");
    if (text) {
      text.textContent = message;
    }
  }
}

export function show(el: Element | null, visible: boolean): void {
  el?.classList.toggle("d-none", !visible);
}

export function byId<T extends HTMLElement>(id: string): T | null {
  return document.getElementById(id) as T | null;
}

/** All escaping goes through the skin's single implementation (U-shared). */
export function escapeHtml(value: string): string {
  return window.CRM?.escapeHtml?.(value) ?? value;
}

/**
 * "Sunday, September 13 at 10:30 AM" — the line §5.6 puts at the top of every card: the
 * weekday spelled out, because a volunteer plans by the day they serve. ChurchCRM's
 * locale and the church's wall-clock time, as every other V2 date (`formatWallClock()`).
 */
export function formatWhen(start: string | null, occurrenceDate: string | null): string {
  const day: Intl.DateTimeFormatOptions = { weekday: "long", day: "numeric", month: "long" };
  if (start) {
    return formatWallClock(start, { ...day, hour: "numeric", minute: "2-digit" });
  }

  return occurrenceDate ? formatWallClock(occurrenceDate, day) : "";
}

/** "Worship Team — Song Leader", skipping whichever parts are absent. */
export function formatWhat(ministryName: string | null, teamName: string | null, positionName: string | null): string {
  return [ministryName, teamName, positionName].filter((part) => !!part).join(" — ");
}

export function notifySuccess(message: string): void {
  window.CRM?.notify?.(message, { type: "success" });
}

/** `"danger"`, never `"error"` — `"error"` renders BLUE in Notyf (U5/E-7). */
export function notifyError(message: string): void {
  window.CRM?.notify?.(message, { type: "danger" });
}

export function confirmAction(title: string, message: string, onConfirm: () => void, danger = true): void {
  window.bootbox?.confirm({
    title,
    message,
    buttons: {
      confirm: { label: i18next.t("Yes"), className: danger ? "btn-danger" : "btn-primary" },
      cancel: { label: i18next.t("No"), className: "btn-default" },
    },
    callback: (result: boolean) => {
      if (result) {
        onConfirm();
      }
    },
  });
}
