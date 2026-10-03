/// <reference types="cypress" />

/**
 * Volunteer v2 — the screens behind "every ministry has at least one team" (#9701).
 *
 * The product decision, on the UI side:
 *
 *   - "Whole ministry" is gone from the position editor and from the schedule
 *     form, because a position or schedule with no team no longer exists.
 *   - The Volunteers grid shows exactly ONE team's positions. It used to offer an
 *     "All teams" filter, and the column label carried a "{Team} · {Position}"
 *     prefix so that two "Lead Teacher" columns could be told apart; the product
 *     owner removed the option instead, so the grid starts on the first team and
 *     the prefix never appears.
 *   - The teams themselves are a card on Overview rather than a tab of their own.
 *
 * The setup wizard is gone, so the cases that drove it are gone with it: the team
 * step's "this ministry was born with a team" copy and its Rename, and the
 * wizard's own position-team select. What they were really asserting — that a
 * team is always preselected and there is no team-less option — is asserted below
 * against the ministry page's editors, which are now the only editors there are.
 *
 * Order inside every hook is API setup → freshAdminLogin() → cy.visit(), because
 * cy.request() rotates the PHP session cookie (cypress-testing.md). Fixtures are
 * removed in `before` as well as `after`: an `after` hook does not run when the
 * runner crashes mid-spec. No cy.dbQuery() here — the `db:query` task is
 * registered only in cypress/configs/docker.config.ts, and ui-admin specs run
 * under docker-admin.config.ts.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const MINISTRIES_URL = "/api/ministries/ministries";

const PREFIX = "UIDEFTEAM";
const MINISTRY_NAME = `${PREFIX} Childrens Ministry`;
const SHARED_POSITION = `${PREFIX} Lead Teacher`;
const TEAM_ELEMENTARY = `${PREFIX} Elementary`;
const TEAM_NURSERY = `${PREFIX} Nursery`;
/** The matrix needs people, and since D19 they come from the ministry's own pool Group. */
const MATRIX_PEOPLE = [4, 5, 8];

// Local helper — NOT a cy.* command (cypress-testing.md).
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
    // One retry: under CI load the submit occasionally lands back on the login
    // page although the server logged the login (a lost cookie on the redirect).
    cy.url().then((url) => {
        if (url.includes("/session/begin")) {
            cy.get("input[name=User]").clear().type(Cypress.env("admin.username"));
            cy.get("input[name=Password]").clear().type(Cypress.env("admin.password") + "{enter}");
        }
    });
    cy.url().should("not.include", "/session/begin");
}

function setVersion(value) {
    cy.makePrivateAdminAPICall("POST", SETTING_URL, { value }, 200);
}

function cleanupFixtures() {
    cy.makePrivateAdminAPICall("GET", MINISTRIES_URL, null, 200).then((resp) => {
        for (const ministry of resp.body.ministries) {
            if (ministry.name.startsWith(PREFIX)) {
                // An active ministry cannot be deleted (409): deactivate first (2026-09-17 lifecycle rule).
                cy.makePrivateAdminAPICall("POST", `${MINISTRIES_URL}/${ministry.id}`, { active: false }, [200, 404]);
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
                        `/api/ministries/teams/${elementaryId}`,
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
                        // The matrix draws nothing without people, and since D19
                        // they come from the ministry's own pool Group.
                        for (const personId of MATRIX_PEOPLE) {
                            cy.makePrivateAdminAPICall(
                                "POST",
                                `${MINISTRIES_URL}/${ministryId}/pool/${personId}`,
                                null,
                                [200, 201],
                            );
                        }
                    });
                });
            });
        });

        beforeEach(() => {
            freshAdminLogin();
        });

        it("has no 'Whole ministry' choice in the position editor", () => {
            cy.visit(`/ministries/${ministryId}`);
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
            cy.visit(`/ministries/${ministryId}`);
            cy.get("#nav-item-positions").click();
            cy.get("#volunteerPositionsTable").should("be.visible");
            cy.get("#volunteerPositionsTable").should("not.contain", "Whole ministry");
            cy.get("#volunteerPositionsTable").should("contain", TEAM_ELEMENTARY);
            cy.get("#volunteerPositionsTable").should("contain", TEAM_NURSERY);
        });

        it("requires a team on the schedule form and offers no team-less option", () => {
            cy.visit(`/ministries/${ministryId}`);
            cy.get("#nav-item-schedules").click();
            cy.get("#schedule-add-btn").should("be.visible").click();
            cy.get("#scheduleModal").should("be.visible");
            cy.get("#schedule-form-team").should("not.contain", "Whole ministry");
            cy.get("#schedule-form-team")
                .find("option[value='']")
                .should("not.exist");
            cy.get("#schedule-form-team").invoke("val").should("not.eq", "");
        });

        it("shows one team at a time, with no 'All teams' option", () => {
            cy.visit(`/ministries/${ministryId}`);
            cy.get("#nav-item-volunteers").click();
            cy.get("#volunteers .volunteer-loading").should("not.be.visible");

            // The option that used to make two "Lead Teacher" columns appear side
            // by side is gone, and the grid opens on the first team instead.
            cy.get("#qualification-team-filter").should("not.contain", "All teams");
            cy.get("#qualification-team-filter")
                .find("option[value='']")
                .should("not.exist");
            cy.get("#qualification-team-filter").should("have.value", String(elementaryId));

            // One team's columns, so the "{Team} · {Position}" prefix is noise and
            // is not drawn.
            cy.get("#volunteerQualificationsTable thead").should("contain", SHARED_POSITION);
            cy.get("#volunteerQualificationsTable thead").should("not.contain", "·");
        });

        it("swaps the columns when another team is chosen", () => {
            cy.visit(`/ministries/${ministryId}`);
            cy.get("#nav-item-volunteers").click();
            cy.get("#volunteers .volunteer-loading").should("not.be.visible");
            cy.get("#qualification-team-filter").select(String(nurseryId));
            cy.get("#volunteerQualificationsTable thead").should("contain", SHARED_POSITION);
            cy.get("#volunteerQualificationsTable thead").should("not.contain", "·");
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
                cy.visit(`/ministries/${soloId}`);
                // The teams card is on Overview, which is the tab the page opens on.
                // Wait for the load AND the DataTables init before touching a row
                // menu: a click landing mid-init is thrown away with the row that
                // DataTables replaces (cypress-testing.md).
                cy.get("#teams-loading").should("not.be.visible");
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
