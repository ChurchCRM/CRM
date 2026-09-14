/// <reference types="cypress" />

/**
 * Volunteer v2 — S3, the ministry page as the product owner restructured it
 * (epic #9701, design §5.4 as amended).
 *
 * This file replaces `admin.volunteer-v2.setup.spec.js`, which covered the guided
 * setup wizard (now deleted) and the ministry page together. Everything that was
 * still about a screen that exists moved here — the tab strip, the lazy tabs, the
 * positions editor, the qualification grid, its hints, its toggle round trip, its
 * person-picker and cart modals, the empty states and the 404 — and the wizard
 * cases went with the wizard.
 *
 * What is NEW, and is the whole reason this file exists:
 *
 *   - the tab strip is **Overview · Volunteers · Positions · Schedules ·
 *     Occurrences · Help Wanted**, in that order, with no Teams tab and no
 *     Qualifications tab;
 *   - Overview carries exactly three counts, the description, the **Teams card**
 *     (with a Team Leader column) and, under it, **Ministry Coordinators** — which
 *     has no team-leader controls or copy at all;
 *   - the Volunteers tab shows ONE team at a time and offers **Remove Volunteer**
 *     to a ministry coordinator and above;
 *   - the **Help wanted** card renders on its own tab and on no other;
 *   - `/volunteer/setup` is gone from the menu and the URL 404s;
 *   - **New ministry** on `/volunteer/ministries` creates one and lands on it.
 *
 * Order inside every hook is API setup → freshAdminLogin() → cy.visit(), because
 * cy.request() rotates the PHP session cookie (cypress-testing.md). Fixture rows
 * are removed in `before` as well as `after`: an `after` hook does not run when
 * the runner crashes mid-spec. No cy.dbQuery() here — the `db:query` task is
 * registered in docker-admin.config.ts too, but every fixture below is reachable
 * through a real endpoint, which is a feature.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/volunteer";
const MINISTRIES_URL = "/volunteer/ministries";
const DASHBOARD_URL = "/volunteer/dashboard";

const PREFIX = "UIMINPAGE";
const MINISTRY_NAME = `${PREFIX} Coffee Bar`;
/** The team a ministry is created with is named "{Ministry} Team". */
const TEAM_NAME = `${MINISTRY_NAME} Team`;
const SECOND_TEAM = `${PREFIX} Saturday Crew`;
const POSITION_ONE = `${PREFIX} Espresso`;
const POSITION_TWO = `${PREFIX} Milk Station`;
const HELP_WANTED_TEXT = `${PREFIX} we would love more help`;

/** Person 4 is toggled in the grid; person 5 is never qualified, so the hint has a row. */
const POOL_PEOPLE = [4, 5];
const POOL_NEVER_QUALIFIED = 5;
const POOL_REMOVED = 4;

/** judith.matthews — given a TEAM scope only, so she is a pure team leader. */
const LEADER_PERSON = 95;

let ministryId = 0;
let emptyMinistryId = 0;
let teamId = 0;
let positionId = 0;

// Local helpers — NOT cy.* commands (cypress-testing.md).
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
    cy.url().should("not.include", "/session/begin");
}

/** Log in as person 95, who this spec makes a leader of the ministry's only team. */
function freshLeaderLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("nofinance.username"));
    cy.get("input[name=Password]").type(Cypress.env("nofinance.password") + "{enter}");
    cy.url().should("not.include", "/session/begin");
}

function adminApi(method, url, body, expectedStatus = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, expectedStatus);
}

function setVersion(value) {
    cy.makePrivateAdminAPICall("POST", SETTING_URL, { value }, 200);
}

/** Teams, positions and the pool Group all cascade from the ministry row. */
function cleanupFixtures() {
    adminApi("GET", `${VOLUNTEER_URL}/scopes?personId=${LEADER_PERSON}`, null, 200).then(
        (resp) => {
            for (const scope of resp.body.scopes) {
                adminApi("DELETE", `${VOLUNTEER_URL}/scopes/${scope.id}`, null, [200, 404]);
            }
        },
    );
    adminApi("GET", `${VOLUNTEER_URL}/ministries`, null, 200).then((resp) => {
        for (const ministry of resp.body.ministries) {
            if (ministry.name.startsWith(PREFIX)) {
                adminApi("DELETE", `${VOLUNTEER_URL}/ministries/${ministry.id}`, null, [
                    200,
                    404,
                ]);
            }
        }
    });
}

function ministryUrl() {
    return `${MINISTRIES_URL}/${ministryId}`;
}

describe("Volunteer v2 ministry page (#9701)", () => {
    before(() => {
        setVersion("v2");
        cleanupFixtures();

        adminApi(
            "POST",
            `${VOLUNTEER_URL}/ministries`,
            { name: MINISTRY_NAME, description: "UC1 worked example" },
            201,
        ).then((resp) => {
            ministryId = resp.body.ministry.id;
            adminApi("GET", `${VOLUNTEER_URL}/ministries/${ministryId}`, null, 200).then(
                (detail) => {
                    expect(detail.body.teams[0].name).to.eq(TEAM_NAME);
                    teamId = detail.body.teams[0].id;
                    adminApi(
                        "POST",
                        `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
                        { name: POSITION_ONE, description: "Pulls shots", teamId },
                        201,
                    ).then((position) => {
                        positionId = position.body.position.id;
                    });
                    // A second team, so the Volunteers tab's team select has a real
                    // choice and "the first team is selected" means something.
                    adminApi(
                        "POST",
                        `${VOLUNTEER_URL}/ministries/${ministryId}/teams`,
                        { name: SECOND_TEAM },
                        201,
                    );
                },
            );

            for (const personId of POOL_PEOPLE) {
                adminApi(
                    "POST",
                    `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${personId}`,
                    null,
                    [200, 201],
                );
            }
        });

        adminApi(
            "POST",
            `${VOLUNTEER_URL}/ministries`,
            { name: `${PREFIX} Empty Ministry` },
            201,
        ).then((resp) => {
            emptyMinistryId = resp.body.ministry.id;
        });
    });

    after(() => {
        cleanupFixtures();
        setVersion("v1");
    });

    describe("the tab strip", () => {
        beforeEach(() => {
            freshAdminLogin();
        });

        it("names six tabs in the order the product owner chose", () => {
            cy.visit(ministryUrl());
            cy.get("#volunteer-ministry-tabs").should("be.visible");

            cy.get("#volunteer-ministry-tabs .nav-link").then(($tabs) => {
                const labels = [...$tabs].map((el) => el.textContent.trim());
                expect(labels).to.deep.eq([
                    "Overview",
                    "Volunteers",
                    "Positions",
                    "Schedules",
                    "Occurrences",
                    "Help Wanted",
                ]);
            });

            // The two tabs that were removed or renamed are genuinely gone.
            cy.get("#nav-item-teams").should("not.exist");
            cy.get("#nav-item-qualifications").should("not.exist");

            // Scoped to the card, not the whole page: since D19 the ministry also
            // owns a GROUP of the same name, which appears in the sidebar's
            // (collapsed, therefore invisible) groups menu — an unscoped
            // `cy.contains` picks that up first.
            cy.get("#volunteer-ministry .card-title")
                .should("be.visible")
                .and("contain", MINISTRY_NAME);
        });

        it("no longer offers the guided setup from the page header", () => {
            cy.visit(ministryUrl());
            cy.get('#volunteer-ministry a[href*="/volunteer/setup"]').should("not.exist");
        });
    });

    describe("the Overview tab", () => {
        beforeEach(() => {
            freshAdminLogin();
        });

        it("shows exactly three counts: Teams, Volunteers, Unfilled Positions", () => {
            cy.visit(ministryUrl());
            cy.get("#overview-content").should("be.visible");

            cy.get("#overview-team-count").should("have.text", "2");
            cy.get("#overview-volunteer-count").should("have.text", String(POOL_PEOPLE.length));
            // Nothing is scheduled yet, so nothing is unfilled.
            cy.get("#overview-unfilled-count").should("have.text", "0");

            cy.get("#overview-content").should("contain", "Teams");
            cy.get("#overview-content").should("contain", "Volunteers");
            cy.get("#overview-content").should("contain", "Unfilled Positions");
            // The counts that used to be here are gone.
            cy.get("#overview-position-count").should("not.exist");
            cy.get("#overview-active-position-count").should("not.exist");

            cy.get("#overview-description").should("contain", "UC1 worked example");
        });

        it("carries the teams card, with a Team Leader column and a row action menu", () => {
            cy.visit(ministryUrl());
            cy.get("#teams-loading").should("not.be.visible");
            cy.get("#volunteerTeamsTable").should("be.visible").and("contain", TEAM_NAME);
            cy.get("#volunteerTeamsTable thead").should("contain", "Team Leader");
            cy.get("#team-add-btn").should("be.visible").and("contain", "Add team");

            cy.get(`#volunteerTeamsTable tbody tr[data-team-id="${teamId}"]`)
                .find("button[data-bs-toggle=dropdown]")
                .click();
            cy.get(`#volunteerTeamsTable tbody tr[data-team-id="${teamId}"] .dropdown-menu`)
                .should("be.visible")
                .and("contain", "Edit")
                .and("contain", "Set Team Leader");
            // Nobody leads it yet, so the opposite item is not offered.
            cy.get(
                `#volunteerTeamsTable tbody tr[data-team-id="${teamId}"] .volunteer-team-leader-remove`,
            ).should("not.exist");
            cy.get(
                `#volunteerTeamsTable tbody tr[data-team-id="${teamId}"] .volunteer-team-leader-cell`,
            ).should("have.text", "");
        });

        it("renames the Coordinators card and strips every team-leader control from it", () => {
            cy.visit(ministryUrl());
            cy.get("#volunteer-scope-panel").should("be.visible");
            cy.get("#volunteer-scope-panel .card-title").should(
                "contain",
                "Ministry Coordinators",
            );
            cy.get("#volunteer-scope-panel").should("not.contain", "team leader");
            cy.get("#volunteer-scope-panel").should("not.contain", "Team leaders");
            cy.get("#volunteerTeamLeadersTable").should("not.exist");
            cy.get("#scope-add-leader").should("not.exist");
            cy.get("#scopeLeaderModal").should("not.exist");

            // And it sits under the teams card, nowhere else.
            cy.get("#overview #volunteer-teams-card").should("exist");
            cy.get("#overview #volunteer-scope-panel").should("exist");
        });
    });

    describe("the Volunteers tab", () => {
        beforeEach(() => {
            freshAdminLogin();
        });

        it("offers no 'All teams' option and opens on the first team", () => {
            cy.visit(ministryUrl());
            cy.get("#nav-item-volunteers").click();
            cy.get("#volunteers .volunteer-loading").should("not.be.visible");

            cy.get("#qualification-team-filter").should("not.contain", "All teams");
            cy.get("#qualification-team-filter")
                .find("option[value='']")
                .should("not.exist");
            cy.get("#qualification-team-filter").should("have.value", String(teamId));
            cy.get("#qualification-team-filter option").should("have.length", 2);
        });

        it("renders the grid with the pool people down the side and positions across the top", () => {
            cy.visit(ministryUrl());
            cy.get("#nav-item-volunteers").click();
            cy.get("#volunteers .volunteer-loading").should("not.be.visible");
            cy.get("#volunteerQualificationsTable").should("be.visible");
            cy.get("#volunteerQualificationsTable thead").should("contain", POSITION_ONE);
            cy.get("#volunteerQualificationsTable tbody tr").should(
                "have.length",
                POOL_PEOPLE.length,
            );
            cy.get(
                `#volunteerQualificationsTable input.volunteer-qual-toggle[data-position-id="${positionId}"]`,
            ).should("have.length", POOL_PEOPLE.length);
        });

        it("marks a pool member with no qualifications yet (D19)", () => {
            cy.visit(ministryUrl());
            cy.get("#nav-item-volunteers").click();
            cy.get(
                `#volunteerQualificationsTable input.volunteer-qual-toggle[data-person-id="${POOL_NEVER_QUALIFIED}"]`,
            )
                .parents("tr")
                .find(".volunteer-pool-hint")
                .should("be.visible")
                .and("contain", "In the pool, not qualified yet");
        });

        it("toggles a qualification and it survives a reload", () => {
            cy.visit(ministryUrl());
            cy.get("#nav-item-volunteers").click();
            cy.get(
                `#volunteerQualificationsTable input.volunteer-qual-toggle[data-person-id="4"][data-position-id="${positionId}"]`,
            )
                .should("not.be.checked")
                .check();

            // Optimistic UI plus a success toast; the write is confirmed by the reload.
            cy.reload();
            cy.get("#nav-item-volunteers").click();
            cy.get(
                `#volunteerQualificationsTable input.volunteer-qual-toggle[data-person-id="4"][data-position-id="${positionId}"]`,
            ).should("be.checked");

            // And back off again — revoke is a deactivation, so the box simply clears.
            cy.get(
                `#volunteerQualificationsTable input.volunteer-qual-toggle[data-person-id="4"][data-position-id="${positionId}"]`,
            ).uncheck();
            cy.reload();
            cy.get("#nav-item-volunteers").click();
            cy.get(
                `#volunteerQualificationsTable input.volunteer-qual-toggle[data-person-id="4"][data-position-id="${positionId}"]`,
            ).should("not.be.checked");
        });

        it("offers the person picker and the cart bulk-grant", () => {
            cy.visit(ministryUrl());
            cy.get("#nav-item-volunteers").click();

            cy.get("#qualification-add-person").should("be.visible").click();
            cy.get("#qualifyPersonModal").should("be.visible");
            cy.get("#qualify-person-position").should("contain", POSITION_ONE);
            // The person picker is only built on `shown.bs.modal`, so its TomSelect
            // wrapper appearing is the signal that the 150 ms fade has finished.
            cy.get("#qualifyPersonModal .ts-wrapper").should("exist");
            cy.get("#qualifyPersonModal .btn-close").click();
            cy.get("#qualifyPersonModal").should("not.be.visible");

            cy.get("#qualification-cart-btn").should("be.visible").click();
            cy.get("#qualifyCartModal").should("be.visible");
            cy.get("#qualify-cart-position").should("contain", POSITION_ONE);
        });

        it("shows a first-class empty state when the team has no positions", () => {
            cy.visit(`${MINISTRIES_URL}/${emptyMinistryId}`);
            cy.get("#nav-item-volunteers").click();
            cy.get("#volunteers-empty").should("be.visible");
            cy.get("#volunteers-empty .empty-title").should("be.visible");
        });

        it("renders every label through gettext/i18next, never a raw key", () => {
            cy.visit(ministryUrl());
            cy.get("#nav-item-volunteers").click();
            cy.get("#volunteers").should("not.contain", "i18next");
            cy.get("#volunteers").invoke("text").should("not.match", /\{\{[a-z]+\}\}/);
        });
    });

    describe("Remove Volunteer", () => {
        beforeEach(() => {
            freshAdminLogin();
        });

        it("is offered to an administrator and takes the person out of the ministry", () => {
            cy.visit(ministryUrl());
            cy.get("#nav-item-volunteers").click();
            cy.get("#volunteers .volunteer-loading").should("not.be.visible");
            cy.get("#volunteerQualificationsTable thead").should("contain", "Actions");

            cy.get(
                `#volunteerQualificationsTable .volunteer-remove-volunteer[data-person-id="${POOL_REMOVED}"]`,
            )
                .parents(".dropdown")
                .find("[data-bs-toggle='dropdown']")
                .click();
            cy.get(
                `#volunteerQualificationsTable .volunteer-remove-volunteer[data-person-id="${POOL_REMOVED}"]`,
            ).click();

            cy.get(".bootbox")
                .should("be.visible")
                .and("contain", "removes them from the volunteer pool");
            cy.get(".bootbox .btn-danger").click();

            cy.get("#volunteerQualificationsTable tbody tr").should(
                "have.length",
                POOL_PEOPLE.length - 1,
            );
            // The Overview count followed them out.
            cy.get("#nav-item-overview").click();
            cy.get("#overview-volunteer-count").should(
                "have.text",
                String(POOL_PEOPLE.length - 1),
            );
        });

        it("is out of reach for a team leader, who cannot open the page at all", () => {
            // A team scope does not reach the ministry page — it is ministry-scoped
            // (§4.6), so a pure team leader is sent to access-denied and never sees
            // the menu item. The API refuses them independently; that 403 is pinned
            // in private.volunteer.ministry-page.spec.js.
            adminApi(
                "POST",
                `${VOLUNTEER_URL}/scopes`,
                { personId: LEADER_PERSON, scopeType: "team", scopeId: teamId },
                [200, 201],
            );

            freshLeaderLogin();
            cy.visit(ministryUrl());
            cy.url().should("include", "access-denied");
            cy.get(".volunteer-remove-volunteer").should("not.exist");
        });
    });

    describe("the Positions tab", () => {
        beforeEach(() => {
            freshAdminLogin();
        });

        it("loads lazily and shows the active badge", () => {
            cy.visit(ministryUrl());
            cy.get("#nav-item-positions").click();
            cy.get("#volunteerPositionsTable").should("be.visible");
            cy.get("#volunteerPositionsTable").should("contain", POSITION_ONE);
            cy.get("#volunteerPositionsTable").should("contain", "Active");
        });

        it("adds a position from the tab", () => {
            cy.visit(ministryUrl());
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

        it("shows a first-class empty state on a ministry with no positions", () => {
            cy.visit(`${MINISTRIES_URL}/${emptyMinistryId}`);
            cy.get("#nav-item-positions").click();
            cy.get("#positions .empty").should("be.visible");
            cy.get("#positions .empty-title").should("be.visible");
        });
    });

    describe("the Help Wanted tab", () => {
        beforeEach(() => {
            freshAdminLogin();
        });

        it("renders the card on its own tab and on no other", () => {
            cy.visit(ministryUrl());
            // It used to sit outside the tab content, so it painted under every tab.
            cy.get("#volunteer-help-wanted").should("not.be.visible");

            cy.get("#nav-item-positions").click();
            cy.get("#volunteer-help-wanted").should("not.be.visible");

            cy.get("#nav-item-help-wanted").click();
            cy.get("#volunteer-help-wanted").should("be.visible");
            cy.get("#help-wanted-toggle").should("be.visible");
        });

        it("saves the advert and it survives a reload", () => {
            cy.visit(ministryUrl());
            cy.get("#nav-item-help-wanted").click();
            cy.get("#help-wanted-toggle").check();
            cy.get("#help-wanted-text").clear().type(HELP_WANTED_TEXT);
            cy.get("#help-wanted-save").click();

            cy.reload();
            cy.get("#nav-item-help-wanted").click();
            cy.get("#help-wanted-toggle").should("be.checked");
            cy.get("#help-wanted-text").should("have.value", HELP_WANTED_TEXT);
        });
    });

    describe("the volunteer pool panel", () => {
        beforeEach(() => {
            freshAdminLogin();
        });

        it("is gone from the ministry page entirely", () => {
            cy.visit(ministryUrl());
            cy.get("#volunteerPoolTable").should("not.exist");
            cy.get("#pool-add-btn").should("not.exist");
            cy.get("#poolAddModal").should("not.exist");
            cy.get("#pool-membership-note").should("not.exist");
            cy.get("#pool-group-link").should("not.exist");
        });

        it("still answers on the API, which the Groups module and qualifying both use", () => {
            adminApi("GET", `${VOLUNTEER_URL}/ministries/${ministryId}/pool`, null, 200).then(
                (resp) => {
                    expect(resp.body.groupId).to.be.greaterThan(0);
                },
            );
        });
    });

    describe("a ministry that does not exist", () => {
        it("404s", () => {
            freshAdminLogin();
            cy.visit(`${MINISTRIES_URL}/99999999`, { failOnStatusCode: false });
            cy.contains(/not found|Access Denied/i).should("exist");
        });
    });
});

// ═════════════════════════════════════════════════════════════════════════════
// The Setup page is gone, and "New ministry" is what replaced its one
// irreplaceable step.
// ═════════════════════════════════════════════════════════════════════════════

const CREATE_PREFIX = "UIMINNEW";

describe("Volunteer v2 — the Setup page is gone (#9701)", () => {
    before(() => {
        setVersion("v2");
    });

    after(() => {
        cy.makePrivateAdminAPICall("GET", `${VOLUNTEER_URL}/ministries`, null, 200).then(
            (resp) => {
                for (const ministry of resp.body.ministries) {
                    if (ministry.name.startsWith(CREATE_PREFIX)) {
                        cy.makePrivateAdminAPICall(
                            "DELETE",
                            `${VOLUNTEER_URL}/ministries/${ministry.id}`,
                            null,
                            [200, 404],
                        );
                    }
                }
            },
        );
        setVersion("v1");
    });

    beforeEach(() => {
        freshAdminLogin();
    });

    it("is not in the Volunteer menu any more", () => {
        cy.visit(DASHBOARD_URL);
        cy.get('a[href$="/volunteer/setup"]').should("not.exist");
        cy.get('a[href$="/volunteer/ministries"]').should("exist");
        cy.get('a[href$="/volunteer/dashboard"]').should("exist");
    });

    it("404s at /volunteer/setup", () => {
        cy.request({ url: "/volunteer/setup", failOnStatusCode: false }).then((resp) => {
            expect(resp.status).to.eq(404);
        });
    });

    it("offers New ministry instead of Guided setup on the dashboard", () => {
        cy.visit(DASHBOARD_URL);
        cy.get("#volunteer-quick-actions").should("not.contain", "Guided setup");
        cy.get("#volunteer-quick-actions #ministry-new-btn")
            .should("be.visible")
            .and("contain", "New ministry");
    });

    it("creates a ministry from the modal and lands on its page", () => {
        cy.visit(MINISTRIES_URL);
        cy.get("#ministry-new-btn").should("be.visible").and("contain", "New ministry").click();

        cy.get("#ministryCreateModal").should("be.visible");
        cy.get("#ministry-create-name")
            .should("be.focused")
            .type(`${CREATE_PREFIX} Hospitality`);
        cy.get("#ministry-create-description").type("Welcomes people on a Sunday");
        cy.get("#ministry-create-save").click();

        cy.url().should("match", /\/volunteer\/ministries\/\d+$/);
        cy.get("#volunteer-ministry .card-title").should(
            "contain",
            `${CREATE_PREFIX} Hospitality`,
        );
        // The team and the pool Group the ministry was created with are already there.
        cy.get("#teams-loading").should("not.be.visible");
        cy.get("#volunteerTeamsTable").should("contain", `${CREATE_PREFIX} Hospitality Team`);
    });
});
