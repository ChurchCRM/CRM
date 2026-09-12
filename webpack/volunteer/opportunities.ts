/**
 * S6 — Open opportunities (#9712, design §5.6, §5.8, §5.9).
 *
 * The same card shape as S5 over `GET /me/opportunities`, each with a single
 * **Sign up** button.
 *
 * Two things are worth saying out loud:
 *
 * 1. **The list is not a filter this file applies.** The server decides what a
 *    volunteer is eligible for — qualification, pool membership, capacity, cancelled
 *    and past occurrences — and `POST /me/signup` re-validates every one of those at
 *    signup time regardless of what was on screen (§3.3.3, D5). So when a sign-up
 *    comes back `403` or `409`, the honest thing to do is show the server's own
 *    sentence and reload the list: somebody else took the slot between the page load
 *    and the tap, and that is normal, not an error state.
 * 2. **An empty list is a first-class state, not an error** (§5.6): the Tabler
 *    `.empty` block, not a spinner that never resolves.
 *
 * The D16/I7 warning ("you already serve on this one as X") is rendered, never used
 * to hide the row — the product decision is explicitly "allowed, with a warning, no
 * server-side block".
 *
 * Every user-visible string is `i18next.t()` in this file (§5.10, F31).
 */

import { errorMessage, listMyOpportunities, signUpForOpportunity, type VolunteerMyOpportunity } from "./api";
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

let opportunities: VolunteerMyOpportunity[] = [];

function cardHtml(opportunity: VolunteerMyOpportunity): string {
  const warning = opportunity.alreadyServing
    ? `<div class="alert alert-warning mt-2 mb-0 py-2 volunteer-already-serving" role="alert">
         <i class="fa-solid fa-triangle-exclamation me-1"></i>${escapeHtml(
           i18next.t("You are already helping that day as {{what}}.", {
             what: opportunity.alreadyServingPositionNames.join(", "),
           }),
         )}
       </div>`
    : "";

  const needed =
    opportunity.openCount > 1
      ? `<span class="badge bg-secondary volunteer-card-needed">${escapeHtml(
          i18next.t("{{count}} still needed", { count: opportunity.openCount }),
        )}</span>`
      : `<span class="badge bg-secondary volunteer-card-needed">${escapeHtml(i18next.t("One more needed"))}</span>`;

  return `
    <div class="card mb-3 volunteer-opportunity-card"
         data-occurrence-id="${opportunity.occurrenceId}" data-position-id="${opportunity.positionId}">
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-start">
          <div>
            <div class="volunteer-card-when fw-bold">
              <i class="fa-solid fa-clock me-1"></i>${escapeHtml(formatWhen(opportunity.start, opportunity.occurrenceDate))}
            </div>
            <div class="volunteer-card-what text-body-secondary">
              ${escapeHtml(formatWhat(opportunity.ministryName, opportunity.teamName, opportunity.positionName))}
            </div>
          </div>
          ${needed}
        </div>
        ${warning}
        <div class="mt-3 d-grid gap-2 d-sm-flex volunteer-card-actions">
          <button type="button" class="btn btn-primary volunteer-touch-target volunteer-signup">
            <i class="fa-solid fa-hand-holding-heart me-1"></i>${escapeHtml(i18next.t("Sign up"))}
          </button>
        </div>
      </div>
    </div>`;
}

function render(): void {
  const content = byId("opportunities-content");
  if (!content) {
    return;
  }

  if (opportunities.length === 0) {
    content.innerHTML = "";
    renderState("opportunities", "empty");

    return;
  }

  content.innerHTML = opportunities.map(cardHtml).join("");
  renderState("opportunities", "loaded");
}

async function load(): Promise<void> {
  renderState("opportunities", "loading");
  try {
    const response = await listMyOpportunities();
    opportunities = response.opportunities;
    render();
  } catch (error) {
    renderState("opportunities", "error", errorMessage(error, i18next.t("Open opportunities could not be loaded.")));
  }
}

async function signUp(occurrenceId: number, positionId: number): Promise<void> {
  try {
    await signUpForOpportunity(occurrenceId, positionId);
    notifySuccess(i18next.t("You are on the list — thank you."));
  } catch (error) {
    // The server re-validated and said no. Its sentence is better than any this file
    // could invent ("That position is already fully staffed", "That person is not
    // qualified for this position"), so show it verbatim.
    notifyError(errorMessage(error, i18next.t("You could not be signed up for that.")));
  }

  // Either way the list is now stale: a success removes the row, a 409 means someone
  // else's row is there.
  await load();
}

function wire(): void {
  byId("opportunities-retry")?.addEventListener("click", () => {
    void load();
  });

  byId("opportunities-content")?.addEventListener("click", (event) => {
    const button = (event.target as HTMLElement).closest(".volunteer-signup");
    if (!button) {
      return;
    }

    const card = button.closest(".volunteer-opportunity-card") as HTMLElement | null;
    if (!card) {
      return;
    }

    const occurrenceId = Number(card.dataset.occurrenceId ?? 0);
    const positionId = Number(card.dataset.positionId ?? 0);
    const opportunity = opportunities.find((row) => row.occurrenceId === occurrenceId && row.positionId === positionId);

    // Signing up is a commitment, so the D16 double-duty case asks first — the one
    // place §5.6's warning becomes a question rather than a note.
    if (opportunity?.alreadyServing) {
      confirmAction(
        i18next.t("You are already helping that day"),
        i18next.t("You are already helping that day as {{what}}. Sign up for this as well?", {
          what: opportunity.alreadyServingPositionNames.join(", "),
        }),
        () => {
          void signUp(occurrenceId, positionId);
        },
        false,
      );

      return;
    }

    void signUp(occurrenceId, positionId);
  });
}

function init(): void {
  if (!byId("volunteer-opportunities")) {
    return;
  }
  wire();
  void load();
}

if (window.CRM?.onLocalesReady) {
  window.CRM.onLocalesReady(init);
} else {
  document.addEventListener("DOMContentLoaded", init);
}
