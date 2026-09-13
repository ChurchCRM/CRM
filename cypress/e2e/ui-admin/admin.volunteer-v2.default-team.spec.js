/// <reference types="cypress" />

/**
 * Volunteer v2 — the screens behind "every ministry has at least one team" (#9701).
 *
 * The product decision, on the UI side:
 *
 *   - The setup wizard's team step shows the team the ministry was born with,
 *     says so in one sentence, and offers Rename beside it.
 *   - "Whole ministry" is gone from the position editor and from the schedule
 *     form, because a position or schedule with no team no longer exists.
 *   - Where positions from more than one team can appear side by side — the
 *     qualification matrix with the team filter on "All teams" — the column is
 *     labelled "{Team} · {Position}", which is the whole point: two teams under
 *     "Children's Ministry" may both have a "Lead Teacher" and the coordinator
 *     has to be able to tell them apart.
 *
 * Order inside every hook is API setup → freshAdminLogin() → cy.visit(), because
 * cy.request() rotates the PHP session cookie (cypress-testing.md). Fixtures are
 * removed in `before` as well as `after`: an `after` hook does not run when the
 * runner crashes mid-spec. No cy.dbQuery() here — the `db:query` task is
 * registered only in cypress/configs/docker.config.ts, and ui-admin specs run
 * under docker-admin.config.ts.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const MINISTRIES_URL = "/api/volunteer/ministries";
const SETUP_URL = "/volunteer/setup";

const PREFIX = "UIDEFTEAM";
const MINISTRY_NAME = `${PREFIX} Childrens Ministry`;
const SHARED_POSITION = `${PREFIX} Lead Teacher`;
const TEAM_ELEMENTARY = `${PREFIX} Elementary`;
const TEAM_NURSERY = `${PREFIX} Nursery`;
/** Seed group 1, "Angels class" — the matrix needs people, and a pool is where they come from. */
const GROUP_ANGELS_ID = 1;

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

function cleanupFixtures() {
    cy.makePrivateAdminAPICall("GET", MINISTRIES_URL, null, 200).then((resp) => {
        for (const ministry of resp.body.ministries) {
            if (ministry.name.startsWith(PREFIX)) {
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `${MINISTRIES_URL}/${ministry.id}`,
                    null,
                    [200, 404],
                );
            }
        }
    });
}

describe("Volunteer v2 — every ministry has at least one team, on screen (#9701)", () => {
    before(() => {
        setVersion("v2");
        cleanupFixtures();
    });

    after(() => {
        cleanupFixtures();
        setVersion("v1");
    });

    describe("The setup wizard's team step", () => {
        beforeEach(() => {
            cleanupFixtures();
            freshAdminLogin();
        });

        it("shows the team the ministry was just born with, and explains it", () => {
            cy.visit(SETUP_URL);
            cy.get("#setup-ministry-name").type(`${PREFIX} Coffee Bar`);
            cy.get("#setup-ministry-save").click();
            cy.get("#setup-step-ministry .setup-step-summary").should("be.visible");

            // The auto-created team is already listed, named after the ministry.
            cy.get("#setup-team-list").should("be.visible");
            cy.get("#setup-team-list").should("contain", `${PREFIX} Coffee Bar Team`);
            cy.get("#setup-step-team").should(
                "contain",
                "Every ministry has at least one team",
            );
        });

        it("offers Rename on that team and renames it in place", () => {
            cy.visit(SETUP_URL);
            cy.get("#setup-ministry-name").type(`${PREFIX} Coffee Bar`);
            cy.get("#setup-ministry-save").click();
            cy.get("#setup-team-list").should("contain", `${PREFIX} Coffee Bar Team`);

            cy.get("#setup-team-list .volunteer-team-rename").first().click();
            cy.get(".bootbox input").clear().type(`${PREFIX} Sunday Crew`);
            cy.get(".bootbox .btn-primary").click();
            cy.get("#setup-team-list").should("contain", `${PREFIX} Sunday Crew`);
        });

        it("preselects a team in the position step and offers no team-less option", () => {
            cy.visit(SETUP_URL);
            cy.get("#setup-ministry-name").type(`${PREFIX} Coffee Bar`);
            cy.get("#setup-ministry-save").click();
            cy.get("#setup-team-list").should("contain", `${PREFIX} Coffee Bar Team`);

            cy.get("#setup-position-team option").should("have.length.at.least", 1);
            cy.get("#setup-position-team").should("not.contain", "Whole ministry");
            cy.get("#setup-position-team")
                .find("option[value='']")
                .should("not.exist");
            cy.get("#setup-position-team").invoke("val").should("not.eq", "");
        });
    });

    describe("The ministry page's editors", () => {
        let ministryId = 0;
        let elementaryId = 0;
        let nurseryId = 0;

        before(() => {
            cleanupFixtures();
            cy.makePrivateAdminAPICall(
                "POST",
                MINISTRIES_URL,
                { name: MINISTRY_NAME },
                201,
            ).then((resp) => {
                ministryId = resp.body.ministry.id;

                // Rename the auto-created team, then add a second one — UC4's two
                // teams that both have a "Lead Teacher".
                cy.makePrivateAdminAPICall(
                    "GET",
                    `${MINISTRIES_URL}/${ministryId}`,
                    null,
                    200,
                ).then((detail) => {
                    elementaryId = detail.body.teams[0].id;
                    cy.makePrivateAdminAPICall(
                        "POST",
                        `/api/volunteer/teams/${elementaryId}`,
                        { name: TEAM_ELEMENTARY },
                        200,
                    );
                    cy.makePrivateAdminAPICall(
                        "POST",
                        `${MINISTRIES_URL}/${ministryId}/teams`,
                        { name: TEAM_NURSERY },
                        201,
                    ).then((team) => {
                        nurseryId = team.body.team.id;

                        cy.makePrivateAdminAPICall(
                            "POST",
                            `${MINISTRIES_URL}/${ministryId}/positions`,
                            { name: SHARED_POSITION, teamId: elementaryId, order: 1 },
                            201,
                        );
                        cy.makePrivateAdminAPICall(
                            "POST",
                            `${MINISTRIES_URL}/${ministryId}/positions`,
                            { name: SHARED_POSITION, teamId: nurseryId, order: 2 },
                            201,
                        );
                        // The matrix draws nothing without people, and people come
                        // from a linked pool Group (design D1).
                        cy.makePrivateAdminAPICall(
                            "POST",
                            `${MINISTRIES_URL}/${ministryId}/pools`,
                            { groupId: GROUP_ANGELS_ID },
                            [200, 201],
                        );
                    });
                });
            });
        });

        beforeEach(() => {
            freshAdminLogin();
        });

        it("has no 'Whole ministry' choice in the position editor", () => {
            cy.visit(`/volunteer/ministries/${ministryId}`);
            cy.get("#nav-item-positions").click();
            cy.get("#position-add-btn").should("be.visible").click();
            cy.get("#positionModal").should("be.visible");
            cy.get("#position-form-team").should("not.contain", "Whole ministry");
            cy.get("#position-form-team")
                .find("option[value='']")
                .should("not.exist");
            cy.get("#position-form-team").should("contain", TEAM_ELEMENTARY);
            cy.get("#position-form-team").should("contain", TEAM_NURSERY);
        });

        it("names a team on every row of the positions table", () => {
            cy.visit(`/volunteer/ministries/${ministryId}`);
            cy.get("#nav-item-positions").click();
            cy.get("#volunteerPositionsTable").should("be.visible");
            cy.get("#volunteerPositionsTable").should("not.contain", "Whole ministry");
            cy.get("#volunteerPositionsTable").should("contain", TEAM_ELEMENTARY);
            cy.get("#volunteerPositionsTable").should("contain", TEAM_NURSERY);
        });

        it("requires a team on the schedule form and offers no team-less option", () => {
            cy.visit(`/volunteer/ministries/${ministryId}`);
            cy.get("#nav-item-schedules").click();
            cy.get("#schedule-add-btn").should("be.visible").click();
            cy.get("#scheduleModal").should("be.visible");
            cy.get("#schedule-form-team").should("not.contain", "Whole ministry");
            cy.get("#schedule-form-team")
                .find("option[value='']")
                .should("not.exist");
            cy.get("#schedule-form-team").invoke("val").should("not.eq", "");
        });

        it("labels matrix columns '{Team} · {Position}' when two teams share a name", () => {
            cy.visit(`/volunteer/ministries/${ministryId}`);
            cy.get("#nav-item-qualifications").click();
            // The filter defaults to every team, which is exactly the view that used
            // to show "Lead Teacher" twice with nothing to tell them apart.
            cy.get("#qualification-team-filter").should("have.value", "");
            cy.get("#volunteerQualificationsTable thead").should(
                "contain",
                `${TEAM_ELEMENTARY} · ${SHARED_POSITION}`,
            );
            cy.get("#volunteerQualificationsTable thead").should(
                "contain",
                `${TEAM_NURSERY} · ${SHARED_POSITION}`,
            );
        });

        it("drops the prefix once the filter names one team", () => {
            cy.visit(`/volunteer/ministries/${ministryId}`);
            cy.get("#nav-item-qualifications").click();
            cy.get("#volunteerQualificationsTable thead").should("contain", "·");
            cy.get("#qualification-team-filter").select(String(nurseryId));
            cy.get("#volunteerQualificationsTable thead").should(
                "not.contain",
                `${TEAM_ELEMENTARY} · `,
            );
        });

        it("refuses to delete the only team, and says to rename it instead", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                MINISTRIES_URL,
                { name: `${PREFIX} Solo` },
                201,
            ).then((resp) => {
                const soloId = resp.body.ministry.id;
                freshAdminLogin();
                cy.visit(`/volunteer/ministries/${soloId}`);
                cy.get("#nav-item-teams").click();
                // Wait for the lazy tab load AND the DataTables init before touching a
                // row menu: a click landing mid-init is thrown away with the row that
                // DataTables replaces (cypress-testing.md).
                cy.get("#teams .volunteer-loading").should("not.be.visible");
                cy.get("#volunteerTeamsTable").should("be.visible");
                cy.get("#volunteerTeamsTable tbody tr").should("have.length", 1);
                cy.get("#volunteerTeamsTable tbody tr")
                    .first()
                    .find("button[data-bs-toggle=dropdown]")
                    .click();
                cy.get("#volunteerTeamsTable .dropdown-menu")
                    .first()
                    .should("be.visible")
                    .find(".volunteer-team-delete")
                    .click();
                cy.get(".bootbox .btn-danger").click();
                // The 409's own message is surfaced verbatim, because it names the
                // way out.
                cy.contains("A ministry needs at least one team").should("exist");
                cy.get("#volunteerTeamsTable tbody tr").should("have.length", 1);
            });
        });
    });
});
