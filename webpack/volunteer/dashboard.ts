/**
 * S1 — the coordinator dashboard (#9711, design §5.2).
 *
 * "What needs my attention", in the order §5.2 fixes: gaps that need filling,
 * assignments still awaiting a reply, proposed substitutions, upcoming occurrences, and
 * the caller's own ministries and teams.
 *
 * **One fetch, five panels.** §5.2 is explicit: "The dashboard makes exactly one API
 * call and renders all five panels from it. Do not fan out to five endpoints." So there
 * is one `getDashboard()` and one state machine — unlike S4, where two genuinely
 * independent reads each need their own, here a single failure means the whole page has
 * nothing to show and every panel says so with its own retry-able error block.
 *
 * **No Volunteer-specific UI framework** (§5.7): row menus go through
 * `window.CRM.buildActionMenu()`, which owns the scaffold, the `data-bs-display="static"`
 * that stops a menu being clipped inside `.table-responsive`, and every bit of escaping;
 * the upcoming table is a DataTable through the canonical `window.CRM.plugin.dataTable`
 * merge idiom; confirms are `bootbox`; toasts are `window.CRM.notify` with `"danger"`,
 * never `"error"` — `"error"` renders blue (U5/E-7).
 *
 * Every user-visible string is `i18next.t()` in this file: the extractor scans only
 * `webpack/**` and `src/skin/js/**`, so the same call inside the `.php` view would never
 * be translated (§5.10, F31). Module-scope translation is deferred behind
 * `window.CRM.onLocalesReady` or it returns undefined on a non-`en_US` locale (#9609).
 */

import {
  approveSwap,
  errorMessage,
  getDashboard,
  notifyError,
  notifySuccess,
  rejectSwap,
  type VolunteerDashboard,
  type VolunteerDashboardGap,
  type VolunteerDashboardOccurrence,
  type VolunteerDashboardPending,
  type VolunteerDashboardSwap,
} from "./api";

interface DashboardConfig {
  days: number;
  isAdmin: boolean;
  isManager: boolean;
}

type Pane = "gaps" | "pending" | "swaps" | "upcoming";

const PANES: Pane[] = ["gaps", "pending", "swaps", "upcoming"];

let days = 28;

function byId<T extends HTMLElement>(id: string): T | null {
  return document.getElementById(id) as T | null;
}

function show(el: Element | null, visible: boolean): void {
  el?.classList.toggle("d-none", !visible);
}

function escapeHtml(value: string): string {
  return window.CRM?.escapeHtml?.(value) ?? value;
}

function root(): string {
  return window.CRM?.root ?? "";
}

/**
 * The §5.8 state machine for one panel, in one place so no panel can forget a state.
 * The loading block is re-shown at the start of EVERY attempt and the error block's
 * Retry genuinely re-runs the load — see `wire()`.
 */
function renderState(pane: Pane, state: "loading" | "error" | "empty" | "loaded", message = ""): void {
  show(byId(`volunteer-${pane}-loading`), state === "loading");
  show(byId(`volunteer-${pane}-error`), state === "error");
  show(byId(`volunteer-${pane}-empty`), state === "empty");
  show(byId(`volunteer-${pane}-content`), state === "loaded");

  if (state === "error") {
    const text = byId(`volunteer-${pane}-error`)?.querySelector(".volunteer-error-text");
    if (text) {
      text.textContent = message;
    }
  }
}

/** A count badge that hides itself at zero rather than showing a bare "0". */
function renderBadge(id: string, count: number): void {
  const badge = byId(id);
  if (!badge) {
    return;
  }
  badge.textContent = String(count);
  show(badge, count > 0);
}

/**
 * Row actions through the shared builder (U1/CR2) — it owns the scaffold and every bit
 * of escaping, so labels and `data-*` values are passed raw here.
 */
function actionMenu(items: Array<CRMActionMenuItem | false>): string {
  const build = window.CRM?.buildActionMenu;

  return build ? build(items) : "";
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

/**
 * When an occurrence happens, in the viewer's own locale.
 *
 * The server sends `start` as `Y-m-d H:i:s` already resolved in the church's timezone —
 * for a LINKED occurrence that value came from the event row (D4) — so it is rendered as
 * a wall-clock reading and never re-zoned here. A row with no time at all (a standalone
 * schedule that carries only a date) falls back to the date.
 */
function whenLabel(start: string | null, occurrenceDate: string | null): string {
  const raw = start ?? occurrenceDate;
  if (!raw) {
    return "";
  }

  const parsed = new Date(raw.replace(" ", "T"));
  if (Number.isNaN(parsed.getTime())) {
    return raw;
  }

  return start === null ? parsed.toLocaleDateString() : parsed.toLocaleString();
}

/** Where this row belongs — "Coffee Bar · Bar Team", with the team dropped when absent. */
function ministryLabel(ministryName: string | null, teamName: string | null): string {
  return [ministryName, teamName].filter((part) => part !== null && part !== "").join(" · ");
}

// ─── Panel 1: gaps ───────────────────────────────────────────────────────────

/**
 * The primary card. Soonest deadline first — the server already ordered it that way, and
 * re-sorting here would be a second ordering to keep in step.
 *
 * The primary action is **Fill**, which is a link straight into S4 anchored on the
 * occurrence. `data-occurrence-id` / `data-position-id` are on the anchor so the page
 * says, in the DOM, exactly which requirement each row is about.
 */
function renderGaps(gaps: VolunteerDashboardGap[]): void {
  renderBadge(
    "volunteer-gap-badge",
    gaps.reduce((total, gap) => total + gap.gapCount, 0),
  );

  const list = byId("volunteer-gaps-list");
  if (!list) {
    return;
  }

  if (gaps.length === 0) {
    list.innerHTML = "";
    renderState("gaps", "empty");

    return;
  }

  list.innerHTML = gaps
    .map((gap) => {
      const where = ministryLabel(gap.ministryName, gap.teamName);

      return `
        <a class="list-group-item list-group-item-action volunteer-gap-link"
           href="${root()}/volunteer/occurrences/${gap.occurrenceId}"
           data-occurrence-id="${gap.occurrenceId}"
           data-position-id="${gap.positionId}">
          <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div>
              <span class="fw-bold">${escapeHtml(gap.positionName ?? "")}</span>
              <div class="text-body-secondary small">
                ${escapeHtml(whenLabel(gap.start, gap.occurrenceDate))}${where === "" ? "" : ` · ${escapeHtml(where)}`}
              </div>
            </div>
            <div class="text-nowrap">
              <span class="badge bg-danger-lt text-danger me-2">${i18next.t("{{count}} still needed", { count: gap.gapCount })}</span>
              <span class="btn btn-sm btn-primary">
                <i class="fa-solid fa-user-plus me-1"></i>${i18next.t("Fill")}
              </span>
            </div>
          </div>
        </a>`;
    })
    .join("");

  renderState("gaps", "loaded");
}

// ─── Panel 2: pending responses ──────────────────────────────────────────────

/**
 * Assignments nobody has answered yet.
 *
 * §5.2 asks for the ones "inside the reminder window"; the server returns every pending
 * row in the window and flags the urgent ones with `withinReminderWindow`, because a
 * coordinator chasing replies wants the whole list and the flag is what makes the
 * urgent ones stand out. The row actions are §5.2's three: send a reminder now, cancel
 * the assignment, replace the person — each reached through the shared action menu and
 * each handled on the occurrence page, which is where the context lives.
 */
function renderPending(rows: VolunteerDashboardPending[]): void {
  renderBadge("volunteer-pending-badge", rows.length);

  const list = byId("volunteer-pending-list");
  if (!list) {
    return;
  }

  if (rows.length === 0) {
    list.innerHTML = "";
    renderState("pending", "empty");

    return;
  }

  list.innerHTML = rows
    .map((row) => {
      const where = ministryLabel(row.ministryName, row.teamName);
      const urgent = row.withinReminderWindow
        ? `<span class="badge bg-yellow-lt text-yellow ms-2">${i18next.t("Due soon")}</span>`
        : "";

      return `
        <div class="list-group-item">
          <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div>
              <span class="fw-bold">${escapeHtml(row.displayName ?? "")}</span>${urgent}
              <div class="text-body-secondary small">
                ${escapeHtml(row.positionName ?? "")} ·
                ${escapeHtml(whenLabel(row.start, row.occurrenceDate))}${where === "" ? "" : ` · ${escapeHtml(where)}`}
              </div>
            </div>
            <div class="text-nowrap">
              ${actionMenu([
                {
                  type: "link",
                  href: `${root()}/volunteer/occurrences/${row.occurrenceId}`,
                  icon: "fa-solid fa-list-check",
                  label: i18next.t("Open the occurrence"),
                },
                {
                  type: "link",
                  href: `${root()}/people/view/${row.personId}`,
                  icon: "fa-solid fa-user",
                  label: i18next.t("View person"),
                },
              ])}
            </div>
          </div>
        </div>`;
    })
    .join("");

  renderState("pending", "loaded");
}

// ─── Panel 3: proposed swaps ─────────────────────────────────────────────────

/**
 * One card per proposal, with Approve / Reject behind a `bootbox.confirm` (U3), exactly
 * as §5.2 item 3 describes. Both go straight to the existing swap endpoints — approval
 * is one server-side transaction (§2.13) and nothing about it is re-implemented here.
 */
function renderSwaps(swaps: VolunteerDashboardSwap[]): void {
  renderBadge("volunteer-swaps-badge", swaps.length);

  const list = byId("volunteer-swaps-list");
  if (!list) {
    return;
  }

  if (swaps.length === 0) {
    list.innerHTML = "";
    renderState("swaps", "empty");

    return;
  }

  list.innerHTML = swaps
    .map((swap) => {
      const when = whenLabel(swap.start ?? null, swap.occurrenceDate ?? null);

      return `
        <div class="col-12 col-md-6">
          <div class="card card-sm h-100">
            <div class="card-body">
              <div class="fw-bold">${escapeHtml(swap.positionName ?? "")}</div>
              <div class="text-body-secondary small mb-2">${escapeHtml(when)}</div>
              <p class="mb-2">
                ${i18next.t("{{proposer}} asks {{substitute}} to take their place", {
                  proposer: swap.proposedByName ?? "",
                  substitute: swap.proposedPersonName ?? "",
                })}
              </p>
              ${swap.comment ? `<p class="text-body-secondary small fst-italic mb-2">${escapeHtml(swap.comment)}</p>` : ""}
              <div class="btn-list">
                <button type="button" class="btn btn-sm btn-success volunteer-swap-approve" data-swap-id="${swap.id}">
                  <i class="fa-solid fa-check me-1"></i>${i18next.t("Approve")}
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger volunteer-swap-reject" data-swap-id="${swap.id}">
                  <i class="fa-solid fa-xmark me-1"></i>${i18next.t("Reject")}
                </button>
                <a class="btn btn-sm btn-outline-secondary" href="${root()}/volunteer/occurrences/${swap.occurrenceId ?? 0}">
                  ${i18next.t("Open the occurrence")}
                </a>
              </div>
            </div>
          </div>
        </div>`;
    })
    .join("");

  renderState("swaps", "loaded");
}

// ─── Panel 4: upcoming occurrences ───────────────────────────────────────────

/** Tear down an existing DataTable BEFORE the `<tbody>` is rewritten (#9715's trap). */
function destroyDataTable(tableId: string): void {
  if ($.fn.dataTable.isDataTable(`#${tableId}`)) {
    $(`#${tableId}`).DataTable().destroy();
  }
}

/** DataTables through the canonical `window.CRM.plugin.dataTable` merge idiom (U2). */
function initDataTable(tableId: string): void {
  const table = $(`#${tableId}`);
  if (table.length === 0) {
    return;
  }

  // §5.8: a failed ajax must render the inline block, never a browser alert.
  $.fn.dataTable.ext.errMode = "none";

  table.DataTable({ ...(window.CRM?.plugin?.dataTable ?? {}) });
}

/**
 * The staffed badge: green at full, amber while a pending reply is what is filling the
 * requirement, red when genuinely short — §5.2 item 4's progress-style badge. The counts
 * come from the server's single gap implementation and nothing is re-derived here.
 */
function staffedBadge(occurrence: VolunteerDashboardOccurrence): string {
  // An occurrence whose plan is EMPTY has no gaps only because nobody ever said what it
  // needs. Green there is a lie — and the one this change exists to stop telling (§2.10).
  if (occurrence.requirementCount === 0) {
    return `<span class="badge bg-secondary-lt text-secondary">${i18next.t("No staffing needs set")}</span>`;
  }

  const label = `${occurrence.liveCount} / ${occurrence.requiredCount}`;

  if (occurrence.gapCount > 0) {
    return `<span class="badge bg-red-lt text-red">${label}</span>`;
  }
  if (occurrence.pendingCount > 0) {
    return `<span class="badge bg-yellow-lt text-yellow">${label}</span>`;
  }

  return `<span class="badge bg-green-lt text-green">${label}</span>`;
}

function renderUpcoming(rows: VolunteerDashboardOccurrence[]): void {
  const body = byId("volunteer-upcoming-table")?.querySelector("tbody");
  if (!body) {
    return;
  }

  if (rows.length === 0) {
    destroyDataTable("volunteer-upcoming-table");
    body.innerHTML = "";
    renderState("upcoming", "empty");

    return;
  }

  destroyDataTable("volunteer-upcoming-table");

  body.innerHTML = rows
    .map((occurrence) => {
      const cancelled = occurrence.status === "cancelled";
      const status = cancelled
        ? `<span class="badge bg-secondary-lt text-secondary">${i18next.t("Cancelled")}</span>`
        : `<span class="badge bg-green-lt text-green">${i18next.t("Scheduled")}</span>`;

      return `
        <tr>
          <td><a href="${root()}/volunteer/occurrences/${occurrence.id}">${escapeHtml(whenLabel(occurrence.start, occurrence.occurrenceDate))}</a></td>
          <td>${escapeHtml(ministryLabel(occurrence.ministryName, occurrence.teamName))}</td>
          <td>${escapeHtml(occurrence.scheduleName ?? "")}</td>
          <td class="text-center">${staffedBadge(occurrence)}</td>
          <td class="text-center">${status}</td>
          <td class="text-center">
            ${actionMenu([
              {
                type: "link",
                href: `${root()}/volunteer/occurrences/${occurrence.id}`,
                icon: "fa-solid fa-list-check",
                label: i18next.t("Staff this occurrence"),
              },
              occurrence.ministryId !== null && {
                type: "link",
                href: `${root()}/volunteer/ministries/${occurrence.ministryId}`,
                icon: "fa-solid fa-handshake-angle",
                label: i18next.t("Open the ministry"),
              },
              occurrence.eventId !== null && {
                type: "link",
                href: `${root()}/event/view/${occurrence.eventId}`,
                icon: "fa-solid fa-calendar-day",
                label: i18next.t("View the event"),
              },
            ])}
          </td>
        </tr>`;
    })
    .join("");

  renderState("upcoming", "loaded");
  initDataTable("volunteer-upcoming-table");
}

// ─── The scope panel — the team leader's entry point ─────────────────────────

/**
 * The ministries and teams this person may navigate to.
 *
 * This is what closes the hole a pure **team leader** would otherwise fall into: the
 * ministry list is scoped to ministries they coordinate, which is empty for them, so
 * without this they pass the role gate and find nothing to open. The server marks a
 * ministry they only reach through a team as `manageable: false`, and it is rendered as
 * plain text with its teams underneath rather than as a link into a page the server
 * would refuse (§4.6).
 */
function renderScope(scope: VolunteerDashboard["scope"]): void {
  const ministries = byId("volunteer-scope-ministries");
  const teams = byId("volunteer-scope-teams");
  if (!ministries || !teams) {
    return;
  }

  show(byId("volunteer-scope-empty"), scope.ministries.length === 0 && scope.teams.length === 0);

  ministries.innerHTML = scope.ministries
    .map((ministry) => {
      const inactive = ministry.active
        ? ""
        : `<span class="badge bg-secondary-lt ms-2">${i18next.t("Inactive")}</span>`;

      if (!ministry.manageable) {
        return `
          <div class="list-group-item">
            <span class="fw-bold">${escapeHtml(ministry.name)}</span>${inactive}
            <span class="badge bg-secondary-lt ms-2">${i18next.t("View only")}</span>
          </div>`;
      }

      return `
        <a class="list-group-item list-group-item-action" href="${root()}/volunteer/ministries/${ministry.id}">
          <span class="fw-bold">${escapeHtml(ministry.name)}</span>${inactive}
        </a>`;
    })
    .join("");

  teams.innerHTML = scope.teams
    .map(
      (team) => `
        <div class="list-group-item">
          <i class="fa-solid fa-people-group me-2 text-body-secondary"></i>${escapeHtml(team.name)}
          <span class="text-body-secondary small ms-1">${escapeHtml(team.ministryName ?? "")}</span>
        </div>`,
    )
    .join("");
}

// ─── The admin notification strip ────────────────────────────────────────────

/**
 * The failed-notification count (§5.2 item 5).
 *
 * `failed` is terminal — five attempts and no more retries (§2.14) — so a non-zero count
 * is something an administrator has to act on, never something that will clear itself.
 */
function renderFailedNotifications(count: number): void {
  const badge = byId("volunteer-failed-count");
  const label = byId("volunteer-failed-label");
  if (!badge || !label) {
    return;
  }

  badge.textContent = String(count);
  badge.className = count > 0 ? "badge bg-red-lt text-red me-1" : "badge bg-green-lt text-green me-1";
  label.textContent =
    count > 0
      ? i18next.t("messages could not be delivered after five attempts")
      : i18next.t("every message has been delivered or is still queued");
}

// ─── Loading ─────────────────────────────────────────────────────────────────

function renderAll(dashboard: VolunteerDashboard): void {
  renderGaps(dashboard.gaps);
  renderPending(dashboard.pendingResponses);
  renderSwaps(dashboard.proposedSwaps);
  renderUpcoming(dashboard.upcoming);
  renderScope(dashboard.scope);
  renderFailedNotifications(dashboard.failedNotifications);
  show(byId("volunteer-capped-note"), dashboard.capped);
}

async function load(): Promise<void> {
  for (const pane of PANES) {
    renderState(pane, "loading");
  }

  try {
    renderAll(await getDashboard(days));
  } catch (error) {
    // Nothing is cached, so the error block's Retry is a genuine retry (§5.8) —
    // it re-runs this same load from scratch.
    const message = errorMessage(error, i18next.t("Could not load the dashboard"));
    for (const pane of PANES) {
      renderState(pane, "error", message);
    }
  }
}

function decideSwap(swapId: number, approve: boolean): void {
  const action = approve ? approveSwap : rejectSwap;

  action(swapId, "")
    .then(() => {
      notifySuccess(approve ? i18next.t("Substitution approved") : i18next.t("Substitution rejected"));

      return load();
    })
    .catch((error: unknown) => {
      notifyError(errorMessage(error, i18next.t("The substitution could not be decided")));
    });
}

function wire(): void {
  byId("volunteer-refresh")?.addEventListener("click", () => {
    void load();
  });

  byId<HTMLSelectElement>("volunteer-days")?.addEventListener("change", (event) => {
    days = Number((event.target as HTMLSelectElement).value) || 28;
    void load();
  });

  // Retry on every panel's error block re-runs the single load (§5.8).
  for (const pane of PANES) {
    byId(`volunteer-${pane}-error`)
      ?.querySelector(".volunteer-retry")
      ?.addEventListener("click", () => {
        void load();
      });
  }

  // Delegated: the cards are re-rendered on every load, so per-card listeners would go
  // stale after the first approval.
  document.addEventListener("click", (event) => {
    const target = (event.target as HTMLElement | null)?.closest<HTMLElement>(
      ".volunteer-swap-approve, .volunteer-swap-reject",
    );
    if (!target) {
      return;
    }

    const swapId = Number(target.dataset.swapId);
    const approve = target.classList.contains("volunteer-swap-approve");

    confirmAction(
      approve ? i18next.t("Approve substitution") : i18next.t("Reject substitution"),
      approve
        ? i18next.t("The original volunteer is released and the substitute takes the position. Both are told.")
        : i18next.t("The original volunteer keeps the position. Both are told."),
      () => decideSwap(swapId, approve),
      !approve,
    );
  });
}

function init(): void {
  const config = (window.CRM?.volunteerDashboard ?? {
    days: 28,
    isAdmin: false,
    isManager: false,
  }) as DashboardConfig;

  days = config.days > 0 ? config.days : 28;

  wire();
  void load();
}

document.addEventListener("DOMContentLoaded", () => {
  if (window.CRM?.onLocalesReady) {
    window.CRM.onLocalesReady(init);
  } else {
    init();
  }
});
