/**
 * S4 — the occurrence / staffing view (#9709, design §5.5).
 *
 * The workhorse coordinator screen. One card per position showing `filled / required`
 * with a live gap badge, the assignment rows underneath with their status badges and
 * row-action menus, an **Assign** control opening the eligible picker, and the
 * substitution queue for this occurrence.
 *
 * There is no "assign everyone in the cart" button any more, and no cart-assign call:
 * assigning is a per-person act with per-person eligibility rules (I1–I5), so the bulk
 * button half-succeeded and reported a list of reasons — strictly worse than the picker
 * beside it, which can only ever offer people who are genuinely assignable. The Cart
 * still feeds V2 from the ministry page, where it fills the volunteer pool.
 *
 * Two independent fetches, two independent state machines (`renderState()`): the
 * staffing read and the swap queue. One failing must not blank the other — a coordinator
 * whose swap queue 500s still needs to see who is on the roster.
 *
 * **The picker is a pre-fetched list, not an AJAX person search.** `initPersonSelect()`
 * (#9819) is the shared widget for "find any person by typing", and this is not that
 * question: the candidate set is bounded (people qualified for one position), it is
 * ORDERED — least-recently-served first, which is the whole of §2.17's rotation — and
 * each entry carries annotations (`inPool`, `lastServedDate`, `conflictPositionId`) that
 * a generic `{objid,text}` search result cannot express, plus §5.5's "Not in the pool"
 * divider, which requires knowing the whole list at once. So the list is fetched whole
 * when the modal opens and rendered into two `<optgroup>`s over the shared
 * `window.TomSelect` class — the same TomSelect everything else uses, with #9819's two
 * hard-won settings (`dropdownParent: "body"`, `maxOptions: null`) applied here too.
 *
 * **No Volunteer-specific action-menu framework** (#9709 says so explicitly, CR2): row
 * menus go through `window.CRM.buildActionMenu()`, which owns the scaffold, the
 * `data-bs-display="static"` that keeps a menu from being clipped in a scroll container,
 * and every bit of escaping. Confirms are `bootbox`; toasts are `window.CRM.notify` with
 * `"danger"`, never `"error"` — `"error"` renders blue (U5/E-7).
 *
 * Every user-visible string is `i18next.t()` in this file: the extractor scans only
 * `webpack/**` and `src/skin/js/**`, so the same call inside the `.php` view would never
 * be translated (design §5.10, F31).
 */

import {
  approveSwap,
  clearOccurrenceRequirements,
  createAssignment,
  deleteAssignment,
  errorMessage,
  getOccurrenceRequirements,
  getStaffing,
  listEligiblePeople,
  listSwaps,
  notifyAssignment,
  notifyError,
  notifySuccess,
  rejectSwap,
  replaceOccurrenceRequirements,
  setAssignmentStatus,
  type VolunteerAssignment,
  type VolunteerEligiblePerson,
  type VolunteerRequirementInput,
  type VolunteerStaffedRequirement,
  type VolunteerStaffing,
  type VolunteerSwap,
} from "./api";
import { readStaffingNeeds, renderStaffingNeeds, validateStaffingNeeds } from "./staffing-needs";

interface OccurrenceConfig {
  occurrenceId: number;
  ministryId: number;
  eventId: number;
}

let occurrenceId = 0;
let staffing: VolunteerStaffing | null = null;
let eligible: VolunteerEligiblePerson[] = [];
/** The position the assign modal is currently filling; 0 when it is closed. */
let assigningPositionId = 0;
let assignSelect: TomSelectInstance | null = null;
/**
 * Whether the assign modal has finished its show transition.
 *
 * Bootstrap 5's `Modal.hide()` returns early while `_isTransitioning` is true — the
 * request is accepted and thrown away, silently, leaving the dialog open. A fast
 * round-trip (a local API answering in a few milliseconds) lands squarely inside that
 * 150 ms fade, so "hide on success" cannot simply call `hide()`. These two flags queue
 * the dismissal for `shown.bs.modal` instead; see `hideAssignModal()`.
 */
let assignModalShown = false;
let assignModalHidePending = false;
/** Same Bootstrap fade trap, for the staffing-needs modal. */
let needsModalShown = false;
let needsModalHidePending = false;
/**
 * The positions the SCHEDULE asks for, captured when the needs modal opens.
 *
 * The effective plan is a UNION (§2.10): an occurrence's rows win position by position,
 * but leaving a position out does not remove the schedule's requirement for it. So
 * unchecking a schedule-provided position cannot mean "send no row" — it has to mean
 * "send Min 0 / Max 0", which is the only way an occurrence can say *not this week*.
 */
let needsSchedulePositionIds: number[] = [];

function byId<T extends HTMLElement>(id: string): T | null {
  return document.getElementById(id) as T | null;
}

function show(el: Element | null, visible: boolean): void {
  el?.classList.toggle("d-none", !visible);
}

function escapeHtml(value: string): string {
  return window.CRM?.escapeHtml?.(value) ?? value;
}

/**
 * The §5.8 state machine for one pane, in one place so neither pane can forget a state.
 * The loading block is re-shown at the start of EVERY attempt and the error block's
 * Retry genuinely re-runs the load — see `wire()`.
 */
function renderState(
  pane: "requirements" | "swaps",
  state: "loading" | "error" | "empty" | "loaded",
  message = "",
): void {
  show(byId(`${pane}-loading`), state === "loading");
  show(byId(`${pane}-error`), state === "error");
  show(byId(`${pane}-empty`), state === "empty");
  show(byId(pane === "requirements" ? "requirements-content" : "swaps-table-wrapper"), state === "loaded");

  if (state === "error") {
    const text = byId(`${pane}-error`)?.querySelector(".volunteer-error-text");
    if (text) {
      text.textContent = message;
    }
  }
}

function modal(id: string): { show(): void; hide(): void } | null {
  const el = byId(id);
  if (!el || !window.bootstrap?.Modal) {
    return null;
  }

  return window.bootstrap.Modal.getOrCreateInstance(el);
}

function confirmAction(title: string, message: string, onConfirm: () => void, danger = true): void {
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

/** Row actions through the shared builder (CR2/#9820) — labels and data are passed raw. */
function actionMenu(items: Array<CRMActionMenuItem | false>): string {
  const build = window.CRM?.buildActionMenu;

  return build ? build(items) : "";
}

// ─── Rendering ───────────────────────────────────────────────────────────────

/** §5.5's badge palette, one place, so a status can never render two ways. */
function statusBadge(status: VolunteerAssignment["status"]): string {
  const map: Record<VolunteerAssignment["status"], { cls: string; label: string }> = {
    pending: { cls: "bg-yellow-lt text-yellow", label: i18next.t("Pending") },
    accepted: { cls: "bg-green-lt text-green", label: i18next.t("Accepted") },
    declined: { cls: "bg-red-lt text-red", label: i18next.t("Declined") },
    substituted: { cls: "bg-azure-lt text-azure", label: i18next.t("Substituted") },
    cancelled: { cls: "bg-secondary-lt text-secondary", label: i18next.t("Cancelled") },
    completed: { cls: "bg-secondary-lt text-secondary", label: i18next.t("Completed") },
  };
  const badge = map[status];

  return `<span class="badge ${badge.cls} volunteer-status-badge">${escapeHtml(badge.label)}</span>`;
}

function sourceLabel(source: VolunteerAssignment["source"]): string {
  if (source === "self_signup") {
    return i18next.t("Signed up");
  }
  if (source === "substitute") {
    return i18next.t("Substitute");
  }

  return i18next.t("Assigned");
}

/** Read-only, and only ever rendered for a linked occurrence (E10). */
function attendanceLabel(value: VolunteerAssignment["attendance"]): string {
  if (value === "checked_in") {
    return i18next.t("Checked in");
  }
  if (value === "checked_out") {
    return i18next.t("Checked out");
  }
  if (value === "not_checked_in") {
    return i18next.t("Not checked in");
  }

  return "";
}

function assignmentMenu(assignment: VolunteerAssignment): string {
  const live = assignment.status === "pending" || assignment.status === "accepted";
  const root = window.CRM?.root ?? "";

  return actionMenu([
    {
      type: "link",
      href: `${root}/people/view/${assignment.personId}`,
      icon: "fa-solid fa-user",
      label: i18next.t("View person"),
    },
    live && {
      type: "button",
      icon: "fa-solid fa-check",
      label: i18next.t("Record: they accepted"),
      data: { action: "accepted", "assignment-id": assignment.id, "person-name": assignment.displayName ?? "" },
    },
    live && {
      type: "button",
      icon: "fa-solid fa-xmark",
      label: i18next.t("Record: they declined"),
      data: { action: "declined", "assignment-id": assignment.id, "person-name": assignment.displayName ?? "" },
    },
    live && {
      type: "button",
      icon: "fa-solid fa-paper-plane",
      label: i18next.t("Send the assignment message again"),
      data: { action: "notify", "assignment-id": assignment.id, "person-name": assignment.displayName ?? "" },
    },
    live && { type: "divider" },
    live && {
      type: "button",
      icon: "fa-solid fa-ban",
      label: i18next.t("Cancel assignment"),
      danger: true,
      data: { action: "cancel", "assignment-id": assignment.id, "person-name": assignment.displayName ?? "" },
    },
  ]);
}

function assignmentRow(assignment: VolunteerAssignment, showAttendance: boolean): string {
  const root = window.CRM?.root ?? "";
  const name = escapeHtml(assignment.displayName ?? String(assignment.personId));
  const attendance = showAttendance ? attendanceLabel(assignment.attendance) : "";

  return `
    <div class="volunteer-assignment-row d-flex align-items-center justify-content-between gap-2 py-2 border-bottom"
         data-assignment-id="${assignment.id}"
         data-person-id="${assignment.personId}"
         data-status="${escapeHtml(assignment.status)}">
      <div class="min-w-0">
        <a href="${root}/people/view/${assignment.personId}" class="text-truncate d-block">${name}</a>
        <div class="small text-body-secondary">
          ${escapeHtml(sourceLabel(assignment.source))}
          ${assignment.respondedDate ? ` &middot; ${escapeHtml(assignment.respondedDate)}` : ""}
          ${attendance === "" ? "" : ` &middot; ${escapeHtml(attendance)}`}
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        ${statusBadge(assignment.status)}
        ${assignmentMenu(assignment)}
      </div>
    </div>`;
}

/**
 * One position card. The counts come from the server's single gap implementation — the
 * screen never adds up rows itself, because `declined` and `cancelled` rows are visible
 * here but deliberately do not count as live (§2.11.3).
 */
function requirementCard(requirement: VolunteerStaffedRequirement, showAttendance: boolean): string {
  const rows = requirement.assignments.map((a) => assignmentRow(a, showAttendance)).join("");
  const hasGap = requirement.gapCount > 0;
  const canAssign = staffing?.occurrence.status !== "cancelled";

  return `
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card h-100 volunteer-requirement" data-position-id="${requirement.positionId}">
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
          <h5 class="card-title mb-0 text-truncate">${escapeHtml(requirement.positionName ?? "")}</h5>
          <span class="badge bg-secondary-lt requirement-counts">
            ${requirement.liveCount} / ${requirement.minCount}
          </span>
        </div>
        <div class="card-body">
          <div class="alert alert-warning py-1 px-2 mb-2 requirement-gap ${hasGap ? "" : "d-none"}" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            ${escapeHtml(i18next.t("{{count}} still needed", { count: requirement.gapCount }))}
          </div>
          <div class="empty py-3 volunteer-empty ${rows === "" ? "" : "d-none"}">
            <div class="empty-icon"><i class="fa-solid fa-user-plus fa-2x text-muted"></i></div>
            <p class="empty-title mb-0">${escapeHtml(i18next.t("Nobody assigned yet"))}</p>
          </div>
          <div class="volunteer-assignment-list">${rows}</div>
        </div>
        <!--
          One button. "Assign everyone in the cart" was here beside it and is gone:
          assigning is a per-person act with per-person eligibility rules (I1-I5), so
          the bulk button half-succeeded and reported a list of reasons — a worse
          answer than the picker next to it, which can only ever offer people who are
          actually assignable. The Cart still feeds V2 on the ministry page, where it
          fills the volunteer pool.
        -->
        <div class="card-footer d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-sm btn-primary volunteer-assign-btn"
                  data-position-id="${requirement.positionId}"
                  data-position-name="${escapeHtml(requirement.positionName ?? "")}"
                  ${canAssign ? "" : "disabled"}>
            <i class="fa-solid fa-user-plus me-1"></i>${escapeHtml(i18next.t("Assign"))}
          </button>
        </div>
      </div>
    </div>`;
}

function renderStaffing(data: VolunteerStaffing): void {
  const container = byId("requirements-content");
  if (!container) {
    return;
  }

  if (data.requirements.length === 0) {
    container.innerHTML = "";
    renderState("requirements", "empty");
  } else {
    container.innerHTML = data.requirements
      .map((requirement) => requirementCard(requirement, data.attendanceAvailable))
      .join("");
    renderState("requirements", "loaded");
  }

  // Rows whose position has fallen out of the plan are surfaced in their own card
  // rather than disappearing — they are still real commitments (§2.11).
  const otherCard = byId("other-assignments-card");
  const otherBody = byId("other-assignments-table")?.querySelector("tbody");
  if (otherBody) {
    otherBody.innerHTML = data.otherAssignments
      .map(
        (assignment) => `
        <tr class="volunteer-assignment-row"
            data-assignment-id="${assignment.id}"
            data-person-id="${assignment.personId}"
            data-status="${escapeHtml(assignment.status)}">
          <td>${escapeHtml(assignment.positionName ?? "")}</td>
          <td>${escapeHtml(assignment.displayName ?? "")}</td>
          <td class="text-center">${statusBadge(assignment.status)}</td>
          <td class="text-center">${assignmentMenu(assignment)}</td>
        </tr>`,
      )
      .join("");
  }
  show(otherCard, data.otherAssignments.length > 0);
}

function renderSwaps(swaps: VolunteerSwap[]): void {
  const body = byId("volunteerSwapsTable")?.querySelector("tbody");
  if (!body) {
    return;
  }

  if (swaps.length === 0) {
    body.innerHTML = "";
    renderState("swaps", "empty");

    return;
  }

  body.innerHTML = swaps
    .map(
      (swap) => `
      <tr data-swap-id="${swap.id}">
        <td>${escapeHtml(swap.positionName ?? "")}</td>
        <td>${escapeHtml(swap.proposedByName ?? "")}</td>
        <td>${escapeHtml(swap.proposedPersonName ?? "")}</td>
        <td>${escapeHtml(swap.proposedDate ?? "")}</td>
        <td class="text-center">
          ${actionMenu([
            {
              type: "button",
              icon: "fa-solid fa-check",
              label: i18next.t("Approve"),
              data: { action: "swap-approve", "swap-id": swap.id, "person-name": swap.proposedPersonName ?? "" },
            },
            {
              type: "button",
              icon: "fa-solid fa-xmark",
              label: i18next.t("Reject"),
              danger: true,
              data: { action: "swap-reject", "swap-id": swap.id, "person-name": swap.proposedPersonName ?? "" },
            },
          ])}
        </td>
      </tr>`,
    )
    .join("");

  renderState("swaps", "loaded");
}

// ─── Loading ─────────────────────────────────────────────────────────────────

function loadStaffing(): Promise<void> {
  renderState("requirements", "loading");

  return getStaffing(occurrenceId)
    .then((data) => {
      staffing = data;
      renderStaffing(data);
    })
    .catch((error: unknown) => {
      staffing = null;
      renderState("requirements", "error", errorMessage(error, i18next.t("The staffing plan could not be loaded")));
    });
}

function loadSwaps(): Promise<void> {
  renderState("swaps", "loading");

  return listSwaps(occurrenceId)
    .then((data) => {
      renderSwaps(data.swaps);
    })
    .catch((error: unknown) => {
      renderState("swaps", "error", errorMessage(error, i18next.t("The substitution requests could not be loaded")));
    });
}

// ─── The eligible picker ─────────────────────────────────────────────────────

function destroyAssignSelect(): void {
  if (!assignSelect) {
    return;
  }
  try {
    assignSelect.destroy();
  } catch (_e) {
    // The modal DOM may already be gone; nothing left to tear down.
  }
  assignSelect = null;
}

function describeCandidate(person: VolunteerEligiblePerson): string {
  const parts: string[] = [];
  if (person.lastServedDate === null) {
    parts.push(i18next.t("has not served yet"));
  } else {
    parts.push(i18next.t("last served {{date}}", { date: person.lastServedDate }));
  }
  if (person.conflictPositionName) {
    parts.push(i18next.t("already serving as {{position}}", { position: person.conflictPositionName }));
  }

  return `${person.displayName} — ${parts.join(", ")}`;
}

function renderCandidates(select: HTMLSelectElement, people: VolunteerEligiblePerson[]): void {
  const inPool = people.filter((person) => person.inPool);
  const outside = people.filter((person) => !person.inPool);

  const option = (person: VolunteerEligiblePerson): string =>
    `<option value="${person.personId}">${escapeHtml(describeCandidate(person))}</option>`;

  // Order within each group is the server's — least recently served first (§2.17).
  // The out-of-pool group is a divider, never a filter: assigning from it is supported
  // behind one confirm (I3), and hiding it would make that override unreachable.
  select.innerHTML = [
    `<option value="">${escapeHtml(i18next.t("Choose a volunteer"))}</option>`,
    inPool.length === 0
      ? ""
      : `<optgroup label="${escapeHtml(i18next.t("In the volunteer pool"))}">${inPool.map(option).join("")}</optgroup>`,
    outside.length === 0
      ? ""
      : `<optgroup label="${escapeHtml(i18next.t("Not in the pool"))}">${outside.map(option).join("")}</optgroup>`,
  ].join("");
}

function selectedCandidate(): VolunteerEligiblePerson | null {
  const value = byId<HTMLSelectElement>("assign-person-select")?.value ?? "";
  if (value === "") {
    return null;
  }

  return eligible.find((person) => person.personId === Number(value)) ?? null;
}

/**
 * I7/D16 and I3, both as non-blocking cautions.
 *
 * The double-duty warning names the position the person already holds on this
 * occurrence and lets the coordinator proceed — multi-position on one occurrence is a
 * supported arrangement, so this is a Tabler `alert-warning`, not a confirm gate and not
 * a server error. The out-of-pool notice explains what the Assign button is about to
 * override.
 */
function refreshWarnings(): void {
  const person = selectedCandidate();

  const conflict = byId("assign-conflict-warning");
  const conflictText = conflict?.querySelector(".assign-conflict-text");
  if (conflictText) {
    conflictText.textContent =
      person?.conflictPositionName == null
        ? ""
        : i18next.t("{{name}} is already serving as {{position}} on this occurrence. You can still assign them.", {
            name: person.displayName,
            position: person.conflictPositionName,
          });
  }
  show(conflict, person?.conflictPositionName != null);

  const outside = byId("assign-outside-pool-warning");
  const outsideText = outside?.querySelector(".assign-outside-pool-text");
  if (outsideText) {
    outsideText.textContent =
      person && !person.inPool
        ? i18next.t(
            "{{name}} is qualified but is not in this ministry's volunteer pool. Assigning them adds them to this occurrence only.",
            {
              name: person.displayName,
            },
          )
        : "";
  }
  show(outside, person !== null && !person.inPool);
}

function openAssignModal(positionId: number, positionName: string): void {
  assigningPositionId = positionId;
  eligible = [];

  const label = byId("assign-position-label");
  if (label) {
    label.textContent = i18next.t("Filling {{position}}", { position: positionName });
  }
  show(byId("assign-form-error"), false);
  show(byId("assign-conflict-warning"), false);
  show(byId("assign-outside-pool-warning"), false);
  show(byId("assign-empty"), false);

  assignModalHidePending = false;
  modal("volunteer-assign-modal")?.show();

  listEligiblePeople(occurrenceId, positionId)
    .then((data) => {
      eligible = data.people;
      const select = byId<HTMLSelectElement>("assign-person-select");
      if (!select) {
        return;
      }

      renderCandidates(select, data.people);
      show(byId("assign-empty"), data.people.length === 0);

      // Re-create TomSelect over the fresh options. The same shared class every other
      // picker uses (window.TomSelect, set by webpack/skin-core.js), with #9819's two
      // settings: a body-mounted dropdown so a modal cannot clip it, and no option cap.
      destroyAssignSelect();
      if (window.TomSelect && data.people.length > 0) {
        assignSelect = new window.TomSelect(select, {
          dropdownParent: "body",
          maxOptions: null,
          onChange: refreshWarnings,
        });
      }
    })
    .catch((error: unknown) => {
      showAssignError(errorMessage(error, i18next.t("The list of eligible volunteers could not be loaded")));
    });
}

/**
 * Dismiss the assign modal, safely.
 *
 * Never call `Modal.hide()` during the modal's own fade (see `assignModalShown`): it is
 * swallowed and the dialog stays open with no error anywhere. If the show transition has
 * not finished yet, the dismissal is queued and fires from the `shown.bs.modal` handler.
 */
function hideAssignModal(): void {
  if (!assignModalShown) {
    assignModalHidePending = true;

    return;
  }

  modal("volunteer-assign-modal")?.hide();
}

function showAssignError(message: string): void {
  const box = byId("assign-form-error");
  const text = box?.querySelector(".volunteer-error-text");
  if (text) {
    text.textContent = message;
  }
  show(box, true);
  notifyError(message);
}

function saveAssignment(): void {
  const person = selectedCandidate();
  if (!person) {
    showAssignError(i18next.t("Choose a volunteer first"));

    return;
  }

  createAssignment(occurrenceId, {
    positionId: assigningPositionId,
    personId: person.personId,
    // The picker already told the coordinator this person is outside the pool, and they
    // chose them anyway — so the override is carried rather than bouncing them off a 409
    // they cannot act on from here (I3).
    allowOutsidePool: !person.inPool,
  })
    .then(() => {
      hideAssignModal();
      notifySuccess(i18next.t("{{name}} assigned", { name: person.displayName }));

      return loadStaffing();
    })
    .catch((error: unknown) => {
      showAssignError(errorMessage(error, i18next.t("That volunteer could not be assigned")));
    });
}

// ─── Row actions ─────────────────────────────────────────────────────────────

function handleAssignmentAction(action: string, assignmentId: number, personName: string): void {
  if (action === "cancel") {
    confirmAction(
      i18next.t("Cancel this assignment"),
      i18next.t("Take {{name}} off this position? They will be told, and the slot reopens.", { name: personName }),
      () => {
        deleteAssignment(assignmentId)
          .then(() => {
            notifySuccess(i18next.t("Assignment cancelled"));

            return loadStaffing();
          })
          .catch((error: unknown) => {
            notifyError(errorMessage(error, i18next.t("The assignment could not be cancelled")));
          });
      },
    );

    return;
  }

  if (action === "accepted" || action === "declined") {
    const message =
      action === "accepted"
        ? i18next.t("Record that {{name}} said yes? The response is saved as coming from you, not from them.", {
            name: personName,
          })
        : i18next.t("Record that {{name}} said no? The response is saved as coming from you, and the slot reopens.", {
            name: personName,
          });

    confirmAction(
      action === "accepted" ? i18next.t("Record an acceptance") : i18next.t("Record a decline"),
      message,
      () => {
        setAssignmentStatus(assignmentId, action)
          .then(() => {
            notifySuccess(i18next.t("Response recorded"));

            return loadStaffing();
          })
          .catch((error: unknown) => {
            notifyError(errorMessage(error, i18next.t("That response could not be recorded")));
          });
      },
      action === "declined",
    );

    return;
  }

  if (action === "notify") {
    // force: the coordinator is explicitly asking for it to go again, which is the one
    // case the dedupe key is meant to be overridden (§2.14).
    notifyAssignment(assignmentId, true)
      .then(() => {
        notifySuccess(i18next.t("The assignment message will be sent again"));
      })
      .catch((error: unknown) => {
        notifyError(errorMessage(error, i18next.t("The message could not be queued")));
      });
  }
}

function handleSwapAction(action: string, swapId: number, personName: string): void {
  const approve = action === "swap-approve";

  confirmAction(
    approve ? i18next.t("Approve this substitute") : i18next.t("Reject this substitute"),
    approve
      ? i18next.t("Put {{name}} on instead? The original volunteer keeps their history and is told.", {
          name: personName,
        })
      : i18next.t("Turn down {{name}}? The original volunteer stays on and is told.", { name: personName }),
    () => {
      const call = approve ? approveSwap(swapId) : rejectSwap(swapId);
      call
        .then(() => {
          notifySuccess(approve ? i18next.t("Substitute approved") : i18next.t("Substitute rejected"));

          return Promise.all([loadStaffing(), loadSwaps()]);
        })
        .then(() => undefined)
        .catch((error: unknown) => {
          notifyError(errorMessage(error, i18next.t("That substitution request could not be decided")));
        });
    },
    !approve,
  );
}

// ─── The staffing-needs editor (§2.10) ───────────────────────────────────────

/**
 * Open the needs editor, pre-filled from the EFFECTIVE requirements.
 *
 * "Effective" is the point: the rows shown are the schedule's plan with this occurrence's
 * overrides merged over it, resolved server-side by
 * `VolunteerScheduleService::getEffectiveRequirements()`. Saving turns every checked row
 * into an occurrence-level override — including the ones that arrived from the schedule,
 * because "this week, exactly this" is what the coordinator just said. "Use the
 * schedule's needs" throws the overrides away again.
 */
function openNeedsModal(): void {
  show(byId("needs-form-error"), false);
  show(byId("needs-loading"), true);
  show(byId("needs-form-reset"), false);

  const rows = byId("needs-form-rows");
  if (rows) {
    rows.innerHTML = "";
  }

  // Shown first, filled when the fetch lands: Bootstrap's 150 ms fade swallows a hide()
  // issued inside it, and an editor that pops open only after a round trip reads as a
  // dead button on a slow install.
  modal("volunteer-needs-modal")?.show();

  getOccurrenceRequirements(occurrenceId)
    .then((data) => {
      needsSchedulePositionIds = data.schedulePositionIds;
      show(byId("needs-loading"), false);
      show(byId("needs-form-reset"), data.overridden);

      const hint = byId("needs-form-hint");
      if (hint) {
        hint.textContent = data.overridden
          ? i18next.t("This occurrence has its own staffing needs, set apart from its schedule.")
          : i18next.t("These needs come from the schedule. Saving here changes this occurrence only.");
      }

      if (rows) {
        // Never check-by-default here: an occurrence with no plan at all is the state
        // the coordinator came to fix, and guessing on their behalf would hide it.
        renderStaffingNeeds(rows, data.positions, data.requirements, false);
      }
    })
    .catch((error: unknown) => {
      show(byId("needs-loading"), false);
      showNeedsError(errorMessage(error, i18next.t("The staffing needs could not be loaded")));
    });
}

function showNeedsError(message: string): void {
  const box = byId("needs-form-error");
  const text = box?.querySelector(".volunteer-error-text");
  if (text) {
    text.textContent = message;
  }
  show(box, true);
}

function saveNeeds(): void {
  const rows = byId("needs-form-rows");
  if (!rows) {
    return;
  }

  const invalid = validateStaffingNeeds(rows);
  if (invalid !== null) {
    showNeedsError(invalid);

    return;
  }

  show(byId("needs-form-error"), false);

  replaceOccurrenceRequirements(occurrenceId, needsPayload(rows))
    .then(() => {
      hideNeedsModal();
      notifySuccess(i18next.t("Staffing needs saved"));

      return loadStaffing();
    })
    .catch((error: unknown) => {
      showNeedsError(errorMessage(error, i18next.t("The staffing needs could not be saved")));
    });
}

/**
 * The checked rows, plus an explicit Min 0 / Max 0 for every schedule-provided position
 * the coordinator just unchecked.
 *
 * Without the second half, unchecking "Helper" would delete the occurrence's row for it
 * and the merge would hand the schedule's Helper requirement straight back — the box
 * would appear to do nothing.
 */
function needsPayload(rows: HTMLElement): VolunteerRequirementInput[] {
  const checked = readStaffingNeeds(rows);
  const checkedIds = new Set(checked.map((row) => row.positionId));

  const suppressed = needsSchedulePositionIds
    .filter((positionId) => !checkedIds.has(positionId))
    .map((positionId) => ({ positionId, minCount: 0, maxCount: 0 }));

  return [...checked, ...suppressed];
}

function resetNeeds(): void {
  confirmAction(
    i18next.t("Use the schedule's needs"),
    i18next.t("Drop this occurrence's own staffing needs and follow its schedule again?"),
    () => {
      clearOccurrenceRequirements(occurrenceId)
        .then(() => {
          hideNeedsModal();
          notifySuccess(i18next.t("This occurrence follows its schedule again"));

          return loadStaffing();
        })
        .catch((error: unknown) => {
          showNeedsError(errorMessage(error, i18next.t("The staffing needs could not be reset")));
        });
    },
  );
}

/**
 * Dismiss the needs modal, queueing the request if the fade is still running.
 *
 * Same trap as the assign modal: `Modal.hide()` returns early while `_isTransitioning`,
 * silently throwing the request away, and a local API answering in a few milliseconds
 * lands squarely inside that 150 ms window.
 */
function hideNeedsModal(): void {
  if (needsModalShown) {
    modal("volunteer-needs-modal")?.hide();

    return;
  }

  needsModalHidePending = true;
}

// ─── Wiring ──────────────────────────────────────────────────────────────────

function wire(): void {
  byId("requirements-refresh")?.addEventListener("click", () => {
    void loadStaffing();
  });

  byId("requirements-edit")?.addEventListener("click", openNeedsModal);
  byId("requirements-empty-edit")?.addEventListener("click", openNeedsModal);
  byId("needs-form-save")?.addEventListener("click", saveNeeds);
  byId("needs-form-reset")?.addEventListener("click", resetNeeds);

  byId("volunteer-needs-modal")?.addEventListener("shown.bs.modal", () => {
    needsModalShown = true;
    if (needsModalHidePending) {
      needsModalHidePending = false;
      modal("volunteer-needs-modal")?.hide();
    }
  });
  byId("volunteer-needs-modal")?.addEventListener("hidden.bs.modal", () => {
    needsModalShown = false;
    needsModalHidePending = false;
  });

  // Retry genuinely re-runs the load that failed, per pane (§5.8).
  byId("requirements-error")
    ?.querySelector(".volunteer-retry")
    ?.addEventListener("click", () => {
      void loadStaffing();
    });
  byId("swaps-error")
    ?.querySelector(".volunteer-retry")
    ?.addEventListener("click", () => {
      void loadSwaps();
    });

  byId("assign-save")?.addEventListener("click", saveAssignment);

  // The warnings are refreshed from TWO signals, registered once here rather than per
  // modal open. TomSelect's `onChange` covers its own control; a native `change` on the
  // underlying `<select>` covers every other way the value can move — assistive
  // technology, a browser's autofill, and a keyboard on a build where TomSelect failed
  // to load and the plain control is what the user actually sees.
  byId("assign-person-select")?.addEventListener("change", refreshWarnings);

  // Bootstrap moves focus to the dialog partway through its 150 ms fade, so anything
  // focused earlier is stolen; wait for shown.bs.modal. Equally, a modal cannot be
  // dismissed DURING that fade — Modal.hide() returns early while _isTransitioning —
  // which is why nothing here ever hides a modal it has only just shown.
  byId("volunteer-assign-modal")?.addEventListener("shown.bs.modal", () => {
    assignModalShown = true;

    // A dismissal that arrived during the fade was queued rather than swallowed; now
    // that the dialog has genuinely finished showing, honour it.
    if (assignModalHidePending) {
      assignModalHidePending = false;
      modal("volunteer-assign-modal")?.hide();

      return;
    }

    assignSelect?.focus();
  });
  byId("volunteer-assign-modal")?.addEventListener("hidden.bs.modal", () => {
    assignModalShown = false;
    assignModalHidePending = false;
    destroyAssignSelect();
    assigningPositionId = 0;
  });

  // Delegated: the cards are re-rendered on every load, so per-row listeners would go
  // stale after the first refresh.
  document.addEventListener("click", (event) => {
    const target = (event.target as HTMLElement | null)?.closest<HTMLElement>(
      ".volunteer-assign-btn, .dropdown-item[data-action]",
    );
    if (!target) {
      return;
    }

    if (target.classList.contains("volunteer-assign-btn")) {
      openAssignModal(Number(target.dataset.positionId), target.dataset.positionName ?? "");

      return;
    }

    const action = target.dataset.action ?? "";
    const personName = target.dataset.personName ?? "";

    if (action.startsWith("swap-")) {
      handleSwapAction(action, Number(target.dataset.swapId), personName);

      return;
    }

    handleAssignmentAction(action, Number(target.dataset.assignmentId), personName);
  });
}

function init(): void {
  const config = (window.CRM?.volunteerOccurrence ?? {
    occurrenceId: 0,
    ministryId: 0,
    eventId: 0,
  }) as OccurrenceConfig;
  occurrenceId = config.occurrenceId;

  if (occurrenceId === 0) {
    return;
  }

  wire();
  void loadStaffing();
  void loadSwaps();
}

// A module-scope i18next.t() returns undefined on a non-en_US locale until the
// catalogue has loaded (upstream #9609), so initialisation is deferred behind
// onLocalesReady — every string above is built at render time, inside init()'s reach.
document.addEventListener("DOMContentLoaded", () => {
  if (window.CRM?.onLocalesReady) {
    window.CRM.onLocalesReady(init);
  } else {
    init();
  }
});
