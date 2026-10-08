/// <reference types="cypress" />

/**
 * Volunteer v2 — granting coordinator and team-leader authority from the UI
 * (#9706's API, design §2.15 and §4.4).
 *
 * The scope API shipped manager-only and with no screen behind it: authority
 * could only be granted with a REST client or a SQL insert. This spec covers the
 * two screens that close that gap, which the product owner split in two: the
 * "Ministry Coordinators" card on the Overview tab, and — because a leader is a
 * property of a TEAM — a **Team leader** field inside the Add team and Edit team
 * dialogs. There is no team-leader table, no team select, and (since the second
 * round of product-owner changes) no standalone "Set Team Leader" modal and no
 * row menu items that open one.
 *
 * Two halves, and the second is the one that matters:
 *
 *   1. an administrator grants a ministry coordinator through the card and a team
 *      leader through the team dialog, sees both listed, is told when a
 *      coordinator grant already exists, and clears the leader by emptying the
 *      field;
 *   2. the person who was granted the coordinator scope — Tony Campbell,
 *      person 3, who holds no admin and no volunteer-manager flag — logs in,
 *      finds the Volunteer menu and the ministry page genuinely open to him, and
 *      chooses the team's leader himself (§4.6, #10375).
 *      Without (2) the first half only proves that rows were written.
 *
 * The second case deliberately depends on the grant the first case made through
 * the UI rather than re-granting it over the API: that dependency is the proof.
 *
 * Order inside every hook is API setup → freshAdminLogin() → cy.visit(),
 * because cy.request() rotates the PHP session cookie (cypress-testing.md).
 * Fixture rows are removed in `before` as well as `after`: an `after` hook does
 * not run when the runner crashes mid-spec.
 *
 * No cy.dbQuery() anywhere — the `db:query` task is registered only in
 * cypress/configs/docker.config.ts, and a ui-admin spec that calls it fails with
 * "task not handled" (E-11). Every fixture is built and torn down through the
 * real endpoints, scope rows included.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/ministries";
const MINISTRIES_URL = "/ministries";

const PREFIX = "UI9706";
const MINISTRY_NAME = `${PREFIX} Hospitality`;
const TEAM_NAME = `${PREFIX} Greeters`;

/** tony.wade — every flag but Admin, and NOT a volunteer manager (usr_ManageMinistries = 0). */
const COORDINATOR_PERSON = 3;
const COORDINATOR_NAME = "Tony Campbell";
/** A second person, granted team-leader authority only. */
const LEADER_PERSON = 8;
const LEADER_NAME = "Herminia Hart";

let ministryId = 0;
let teamId = 0;

// Local helpers — NOT cy.* commands (cypress-testing.md).
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.testEnv("admin.username"));
    cy.get("input[name=Password]").type(Cypress.testEnv("admin.password") + "{enter}");
    // One retry: under CI load the submit occasionally lands back on the login
    // page although the server logged the login (a lost cookie on the redirect).
    cy.url().then((url) => {
        if (url.includes("/session/begin")) {
            cy.get("input[name=User]").clear().type(Cypress.testEnv("admin.username"));
            cy.get("input[name=Password]").clear().type(Cypress.testEnv("admin.password") + "{enter}");
        }
    });
    cy.url().should("not.include", "/session/begin");
}

/** Log in as the standard user (person 3) — the person this spec makes a coordinator. */
function freshCoordinatorLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.testEnv("standard.username"));
    cy.get("input[name=Password]").type(Cypress.testEnv("standard.password") + "{enter}");
    // One retry: under CI load the submit occasionally lands back on the login
    // page although the server logged the login (a lost cookie on the redirect).
    cy.url().then((url) => {
        if (url.includes("/session/begin")) {
            cy.get("input[name=User]").clear().type(Cypress.testEnv("standard.username"));
            cy.get("input[name=Password]").clear().type(Cypress.testEnv("standard.password") + "{enter}");
        }
    });
    cy.url().should("not.include", "/session/begin");
}

function adminApi(method, url, body, expectedStatus = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, expectedStatus);
}

function setVersion(value) {
    adminApi("POST", SETTING_URL, { value }, 200);
}

/** Every scope row this spec could have granted, through the API it is testing. */
function cleanupScopes() {
    for (const personId of [COORDINATOR_PERSON, LEADER_PERSON]) {
        adminApi("GET", `${VOLUNTEER_URL}/scopes?personId=${personId}`, null, 200).then((resp) => {
            for (const scope of resp.body.scopes) {
                adminApi("DELETE", `${VOLUNTEER_URL}/scopes/${scope.id}`, null, [200, 404]);
            }
        });
    }
}

function revokeTeamLeaders() {
    adminApi("GET", `${VOLUNTEER_URL}/scopes?teamId=${teamId}`, null, 200).then((resp) => {
        for (const scope of resp.body.scopes) {
            adminApi("DELETE", `${VOLUNTEER_URL}/scopes/${scope.id}`, null, [200, 404]);
        }
    });
}

/** Teams cascade from the ministry row, so one DELETE per ministry is enough. */
function cleanupMinistries() {
    adminApi("GET", `${VOLUNTEER_URL}/ministries`, null, 200).then((resp) => {
        for (const ministry of resp.body.ministries) {
            if (ministry.name.startsWith(PREFIX)) {
                // An active ministry cannot be deleted (409): deactivate first (2026-09-17 lifecycle rule).
                adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministry.id}`, { active: false }, [200, 404]);
                adminApi("DELETE", `${VOLUNTEER_URL}/ministries/${ministry.id}`, null, [200, 404]);
            }
        }
    });
}

function cleanupFixtures() {
    cleanupScopes();
    cleanupMinistries();
}

function createFixtures() {
    adminApi(
        "POST",
        `${VOLUNTEER_URL}/ministries`,
        { name: MINISTRY_NAME, description: "Welcomes people on a Sunday" },
        201,
    ).then((resp) => {
        ministryId = resp.body.ministry.id;

        adminApi(
            "POST",
            `${VOLUNTEER_URL}/ministries/${ministryId}/teams`,
            { name: TEAM_NAME, description: "Front door" },
            201,
        ).then((teamResp) => {
            teamId = teamResp.body.team.id;
        });
    });
}

function ministryUrl() {
    return `${MINISTRIES_URL}/${ministryId}`;
}

/**
 * Drive the shared person picker (#9819): it is a TomSelect whose options come
 * from `/api/persons/search/{query}`, and whose dropdown is mounted on `body`
 * rather than inside the modal, so the option is NOT under the modal selector.
 */
function pickPerson(modalId, query, fullName) {
    cy.get(`${modalId} .ts-control`).click();
    cy.get(`${modalId} .ts-control input`).type(query);
    cy.get("body > .ts-dropdown .option", { timeout: 10000 }).contains(fullName).click();
}

/** Open the Edit team dialog from the team's row action menu. */
function openTeamEditor() {
    cy.get(`#volunteerTeamsTable tbody tr[data-team-id="${teamId}"]`)
        .find("button[data-bs-toggle=dropdown]")
        .click();
    cy.get(`#volunteerTeamsTable tbody tr[data-team-id="${teamId}"] .volunteer-team-edit`).click();
    cy.get("#teamModal").should("be.visible");
    // The picker is built on `shown.bs.modal`, so its TomSelect wrapper appearing
    // is the signal that Bootstrap's 150 ms fade has finished.
    cy.get("#teamModal .ts-wrapper").should("exist");
}

before(() => {
    cy.rememberTestEnv(["admin.username", "admin.password", "standard.username", "standard.password"]);
});

describe("Volunteer v2 coordinator and team-leader grants (#9706 UI)", () => {
    before(() => {
        setVersion("v2");
        cleanupFixtures();
        createFixtures();
    });

    after(() => {
        cleanupFixtures();
        setVersion("v1");
    });

    describe("the ministry page card", () => {
        beforeEach(() => {
            freshAdminLogin();
        });

        it("shows the card with an explanation and an empty coordinator list", () => {
            cy.visit(ministryUrl());

            cy.get("#volunteer-scope-panel").should("be.visible");
            cy.get("#volunteer-scope-help").should("contain", "coordinator");
            cy.get("#scopes-coordinators-empty").should("be.visible");
            // The card is coordinators and nothing else now: no team-leader table,
            // no "Add team leader" button, no copy about them.
            cy.get("#volunteerTeamLeadersTable").should("not.exist");
            cy.get("#scope-add-leader").should("not.exist");
            cy.get("#volunteer-scope-panel").should("not.contain", "team leader");
            // Every team of the ministry is listed on the Teams card instead.
            cy.get("#volunteerTeamsTable tbody").should("contain", TEAM_NAME);
        });

        it("grants a ministry coordinator through the person picker", () => {
            cy.visit(ministryUrl());

            cy.get("#scope-add-coordinator").click();
            cy.get("#scopeCoordinatorModal").should("be.visible");
            pickPerson("#scopeCoordinatorModal", "Tony", COORDINATOR_NAME);
            cy.get("#scope-coordinator-save").click();

            cy.get("#scopeCoordinatorModal").should("not.be.visible");
            cy.get("#volunteerCoordinatorsTable tbody")
                .should("contain", COORDINATOR_NAME)
                .find(`a[href$="/people/view/${COORDINATOR_PERSON}"]`)
                .should("exist");
            cy.get("#scopes-coordinators-empty").should("not.be.visible");
        });

        it("says so instead of granting twice when the person already coordinates", () => {
            cy.visit(ministryUrl());
            cy.get("#volunteerCoordinatorsTable tbody tr").should("have.length", 1);

            cy.get("#scope-add-coordinator").click();
            pickPerson("#scopeCoordinatorModal", "Tony", COORDINATOR_NAME);
            cy.get("#scope-coordinator-save").click();

            cy.get("#scope-coordinator-error").should("be.visible").and("contain", "already");
            cy.get("#scopeCoordinatorModal .btn-outline-secondary").click();
            cy.get("#volunteerCoordinatorsTable tbody tr").should("have.length", 1);
        });

        it("has no standalone team-leader modal and no row items that open one", () => {
            cy.visit(ministryUrl());
            cy.get("#teams-loading").should("not.be.visible");
            cy.get("#volunteerTeamsTable").should("be.visible");

            cy.get("#teamLeaderModal").should("not.exist");
            cy.get("#team-leader-save").should("not.exist");
            cy.get(`#volunteerTeamsTable tbody tr[data-team-id="${teamId}"]`)
                .find("button[data-bs-toggle=dropdown]")
                .click();
            cy.get(`#volunteerTeamsTable tbody tr[data-team-id="${teamId}"] .dropdown-menu`)
                .should("be.visible")
                .and("not.contain", "Set Team Leader")
                .and("not.contain", "Remove Team Leader");
        });

        it("sets a team leader from the Edit team dialog", () => {
            cy.visit(ministryUrl());
            cy.get("#teams-loading").should("not.be.visible");
            cy.get("#volunteerTeamsTable").should("be.visible");

            openTeamEditor();

            // The dialog that edits the team asks the leader question too, and it
            // is the only person picker in it.
            cy.get("#team-form-leader").should("exist");
            cy.get("#team-form-leader-readonly").should("not.exist");
            pickPerson("#teamModal", "Herminia", LEADER_NAME);
            cy.get("#team-form-save").click();

            cy.get("#teamModal").should("not.be.visible");
            cy.get(`#volunteerTeamsTable tbody tr[data-team-id="${teamId}"] .volunteer-team-leader-cell`)
                .should("contain", LEADER_NAME)
                .find(`a[href$="/people/view/${LEADER_PERSON}"]`)
                .should("exist");
        });

        it("opens the Edit team dialog pre-filled with the leader it already has", () => {
            cy.visit(ministryUrl());
            cy.get("#teams-loading").should("not.be.visible");
            cy.get(`#volunteerTeamsTable tbody tr[data-team-id="${teamId}"] .volunteer-team-leader-cell`)
                .should("contain", LEADER_NAME);

            openTeamEditor();
            // The shared picker renders the chosen person as a `.item` in its
            // control, which is what "pre-filled" looks like from outside.
            cy.get("#teamModal .ts-control .item").should("contain", LEADER_NAME);
            cy.get("#teamModal .btn-close").click();
        });

        it("clears the team leader by emptying the field", () => {
            cy.visit(ministryUrl());
            cy.get("#teams-loading").should("not.be.visible");
            cy.get(`#volunteerTeamsTable tbody tr[data-team-id="${teamId}"] .volunteer-team-leader-cell`)
                .should("contain", LEADER_NAME);

            openTeamEditor();
            cy.get("#teamModal .ts-control .item").should("contain", LEADER_NAME);
            cy.get("#team-form-leader-clear").click();
            cy.get("#teamModal .ts-control .item").should("not.exist");
            cy.get("#team-form-save").click();

            cy.get("#teamModal").should("not.be.visible");
            cy.get(`#volunteerTeamsTable tbody tr[data-team-id="${teamId}"] .volunteer-team-leader-cell`)
                .should("not.contain", LEADER_NAME);
            // The coordinator grant is untouched — and the next case depends on it.
            cy.get("#volunteerCoordinatorsTable tbody").should("contain", COORDINATOR_NAME);
        });
    });

    describe("what the granted coordinator can now do", () => {
        it("gives person 3 the Ministries menu and the ministry page", () => {
            freshCoordinatorLogin();

            // The coordinator entries appear only for User::isVolunteerCoordinatorEnabled(),
            // which for this person is true solely because of the scope row the UI wrote.
            // They live under the Ministries heading: the dashboard, and one
            // entry per ministry they hold a scope on — which is this one.
            cy.get('a[href$="/ministries/dashboard"]').should("exist");
            cy.get(`a[href$="${ministryUrl()}"]`).should("exist");

            cy.visit(ministryUrl());
            cy.url().should("not.include", "access-denied");
            cy.get("#volunteer-ministry").should("contain", MINISTRY_NAME);

            // Making a coordinator is manager-only (§3.2): a coordinator does not get
            // the card, and the team rows carry no leader items either.
            cy.get("#volunteer-scope-panel").should("not.exist");
            cy.get("#teams-loading").should("not.be.visible");
            cy.get("#volunteerTeamsTable").should("be.visible");
            cy.get("#volunteerTeamsTable thead").should("contain", "Team Leader");
            cy.get("#volunteerTeamsTable .volunteer-team-leader-set").should("not.exist");
            cy.get("#volunteerTeamsTable .volunteer-team-leader-remove").should("not.exist");
        });

        it("lets the coordinator set and clear the team leader from the team dialog", () => {
            const leaderCell = `#volunteerTeamsTable tbody tr[data-team-id="${teamId}"] .volunteer-team-leader-cell`;
            revokeTeamLeaders();
            freshCoordinatorLogin();
            cy.visit(ministryUrl());
            cy.get("#teams-loading").should("not.be.visible");
            cy.get(leaderCell).should("not.contain", LEADER_NAME);

            openTeamEditor();
            cy.get("#team-form-leader-readonly").should("not.exist");
            pickPerson("#teamModal", "Herminia", LEADER_NAME);
            cy.get("#team-form-save").click();
            cy.get("#teamModal").should("not.be.visible");
            cy.get(leaderCell).should("contain", LEADER_NAME);

            cy.reload();
            cy.get("#teams-loading").should("not.be.visible");
            cy.get(leaderCell).should("contain", LEADER_NAME);
            openTeamEditor();
            cy.get("#teamModal .ts-control .item").should("contain", LEADER_NAME);
            cy.get("#team-form-leader-clear").click();
            cy.get("#team-form-save").click();
            cy.get("#teamModal").should("not.be.visible");
            cy.get(leaderCell).should("not.contain", LEADER_NAME);

            adminApi("GET", `${VOLUNTEER_URL}/scopes?teamId=${teamId}`, null, 200).then((resp) => {
                expect(resp.body.scopes).to.have.length(0);
            });
        });
    });
});
