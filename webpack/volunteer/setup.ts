/**
 * S2 — the guided setup flow (#9715, design §5.3).
 *
 * The page is one thread, not a pile of forms: until a ministry exists every later
 * step's controls stay `disabled`, and each completed step collapses to a
 * summary line with an Edit link. That is the whole difference between a guided
 * flow and the "collection of disconnected CRUD pages" #9715 explicitly rules
 * out.
 *
 * Every user-visible string goes through `i18next.t()` and lives in this file —
 * the JS extractor scans only `webpack/**` and `src/skin/js/**`, so the same
 * call inside the .php view would be translated by nothing (design §5.10, F31).
 * Module-scope translation is deferred behind `window.CRM.onLocalesReady` for
 * the same reason `email-composer.ts` defers it: on a non-en_US locale
 * `i18next.t()` returns undefined until the catalog has loaded (upstream #9609).
 */

import {
  createMinistry,
  createPosition,
  createTeam,
  errorMessage,
  listPositions,
  listTeams,
  notifyError,
  notifySuccess,
  updateTeam,
  type VolunteerPosition,
  type VolunteerTeam,
} from "./api";

interface SetupConfig {
  isManager: boolean;
  ministryId: number;
}

/** The one piece of mutable state the flow has: which ministry we are building. */
let ministryId = 0;
let ministryName = "";
let teams: VolunteerTeam[] = [];

function byId<T extends HTMLElement>(id: string): T | null {
  return document.getElementById(id) as T | null;
}

function show(el: Element | null, visible: boolean): void {
  el?.classList.toggle("d-none", !visible);
}

/** Inline error blocks live inside their step card so the message is where the action was. */
function showStepError(stepId: string, message: string): void {
  const box = byId(`${stepId}-error`);
  if (!box) {
    return;
  }
  const text = box.querySelector(".setup-error-text");
  if (text) {
    text.textContent = message;
  }
  show(box, true);
  notifyError(message);
}

function clearStepError(stepId: string): void {
  show(byId(`${stepId}-error`), false);
}

/**
 * Enable or disable a whole step's controls. Disabling is deliberate rather than
 * hiding: a step the coordinator can see but not yet use tells them what is
 * coming, which is the point of a wizard.
 */
function setStepEnabled(ids: string[], enabled: boolean): void {
  for (const id of ids) {
    const control = byId<HTMLInputElement | HTMLButtonElement | HTMLSelectElement>(id);
    if (control) {
      control.disabled = !enabled;
    }
  }
}

const TEAM_CONTROLS = ["setup-team-name", "setup-team-description", "setup-team-save"];
const POSITION_CONTROLS = [
  "setup-position-name",
  "setup-position-description",
  "setup-position-team",
  "setup-position-save",
];

function renderMinistrySummary(): void {
  const card = byId("setup-step-ministry");
  if (!card) {
    return;
  }
  const summary = card.querySelector(".setup-step-summary");
  const form = card.querySelector(".setup-step-form");
  const name = card.querySelector(".setup-summary-name");
  if (name) {
    name.textContent = ministryName;
  }
  summary?.classList.toggle("d-none", ministryId === 0);
  form?.classList.toggle("d-none", ministryId !== 0);
}

/**
 * The ministry's teams, each with a Rename button.
 *
 * A ministry is created with one team already in it, named after the ministry
 * ("Coffee Bar" → "Coffee Bar Team"), so this list is never empty once step 1 is
 * done. Rename is offered right here because renaming that starter team — rather
 * than deleting it and making another — is the thing most coordinators want, and
 * the API refuses to delete a ministry's only team anyway.
 */
function renderTeams(): void {
  const list = byId<HTMLUListElement>("setup-team-list");
  if (!list) {
    return;
  }

  list.textContent = "";
  for (const team of teams) {
    const item = document.createElement("li");
    item.className = "list-group-item d-flex align-items-center justify-content-between gap-2";

    const label = document.createElement("span");
    label.className = "flex-fill text-truncate";
    label.textContent = team.name;

    const actions = document.createElement("span");
    actions.className = "d-flex align-items-center gap-2";

    const badge = document.createElement("span");
    badge.className = "badge bg-blue-lt";
    badge.textContent = i18next.t("{{count}} positions", { count: team.positionCount });

    const rename = document.createElement("button");
    rename.type = "button";
    rename.className = "btn btn-sm btn-ghost-secondary volunteer-team-rename";
    rename.dataset.teamId = String(team.id);
    rename.textContent = i18next.t("Rename");

    actions.append(badge, rename);
    item.append(label, actions);
    list.append(item);
  }
  show(list, teams.length > 0);

  // The position form's team picker is fed from the same list. There is no
  // "no team" entry: a position always belongs to a team, and the ministry
  // always has one to offer.
  const select = byId<HTMLSelectElement>("setup-position-team");
  if (select) {
    const previous = select.value;
    select.textContent = "";
    for (const team of teams) {
      const option = document.createElement("option");
      option.value = String(team.id);
      option.textContent = team.name;
      select.append(option);
    }
    // Keep the coordinator's choice across a re-render, but never leave the
    // picker on a value that is no longer in the list.
    select.value = teams.some((team) => String(team.id) === previous) ? previous : String(teams[0]?.id ?? "");
  }
}

function renderPositions(positions: VolunteerPosition[]): void {
  const list = byId<HTMLUListElement>("setup-position-list");
  if (!list) {
    return;
  }

  list.textContent = "";
  for (const position of positions) {
    const item = document.createElement("li");
    item.className = "list-group-item d-flex align-items-center justify-content-between";
    const label = document.createElement("span");
    label.textContent = position.name;
    const scope = document.createElement("span");
    scope.className = "badge bg-azure-lt";
    scope.textContent = position.teamName ?? "";
    item.append(label, scope);
    list.append(item);
  }
  show(list, positions.length > 0);
}

/**
 * Load what already exists for the chosen ministry and unlock the rest of the
 * flow. Runs on a resume (`?ministryId=`) as well as straight after a create, so
 * both paths land in the same state.
 */
async function adoptMinistry(id: number, name: string): Promise<void> {
  ministryId = id;
  ministryName = name;
  renderMinistrySummary();
  setStepEnabled(TEAM_CONTROLS, true);
  setStepEnabled(POSITION_CONTROLS, true);

  const nextCard = byId("setup-step-next");
  show(nextCard, true);
  const openLink = byId<HTMLAnchorElement>("setup-open-ministry");
  if (openLink) {
    openLink.href = `${window.CRM?.root ?? ""}/volunteer/ministries/${id}`;
  }

  show(byId("setup-team-loading"), true);
  show(byId("setup-position-loading"), true);
  try {
    const [teamResult, positionResult] = await Promise.all([listTeams(id), listPositions(id)]);
    teams = teamResult.teams;
    renderTeams();
    renderPositions(positionResult.positions);
  } catch (error) {
    showStepError("setup-team", errorMessage(error, i18next.t("Could not load this ministry")));
  } finally {
    show(byId("setup-team-loading"), false);
    show(byId("setup-position-loading"), false);
  }
}

function wireMinistryStep(): void {
  byId("setup-ministry-save")?.addEventListener("click", () => {
    const nameInput = byId<HTMLInputElement>("setup-ministry-name");
    const descriptionInput = byId<HTMLInputElement>("setup-ministry-description");
    const name = nameInput?.value.trim() ?? "";

    if (name === "") {
      showStepError("setup-ministry", i18next.t("Give the ministry a name to carry on"));
      return;
    }

    clearStepError("setup-ministry");
    createMinistry(name, descriptionInput?.value.trim() ?? "")
      .then((result) => {
        notifySuccess(i18next.t("Ministry created"));
        // Creating a ministry is manager-only and grants the creator no scope
        // row (§4.4), so the note says where to grant one to somebody ELSE. It
        // is shown only on this path — picking an existing ministry says
        // nothing about who may run it.
        show(byId("setup-ministry-scope-note"), true);

        return adoptMinistry(result.ministry.id, result.ministry.name);
      })
      .catch((error: unknown) => {
        showStepError("setup-ministry", errorMessage(error, i18next.t("The ministry could not be created")));
      });
  });

  byId("setup-ministry-choose")?.addEventListener("click", () => {
    const select = byId<HTMLSelectElement>("setup-ministry-existing");
    const chosen = Number(select?.value ?? "");
    if (!chosen) {
      showStepError("setup-ministry", i18next.t("Choose a ministry to carry on"));
      return;
    }
    clearStepError("setup-ministry");
    const label = select?.options[select.selectedIndex]?.text.trim() ?? "";
    void adoptMinistry(chosen, label);
  });

  // "Edit" reopens the step; it does not undo anything already created.
  byId("setup-ministry-edit")?.addEventListener("click", () => {
    ministryId = 0;
    ministryName = "";
    show(byId("setup-ministry-scope-note"), false);
    renderMinistrySummary();
    setStepEnabled(TEAM_CONTROLS, false);
    setStepEnabled(POSITION_CONTROLS, false);
    show(byId("setup-step-next"), false);
  });
}

/**
 * Rename a team from the wizard's list. Delegated on the list itself, because
 * `renderTeams()` replaces its rows wholesale on every change.
 */
function wireTeamRename(): void {
  byId("setup-team-list")?.addEventListener("click", (event) => {
    const button = (event.target as HTMLElement | null)?.closest<HTMLElement>(".volunteer-team-rename");
    if (!button) {
      return;
    }

    const teamId = Number(button.dataset.teamId ?? 0);
    const team = teams.find((candidate) => candidate.id === teamId);
    const bootbox = window.bootbox;
    if (!teamId || !team || !bootbox?.prompt) {
      return;
    }

    bootbox.prompt({
      title: i18next.t("Rename the team"),
      value: team.name,
      callback: (result: string | null) => {
        const name = (result ?? "").trim();
        if (name === "" || name === team.name) {
          return;
        }

        clearStepError("setup-team");
        updateTeam(teamId, { name })
          .then((response) => {
            teams = teams.map((candidate) =>
              candidate.id === teamId ? { ...candidate, name: response.team.name } : candidate,
            );
            renderTeams();
            notifySuccess(i18next.t("Team renamed"));
          })
          .catch((error: unknown) => {
            showStepError("setup-team", errorMessage(error, i18next.t("The team could not be renamed")));
          });
      },
    });
  });
}

function wireTeamStep(): void {
  wireTeamRename();

  byId("setup-team-save")?.addEventListener("click", () => {
    const nameInput = byId<HTMLInputElement>("setup-team-name");
    const descriptionInput = byId<HTMLInputElement>("setup-team-description");
    const name = nameInput?.value.trim() ?? "";

    if (ministryId === 0 || name === "") {
      showStepError("setup-team", i18next.t("Give the team a name to add it"));
      return;
    }

    clearStepError("setup-team");
    createTeam(ministryId, name, descriptionInput?.value.trim() ?? "")
      .then((result) => {
        teams = [...teams, result.team];
        renderTeams();
        notifySuccess(i18next.t("Team added"));
        if (nameInput) {
          nameInput.value = "";
        }
        if (descriptionInput) {
          descriptionInput.value = "";
        }
      })
      .catch((error: unknown) => {
        showStepError("setup-team", errorMessage(error, i18next.t("The team could not be added")));
      });
  });
}

function wirePositionStep(): void {
  byId("setup-position-save")?.addEventListener("click", () => {
    const nameInput = byId<HTMLInputElement>("setup-position-name");
    const descriptionInput = byId<HTMLInputElement>("setup-position-description");
    const teamSelect = byId<HTMLSelectElement>("setup-position-team");
    const list = byId<HTMLUListElement>("setup-position-list");
    const name = nameInput?.value.trim() ?? "";

    if (ministryId === 0 || name === "") {
      showStepError("setup-position", i18next.t("Give the position a name to add it"));
      return;
    }
    if (!teamSelect?.value) {
      showStepError("setup-position", i18next.t("Choose the team this position serves on"));
      return;
    }

    clearStepError("setup-position");
    createPosition(ministryId, {
      name,
      description: descriptionInput?.value.trim() ?? "",
      teamId: Number(teamSelect?.value ?? 0),
      // Appended in the order the coordinator typed them, which is almost always
      // the order they want to see them in (§2.6 vpos_Order).
      order: (list?.childElementCount ?? 0) + 1,
    })
      .then((result) => {
        const item = document.createElement("li");
        item.className = "list-group-item d-flex align-items-center justify-content-between";
        const label = document.createElement("span");
        label.textContent = result.position.name;
        const scope = document.createElement("span");
        scope.className = "badge bg-azure-lt";
        scope.textContent = result.position.teamName ?? "";
        item.append(label, scope);
        list?.append(item);
        show(list, true);
        notifySuccess(i18next.t("Position added"));
        if (nameInput) {
          nameInput.value = "";
        }
        if (descriptionInput) {
          descriptionInput.value = "";
        }
      })
      .catch((error: unknown) => {
        showStepError("setup-position", errorMessage(error, i18next.t("The position could not be added")));
      });
  });
}

function init(): void {
  const config = (window.CRM?.volunteerSetup ?? { isManager: false, ministryId: 0 }) as SetupConfig;

  wireMinistryStep();
  wireTeamStep();
  wirePositionStep();

  if (config.ministryId > 0) {
    const select = byId<HTMLSelectElement>("setup-ministry-existing");
    const label = select?.options[select.selectedIndex]?.text.trim() ?? "";
    void adoptMinistry(config.ministryId, label);
  }
}

document.addEventListener("DOMContentLoaded", () => {
  // i18next.t() returns undefined on a non-en_US locale until the catalog has
  // loaded (upstream #9609), so nothing here runs before it is ready.
  if (window.CRM?.onLocalesReady) {
    window.CRM.onLocalesReady(init);
  } else {
    init();
  }
});
