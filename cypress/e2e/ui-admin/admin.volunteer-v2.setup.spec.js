/// <reference types="cypress" />

/**
 * Volunteer v2 — the guided setup flow and the ministry detail page (#9715).
 *
 * Design §5.3 (S2 setup flow) and §5.4 (S3 ministry detail), plus the §5.8
 * state-handling rules every V2 screen must satisfy: a loading block on every
 * attempt, a first-class empty state, an error block with a retry, and toasts
 * through `window.CRM.notify`.
 *
 * Scope note: steps 1–3 of §5.3 that this issue owns are Ministry → Team →
 * Positions. The volunteer pool (step 3 in the design's numbering) and the
 * qualification matrix arrive with #9707; the schedule, staffing and generate
 * steps with #9708/#9711. The flow is built so those slot in without moving
 * what is here.
 *
 * Order inside every hook is API setup → freshAdminLogin() → cy.visit(),
 * because cy.request() rotates the PHP session cookie (cypress-testing.md).
 * Fixture rows are removed in `before` as well as `after`: an `after` hook does
 * not run when the runner crashes mid-spec.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const SETUP_URL = "/volunteer/setup";
const DASHBOARD_URL = "/volunteer/dashboard";

const PREFIX = "UI9715";
const MINISTRY_NAME = `${PREFIX} Coffee Bar`;
const TEAM_NAME = `${PREFIX} Coffee Bar Team`;
const POSITION_ONE = `${PREFIX} Espresso`;
const POSITION_TWO = `${PREFIX} Milk Station`;

// Local helper — NOT a cy.* command (cypress-testing.md).
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
    cy.url().should("not.include", "/session/begin");
}

function setVersion(value) {
    cy.makePrivateAdminAPICall("POST", SETTING_URL, { value }, 200);
}

/**
 * Delete every ministry this spec could have made, through the API.
 *
 * Deliberately NOT `cy.dbQuery()`: the `db:query` task is registered only in
 * `cypress/configs/docker.config.ts`, so a ui-admin spec that calls it fails
 * with "task not handled" (the config drift the design notes as E-11). Teams and
 * positions cascade from the ministry row, so one DELETE per ministry is enough
 * — and it exercises the very endpoint under test, which is a feature here.
 */
function cleanupFixtures() {
    cy.makePrivateAdminAPICall("GET", "/api/volunteer/ministries", null, 200).then(
        (resp) => {
            for (const ministry of resp.body.ministries) {
                if (ministry.name.startsWith(PREFIX)) {
                    cy.makePrivateAdminAPICall(
                        "DELETE",
                        `/api/volunteer/ministries/${ministry.id}`,
                        null,
                        [200, 404],
                    );
                }
            }
        },
    );
}

describe("Volunteer v2 setup flow and ministry page (#9715)", () => {
    before(() => {
        setVersion("v2");
        cleanupFixtures();
    });

    after(() => {
        cleanupFixtures();
        setVersion("v1");
    });

    describe("S2 — the guided setup flow", () => {
        before(() => {
            cleanupFixtures();
        });

        // Every test logs in again: test isolation clears cookies between tests,
        // and `cy.request()` rotates the PHP session cookie anyway
        // (cypress-testing.md), so a login in `before` is gone by the second test.
        beforeEach(() => {
            freshAdminLogin();
        });

        it("is reachable from the Volunteer menu and from the dashboard", () => {
            cy.visit(DASHBOARD_URL);
            cy.get('a[href$="/volunteer/setup"]').should("exist");
        });

        it("walks ministry → team → positions and lands on the ministry page", () => {
            cy.visit(SETUP_URL);

            // Step 1 — ministry. Manager-only; an administrator is a manager.
            cy.get("#setup-step-ministry").should("be.visible");
            cy.get("#setup-ministry-name").type(MINISTRY_NAME);
            cy.get("#setup-ministry-description").type("UC1 worked example");
            cy.get("#setup-ministry-save").click();
            cy.get("#setup-step-ministry .setup-step-summary")
                .should("be.visible")
                .and("contain", MINISTRY_NAME);

            // Step 2 — team. Locked until a ministry exists.
            cy.get("#setup-team-name").should("not.be.disabled").type(TEAM_NAME);
            cy.get("#setup-team-save").click();
            cy.get("#setup-team-list").should("contain", TEAM_NAME);

            // Step 3 — positions, repeatable.
            cy.get("#setup-position-name").should("not.be.disabled").type(POSITION_ONE);
            cy.get("#setup-position-description").type("Pulls shots");
            cy.get("#setup-position-save").click();
            cy.get("#setup-position-list").should("contain", POSITION_ONE);

            cy.get("#setup-position-name").clear().type(POSITION_TWO);
            cy.get("#setup-position-save").click();
            cy.get("#setup-position-list").should("contain", POSITION_TWO);

            // "What next" — the links that carry the coordinator out of the wizard.
            cy.get("#setup-step-next").should("be.visible");
            cy.get("#setup-open-ministry").should("be.visible").click();
            cy.url().should("match", /\/volunteer\/ministries\/\d+$/);
        });

        it("reports a duplicate ministry name instead of silently failing", () => {
            cy.visit(SETUP_URL);
            cy.get("#setup-ministry-name").type(MINISTRY_NAME);
            cy.get("#setup-ministry-save").click();
            cy.get(".notyf, .notyf__toast, #setup-ministry-error")
                .should("exist");
            cy.get("#setup-step-ministry").should("be.visible");
        });

        it("locks the later steps until a ministry has been chosen", () => {
            // Opening /volunteer/setup without ?ministryId= always starts at step
            // one, whatever already exists — so no fixture teardown is needed, and
            // none is done: a cy.request here would rotate the session cookie out
            // from under the login this test already has (cypress-testing.md).
            cy.visit(SETUP_URL);
            cy.get("#setup-team-name").should("be.disabled");
            cy.get("#setup-position-name").should("be.disabled");
        });

        it("renders every label through gettext/i18next, never a raw key", () => {
            cy.visit(SETUP_URL);
            cy.contains("Set up volunteer scheduling").should("be.visible");
            // A raw translation call rendered as text means the string was written
            // where no extractor looks (design §5.10, F31) — the page must never
            // show the call itself, nor an unsubstituted {{placeholder}}.
            cy.get("#volunteer-setup").should("not.contain", "i18next");
            cy.get("#volunteer-setup").invoke("text").should("not.match", /\{\{[a-z]+\}\}/);
        });
    });

    describe("S3 — the ministry detail page", () => {
        let ministryId = 0;
        let emptyMinistryId = 0;

        before(() => {
            cleanupFixtures();
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/volunteer/ministries",
                { name: MINISTRY_NAME, description: "UC1 worked example" },
                201,
            ).then((resp) => {
                ministryId = resp.body.ministry.id;
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/volunteer/ministries/${ministryId}/teams`,
                    { name: TEAM_NAME },
                    201,
                );
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/volunteer/ministries/${ministryId}/positions`,
                    { name: POSITION_ONE, description: "Pulls shots" },
                    201,
                );
            });
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/volunteer/ministries",
                { name: `${PREFIX} Empty Ministry` },
                201,
            ).then((resp) => {
                emptyMinistryId = resp.body.ministry.id;
            });
        });

        beforeEach(() => {
            freshAdminLogin();
        });

        it("renders the tab strip with Overview, Teams and Positions", () => {
            cy.visit(`/volunteer/ministries/${ministryId}`);
            cy.get("#volunteer-ministry-tabs").should("be.visible");
            cy.get("#nav-item-overview").should("contain", "Overview");
            cy.get("#nav-item-teams").should("contain", "Teams");
            cy.get("#nav-item-positions").should("contain", "Positions");
            cy.contains(MINISTRY_NAME).should("be.visible");
        });

        it("loads the Teams tab lazily and shows its rows with an action menu", () => {
            cy.visit(`/volunteer/ministries/${ministryId}`);
            cy.get("#nav-item-teams").click();
            cy.get("#teams .volunteer-loading").should("not.be.visible");
            cy.get("#volunteerTeamsTable").should("be.visible");
            cy.get("#volunteerTeamsTable").should("contain", TEAM_NAME);
            cy.get("#volunteerTeamsTable tbody tr")
                .first()
                .find("button[data-bs-toggle=dropdown]")
                .should("exist")
                .click();
            cy.get("#volunteerTeamsTable .dropdown-menu")
                .first()
                .should("be.visible")
                .and("contain", "Edit");
        });

        it("loads the Positions tab and shows the active badge", () => {
            cy.visit(`/volunteer/ministries/${ministryId}`);
            cy.get("#nav-item-positions").click();
            cy.get("#volunteerPositionsTable").should("be.visible");
            cy.get("#volunteerPositionsTable").should("contain", POSITION_ONE);
            cy.get("#volunteerPositionsTable").should("contain", "Active");
        });

        it("adds a position from the tab and shows a success toast", () => {
            cy.visit(`/volunteer/ministries/${ministryId}`);
            cy.get("#nav-item-positions").click();
            cy.get("#position-add-btn").should("be.visible").click();
            cy.get("#positionModal").should("be.visible");
            // Wait for the modal to hand focus to the first field before typing:
            // "visible" is true partway through Bootstrap's 150 ms fade, and
            // focus moving mid-type silently truncates the value.
            cy.get("#position-form-name").should("be.focused").type(POSITION_TWO);
            cy.get("#position-form-save").click();
            cy.get("#volunteerPositionsTable").should("contain", POSITION_TWO);
        });

        it("shows a first-class empty state on a ministry with nothing in it", () => {
            cy.visit(`/volunteer/ministries/${emptyMinistryId}`);
            cy.get("#nav-item-teams").click();
            cy.get("#teams .empty").should("be.visible");
            cy.get("#teams .empty-title").should("be.visible");
            cy.get("#nav-item-positions").click();
            cy.get("#positions .empty").should("be.visible");
        });

        it("404s a ministry that does not exist", () => {
            cy.visit("/volunteer/ministries/99999999", {
                failOnStatusCode: false,
            });
            cy.contains(/not found|Access Denied/i).should("exist");
        });
    });
});
