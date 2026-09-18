/// <reference types="cypress" />

/**
 * Volunteer v2 — the ministry page's THIRD round of product-owner changes
 * (epic #9701, design §5.4 as amended three times).
 *
 *   1. **A tick confirms itself in a toast, not beside the box.** The inline
 *      "Saved" / "Not saved" badge is gone along with the status cell it lived
 *      in, because it moved the checkbox column every time somebody ticked a
 *      box. The confirmation is now the standard green top-right notification
 *      the rest of V2 uses, naming the position and the person; a failure still
 *      rolls the box back and shows the red one. The hint stays.
 *
 *   2. **"Qualify someone else" is now "Add Volunteer".** The dialog is the
 *      shared person search and nothing else — no position select — because
 *      adding somebody to the ministry and saying what they can do are two
 *      steps. It puts them in the pool and the grid gains their row.
 *
 *   3. **"Qualify the cart" is now "Add from Cart".** Same shape: everyone in
 *      the cart joins the pool, nobody is qualified for anything, and one
 *      endpoint does it (`POST /ministries/{id}/pool/from-cart`) rather than N
 *      calls from the browser.
 *
 *   4. **The Occurrences tab has a search form.** Team · Event · From · To,
 *      above the table, every field live. "Filter by Date", its dialog, the
 *      "Showing … to …" line and "Back to upcoming" are gone; the past is
 *      reached by moving From back.
 *
 * Order inside every hook is API setup → freshAdminLogin() → cy.visit(), because
 * cy.request() rotates the PHP session cookie (cypress-testing.md). Fixture rows
 * are removed in `before` as well as `after`: an `after` hook does not run when
 * the runner crashes mid-spec.
 *
 * `cy.dbQuery()` appears once, for the past occurrence — generation deliberately
 * refuses to back-fill (§2.9), so there is no API that can make one.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/ministries";
const MINISTRIES_URL = "/ministries";

const PREFIX = "UIPAGE3";
// An apostrophe and an ampersand on purpose: the Add dialogs' titles are set as
// text, and both used to show as HTML entities (2026-09-18).
const MINISTRY_NAME = `${PREFIX} Kids' Café & Crèche`;
const SECOND_TEAM_NAME = `${PREFIX} Saturday Crew`;

const POSITION_ONE = `${PREFIX} Espresso`;
const POSITION_TWO = `${PREFIX} Pastry`;
const SECOND_TEAM_POSITION = `${PREFIX} Saturday Barista`;

/** Two pool members, so the grid has rows before anything is added. */
const POOL_PEOPLE = [4, 5];
const TICKED_PERSON = 4;
/** Nobody's pool member — the person "Add Volunteer" brings in. */
const OUTSIDE_PERSON = 6;
/** The two the cart brings in. Deliberately not 4/5, which are already in. */
const CART_PEOPLE = [7, 8];

/** The two schedules, so the Event text box has something to tell apart. */
const SCHEDULE_SUNDAY = `${PREFIX} Sunday Morning`;
const SCHEDULE_WEDNESDAY = `${PREFIX} Wednesday Class`;

let ministryId = 0;
let firstTeamId = 0;
let secondTeamId = 0;
let firstPositionId = 0;
let secondPositionId = 0;
let secondTeamPositionId = 0;
let sundayScheduleId = 0;
let wednesdayScheduleId = 0;
let firstTeamName = "";

// Local helpers — NOT cy.* commands (cypress-testing.md).
function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
    cy.url().should("not.include", "/session/begin");
}

function adminApi(method, url, body, expectedStatus = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, expectedStatus);
}

function setVersion(value) {
    adminApi("POST", SETTING_URL, { value }, 200);
}

function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function cleanupFixtures() {
    adminApi("GET", `${VOLUNTEER_URL}/ministries`, null, 200).then((resp) => {
        for (const ministry of resp.body.ministries) {
            if (!ministry.name.startsWith(PREFIX)) {
                continue;
            }
            adminApi("GET", `${VOLUNTEER_URL}/ministries/${ministry.id}/schedules`, null, 200).then(
                (list) => {
                    for (const schedule of list.body.schedules) {
                        adminApi("DELETE", `${VOLUNTEER_URL}/schedules/${schedule.id}`, null, [
                            200,
                            404,
                            409,
                        ]);
                    }
                    // An active ministry cannot be deleted (409): deactivate first (2026-09-17 lifecycle rule).
                    adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministry.id}`, { active: false }, [200, 404]);
                    adminApi("DELETE", `${VOLUNTEER_URL}/ministries/${ministry.id}`, null, [
                        200,
                        404,
                        409,
                    ]);
                },
            );
        }
    });
}

function ministryUrl() {
    return `${MINISTRIES_URL}/${ministryId}`;
}

function openVolunteersTab() {
    cy.get("#nav-item-volunteers").click();
    cy.get("#volunteers .volunteer-loading").should("not.be.visible");
    cy.get("#volunteerQualificationsTable").should("be.visible");
}

/**
 * Open the Occurrences tab and wait for the table, not just for the click.
 *
 * Waiting only on `#occurrences-loading` being invisible is a trap: the whole pane
 * is hidden until the tab is activated, so "not visible" is already true before the
 * first request has even been made and the assertion passes instantly.
 */
function openOccurrencesTab() {
    cy.get("#nav-item-occurrences").click();
    cy.get("#occurrences .volunteer-loading").should("not.be.visible");
    cy.get("#volunteerOccurrencesTable").should("be.visible");
}

/** The query string of the most recent `GET /occurrences`, retried until it matches. */
function lastOccurrenceQuery(assert) {
    cy.get("@listOccurrences.all").should((calls) => {
        expect(calls.length, "occurrence requests made").to.be.greaterThan(0);
        assert(new URL(calls[calls.length - 1].request.url).searchParams);
    });
}

function qualBox(personId, positionId) {
    return cy.get(
        `#volunteerQualificationsTable input.volunteer-qual-toggle[data-person-id="${personId}"][data-position-id="${positionId}"]`,
    );
}

function clearQualifications(positionId) {
    adminApi("GET", `${VOLUNTEER_URL}/positions/${positionId}/qualifications?active=1`, null, 200).then(
        (resp) => {
            for (const qualification of resp.body.qualifications) {
                adminApi("DELETE", `${VOLUNTEER_URL}/qualifications/${qualification.id}`, null, [200, 404]);
            }
        },
    );
}

/** Put the pool back to exactly POOL_PEOPLE, so "a row appeared" is provable. */
function resetPool() {
    adminApi("GET", `${VOLUNTEER_URL}/ministries/${ministryId}/pool`, null, 200).then((resp) => {
        for (const member of resp.body.members) {
            if (POOL_PEOPLE.includes(member.personId)) {
                continue;
            }
            adminApi(
                "DELETE",
                `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${member.personId}`,
                null,
                [200, 404],
            );
        }
    });
}

/**
 * Seed the session cart in the SAME PHP session the browser is using.
 * `cy.makePrivateAdminAPICall` authenticates with an x-api-key and sends no
 * session cookie, which would land the cart in a different session entirely,
 * and `freshAdminLogin()` clears cookies — so this has to be a plain
 * `cy.request` AFTER the login.
 */
function seedCart(personIds) {
    cy.request({ method: "DELETE", url: "/api/cart/", failOnStatusCode: false });
    cy.request({
        method: "POST",
        url: "/api/cart/",
        headers: { "content-type": "application/json" },
        body: { Persons: personIds },
    });
}

describe("Volunteer v2 ministry page, round three (#9701)", () => {
    before(() => {
        setVersion("v2");
        cleanupFixtures();

        adminApi(
            "POST",
            `${VOLUNTEER_URL}/ministries`,
            { name: MINISTRY_NAME, description: "round-three worked example" },
            201,
        ).then((resp) => {
            ministryId = resp.body.ministry.id;

            adminApi("GET", `${VOLUNTEER_URL}/ministries/${ministryId}`, null, 200).then((detail) => {
                firstTeamId = detail.body.teams[0].id;
                firstTeamName = detail.body.teams[0].name;

                adminApi(
                    "POST",
                    `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
                    { name: POSITION_ONE, teamId: firstTeamId, order: 1 },
                    201,
                ).then((position) => {
                    firstPositionId = position.body.position.id;
                });
                adminApi(
                    "POST",
                    `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
                    { name: POSITION_TWO, teamId: firstTeamId, order: 2 },
                    201,
                ).then((position) => {
                    secondPositionId = position.body.position.id;
                });

                // A second team with a position and a schedule of its own, so the
                // Team select on the Occurrences tab genuinely narrows something.
                adminApi(
                    "POST",
                    `${VOLUNTEER_URL}/ministries/${ministryId}/teams`,
                    { name: SECOND_TEAM_NAME },
                    201,
                ).then((team) => {
                    secondTeamId = team.body.team.id;
                    adminApi(
                        "POST",
                        `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
                        { name: SECOND_TEAM_POSITION, teamId: secondTeamId, order: 1 },
                        201,
                    ).then((position) => {
                        secondTeamPositionId = position.body.position.id;
                    });

                    adminApi(
                        "POST",
                        `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
                        {
                            name: SCHEDULE_WEDNESDAY,
                            teamId: secondTeamId,
                            linkMode: "standalone",
                            recurType: "weekly",
                            recurDow: "Wednesday",
                            startTime: "19:00",
                            endTime: "20:00",
                            windowStart: isoDate(0),
                            windowEnd: isoDate(60),
                        },
                        201,
                    ).then((schedule) => {
                        wednesdayScheduleId = schedule.body.schedule.id;
                        adminApi(
                            "POST",
                            `${VOLUNTEER_URL}/schedules/${wednesdayScheduleId}/generate`,
                            {},
                            200,
                        );
                    });
                });

                adminApi(
                    "POST",
                    `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
                    {
                        name: SCHEDULE_SUNDAY,
                        teamId: firstTeamId,
                        linkMode: "standalone",
                        recurType: "weekly",
                        recurDow: "Sunday",
                        startTime: "09:00",
                        endTime: "10:30",
                        windowStart: isoDate(0),
                        windowEnd: isoDate(60),
                    },
                    201,
                ).then((schedule) => {
                    sundayScheduleId = schedule.body.schedule.id;
                    adminApi("POST", `${VOLUNTEER_URL}/schedules/${sundayScheduleId}/generate`, {}, 200);
                });
            });

            for (const personId of POOL_PEOPLE) {
                adminApi("POST", `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${personId}`, null, [
                    200, 201,
                ]);
            }
        });
    });

    after(() => {
        cleanupFixtures();
        setVersion("v1");
    });

    // ═════════════════════════════════════════════════════════════════════════
    // 1. A tick confirms itself in a toast and moves nothing
    // ═════════════════════════════════════════════════════════════════════════

    describe("ticking a qualification", () => {
        beforeEach(() => {
            clearQualifications(firstPositionId);
            clearQualifications(secondPositionId);
            freshAdminLogin();
        });

        it("keeps the 'ticks save as you make them' hint and has no Save button", () => {
            cy.visit(ministryUrl());
            openVolunteersTab();

            cy.get("#volunteers-save-hint").should("be.visible").and("contain", "Ticks save as you make them.");
            cy.get("#volunteers button").each(($button) => {
                expect($button.text().trim()).to.not.eq("Save");
            });
        });

        it("confirms the save in a toast naming the position and the person", () => {
            cy.visit(ministryUrl());
            openVolunteersTab();

            qualBox(TICKED_PERSON, firstPositionId).should("not.be.checked").check();

            cy.get(".notyf__toast--success").should("be.visible").and("contain", `${POSITION_ONE}:`);
            cy.get(".notyf__toast--success").should("contain", "qualified");
        });

        it("says so in the toast when a tick is taken away again", () => {
            adminApi(
                "POST",
                `${VOLUNTEER_URL}/positions/${firstPositionId}/qualifications`,
                { personId: TICKED_PERSON },
                [200, 201],
            );
            freshAdminLogin();

            cy.visit(ministryUrl());
            openVolunteersTab();

            qualBox(TICKED_PERSON, firstPositionId).should("be.checked").uncheck();

            cy.get(".notyf__toast--success").should("be.visible").and("contain", "no longer qualified");
        });

        it("has no inline status cell left, and the checkbox does not move when it saves", () => {
            // Already qualified for the FIRST position, so the row's "In the pool, not
            // qualified yet" badge is already gone before anything is measured. That
            // badge legitimately disappears on a person's first tick and shifts the
            // name column; isolating it is what makes this a test of the status cell.
            adminApi(
                "POST",
                `${VOLUNTEER_URL}/positions/${firstPositionId}/qualifications`,
                { personId: TICKED_PERSON },
                [200, 201],
            );
            freshAdminLogin();

            cy.visit(ministryUrl());
            openVolunteersTab();

            // The badge and the span it lived in are gone from the markup entirely.
            cy.get("#volunteerQualificationsTable .volunteer-qual-status").should("not.exist");
            cy.get("#volunteerQualificationsTable .volunteer-qual-cell").should("not.exist");

            cy.intercept("POST", "**/api/ministries/positions/*/qualifications").as("grantQual");

            // The box that is about to be ticked, and its neighbour to the right — the
            // inline badge used to widen its own cell and push everything after it.
            qualBox(TICKED_PERSON, secondPositionId).then(($box) => {
                const before = $box[0].getBoundingClientRect();

                qualBox(TICKED_PERSON, secondPositionId).check();
                cy.wait("@grantQual");
                cy.get(".notyf__toast--success").should("be.visible");

                qualBox(TICKED_PERSON, secondPositionId).then(($after) => {
                    const after = $after[0].getBoundingClientRect();
                    expect(after.left, "checkbox left edge").to.be.closeTo(before.left, 1);
                    expect(after.top, "checkbox top edge").to.be.closeTo(before.top, 1);
                });
            });
        });

        it("rolls the box back and shows the red toast when the write is refused", () => {
            cy.intercept("POST", "**/api/ministries/positions/*/qualifications", {
                statusCode: 403,
                body: { message: "Not authorized for this position" },
            }).as("denied");

            cy.visit(ministryUrl());
            openVolunteersTab();

            qualBox(TICKED_PERSON, firstPositionId).should("not.be.checked").check();
            cy.wait("@denied");

            cy.get(".notyf__toast--error").should("be.visible").and("contain", "Not authorized");
            qualBox(TICKED_PERSON, firstPositionId).should("not.be.checked");
        });
    });

    // ═════════════════════════════════════════════════════════════════════════
    // 2. Add Volunteer
    // ═════════════════════════════════════════════════════════════════════════

    describe("Add Volunteer", () => {
        beforeEach(() => {
            resetPool();
            freshAdminLogin();
        });

        it("is called Add Volunteer and names the selected team in its title", () => {
            cy.visit(ministryUrl());
            openVolunteersTab();

            cy.get("#qualification-add-person").should("be.visible").and("contain", "Add Volunteer");
            cy.get("#qualification-add-person").click();

            cy.get("#addVolunteerModal").should("be.visible");
            cy.get("#addVolunteerModalTitle").should("have.text", `Add Volunteer to ${MINISTRY_NAME}`);
            // The picker is only built on `shown.bs.modal`, so its TomSelect wrapper
            // appearing is the signal the 150 ms fade has finished.
            cy.get("#addVolunteerModal .ts-wrapper").should("exist");
        });

        it("offers the person search, a Cancel and an Add — and no position select", () => {
            cy.visit(ministryUrl());
            openVolunteersTab();
            cy.get("#qualification-add-person").click();
            cy.get("#addVolunteerModal .ts-wrapper").should("exist");

            cy.get("#addVolunteerModal select#add-volunteer-person").should("exist");
            cy.get("#addVolunteerModal select").should("have.length", 1);
            cy.get("#addVolunteerModal .modal-footer button").should("have.length", 2);
            cy.get("#addVolunteerModal .modal-footer button").first().should("contain", "Cancel");
            cy.get("#add-volunteer-save").should("contain", "Add");
        });

        it("adds the chosen person to the pool and their row appears in the grid", () => {
            cy.intercept("POST", "**/api/ministries/ministries/*/pool/*").as("addPool");

            cy.visit(ministryUrl());
            openVolunteersTab();
            cy.get(
                `#volunteerQualificationsTable input.volunteer-qual-toggle[data-person-id="${OUTSIDE_PERSON}"]`,
            ).should("not.exist");

            cy.get("#qualification-add-person").click();
            cy.get("#addVolunteerModal .ts-wrapper").should("exist");
            cy.get("#add-volunteer-person").then(($select) => {
                const ts = $select[0].tomselect;
                ts.addOption({ objid: String(OUTSIDE_PERSON), text: "Chosen Volunteer" });
                ts.setValue(String(OUTSIDE_PERSON), true);
            });
            cy.get("#add-volunteer-save").click();

            cy.wait("@addPool").its("response.statusCode").should("be.oneOf", [200, 201]);
            cy.get("#addVolunteerModal").should("not.be.visible");
            cy.get(".notyf__toast--success").should("contain", "now tick the positions they can serve");

            // Force-refreshed, so the new person is a ROW — with no ticks on it.
            cy.get(
                `#volunteerQualificationsTable input.volunteer-qual-toggle[data-person-id="${OUTSIDE_PERSON}"]`,
            )
                .should("have.length.at.least", 1)
                .and("not.be.checked");
        });

        it("says so, and still refreshes, when they were already a volunteer here", () => {
            cy.intercept("POST", "**/api/ministries/ministries/*/pool/*").as("addPool");

            cy.visit(ministryUrl());
            openVolunteersTab();

            cy.get("#qualification-add-person").click();
            cy.get("#addVolunteerModal .ts-wrapper").should("exist");
            cy.get("#add-volunteer-person").then(($select) => {
                const ts = $select[0].tomselect;
                ts.addOption({ objid: String(TICKED_PERSON), text: "Already In" });
                ts.setValue(String(TICKED_PERSON), true);
            });
            cy.get("#add-volunteer-save").click();

            cy.wait("@addPool").its("response.statusCode").should("eq", 200);
            cy.get(".notyf__toast").should("contain", "They were already a volunteer here");
            cy.get(
                `#volunteerQualificationsTable input.volunteer-qual-toggle[data-person-id="${TICKED_PERSON}"]`,
            ).should("have.length.at.least", 1);
        });
    });

    // ═════════════════════════════════════════════════════════════════════════
    // 3. Add from Cart
    // ═════════════════════════════════════════════════════════════════════════

    describe("Add from Cart", () => {
        beforeEach(() => {
            resetPool();
            freshAdminLogin();
        });

        it("is called Add from Cart and offers no position selector", () => {
            cy.visit(ministryUrl());
            openVolunteersTab();

            cy.get("#qualification-cart-btn").should("be.visible").and("contain", "Add from Cart");
            cy.get("#qualification-cart-btn").click();

            cy.get("#addFromCartModal").should("be.visible");
            cy.get("#addFromCartModalTitle").should("have.text", `Add Everyone in Cart to ${MINISTRY_NAME}`);
            cy.get("#addFromCartModal").should(
                "contain",
                "Everyone in the cart joins this ministry's volunteers.",
            );
            cy.get("#addFromCartModal select").should("not.exist");
            cy.get("#addFromCartModal .modal-footer button").should("have.length", 2);
            cy.get("#addFromCartModal .modal-footer button").first().should("contain", "Cancel");
            cy.get("#add-from-cart-save").should("contain", "Add All");
        });

        it("adds everyone in the cart to the pool in one call and reports the counts", () => {
            cy.intercept("POST", "**/api/ministries/ministries/*/pool/from-cart").as("fromCart");

            cy.visit(ministryUrl());
            seedCart(CART_PEOPLE);
            cy.visit(ministryUrl());
            openVolunteersTab();

            cy.get("#qualification-cart-btn").click();
            cy.get("#addFromCartModal").should("be.visible");
            cy.get("#add-from-cart-save").click();

            cy.wait("@fromCart").then((interception) => {
                expect(interception.response.statusCode).to.eq(200);
                expect(interception.response.body.added, "added").to.eq(CART_PEOPLE.length);
                expect(interception.response.body.alreadyMembers, "alreadyMembers").to.eq(0);
            });

            cy.get("#addFromCartModal").should("not.be.visible");
            cy.get(".notyf__toast--success").should("be.visible");

            for (const personId of CART_PEOPLE) {
                cy.get(
                    `#volunteerQualificationsTable input.volunteer-qual-toggle[data-person-id="${personId}"]`,
                )
                    .should("have.length.at.least", 1)
                    .and("not.be.checked");
            }
        });

        it("counts the ones that were already there rather than failing", () => {
            cy.intercept("POST", "**/api/ministries/ministries/*/pool/from-cart").as("fromCart");

            cy.visit(ministryUrl());
            seedCart([...POOL_PEOPLE, CART_PEOPLE[0]]);
            cy.visit(ministryUrl());
            openVolunteersTab();

            cy.get("#qualification-cart-btn").click();
            cy.get("#add-from-cart-save").click();

            cy.wait("@fromCart").then((interception) => {
                expect(interception.response.body.added, "added").to.eq(1);
                expect(interception.response.body.alreadyMembers, "alreadyMembers").to.eq(
                    POOL_PEOPLE.length,
                );
            });
        });
    });

    // ═════════════════════════════════════════════════════════════════════════
    // 4. The Occurrences tab's search form
    // ═════════════════════════════════════════════════════════════════════════

    describe("the Occurrences tab", () => {
        const PAST_DATE = isoDate(-30);

        before(() => {
            cy.dbQuery(
                `INSERT INTO volunteer_occurrence_vocc
                    (vocc_vsch_ID, vocc_OccurrenceDate, vocc_StartDateTime, vocc_EndDateTime, vocc_Status, vocc_GeneratedDate)
                 VALUES (?, ?, ?, ?, 'scheduled', NOW())`,
                [sundayScheduleId, PAST_DATE, `${PAST_DATE} 09:00:00`, `${PAST_DATE} 10:30:00`],
            );
        });

        beforeEach(() => {
            freshAdminLogin();
        });

        it("replaces Filter by Date with a Team / Event / From / To form", () => {
            cy.visit(ministryUrl());
            openOccurrencesTab();

            cy.get("#occurrences-filter-btn").should("not.exist");
            cy.get("#occurrenceRangeModal").should("not.exist");
            cy.get("#occurrences-range-note").should("not.exist");
            cy.get("#occurrences-range-reset").should("not.exist");

            cy.get("#occurrence-team-filter").should("be.visible");
            cy.get("#occurrence-event-filter").should("be.visible");
            cy.get("#occurrence-from").should("be.visible").and("have.value", isoDate(0));
            cy.get("#occurrence-to").should("be.visible").and("have.value", "");
        });

        it("offers All Teams first and defaults to it", () => {
            cy.visit(ministryUrl());
            openOccurrencesTab();

            cy.get("#occurrence-team-filter option").first().should("contain", "All Teams");
            cy.get("#occurrence-team-filter option").first().should("have.value", "");
            cy.get("#occurrence-team-filter").should("have.value", "");
            // Both teams' schedules are listed, which is what "All Teams" means.
            cy.get("#volunteerOccurrencesTable tbody").should("contain", SCHEDULE_SUNDAY);
            cy.get("#volunteerOccurrencesTable tbody").should("contain", SCHEDULE_WEDNESDAY);
        });

        it("narrows to one team's occurrences as soon as the Team select changes", () => {
            cy.intercept("GET", "**/api/ministries/occurrences?*").as("listOccurrences");
            cy.visit(ministryUrl());
            openOccurrencesTab();

            cy.get("#occurrence-team-filter").select(String(secondTeamId));
            cy.wait("@listOccurrences");
            cy.get("#occurrences .volunteer-loading").should("not.be.visible");

            cy.get("#volunteerOccurrencesTable tbody").should("contain", SCHEDULE_WEDNESDAY);
            cy.get("#volunteerOccurrencesTable tbody").should("not.contain", SCHEDULE_SUNDAY);
        });

        it("filters by the occurrence title as it is typed, case-insensitively", () => {
            cy.intercept("GET", "**/api/ministries/occurrences?*").as("listOccurrences");
            cy.visit(ministryUrl());
            openOccurrencesTab();

            // Lower case against a schedule named "… Wednesday Class".
            cy.get("#occurrence-event-filter").type("wednesday");

            cy.get("#volunteerOccurrencesTable tbody").should("contain", SCHEDULE_WEDNESDAY);
            cy.get("#volunteerOccurrencesTable tbody").should("not.contain", SCHEDULE_SUNDAY);

            // The narrowing is done by the SERVER, not by hiding rows in the browser.
            // Retried, because the box is debounced: an intermediate request carrying a
            // prefix of what was typed is expected, and the LAST one is the real query.
            lastOccurrenceQuery((params) => {
                expect(params.get("text"), "text parameter").to.eq("wednesday");
            });
        });

        it("reaches the past by moving From back, with no dialog anywhere", () => {
            cy.intercept("GET", "**/api/ministries/occurrences?*").as("listOccurrences");
            cy.visit(ministryUrl());
            openOccurrencesTab();

            cy.get("#volunteerOccurrencesTable tbody").should("not.contain", PAST_DATE);

            cy.get("#occurrence-from").clear();
            cy.get("#occurrence-from").type(isoDate(-90));

            lastOccurrenceQuery((params) => {
                expect(params.get("from"), "from").to.eq(isoDate(-90));
            });
            cy.get("#volunteerOccurrencesTable tbody").should("contain", PAST_DATE);
        });

        it("sends From + one year when To is left empty, and honours To when it is set", () => {
            const plusOneYear = (iso) => {
                const d = new Date(`${iso}T12:00:00`);
                d.setFullYear(d.getFullYear() + 1);
                const pad = (n) => String(n).padStart(2, "0");
                return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
            };

            cy.intercept("GET", "**/api/ministries/occurrences?*").as("listOccurrences");
            cy.visit(ministryUrl());
            openOccurrencesTab();

            // The endpoint's window is mandatory (design M9), so an empty To has to
            // become a real upper bound — From + one year.
            lastOccurrenceQuery((params) => {
                expect(params.get("from"), "from").to.eq(isoDate(0));
                expect(params.get("to"), "to when the To box is empty").to.eq(plusOneYear(isoDate(0)));
            });

            cy.get("#occurrence-to").type(isoDate(7));
            lastOccurrenceQuery((params) => {
                expect(params.get("to"), "to once it is set").to.eq(isoDate(7));
            });
        });

        it("renders every label through gettext/i18next, never a raw key", () => {
            cy.visit(ministryUrl());
            openOccurrencesTab();
            cy.get("#occurrences").should("not.contain", "i18next");
            cy.get("#occurrences").invoke("text").should("not.match", /\{\{[a-z]+\}\}/);
        });
    });
});
