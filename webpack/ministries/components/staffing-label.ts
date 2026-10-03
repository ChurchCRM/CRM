/**
 * How a staffing count reads, everywhere it is shown (D34): a status word first, then the
 * range — "Needs 1 more", "Covered · 1 more welcome", "Optional · 1 of up to 2", "Full · 3 of 3".
 * The minimum drives the urgency (the tone), the maximum drives the room.
 *
 * Every number comes from the server's single gap implementation
 * (`VolunteerAssignmentService::getGaps()`); this module only chooses the words.
 */

import { tText } from "./ui";

/** gap = red, pending = yellow, filled = green, an optional slot nobody took = neutral. */
export type StaffingTone = "danger" | "warning" | "success" | "secondary";

export interface StaffingLabel {
  label: string;
  /** The numbers behind the label, for a tooltip or a second line; "" when the label says it all. */
  detail: string;
  tone: StaffingTone;
}

/** One requirement's counts, as `VolunteerStaffedRequirement` and its kin carry them. */
export interface RequirementCounts {
  minCount: number;
  /** NULL is the schema's "same as MinCount" (§2.10), not "no limit". */
  maxCount: number | null;
  liveCount: number;
  gapCount: number;
  openCount: number;
  pendingCount?: number;
}

/** Several requirements summed: a team on an event, or an occurrence. */
export interface RollupCounts {
  /** Summed minimums. */
  requiredCount: number;
  /** Summed maximums. */
  capacity: number;
  liveCount: number;
  /** Summed shortfall. */
  gapCount: number;
  /** Summed room. */
  openCount: number;
  pendingCount: number;
}

/** Badge classes per tone, in the Tabler "-lt" style the V2 badges already use. */
export const STAFFING_BADGE_CLASS: Record<StaffingTone, string> = {
  danger: "bg-red-lt text-red",
  warning: "bg-yellow-lt text-yellow",
  success: "bg-green-lt text-green",
  secondary: "bg-secondary-lt text-secondary",
};

function range(live: number, min: number, max: number): string {
  return min === max
    ? tText("{{live}} of {{min}}", { live, min })
    : tText("{{live}} of {{min}}–{{max}}", { live, min, max });
}

/** Met every minimum: yellow while an assignment still waits for an answer, otherwise green. */
function settledTone(pending: number): StaffingTone {
  return pending > 0 ? "warning" : "success";
}

function withPending(detail: string, pending: number): string {
  if (pending <= 0) {
    return detail;
  }
  const note = tText("{{pending}} not yet confirmed", { pending });

  return detail === "" ? note : `${detail} · ${note}`;
}

export function requirementStaffing(counts: RequirementCounts): StaffingLabel {
  const min = counts.minCount;
  const max = counts.maxCount ?? counts.minCount;
  const live = counts.liveCount;
  const pending = counts.pendingCount ?? 0;

  if (counts.gapCount > 0) {
    return {
      label: tText("Needs {{more}} more", { more: counts.gapCount }),
      detail: range(live, min, max),
      tone: "danger",
    };
  }
  if (max === 0 && live === 0) {
    return { label: tText("Not needed this time"), detail: "", tone: "secondary" };
  }
  if (counts.openCount <= 0) {
    return {
      label: tText("Full · {{live}} of {{max}}", { live, max }),
      detail: withPending("", pending),
      tone: settledTone(pending),
    };
  }
  if (min === 0) {
    return live === 0
      ? { label: tText("Optional · up to {{max}} welcome", { max }), detail: "", tone: "secondary" }
      : {
          label: tText("Optional · {{live}} of up to {{max}}", { live, max }),
          detail: withPending("", pending),
          tone: settledTone(pending),
        };
  }

  return {
    label: tText("Covered · {{more}} more welcome", { more: counts.openCount }),
    detail: withPending(
      tText("{{live}} assigned, {{min}} needed, room for {{more}} more", { live, min, more: counts.openCount }),
      pending,
    ),
    tone: settledTone(pending),
  };
}

/**
 * The rollup reads per position, summed: short anywhere is "Needs N more" with the summed
 * shortfall; otherwise "Full" when no position has room, "Optional" when nothing is required,
 * else "Covered · N more welcome". A caller shows "No staffing needs set" itself when the plan
 * names no position (`requirementCount === 0`, §2.10) — this never sees that case.
 */
export function rollupStaffing(counts: RollupCounts): StaffingLabel {
  const min = counts.requiredCount;
  const max = counts.capacity;
  const live = counts.liveCount;
  const pending = counts.pendingCount;

  if (counts.gapCount > 0) {
    return {
      label: tText("Needs {{more}} more", { more: counts.gapCount }),
      detail: range(live, min, max),
      tone: "danger",
    };
  }
  if (counts.openCount <= 0) {
    return {
      label: tText("Full"),
      detail: withPending(tText("{{live}} of {{max}}", { live, max }), pending),
      tone: settledTone(pending),
    };
  }
  if (min === 0) {
    return live === 0
      ? { label: tText("Optional"), detail: tText("Up to {{max}} welcome", { max }), tone: "secondary" }
      : {
          label: tText("Optional"),
          detail: withPending(tText("{{live}} of up to {{max}}", { live, max }), pending),
          tone: settledTone(pending),
        };
  }

  return {
    label: tText("Covered · {{more}} more welcome", { more: counts.openCount }),
    detail: withPending(
      tText("{{live}} assigned, {{min}} needed, room for {{more}} more", { live, min, more: counts.openCount }),
      pending,
    ),
    tone: settledTone(pending),
  };
}

/** Label and detail on two lines, for a `title` / `aria-label`. Plain text: escape it for HTML. */
export function staffingTitle(staffing: StaffingLabel): string {
  return staffing.detail === "" ? staffing.label : `${staffing.label}\n${staffing.detail}`;
}
