/**
 * Coordinators and team leaders — the screen for #9706's scope API
 * (design §2.15 and §4.4).
 *
 * The API to grant ministry-coordinator and team-leader authority shipped with
 * the epic; nothing ever called it, so the only ways to make someone a
 * coordinator were a REST client and a SQL insert. This module is the card on
 * the ministry page that closes that gap.
 *
 * It is a SEPARATE module rather than another section of ministry.ts on purpose:
 * ministry.ts is already six tabs and 1,400 lines, and this surface has nothing
 * to do with any of them. `ministry.ts` imports one function and calls it once.
 *
 * **Who sees it.** Granting authority is manager-only — §3.2 is explicit that it
 * is the one thing a coordinator must not be able to do for themselves — so the
 * view renders the markup only for a global manager and `init()` returns early
 * when `window.CRM.volunteerMinistry.isManager` is false. Neither is the
 * decision: the API is, and it is manager-gated by
 * `VolunteerManagerRoleAuthMiddleware`. A 403 from any of the three requests
 * hides the whole card rather than showing a broken one, so a stale flag degrades
 * to "not offered" instead of "offered and then refused".
 *
 * **Duplicate grants are not errors.** `POST /scopes` is idempotent by the
 * `vscp_person_scope_uidx` unique key and answers 200 with the existing row
 * (§6.6) — no 409, and no second row. Posting a duplicate would therefore look
 * like success and change nothing, which is worse than an error, so the grant is
 * checked against the list already on screen first and the person is told
 * plainly that the grant is already there.
 *
 * Every user-visible string is `i18next.t()` in this file: the extractor scans
 * only `webpack/**` and `src/skin/js/**`, so the same call inside the .php view
 * would be translated by nothing (design §5.10, F31). Toasts use
 * `window.CRM.notify` with `"danger"`, never `"error"`, which renders blue
 * (U5/E-7).
 */

import { attachToModal } from "../common/person-select";
import {
  errorMessage,
  grantScope,
  listScopes,
  listTeams,
  notifyError,
  notifySuccess,
  notifyWarning,
  revokeScope,
  VolunteerApiError,
  type VolunteerScopeGrant,
  type VolunteerTeam,
} from "./api";

let ministryId = 0;
let teams: VolunteerTeam[] = [];
let coordinators: VolunteerScopeGrant[] = [];
/** Team-leader grants keyed by team id, so a team with none still renders its row. */
let leaders = new Map<number, VolunteerScopeGrant[]>();
let wired = false;

function byId<T extends HTMLElement>(id: string): T | null {
  return document.getElementById(id) as T | null;
}

function show(el: Element | null, visible: boolean): void {
  el?.classList.toggle("d-none", !visible);
}

function escapeHtml(value: string): string {
  return window.CRM?.escapeHtml?.(value) ?? value;
}

function escapeAttribute(value: string): string {
  return window.CRM?.escapeAttribute?.(value) ?? value;
}

function root(): string {
  return window.CRM?.root ?? "";
}

/** Row actions through the shared builder (U1/#9820), which owns every bit of escaping. */
function actionMenu(items: CRMActionMenuItem[]): string {
  return window.CRM?.buildActionMenu?.(items) ?? "";
}

/**
 * When the grant was made, in the viewer's own locale.
 *
 * The server sends `Y-m-d H:i:s` already resolved in the church's timezone
 * (§2.0), so it is rendered as a wall-clock reading and never re-zoned.
 */
function grantedLabel(granted: string | null): string {
  if (!granted) {
    return "";
  }

  const parsed = new Date(granted.replace(" ", "T"));

  return Number.isNaN(parsed.getTime()) ? granted : parsed.toLocaleDateString();
}

/** The §5.8 state machine for the card, in one place so no state can be forgotten. */
function renderState(state: "loading" | "error" | "loaded", message = ""): void {
  show(byId("scopes-loading"), state === "loading");
  show(byId("scopes-error"), state === "error");
  show(byId("scopes-content"), state === "loaded");

  // Both buttons need the loaded data to do anything sensible — the team select
  // is built from it, and a grant is checked against it for duplicates — so they
  // stay disabled until it is there. The markup ships them disabled for the same
  // reason: the card is shown before the first response arrives.
  for (const id of ["scope-add-coordinator", "scope-add-leader"]) {
    const button = byId<HTMLButtonElement>(id);
    if (button) {
      button.disabled = state !== "loaded";
    }
  }

  if (state === "error") {
    const text = byId("scopes-error")?.querySelector(".volunteer-error-text");
    if (text) {
      text.textContent = message;
    }
  }
}

/** A 403 means this viewer may not grant anything: take the card away entirely. */
function hidePanel(): void {
  show(byId("volunteer-scope-panel"), false);
}

function modal(id: string): { show(): void; hide(): void } | null {
  const el = byId(id);
  if (!el || !window.bootstrap?.Modal) {
    return null;
  }

  return window.bootstrap.Modal.getOrCreateInstance(el);
}

/**
 * The label the person-search endpoint returned for the chosen person.
 *
 * Read back off the TomSelect option rather than re-derived from a name field:
 * the name on screen should be the one the server knows the person by.
 */
function pickedName(instance: TomSelectInstance | null | undefined, personId: number): string {
  const label = instance?.options?.[String(personId)]?.text;

  return typeof label === "string" ? label : "";
}

function showModalError(prefix: string, message: string): void {
  const box = byId(`${prefix}-error`);
  const text = box?.querySelector(".volunteer-error-text");
  if (text) {
    text.textContent = message;
  }
  show(box, true);
}

// ─── Renderers ───────────────────────────────────────────────────────────────

/**
 * One grant row. The name links into the person record — the grant is about a
 * PERSON, and "who is this?" is the first question a manager asks of a name they
 * do not recognise.
 */
function grantRow(grant: VolunteerScopeGrant, extraClass: string, data: Record<string, string | number>): string {
  const menu = actionMenu([
    {
      type: "button",
      icon: "fa-solid fa-user-minus",
      label: i18next.t("Remove"),
      className: "volunteer-scope-remove",
      danger: true,
      data: {
        "scope-id": grant.id,
        "scope-person": grant.personName,
        "scope-type": grant.scopeType,
        "scope-target": grant.scopeName ?? "",
      },
    },
  ]);

  const attributes = Object.entries(data)
    .map(([key, value]) => ` data-${key}="${escapeAttribute(String(value))}"`)
    .join("");

  return `<tr class="${extraClass}"${attributes}>
      <td class="fw-bold">
        <a href="${root()}/PersonView.php?PersonID=${grant.personId}">${escapeHtml(grant.personName)}</a>
      </td>
      <td>${escapeHtml(grantedLabel(grant.grantedDate))}</td>
      <td class="w-1">${menu}</td>
    </tr>`;
}

function renderCoordinators(): void {
  const body = document.querySelector("#volunteerCoordinatorsTable tbody");
  if (!body) {
    return;
  }

  body.innerHTML = coordinators
    .map((grant) => grantRow(grant, "volunteer-scope-row", { "scope-id": grant.id }))
    .join("");

  show(byId("scopes-coordinators-empty"), coordinators.length === 0);
  show(byId("scopes-coordinators-wrapper"), coordinators.length > 0);
}

/**
 * Team leaders, grouped under EVERY team of the ministry — including the teams
 * that have no leader. A team with nobody on it is the useful half of this list:
 * it is what tells a manager where the gap is.
 */
function renderTeamLeaders(): void {
  const body = document.querySelector("#volunteerTeamLeadersTable tbody");
  if (!body) {
    return;
  }

  body.innerHTML = teams
    .map((team) => {
      const rows = leaders.get(team.id) ?? [];
      const header = `<tr class="volunteer-scope-team-row" data-team-id="${team.id}">
          <th colspan="3" class="bg-light">
            <i class="fa-solid fa-people-group me-2 text-body-secondary"></i>${escapeHtml(team.name)}
          </th>
        </tr>`;

      if (rows.length === 0) {
        return `${header}<tr class="volunteer-scope-empty-row" data-team-id="${team.id}">
            <td colspan="3" class="text-body-secondary">${i18next.t("No team leader yet")}</td>
          </tr>`;
      }

      return header + rows.map((grant) => grantRow(grant, "volunteer-scope-row", { "team-id": team.id })).join("");
    })
    .join("");

  show(byId("scopes-teams-empty"), teams.length === 0);
  show(byId("scopes-teams-wrapper"), teams.length > 0);
}

/** The team select in the "Add team leader" modal, rebuilt from the loaded teams. */
function fillTeamSelect(): void {
  const select = byId<HTMLSelectElement>("scope-leader-team");
  if (!select) {
    return;
  }

  select.innerHTML = teams.map((team) => `<option value="${team.id}">${escapeHtml(team.name)}</option>`).join("");
}

// ─── Loading ─────────────────────────────────────────────────────────────────

/**
 * Everything the card shows, in two round trips rather than one per team.
 *
 * The ministry's teams and its coordinator grants are independent, so they go
 * together; the per-team grants can only be asked for once the team ids are
 * known. `?ministryId=` and `?teamId=` filter the same polymorphic column two
 * ways and the API refuses to combine them, which is why this is two calls and
 * not one.
 */
async function load(): Promise<void> {
  renderState("loading");

  try {
    const [teamResult, coordinatorResult] = await Promise.all([listTeams(ministryId), listScopes({ ministryId })]);

    teams = teamResult.teams;
    coordinators = coordinatorResult.scopes;

    const perTeam = await Promise.all(teams.map((team) => listScopes({ teamId: team.id })));
    leaders = new Map(teams.map((team, index) => [team.id, perTeam[index]?.scopes ?? []]));

    renderCoordinators();
    renderTeamLeaders();
    fillTeamSelect();
    renderState("loaded");
  } catch (error: unknown) {
    // 403 is not a failure to report — it is the answer that this viewer is not
    // a volunteer manager, and the card should never have been offered.
    if (error instanceof VolunteerApiError && error.status === 403) {
      hidePanel();
      return;
    }

    renderState("error", errorMessage(error, i18next.t("The coordinator list could not be loaded")));
  }
}

// ─── Granting and revoking ───────────────────────────────────────────────────

function saveCoordinator(personId: number, personName: string): void {
  const existing = coordinators.find((grant) => grant.personId === personId);
  if (existing) {
    // The API would answer 200 with this very row (§6.6) — success that changed
    // nothing. Say what actually happened instead.
    showModalError("scope-coordinator", i18next.t("{{name}} already coordinates this ministry", { name: personName }));
    notifyWarning(i18next.t("{{name}} already coordinates this ministry", { name: personName }));
    return;
  }

  grantScope(personId, "ministry", ministryId)
    .then(() => {
      modal("scopeCoordinatorModal")?.hide();
      notifySuccess(i18next.t("{{name}} now coordinates this ministry", { name: personName }));

      return load();
    })
    .catch((error: unknown) => {
      showModalError("scope-coordinator", errorMessage(error, i18next.t("The coordinator could not be added")));
    });
}

function saveLeader(personId: number, personName: string, teamId: number, teamName: string): void {
  const existing = (leaders.get(teamId) ?? []).find((grant) => grant.personId === personId);
  if (existing) {
    showModalError("scope-leader", i18next.t("{{name}} already leads {{team}}", { name: personName, team: teamName }));
    notifyWarning(i18next.t("{{name}} already leads {{team}}", { name: personName, team: teamName }));
    return;
  }

  grantScope(personId, "team", teamId)
    .then(() => {
      modal("scopeLeaderModal")?.hide();
      notifySuccess(i18next.t("{{name}} now leads {{team}}", { name: personName, team: teamName }));

      return load();
    })
    .catch((error: unknown) => {
      showModalError("scope-leader", errorMessage(error, i18next.t("The team leader could not be added")));
    });
}

/** Revoking authority is destructive, so it goes behind a bootbox confirm (U3). */
function confirmRemove(target: HTMLElement): void {
  const scopeId = Number(target.dataset.scopeId);
  const personName = target.dataset.scopePerson ?? "";
  const isTeam = target.dataset.scopeType === "team";
  const targetName = target.dataset.scopeTarget ?? "";

  window.bootbox?.confirm({
    title: isTeam ? i18next.t("Remove team leader") : i18next.t("Remove coordinator"),
    message: isTeam
      ? i18next.t("Stop {{name}} leading {{team}}? Their login and their own assignments are untouched.", {
          name: personName,
          team: targetName,
        })
      : i18next.t("Stop {{name}} coordinating {{ministry}}? Their login and their own assignments are untouched.", {
          name: personName,
          ministry: targetName,
        }),
    buttons: {
      confirm: { label: i18next.t("Yes"), className: "btn-danger" },
      cancel: { label: i18next.t("No"), className: "btn-default" },
    },
    callback: (result: boolean) => {
      if (!result) {
        return;
      }

      revokeScope(scopeId)
        .then(() => {
          notifySuccess(isTeam ? i18next.t("Team leader removed") : i18next.t("Coordinator removed"));

          return load();
        })
        .catch((error: unknown) => {
          notifyError(errorMessage(error, i18next.t("The grant could not be removed")));
        });
    },
  });
}

// ─── Wiring ──────────────────────────────────────────────────────────────────

function wire(): void {
  if (wired) {
    return;
  }
  wired = true;

  byId("scopes-error")
    ?.querySelector(".volunteer-retry")
    ?.addEventListener("click", () => {
      void load();
    });

  // The shared person selector (CR1/#9819) rather than a hand-rolled TomSelect:
  // it owns the modal lifecycle, the body-mounted dropdown and the maxOptions fix.
  const coordinatorModal = byId("scopeCoordinatorModal");
  const coordinatorPicker = coordinatorModal ? attachToModal(coordinatorModal, "#scope-coordinator-person") : null;
  const leaderModal = byId("scopeLeaderModal");
  const leaderPicker = leaderModal ? attachToModal(leaderModal, "#scope-leader-person") : null;

  byId("scope-add-coordinator")?.addEventListener("click", () => {
    show(byId("scope-coordinator-error"), false);
    modal("scopeCoordinatorModal")?.show();
  });

  byId("scope-add-leader")?.addEventListener("click", () => {
    show(byId("scope-leader-error"), false);
    if (teams.length === 0) {
      notifyWarning(i18next.t("Add a team first — a team leader has to lead something"));
      return;
    }
    fillTeamSelect();
    modal("scopeLeaderModal")?.show();
  });

  byId("scope-coordinator-save")?.addEventListener("click", () => {
    const instance = coordinatorPicker?.getInstance();
    const personId = Number(instance?.getValue() ?? 0);
    if (!personId) {
      showModalError("scope-coordinator", i18next.t("Choose a person"));
      return;
    }

    show(byId("scope-coordinator-error"), false);
    saveCoordinator(personId, pickedName(instance, personId));
  });

  byId("scope-leader-save")?.addEventListener("click", () => {
    const instance = leaderPicker?.getInstance();
    const personId = Number(instance?.getValue() ?? 0);
    const select = byId<HTMLSelectElement>("scope-leader-team");
    const teamId = Number(select?.value ?? 0);

    if (!personId || !teamId) {
      showModalError("scope-leader", i18next.t("Choose a team and a person"));
      return;
    }

    show(byId("scope-leader-error"), false);
    saveLeader(personId, pickedName(instance, personId), teamId, teams.find((team) => team.id === teamId)?.name ?? "");
  });

  // Delegated: the rows are re-rendered on every load, so per-row listeners
  // would go stale.
  document.addEventListener("click", (event) => {
    const target = (event.target as HTMLElement | null)?.closest<HTMLElement>(".volunteer-scope-remove");
    if (target) {
      confirmRemove(target);
    }
  });
}

/**
 * Mount the card. One call from ministry.ts, and a no-op on every page that does
 * not carry the markup.
 *
 * @param config The ministry this page is showing, and whether the server
 *               believes the viewer is a global volunteer manager. The flag is
 *               advisory — the API decides — but honouring it avoids a request
 *               that is certain to be refused.
 */
export function initVolunteerScopes(config: { ministryId: number; isManager: boolean }): void {
  if (config.ministryId === 0 || !config.isManager || !byId("volunteer-scope-panel")) {
    return;
  }

  ministryId = config.ministryId;
  show(byId("volunteer-scope-panel"), true);
  wire();
  void load();
}
