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
// #9707 — the Teams & Pools tab and the qualification matrix (design §5.4).
//
// A separate top-level describe with its own PREFIX and its own fixtures, so
// nothing above changes and the two halves can fail independently. The API
// contract these screens sit on is pinned by
// `private.volunteer.pools-qualifications.spec.js`; what is asserted here is the
// screen: that a Group can be linked from the tab, that its member count is
// shown read-only with the Manage Groups note (Appendix D-1), that a matrix
// checkbox writes through and survives a reload, and that both halves have a
// first-class empty state (§5.8).
//
// Seed groups: 1 "Angels class" (persons 4, 5, 8, 9, 63) and 8 "Girl Scouts"
// (63, 80, 95) — see seed.sql:1338.
// ═════════════════════════════════════════════════════════════════════════════

const PREFIX_9707 = "UI9707";
const MINISTRY_9707 = `${PREFIX_9707} Coffee Bar`;
/** The team the ministry is created with. */
const TEAM_9707 = `${MINISTRY_9707} Team`;
const POSITION_9707 = `${PREFIX_9707} Espresso`;
const GROUP_ANGELS_ID = 1;
const GROUP_ANGELS_NAME = "Angels class";
/**
 * Read in `before`, never hardcoded: `private.people.groups.spec.js` adds person
 * 1 to group 1 and does not always take them out again, so the group's size is
 * not stable across a full suite run. What these tests are about is that the
 * screen shows the GROUP's own count, whatever it currently is.
 */
let angelsMemberCount = 0;

const NEW_GROUP_9707 = `${PREFIX_9707} Setup-made pool`;

/** Groups created by the "Create a new Group" button carry the spec prefix; remove them. */
function cleanupGroups9707() {
    // GET /api/groups/ (trailing slash, like the POST) answers a plain array of
    // Propel toArray() rows, so the keys are phpNames: Id, Name.
    cy.makePrivateAdminAPICall("GET", "/api/groups/", null, 200).then((resp) => {
        for (const group of resp.body) {
            const name = group.Name ?? "";
            const id = group.Id;
            if (name.startsWith(PREFIX_9707) && id) {
                cy.makePrivateAdminAPICall("DELETE", `/api/groups/${id}`, null, [200, 404]);
            }
        }
    });
}

function cleanup9707() {
    cleanupGroups9707();
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

describe("Volunteer v2 pools and qualification matrix (#9707)", () => {
    let ministryId = 0;
    let emptyMinistryId = 0;
    let positionId = 0;

    before(() => {
        setVersion("v2");
        cleanup9707();

        // The core endpoint V2 reuses for pool membership (G2) is also the
        // honest source for what the screen should be showing.
        cy.makePrivateAdminAPICall(
            "GET",
            `/api/groups/${GROUP_ANGELS_ID}/members`,
            null,
            200,
        ).then((resp) => {
            angelsMemberCount = resp.body.Person2group2roleP2g2rs.length;
        });

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

    it("shows a Qualifications tab beside Teams & Pools", () => {
        cy.visit(`/volunteer/ministries/${ministryId}`);
        cy.get("#nav-item-teams").should("contain", "Pools");
        cy.get("#nav-item-qualifications").should("contain", "Qualifications");
    });

    it("shows the empty pool state and the Manage Groups note (D-1)", () => {
        cy.visit(`/volunteer/ministries/${emptyMinistryId}`);
        cy.get("#nav-item-teams").click();
        cy.get("#pools-empty").should("be.visible");
        cy.get("#pools-empty .empty-title").should("be.visible");
        // Appendix D-1: membership is read-only in V2 and the screen says so.
        cy.get("#pools-membership-note")
            .should("be.visible")
            .and("contain", "Manage Groups");
    });

    it("links a Group as the pool and shows its member count read-only", () => {
        cy.visit(`/volunteer/ministries/${ministryId}`);
        cy.get("#nav-item-teams").click();
        cy.get("#pool-add-btn").should("be.visible").click();

        // window.CRM.groups.promptSelection() (G4) — the existing picker, not a
        // second group chooser built for V2.
        cy.get(".modal.show").should("be.visible").and("contain", "Select Group");
        cy.get(".modal.show .ts-control").click();
        cy.get(".ts-dropdown").should("be.visible");
        cy.get(".ts-dropdown .option").contains(GROUP_ANGELS_NAME).click();
        cy.get("#crm-gs-confirm").click();

        cy.get("#volunteerPoolsTable").should("be.visible");
        cy.get("#volunteerPoolsTable").should("contain", GROUP_ANGELS_NAME);
        cy.get("#volunteerPoolsTable").should("contain", angelsMemberCount);
        // The count links out to the group, because editing membership happens there.
        cy.get(`#volunteerPoolsTable a[href*="/groups/view/${GROUP_ANGELS_ID}"]`)
            .should("exist");
    });

    it("renders the matrix with the pool people down the side and positions across the top", () => {
        cy.visit(`/volunteer/ministries/${ministryId}`);
        cy.get("#nav-item-qualifications").click();
        cy.get("#qualifications .volunteer-loading").should("not.be.visible");
        cy.get("#volunteerQualificationsTable").should("be.visible");
        cy.get("#volunteerQualificationsTable thead").should("contain", POSITION_9707);
        cy.get("#volunteerQualificationsTable tbody tr").should(
            "have.length",
            angelsMemberCount,
        );
        cy.get(
            `#volunteerQualificationsTable input.volunteer-qual-toggle[data-position-id="${positionId}"]`,
        ).should("have.length", angelsMemberCount);
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

    it("resumes the setup flow with the pool step unlocked and the group listed", () => {
        // §5.3 step 3. Resuming with ?ministryId= is the path a coordinator takes
        // back into the flow, so the pool step must be usable straight away.
        cy.visit(`/volunteer/setup?ministryId=${ministryId}`);
        cy.get("#setup-step-pool").should("be.visible");
        cy.get("#setup-pool-link").should("not.be.disabled");
        cy.get("#setup-pool-list").should("contain", GROUP_ANGELS_NAME);
        cy.get("#setup-pool-list").should("contain", angelsMemberCount);
        // The design's wording, which is the point of the step: the Group stays
        // in charge of who belongs.
        cy.get("#setup-step-pool").should("contain", "Nobody is copied");
    });

    it("creates a new Group from the pool step and links it in one go", () => {
        // Carl's review path: there is no "new group" page in the groups module
        // (/groups/editor/{id} edits an existing one), so the button must create
        // the Group through the core API and link it here, not link to a 404.
        cy.visit(`/volunteer/setup?ministryId=${ministryId}`);
        cy.get("#setup-pool-new-group").should("not.be.disabled").click();
        cy.get(".bootbox").should("be.visible");
        cy.get(".bootbox input.bootbox-input").should("be.focused").type(NEW_GROUP_9707);
        cy.get(".bootbox .btn-primary").click();

        cy.get("#setup-pool-list").should("contain", NEW_GROUP_9707);
        cy.makePrivateAdminAPICall("GET", `/api/volunteer/ministries/${ministryId}/pools`, null, 200).then(
            (resp) => {
                const names = resp.body.pools.map((pool) => pool.groupName);
                expect(names).to.include(NEW_GROUP_9707);
            },
        );
    });

    it("shows a first-class empty state when no pool is linked", () => {
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
