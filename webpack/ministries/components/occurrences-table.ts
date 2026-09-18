/**
 * The Occurrences tab — the upcoming weeks with their derived gap counts, and a
 * link into the staffing view (#9709, design §5.5 entry point).
 *
 * Extracted verbatim from `webpack/ministries/ministry.ts` for #9868 so the Member
 * Portal's My Teams page lists a team leader's own occurrences with the same
 * search form and the same badges.
 *
 * Deliberately minimal — #9711's dashboard is the richer "what needs my
 * attention" answer. Its whole job is to make the staffing view reachable, which
 * #9708 and #9715 left no hook for. Every count comes from the API, which serves
 * them from `VolunteerAssignmentService::getGaps()`; nothing is re-derived here.
 *
 * Two things the caller decides: the search form's Team select (the ministry page
 * offers "All Teams" and every team; the portal's page is fixed to one team and
 * leaves the select out of its markup entirely), and where an occurrence link
 * points — `/ministries/occurrences/{id}` in the admin shell,
 * `/portal/teams/{teamId}/occurrences/{id}` in the portal.
 */

import {
  errorMessage,
  listOccurrences,
  positionLabel,
  type VolunteerOccurrenceSummary,
  type VolunteerTeam,
} from "../api";
import {
  actionMenu,
  byId,
  destroyDataTable,
  escapeAttribute,
  escapeHtml,
  formatIsoDate,
  initDataTable,
  isoDate,
  renderState,
  tText,
} from "./ui";

/**
 * The Occurrences table's own DataTables options.
 *
 * `searching: false` removes the "Search:" box the Team/Event/From/To form
 * replaced; the layout then names only the three regions that are left, because
 * a `layout` slot pointing at a feature that no longer exists renders nothing but
 * still reserves its row.
 */
const OCCURRENCES_TABLE_OPTIONS: Record<string, unknown> = {
  searching: false,
  layout: {
    topStart: null,
    topEnd: null,
    bottomStart: "pageLength",
    bottomEnd: ["info", "paging"],
  },
};

/**
 * How far past From the window runs when the To box is left empty.
 *
 * The list endpoint takes a MANDATORY `from`/`to` window (design M9: no pagination
 * protocol was invented for one module), so "no end date" is not something the API
 * can be asked. An empty To therefore has to be turned into a real upper bound here,
 * and a year is the honest reading of "as far as it goes": a schedule generates at
 * most a year or so ahead, and the 500-row cap — which the response reports as
 * `capped` — is the real limit on how much comes back anyway.
 */
const OPEN_ENDED_TO_YEARS = 1;

/** How long a typed field waits after the last keystroke before it queries. */
const OCCURRENCE_TEXT_DEBOUNCE_MS = 300;

export interface OccurrencesTableOptions {
  /** Narrow the query to one ministry, or 0 for "do not narrow". */
  ministryId(): number;
  /**
   * Narrow the query to one team whatever the form says. The portal's page is one
   * team's, so it pins the filter here instead of offering a select; the ministry
   * page returns null and lets `#occurrence-team-filter` decide.
   */
  fixedTeamId(): number | null;
  /** The teams `#occurrence-team-filter` may offer, below its "All Teams" entry. */
  teams(): VolunteerTeam[];
  /** Make `teams()` answerable before the first query. */
  ensureContext(): Promise<void>;
  /** Where a row links to. */
  occurrenceUrl(occurrenceId: number): string;
}

export interface OccurrencesTableHandle {
  load(force?: boolean): Promise<void>;
  invalidate(): void;
}

export function createOccurrencesTable(options: OccurrencesTableOptions): OccurrencesTableHandle {
  /**
   * The occurrences with their derived gap counts. Cached so switching tabs does not
   * re-fetch, and cleared on error so Retry is a genuine retry (§5.8).
   */
  let occurrences: VolunteerOccurrenceSummary[] | null = null;
  /** Pending debounce for the typed fields, so a fast typist makes one request. */
  let typingTimer = 0;

  /**
   * "1 Lead Teacher, 2 Helper" — what is missing, by name.
   *
   * A bare gap count tells a coordinator that something is short without telling them
   * what, which is one click of guessing per row. The names come from the server's gap
   * list; only the join happens here.
   */
  function gapSummary(occurrence: VolunteerOccurrenceSummary): string {
    if (occurrence.gaps.length === 0) {
      return i18next.t("{{count}} still needed", { count: occurrence.gapCount });
    }

    // A ministry-wide table lists every schedule of the ministry, so two rows can be
    // short of a "Lead Teacher" that means two different teams' positions. The team is
    // named on each one, from the occurrence's own schedule.
    const teamName = options.teams().find((team) => team.id === occurrence.teamId)?.name ?? null;

    return occurrence.gaps
      .map((gap) => `${gap.gapCount} ${positionLabel(teamName, gap.positionName)}`.trim())
      .join(", ");
  }

  function render(rows: VolunteerOccurrenceSummary[]): void {
    const body = byId("volunteerOccurrencesTable")?.querySelector("tbody");
    if (!body) {
      return;
    }

    if (rows.length === 0) {
      destroyDataTable("volunteerOccurrencesTable");
      body.innerHTML = "";
      // The table is gone, so its export buttons have nothing to export.
      const toolbar = byId("occurrences-toolbar");
      if (toolbar) {
        toolbar.textContent = "";
      }
      renderState("occurrences", "empty");

      return;
    }

    destroyDataTable("volunteerOccurrencesTable");

    body.innerHTML = rows
      .map((occurrence) => {
        const href = options.occurrenceUrl(occurrence.id);
        const when = occurrence.start ?? occurrence.occurrenceDate ?? "";
        // One cell, one icon, says how the occurrence stands (review, 2026-09-18);
        // the words live in the tooltip. An EMPTY plan is not "fully staffed"
        // (§2.10): it has no gaps only because nobody said what it needs, so its
        // icon links to where the needs are set. Otherwise: green when every
        // position is assigned AND every assignment accepted, amber when every
        // position is assigned but somebody has not answered yet, red when a
        // position is still unassigned — the tooltip names what is short.
        let filled: string;
        if (occurrence.requirementCount === 0) {
          const label = i18next.t("No staffing needs set");
          filled = `<a class="text-secondary" href="${href}" title="${escapeAttribute(label)}" aria-label="${escapeAttribute(label)}"><i class="fa-solid fa-circle-question fa-lg" aria-hidden="true"></i></a>`;
        } else if (occurrence.gapCount > 0) {
          const label = tText("{{live}} of {{required}} filled — {{needed}} still needed", {
            live: occurrence.liveCount,
            required: occurrence.requiredCount,
            needed: gapSummary(occurrence),
          });
          filled = `<span class="text-red" title="${escapeAttribute(label)}" aria-label="${escapeAttribute(label)}"><i class="fa-solid fa-triangle-exclamation fa-lg" aria-hidden="true"></i></span>`;
        } else if (occurrence.pendingCount > 0) {
          const label = tText("Every position is assigned; {{count}} not yet confirmed", {
            count: occurrence.pendingCount,
          });
          filled = `<span class="text-yellow" title="${escapeAttribute(label)}" aria-label="${escapeAttribute(label)}"><i class="fa-solid fa-hourglass-half fa-lg" aria-hidden="true"></i></span>`;
        } else {
          const label = i18next.t("Every position is filled and confirmed");
          filled = `<span class="text-green" title="${escapeAttribute(label)}" aria-label="${escapeAttribute(label)}"><i class="fa-solid fa-circle-check fa-lg" aria-hidden="true"></i></span>`;
        }

        return `
        <tr>
          <td><a href="${href}">${escapeHtml(when)}</a></td>
          <td>${escapeHtml(occurrence.scheduleName ?? "")}</td>
          <td class="text-center">${filled}</td>
          <td class="text-center">
            ${actionMenu([
              {
                type: "link",
                href,
                icon: "fa-solid fa-list-check",
                label: i18next.t("Staff this occurrence"),
              },
            ])}
          </td>
        </tr>`;
      })
      .join("");

    renderState("occurrences", "loaded");
    initDataTable("volunteerOccurrencesTable", OCCURRENCES_TABLE_OPTIONS, "occurrences-toolbar");
  }

  /**
   * The search form's fields, read off the DOM at the moment of the request.
   *
   * There is no cached "current filter" object: the inputs ARE the state, so nothing
   * can drift out of step with what the reader is looking at, and a re-render cannot
   * silently query something other than what the form says.
   *
   * `from` falls back to today because the tab exists to staff the weeks ahead — the
   * weeks that already happened are reached by moving From back, which is one field
   * rather than the dialog this replaced.
   */
  function query(): { from: string; to: string; teamId?: number; text?: string } {
    const from = byId<HTMLInputElement>("occurrence-from")?.value || isoDate(0);
    const typedTo = byId<HTMLInputElement>("occurrence-to")?.value ?? "";
    const selectedTeam = Number(byId<HTMLSelectElement>("occurrence-team-filter")?.value ?? "") || undefined;
    const teamId = options.fixedTeamId() ?? selectedTeam;
    const text = byId<HTMLInputElement>("occurrence-event-filter")?.value.trim() || undefined;

    // An empty To means "as far as it goes"; the endpoint requires a bound, so
    // From + a year is sent and the code says so rather than leaving a magic date.
    let to = typedTo;
    if (to === "") {
      const end = new Date(`${from}T12:00:00`);
      end.setFullYear(end.getFullYear() + OPEN_ENDED_TO_YEARS);
      to = formatIsoDate(end);
    }

    return { from, to, teamId: teamId ?? undefined, text };
  }

  /**
   * The Team select above the occurrence list.
   *
   * Unlike the Volunteers grid's team filter, this one DOES have an "All teams" entry
   * and starts on it: an occurrence belongs to exactly one team's schedule, so a list
   * spanning teams is unambiguous — it is the columns of the qualification grid that
   * were not — and "what is coming up" is the question the tab opens on.
   */
  function fillTeamFilter(): void {
    const select = byId<HTMLSelectElement>("occurrence-team-filter");
    if (!select) {
      return;
    }

    const previous = select.value;
    select.textContent = "";

    const all = document.createElement("option");
    all.value = "";
    all.textContent = i18next.t("All Teams");
    select.append(all);

    for (const team of options.teams()) {
      const option = document.createElement("option");
      option.value = String(team.id);
      option.textContent = team.name;
      select.append(option);
    }

    // Keep whatever was chosen if that team still exists; otherwise fall back to All.
    select.value = previous;
    if (select.value !== previous) {
      select.value = "";
    }
  }

  async function load(force = false): Promise<void> {
    if (occurrences !== null && !force) {
      render(occurrences);

      return;
    }

    renderState("occurrences", "loading");

    // The Team select is filled from the caller's document, and `gapSummary()` names
    // the team of a short position from the same place — so the document has to be
    // there before the first occurrence request, exactly as it does for the grid.
    await options.ensureContext();
    fillTeamFilter();

    const params = query();
    // Said here rather than only by the server's 400, because it is the one mistake
    // the form makes easy and the answer is faster where the mistake was made.
    if (params.to < params.from) {
      occurrences = null;
      renderState("occurrences", "error", i18next.t("The window ends before it starts"));

      return;
    }

    try {
      const ministryId = options.ministryId();
      const data = await listOccurrences(ministryId > 0 ? { ...params, ministryId } : params);
      occurrences = data.occurrences;
      render(occurrences);
    } catch (error) {
      occurrences = null;
      renderState("occurrences", "error", errorMessage(error, i18next.t("Could not load the occurrences")));
    }
  }

  function invalidate(): void {
    occurrences = null;
  }

  /**
   * The search form: Team · Event · From · To, all live.
   *
   * It replaces a "Filter by Date" button, a modal, a "Showing … to …" line and a
   * "Back to upcoming" link. Every field re-runs the query as soon as it changes.
   *
   * The Team select fires once per choice, so it queries immediately. The three typed
   * fields are debounced together: the Event box fires per keystroke, and a date input
   * typed rather than picked fires `input` for every complete date it passes through on
   * the way to the one that was meant — 2026-06-16 is a valid date at "0026", "0206"
   * and "2026" too. Both `input` and `change` are listened for on the dates, because a
   * browser fires `input` when a date is typed and `change` when one is picked, and
   * neither event alone covers both ways of using the control.
   *
   * Every one of them narrows the query SERVER-side — `teamId` and `text` are query
   * parameters of `GET /occurrences`, not a filter over rows already drawn. The table
   * may be a DataTable, and `render()` destroys it before rewriting the `<tbody>`;
   * doing it the other way round silently restores the cached rows.
   */
  function wire(): void {
    const from = byId<HTMLInputElement>("occurrence-from");
    if (from && from.value === "") {
      from.value = isoDate(0);
    }

    const reloadSoon = (): void => {
      window.clearTimeout(typingTimer);
      typingTimer = window.setTimeout(() => {
        void load(true);
      }, OCCURRENCE_TEXT_DEBOUNCE_MS);
    };

    byId("occurrence-team-filter")?.addEventListener("change", () => {
      void load(true);
    });

    for (const id of ["occurrence-event-filter", "occurrence-from", "occurrence-to"]) {
      byId(id)?.addEventListener("input", reloadSoon);
      byId(id)?.addEventListener("change", reloadSoon);
    }
  }

  wire();

  return { load, invalidate };
}
