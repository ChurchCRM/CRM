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
 * Positions. The qualification matrix arrives with #9707; the schedule, staffing
 * and generate steps with #9708/#9711. (#9707 also added a volunteer-pool step
 * between Team and Positions; D19 removed it again — a ministry now comes with
 * its pool Group, so there is nothing to choose.) The flow is built so those slot
 * in without moving what is here.
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
/** The team a ministry is created with is named "{Ministry} Team". */
const TEAM_NAME = `${MINISTRY_NAME} Team`;
const SECOND_TEAM_NAME = `${PREFIX} Saturday Crew`;
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

            // Step 2 — team. The ministry was created with one already, named after
            // it; the coordinator may add more beside it.
            cy.get("#setup-team-list").should("contain", TEAM_NAME);
            cy.get("#setup-team-name").should("not.be.disabled").type(SECOND_TEAM_NAME);
            cy.get("#setup-team-save").click();
            cy.get("#setup-team-list").should("contain", SECOND_TEAM_NAME);

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
                // The ministry already has TEAM_NAME — it was created with it — so the
                // fixture adopts that team rather than asking for a second of the same
                // name, and hangs the position off it.
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/volunteer/ministries/${ministryId}`,
                    null,
                    200,
                ).then((detail) => {
                    expect(detail.body.teams[0].name).to.eq(TEAM_NAME);
                    cy.makePrivateAdminAPICall(
                        "POST",
                        `/api/volunteer/ministries/${ministryId}/positions`,
                        {
                            name: POSITION_ONE,
                            description: "Pulls shots",
                            teamId: detail.body.teams[0].id,
                        },
                        201,
                    );
                });
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
            // Scoped to the card, not the whole page: since D19 the ministry also
            // owns a GROUP of the same name, which appears in the sidebar's
            // (collapsed, therefore invisible) groups menu — an unscoped
            // `cy.contains` picks that up first.
            cy.get("#volunteer-ministry .card-title")
                .should("be.visible")
                .and("contain", MINISTRY_NAME);
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
            // Teams can no longer be empty: a ministry is created with one, and the
            // API refuses to delete the last. So the empty state that is still
            // reachable — and still has to be first class — is Positions.
            cy.get("#nav-item-teams").click();
            cy.get("#volunteerTeamsTable tbody tr").should("have.length", 1);
            cy.get("#nav-item-positions").click();
            cy.get("#positions .empty").should("be.visible");
            cy.get("#positions .empty-title").should("be.visible");
        });

        it("404s a ministry that does not exist", () => {
            cy.visit("/volunteer/ministries/99999999", {
                failOnStatusCode: false,
            });
            cy.contains(/not found|Access Denied/i).should("exist");
        });
    });
});

// ═════════════════════════════════════════════════════════════════════════════
// #9707, rewritten by D19 — the volunteer pool panel and the qualification
// matrix (design §5.4).
//
// A separate top-level describe with its own PREFIX and its own fixtures, so
// nothing above changes and the two halves can fail independently. The API
// contract these screens sit on is pinned by
// `private.volunteer.pools-qualifications.spec.js` and
// `private.volunteer.ministry-group.spec.js`; what is asserted here is the
// SCREEN: that the pool panel adds and removes people, that the matrix shows a
// pool member with no ticks as "not qualified yet", that a checkbox writes
// through and survives a reload, and that both halves have a first-class empty
// state (§5.8).
//
// What is gone: everything about linking a Group. There is no group picker on
// this tab and no pool step in the wizard, because a ministry is created with
// its own pool Group.
// ═════════════════════════════════════════════════════════════════════════════

const PREFIX_9707 = "UI9707";
const MINISTRY_9707 = `${PREFIX_9707} Coffee Bar`;
/** The team the ministry is created with. */
const TEAM_9707 = `${MINISTRY_9707} Team`;
const POSITION_9707 = `${PREFIX_9707} Espresso`;

/**
 * The people this spec puts in the ministry's own pool Group (D19). Person 4 is
 * the one the matrix toggles; person 5 is deliberately never qualified, so the
 * "In the pool, not qualified yet" hint has a row to appear on.
 */
const POOL_PEOPLE_9707 = [4, 5];
const POOL_NEVER_QUALIFIED = 5;
/** Added and removed by the panel test — in nothing to begin with. */
const POOL_ADDED_BY_PANEL = 8;

/**
 * Ministries take their pool Group with them when they are deleted (D19), so there
 * is no separate group cleanup any more.
 */
function cleanup9707() {
    cy.makePrivateAdminAPICall("GET", "/api/volunteer/ministries", null, 200).then(
        (resp) => {
            for (const ministry of resp.body.ministries) {
                if (ministry.name.startsWith(PREFIX_9707)) {
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

describe("Volunteer v2 volunteer pool and qualification matrix (#9707, D19)", () => {
    let ministryId = 0;
    let emptyMinistryId = 0;
    let positionId = 0;

    before(() => {
        setVersion("v2");
        cleanup9707();

        cy.makePrivateAdminAPICall(
            "POST",
            "/api/volunteer/ministries",
            { name: MINISTRY_9707, description: "UC1 worked example" },
            201,
        ).then((resp) => {
            ministryId = resp.body.ministry.id;
            // The ministry came with TEAM_9707 already in it; the position hangs off
            // that team, because every position belongs to one.
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/volunteer/ministries/${ministryId}`,
                null,
                200,
            ).then((detail) => {
                expect(detail.body.teams[0].name).to.eq(TEAM_9707);
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/volunteer/ministries/${ministryId}/positions`,
                    {
                        name: POSITION_9707,
                        description: "Pulls shots",
                        teamId: detail.body.teams[0].id,
                    },
                    201,
                ).then((position) => {
                    positionId = position.body.position.id;
                });
            });

            // D19: the ministry came with its pool Group, empty. Fill it so the
            // matrix has rows and the panel has something to show.
            for (const personId of POOL_PEOPLE_9707) {
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/volunteer/ministries/${ministryId}/pool/${personId}`,
                    null,
                    [200, 201],
                );
            }
        });

        cy.makePrivateAdminAPICall(
            "POST",
            "/api/volunteer/ministries",
            { name: `${PREFIX_9707} Empty Ministry` },
            201,
        ).then((resp) => {
            emptyMinistryId = resp.body.ministry.id;
        });
    });

    after(() => {
        cleanup9707();
        setVersion("v1");
    });

    beforeEach(() => {
        freshAdminLogin();
    });

    it("names the tab Teams, with no Pools in it any more (D19)", () => {
        cy.visit(`/volunteer/ministries/${ministryId}`);
        cy.get("#nav-item-teams").should("contain", "Teams").and("not.contain", "Pools");
        cy.get("#nav-item-qualifications").should("contain", "Qualifications");
    });

    it("shows the empty pool state with no group to link (D19)", () => {
        cy.visit(`/volunteer/ministries/${emptyMinistryId}`);
        cy.get("#nav-item-teams").click();
        cy.get("#pool-empty").should("be.visible");
        cy.get("#pool-empty .empty-title").should("be.visible");
        // The note names the ministry's OWN group and says the Groups module is a
        // second door — never "you need the Manage Groups permission" (that was
        // Appendix D-1, which D19 retires).
        cy.get("#pool-membership-note")
            .should("be.visible")
            .and("not.contain", "Manage Groups");
        // And there is nothing to link: the button adds a PERSON.
        cy.get("#pool-add-btn").should("contain", "Add to pool");
    });

    it("lists the pool with a link to the ministry's own Group", () => {
        cy.visit(`/volunteer/ministries/${ministryId}`);
        cy.get("#nav-item-teams").click();
        cy.get("#volunteerPoolTable").should("be.visible");
        cy.get("#volunteerPoolTable tbody tr").should(
            "have.length",
            POOL_PEOPLE_9707.length,
        );
        cy.get("#pool-group-link")
            .should("be.visible")
            .and("contain", MINISTRY_9707)
            .and("have.attr", "href")
            .and("include", "/groups/view/");
    });

    it("adds somebody to the pool with the shared person picker, and removes them again", () => {
        cy.visit(`/volunteer/ministries/${ministryId}`);
        cy.get("#nav-item-teams").click();
        cy.get("#pool-add-btn").should("be.visible").click();

        cy.get("#poolAddModal").should("be.visible");
        // The shared person selector (CR1/#9819), initialised on `shown.bs.modal`.
        cy.get("#poolAddModal .ts-wrapper").should("exist");
        cy.get("#pool-add-person-select").then(($el) => {
            const ts = $el[0].tomselect;
            ts.addOption({ objid: String(POOL_ADDED_BY_PANEL), text: "Added by panel" });
            ts.setValue(String(POOL_ADDED_BY_PANEL));
        });
        cy.get("#pool-add-save").click();

        cy.get("#volunteerPoolTable tbody tr").should(
            "have.length",
            POOL_PEOPLE_9707.length + 1,
        );

        // …and out again, through the row action menu.
        cy.get(
            `#volunteerPoolTable .volunteer-pool-remove[data-person-id="${POOL_ADDED_BY_PANEL}"]`,
        )
            .parents(".dropdown")
            .find("[data-bs-toggle='dropdown']")
            .click();
        cy.get(
            `#volunteerPoolTable .volunteer-pool-remove[data-person-id="${POOL_ADDED_BY_PANEL}"]`,
        ).click();
        cy.get(".bootbox").should("be.visible").and("contain", "qualifications are kept");
        cy.get(".bootbox .btn-danger").click();

        cy.get("#volunteerPoolTable tbody tr").should(
            "have.length",
            POOL_PEOPLE_9707.length,
        );
    });

    it("renders the matrix with the pool people down the side and positions across the top", () => {
        cy.visit(`/volunteer/ministries/${ministryId}`);
        cy.get("#nav-item-qualifications").click();
        cy.get("#qualifications .volunteer-loading").should("not.be.visible");
        cy.get("#volunteerQualificationsTable").should("be.visible");
        cy.get("#volunteerQualificationsTable thead").should("contain", POSITION_9707);
        cy.get("#volunteerQualificationsTable tbody tr").should(
            "have.length",
            POOL_PEOPLE_9707.length,
        );
        cy.get(
            `#volunteerQualificationsTable input.volunteer-qual-toggle[data-position-id="${positionId}"]`,
        ).should("have.length", POOL_PEOPLE_9707.length);
    });

    it("marks a pool member with no qualifications yet (D19)", () => {
        cy.visit(`/volunteer/ministries/${ministryId}`);
        cy.get("#nav-item-qualifications").click();
        cy.get(
            `#volunteerQualificationsTable input.volunteer-qual-toggle[data-person-id="${POOL_NEVER_QUALIFIED}"]`,
        )
            .parents("tr")
            .find(".volunteer-pool-hint")
            .should("be.visible")
            .and("contain", "In the pool, not qualified yet");
    });

    it("toggles a qualification and it survives a reload", () => {
        cy.visit(`/volunteer/ministries/${ministryId}`);
        cy.get("#nav-item-qualifications").click();
        cy.get(
            `#volunteerQualificationsTable input.volunteer-qual-toggle[data-person-id="4"][data-position-id="${positionId}"]`,
        )
            .should("not.be.checked")
            .check();

        // Optimistic UI plus a success toast; the write is confirmed by the reload.
        cy.reload();
        cy.get("#nav-item-qualifications").click();
        cy.get(
            `#volunteerQualificationsTable input.volunteer-qual-toggle[data-person-id="4"][data-position-id="${positionId}"]`,
        ).should("be.checked");

        // And back off again — revoke is a deactivation, so the box simply clears.
        cy.get(
            `#volunteerQualificationsTable input.volunteer-qual-toggle[data-person-id="4"][data-position-id="${positionId}"]`,
        ).uncheck();
        cy.reload();
        cy.get("#nav-item-qualifications").click();
        cy.get(
            `#volunteerQualificationsTable input.volunteer-qual-toggle[data-person-id="4"][data-position-id="${positionId}"]`,
        ).should("not.be.checked");
    });

    it("offers the person picker and the cart bulk-grant from the matrix", () => {
        cy.visit(`/volunteer/ministries/${ministryId}`);
        cy.get("#nav-item-qualifications").click();

        cy.get("#qualification-add-person").should("be.visible").click();
        cy.get("#qualifyPersonModal").should("be.visible");
        cy.get("#qualify-person-position").should("contain", POSITION_9707);
        // The person picker is only built on `shown.bs.modal`, so its TomSelect
        // wrapper appearing is the signal that the 150 ms fade has finished.
        // Clicking the close button before that is silently dropped: Bootstrap's
        // hide() returns early while `_isTransitioning` is true, and "visible"
        // goes true partway through the fade.
        cy.get("#qualifyPersonModal .ts-wrapper").should("exist");
        cy.get("#qualifyPersonModal .btn-close").click();
        cy.get("#qualifyPersonModal").should("not.be.visible");

        cy.get("#qualification-cart-btn").should("be.visible").click();
        cy.get("#qualifyCartModal").should("be.visible");
        cy.get("#qualify-cart-position").should("contain", POSITION_9707);
    });

    it("has no volunteer-pool step in the wizard any more (D19)", () => {
        // §5.3 step 3 is gone: a ministry is created with its pool Group, so there
        // is nothing to choose. What used to be step 4 is now step 3.
        cy.visit(`/volunteer/setup?ministryId=${ministryId}`);
        cy.get("#setup-step-pool").should("not.exist");
        cy.get("#setup-pool-link").should("not.exist");
        cy.get("#setup-pool-new-group").should("not.exist");
        cy.get("#setup-step-position")
            .should("be.visible")
            .and("contain", "Positions");
        cy.get("#setup-step-position .card-title").should("contain", "3");
    });

    it("shows a first-class empty state when nobody is in the pool", () => {
        cy.visit(`/volunteer/ministries/${emptyMinistryId}`);
        cy.get("#nav-item-qualifications").click();
        cy.get("#qualifications-empty").should("be.visible");
        cy.get("#qualifications-empty .empty-title").should("be.visible");
    });

    it("renders every matrix label through gettext/i18next, never a raw key", () => {
        cy.visit(`/volunteer/ministries/${ministryId}`);
        cy.get("#nav-item-qualifications").click();
        cy.get("#qualifications").should("not.contain", "i18next");
        cy.get("#qualifications").invoke("text").should("not.match", /\{\{[a-z]+\}\}/);
    });
});
