/**
 * S5 — My volunteer schedule (#9712, design §5.6, §5.8, §5.9).
 *
 * A vertical list of cards, soonest first: when, what, a status badge, and the three
 * things a volunteer can do about it — "I'll be there", "I can't", "Find a sub" —
 * plus "Take it back" while a substitution request of theirs is still pending.
 *
 * Three deliberate choices:
 *
 * 1. **Every action is idempotent on the server**, so a double tap on a phone is
 *    harmless (§5.6). The buttons are not disabled defensively on click; they are
 *    re-rendered from the response, which is the state that actually exists.
 * 2. **The substitute picker is fetched, not searched.**
 *    `GET /me/assignments/{id}/substitutes` has already removed the volunteer and
 *    anyone on that slot, so the list cannot offer a name `propose-substitute` will
 *    reject. `initPersonSelect()` (#9819) is the shared "find any person by typing"
 *    widget and answers a different question — here the candidate set is small,
 *    bounded and pre-filtered. It is rendered into the **same shared
 *    `window.TomSelect`** everything else uses, with #9819's two hard-won settings
 *    (`dropdownParent: "body"` so the modal cannot clip the dropdown, `maxOptions:
 *    null` so a long list does not silently lose its tail).
 * 3. **Nothing here knows a person id.** The API derives the volunteer from the
 *    session; the only id this file ever sends is the *substitute's*.
 *
 * Every user-visible string is `i18next.t()` in this file: the extractor scans only
 * `webpack/**` and `src/skin/js/**`, so the same call inside the `.php` view would
 * never be translated (§5.10, F31).
 */

import {
  errorMessage,
  listMyAssignments,
  listMySubstituteCandidates,
  proposeMySubstitute,
  respondToMyAssignment,
  type VolunteerEligiblePerson,
  type VolunteerMyAssignment,
  withdrawMySwap,
} from "./api";
import {
  byId,
  confirmAction,
  escapeHtml,
  formatWhat,
  formatWhen,
  notifyError,
  notifySuccess,
  renderState,
} from "./member-ui";

let assignments: VolunteerMyAssignment[] = [];
let includePast = false;
/** The assignment the substitute modal is currently filling; 0 when it is closed. */
let substitutingAssignmentId = 0;
let substituteSelect: TomSelectInstance | null = null;

/**
 * Bootstrap 5's `Modal.hide()` returns early while `_isTransitioning` is true — the
 * request is accepted and thrown away, silently, leaving the dialog open. A local API
 * answering in a few milliseconds lands squarely inside that 150 ms fade, so "hide on
 * success" cannot simply call `hide()`. These two flags queue the dismissal for
 * `shown.bs.modal` instead (learned in #9707, hit again in #9709).
 */
let substituteModalShown = false;
let substituteModalHidePending = false;

function modal(): { show(): void; hide(): void } | null {
  const el = byId("substitute-modal");
  if (!el || !window.bootstrap?.Modal) {
    return null;
  }

  return window.bootstrap.Modal.getOrCreateInstance(el);
}

/** The badge §5.6 puts on the right of every card, in the volunteer's own words. */
function statusBadge(assignment: VolunteerMyAssignment): { label: string; className: string } {
  if (assignment.pendingSwapId !== null) {
    return {
      label: assignment.pendingSwapPersonName
        ? i18next.t("Substitute asked: {{name}}", { name: assignment.pendingSwapPersonName })
        : i18next.t("Substitute asked"),
      className: "bg-info",
    };
  }

  switch (assignment.status) {
    case "accepted":
      return { label: i18next.t("Going"), className: "bg-success" };
    case "declined":
      return { label: i18next.t("Declined"), className: "bg-secondary" };
    case "cancelled":
      return { label: i18next.t("No longer needed"), className: "bg-secondary" };
    case "substituted":
      return { label: i18next.t("Someone else is covering"), className: "bg-secondary" };
    case "completed":
      return { label: i18next.t("Thank you"), className: "bg-primary" };
    default:
      return { label: i18next.t("Needs an answer"), className: "bg-warning" };
  }
}

function cardHtml(assignment: VolunteerMyAssignment): string {
  const badge = statusBadge(assignment);
  const waiting = assignment.pendingSwapId !== null;
  const buttons: string[] = [];

  if (assignment.canRespond && assignment.status !== "accepted") {
    buttons.push(
      `<button type="button" class="btn btn-success volunteer-touch-target volunteer-accept">` +
        `<i class="fa-solid fa-check me-1"></i>${escapeHtml(i18next.t("I'll be there"))}</button>`,
    );
  }
  if (assignment.canRespond) {
    buttons.push(
      `<button type="button" class="btn btn-outline-danger volunteer-touch-target volunteer-decline">` +
        `<i class="fa-solid fa-xmark me-1"></i>${escapeHtml(i18next.t("I can't"))}</button>`,
    );
  }
  if (waiting) {
    buttons.push(
      `<button type="button" class="btn btn-outline-secondary volunteer-touch-target volunteer-withdraw" ` +
        `data-swap-id="${assignment.pendingSwapId}">` +
        `<i class="fa-solid fa-rotate-left me-1"></i>${escapeHtml(i18next.t("Take that back"))}</button>`,
    );
  } else if (assignment.canProposeSubstitute) {
    buttons.push(
      `<button type="button" class="btn btn-outline-primary volunteer-touch-target volunteer-find-sub">` +
        `<i class="fa-solid fa-right-left me-1"></i>${escapeHtml(i18next.t("Find a sub"))}</button>`,
    );
  }

  const waitingNote = waiting
    ? `<div class="mt-2 small text-body-secondary"><i class="fa-solid fa-hourglass-half me-1"></i>` +
      `${escapeHtml(i18next.t("Waiting for your coordinator to confirm."))}</div>`
    : "";

  return `
    <div class="card mb-3 volunteer-assignment-card" data-assignment-id="${assignment.id}">
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-start">
          <div>
            <div class="volunteer-card-when fw-bold">
              <i class="fa-solid fa-clock me-1"></i>${escapeHtml(formatWhen(assignment.start, assignment.occurrenceDate))}
            </div>
            <div class="volunteer-card-what text-body-secondary">
              ${escapeHtml(formatWhat(assignment.ministryName, assignment.teamName, assignment.positionName))}
            </div>
          </div>
          <span class="badge volunteer-card-status ${badge.className}">${escapeHtml(badge.label)}</span>
        </div>
        ${waitingNote}
        ${buttons.length > 0 ? `<div class="mt-3 d-grid gap-2 d-sm-flex volunteer-card-actions">${buttons.join("")}</div>` : ""}
      </div>
    </div>`;
}

function render(): void {
  const content = byId("assignments-content");
  if (!content) {
    return;
  }

  if (assignments.length === 0) {
    renderState("assignments", "empty");
    content.innerHTML = "";

    return;
  }

  content.innerHTML = assignments.map(cardHtml).join("");
  renderState("assignments", "loaded");
}

async function load(): Promise<void> {
  renderState("assignments", "loading");
  try {
    const response = await listMyAssignments(includePast);
    assignments = response.assignments;
    render();
  } catch (error) {
    renderState("assignments", "error", errorMessage(error, i18next.t("Your schedule could not be loaded.")));
  }
}

/** Replace one card's row in place, so the rest of the list does not flicker. */
function replaceAssignment(updated: VolunteerMyAssignment): void {
  assignments = assignments.map((row) => (row.id === updated.id ? updated : row));
  render();
}

async function respond(assignmentId: number, answer: "accepted" | "declined", comment = ""): Promise<void> {
  try {
    const response = await respondToMyAssignment(assignmentId, answer, comment);
    replaceAssignment(response.assignment);
    notifySuccess(
      answer === "accepted" ? i18next.t("Thanks — you are on the list.") : i18next.t("Thanks for letting us know."),
    );
  } catch (error) {
    notifyError(errorMessage(error, i18next.t("That could not be saved.")));
    // The server refused, so the card on screen may be stale — re-read rather than
    // leaving the volunteer looking at a state that does not exist.
    await load();
  }
}

function decline(assignmentId: number): void {
  // §5.6: an optional reason, in a prompt, never a required form.
  window.bootbox?.prompt({
    title: i18next.t("Let them know why (optional)"),
    inputType: "text",
    buttons: {
      confirm: { label: i18next.t("Send"), className: "btn-primary" },
      cancel: { label: i18next.t("Cancel"), className: "btn-default" },
    },
    callback: (value: string | null) => {
      if (value === null) {
        return;
      }
      void respond(assignmentId, "declined", value);
    },
  });
}

function hideSubstituteModal(): void {
  if (!substituteModalShown) {
    substituteModalHidePending = true;

    return;
  }
  modal()?.hide();
}

async function openSubstituteModal(assignmentId: number): Promise<void> {
  substitutingAssignmentId = assignmentId;
  const hint = byId("substitute-hint");
  const comment = byId<HTMLTextAreaElement>("substitute-comment");
  if (comment) {
    comment.value = "";
  }
  if (hint) {
    hint.textContent = i18next.t("Loading");
  }

  substituteSelect?.destroy();
  substituteSelect = null;
  const select = byId<HTMLSelectElement>("substitute-select");
  if (select) {
    select.innerHTML = "";
  }

  modal()?.show();

  try {
    const response = await listMySubstituteCandidates(assignmentId);
    fillSubstituteSelect(response.people);
    if (hint) {
      hint.textContent =
        response.people.length === 0
          ? i18next.t("Nobody else is trained for this yet — tell your coordinator instead.")
          : i18next.t("Only people trained for this are listed.");
    }
  } catch (error) {
    hideSubstituteModal();
    notifyError(errorMessage(error, i18next.t("That list could not be loaded.")));
  }
}

function fillSubstituteSelect(people: VolunteerEligiblePerson[]): void {
  const select = byId<HTMLSelectElement>("substitute-select");
  if (!select) {
    return;
  }

  select.innerHTML =
    `<option value=""></option>` +
    people.map((person) => `<option value="${person.personId}">${escapeHtml(person.displayName)}</option>`).join("");

  if (!window.TomSelect) {
    return;
  }

  substituteSelect = new window.TomSelect(select, {
    placeholder: i18next.t("Search by name"),
    // #9819: without these two a modal clips the dropdown and a long list silently
    // loses its tail at 50 options.
    dropdownParent: "body",
    maxOptions: null,
    allowEmptyOption: true,
  });
}

async function saveSubstitute(): Promise<void> {
  // The native `<select>` is the source of truth whether or not TomSelect wrapped it:
  // TomSelect writes the chosen value back to the element it wrapped, and on a build
  // where the global class is missing the element is still a plain select.
  const select = byId<HTMLSelectElement>("substitute-select");
  const personId = Number(select?.value || substituteSelect?.getValue?.() || 0);
  if (!personId) {
    notifyError(i18next.t("Choose who is covering for you."));

    return;
  }

  const comment = byId<HTMLTextAreaElement>("substitute-comment")?.value ?? "";

  try {
    await proposeMySubstitute(substitutingAssignmentId, personId, comment);
    hideSubstituteModal();
    notifySuccess(i18next.t("Asked — your coordinator will confirm it."));
    await load();
  } catch (error) {
    notifyError(errorMessage(error, i18next.t("That could not be saved.")));
  }
}

function withdraw(swapId: number): void {
  confirmAction(
    i18next.t("Take that back"),
    i18next.t("You will be back on the list for this one."),
    async () => {
      try {
        await withdrawMySwap(swapId);
        notifySuccess(i18next.t("Done — you are back on the list."));
        await load();
      } catch (error) {
        notifyError(errorMessage(error, i18next.t("That could not be saved.")));
      }
    },
    false,
  );
}

function assignmentIdOf(el: Element): number {
  return Number((el.closest(".volunteer-assignment-card") as HTMLElement | null)?.dataset.assignmentId ?? 0);
}

function wire(): void {
  byId("assignments-retry")?.addEventListener("click", () => {
    void load();
  });

  byId<HTMLInputElement>("show-past")?.addEventListener("change", (event) => {
    includePast = (event.target as HTMLInputElement).checked;
    void load();
  });

  // One delegated listener: the cards are re-rendered on every change, so per-button
  // listeners would have to be re-bound every time.
  byId("assignments-content")?.addEventListener("click", (event) => {
    const target = (event.target as HTMLElement).closest("button");
    if (!target) {
      return;
    }

    if (target.classList.contains("volunteer-accept")) {
      void respond(assignmentIdOf(target), "accepted");
    } else if (target.classList.contains("volunteer-decline")) {
      decline(assignmentIdOf(target));
    } else if (target.classList.contains("volunteer-find-sub")) {
      void openSubstituteModal(assignmentIdOf(target));
    } else if (target.classList.contains("volunteer-withdraw")) {
      withdraw(Number(target.dataset.swapId ?? 0));
    }
  });

  byId("substitute-save")?.addEventListener("click", () => {
    void saveSubstitute();
  });

  const modalEl = byId("substitute-modal");
  modalEl?.addEventListener("shown.bs.modal", () => {
    substituteModalShown = true;
    if (substituteModalHidePending) {
      substituteModalHidePending = false;
      modal()?.hide();
    }
  });
  modalEl?.addEventListener("hidden.bs.modal", () => {
    substituteModalShown = false;
    substituteModalHidePending = false;
    substitutingAssignmentId = 0;
  });
}

function init(): void {
  if (!byId("volunteer-my-schedule")) {
    return;
  }
  wire();
  void load();
}

// Module-scope i18next lookups return undefined on a non-en_US locale until the
// catalogues have loaded (upstream #9609), and every string on this page is one.
if (window.CRM?.onLocalesReady) {
  window.CRM.onLocalesReady(init);
} else {
  document.addEventListener("DOMContentLoaded", init);
}
