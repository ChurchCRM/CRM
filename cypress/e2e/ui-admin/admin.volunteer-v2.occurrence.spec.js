/// <reference types="cypress" />

/**
 * Volunteer v2 — S4, the occurrence / staffing view (#9709, design §5.5).
 *
 * The workhorse coordinator screen: requirements with live/gap counts, the
 * assignment rows under each, the eligible picker, the I7 double-duty warning
 * (D16), cancel through the shared action menu, the cart sink and the swap
 * queue — plus the §5.8 states every V2 screen must have.
 *
 * `cy.dbQuery()` is available here (all three docker configs register `dbTasks`
 * now, not just `docker.config.ts` as #9715's notes had it) but is deliberately
 * not used: everything is built and torn down through the very API this page
 * calls, so a shape change breaks the fixture as loudly as it breaks the page.
 *
 * Order inside every hook is API setup → freshAdminLogin() → cy.visit(),
 * because cy.request() rotates the PHP session cookie (cypress-testing.md).
 * Fixtures are removed in `before` as well as `after`.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/volunteer";

const PREFIX = "UI9709";
const MINISTRY_NAME = `${PREFIX} Coffee Bar`;
const TEAM_NAME = `${PREFIX} Coffee Bar Team`;
const POSITION_ESPRESSO = `${PREFIX} Espresso`;
const POSITION_MILK = `${PREFIX} Milk Station`;
/** Min 0 / Max 1, and never assigned by any test — the reliable empty state. */
const POSITION_EXPEDITOR = `${PREFIX} Expeditor`;
const EVENT_TITLE = `${PREFIX} Coffee Bar Service`;

const POOL_MEMBER_A = 8;
const POOL_MEMBER_B = 9;
const CHURCH_SERVICE_TYPE = 1;

let ministryId = 0;
let teamId = 0;
let posEspresso = 0;
let posMilk = 0;
let posExpeditor = 0;
let scheduleId = 0;
let occurrenceId = 0;
let seriesStart = "";
let seriesEnd = "";

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

function occurrenceUrl() {
    return `/volunteer/occurrences/${occurrenceId}`;
}

/**
 * Take the fixture occurrence back to "nobody assigned".
 *
 * `DELETE /assignments/{id}` hard-deletes a row nobody has answered and cancels
 * one that carries history (§3.3.2) — so a row this spec created and never
 * responded to genuinely disappears, and one a test declined is left as the
 * audit trail it is. Tests therefore assert on the people they put there rather
 * than on a bare row count where a leftover would matter.
 */
function clearAssignments() {
    cy.makePrivateAdminAPICall(
        "GET",
        `${VOLUNTEER_URL}/occurrences/${occurrenceId}/staffing`,
        null,
        200,
    ).then((resp) => {
        const rows = [
            ...resp.body.requirements.flatMap((r) => r.assignments),
            ...(resp.body.otherAssignments ?? []),
        ];
        for (const a of rows) {
            cy.makePrivateAdminAPICall(
                "DELETE",
                `${VOLUNTEER_URL}/assignments/${a.id}`,
                null,
                [200, 404, 409],
            );
        }
    });
}

/**
 * Find the fixture ministry, or create it.
 *
 * A ministry that still owns an assignment refuses to delete (§3.3.1), and a
 * declined row is history this issue promises to preserve — so the fixture is
 * reused across runs instead of being torn down and rebuilt. Everything it
 * contains is prefixed, so nothing here can collide with another spec.
 */
function findOrCreateMinistry() {
    return cy
        .makePrivateAdminAPICall("GET", `${VOLUNTEER_URL}/ministries`, null, 200)
        .then((resp) => {
            const existing = resp.body.ministries.find((m) => m.name === MINISTRY_NAME);
            if (existing) {
                return cy.wrap(existing.id);
            }
            return cy
                .makePrivateAdminAPICall("POST", `${VOLUNTEER_URL}/ministries`, {
                    name: MINISTRY_NAME,
                    description: "UC1 worked example",
                }, 201)
                .then((created) => created.body.ministry.id);
        });
}

function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function daysToNext(dow) {
    const today = new Date().getDay();
    return ((dow - today + 7) % 7) || 7;
}

describe("Volunteer v2 — occurrence / staffing view (#9709)", () => {
    before(() => {
        setVersion("v2");

        findOrCreateMinistry().then((id) => {
            ministryId = id;
        });

        cy.then(() => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${VOLUNTEER_URL}/ministries/${ministryId}`,
                null,
                200,
            ).then((resp) => {
                const team = resp.body.teams.find((t) => t.name === TEAM_NAME);
                if (team) {
                    teamId = team.id;
                    return;
                }
                cy.makePrivateAdminAPICall(
                    "POST",
                    `${VOLUNTEER_URL}/ministries/${ministryId}/teams`,
                    { name: TEAM_NAME, description: "" },
                    201,
                ).then((created) => {
                    teamId = created.body.team.id;
                });
            });
        });

        cy.then(() => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
                null,
                200,
            ).then((resp) => {
                const wanted = [
                    [POSITION_ESPRESSO, 1],
                    [POSITION_MILK, 2],
                    [POSITION_EXPEDITOR, 3],
                ];
                for (const [name, order] of wanted) {
                    const found = resp.body.positions.find((p) => p.name === name);
                    const assignId = (id) => {
                        if (name === POSITION_ESPRESSO) {
                            posEspresso = id;
                        } else if (name === POSITION_MILK) {
                            posMilk = id;
                        } else {
                            posExpeditor = id;
                        }
                    };
                    if (found) {
                        assignId(found.id);
                        continue;
                    }
                    cy.makePrivateAdminAPICall(
                        "POST",
                        `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
                        { name, description: "", teamId, order },
                        201,
                    ).then((created) => assignId(created.body.position.id));
                }
            });
        });

        cy.then(() => {
            // D19: the ministry came with its own pool Group, empty. Adding
            // somebody already in it is 200, not 409.
            for (const personId of [POOL_MEMBER_A, POOL_MEMBER_B]) {
                cy.makePrivateAdminAPICall(
                    "POST",
                    `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${personId}`,
                    null,
                    [200, 201],
                );
            }
        });

        cy.then(() => {
            // Granting a qualification is idempotent (§3.3.1).
            for (const position of [posEspresso, posMilk]) {
                for (const personId of [POOL_MEMBER_A, POOL_MEMBER_B]) {
                    cy.makePrivateAdminAPICall(
                        "POST",
                        `${VOLUNTEER_URL}/positions/${position}/qualifications`,
                        { personId, notes: "" },
                        [200, 201],
                    );
                }
            }
        });

        cy.then(() => {
            seriesStart = isoDate(daysToNext(0));
            seriesEnd = isoDate(daysToNext(0) + 7);
            cy.makePrivateAdminAPICall("POST", "/api/events/repeat", {
                Title: EVENT_TITLE,
                Type: CHURCH_SERVICE_TYPE,
                StartTime: "10:30:00",
                EndTime: "11:45:00",
                RecurType: "weekly",
                RecurDOW: "Sunday",
                RangeStart: seriesStart,
                RangeEnd: seriesEnd,
            }, 200);
        });

        cy.then(() => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
                null,
                200,
            ).then((resp) => {
                const found = resp.body.schedules.find((s) => s.titleFilter === EVENT_TITLE);
                if (found) {
                    scheduleId = found.id;
                    return;
                }
                cy.makePrivateAdminAPICall(
                    "POST",
                    `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
                    {
                        name: `${PREFIX} Coffee Bar — Sunday`,
                        linkMode: "event_type",
                        eventTypeId: CHURCH_SERVICE_TYPE,
                        titleFilter: EVENT_TITLE,
                        windowStart: seriesStart,
                        teamId,
                    },
                    201,
                ).then((created) => {
                    scheduleId = created.body.schedule.id;
                });
            });
        });

        cy.then(() => {
            // Requirement upsert and occurrence generation are both idempotent.
            for (const positionId of [posEspresso, posMilk]) {
                cy.makePrivateAdminAPICall(
                    "POST",
                    `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`,
                    { positionId, minCount: 1, maxCount: 1 },
                    [200, 201],
                );
            }
            // §2.17's optional third person: no gap, but a slot stays open.
            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`,
                { positionId: posExpeditor, minCount: 0, maxCount: 1 },
                [200, 201],
            );
            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/schedules/${scheduleId}/generate`,
                { through: seriesEnd },
                200,
            );
        });

        cy.then(() => {
            cy.makePrivateAdminAPICall(
                "GET",
                `${VOLUNTEER_URL}/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryId}`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body.occurrences.length).to.be.greaterThan(0);
                occurrenceId = resp.body.occurrences[0].id;
            });
        });
    });

    after(() => {
        clearAssignments();
        setVersion("v1");
    });

    describe("rendering (§5.5, §5.8)", () => {
        beforeEach(() => {
            clearAssignments();
            freshAdminLogin();
        });

        it("breadcrumbs link to the dashboard and the ministry page (not relative 404s)", () => {
        // Carl's review path: from /volunteer/occurrences/{id} the "Volunteer" and
        // ministry crumbs resolved relative to the page and 404'd. Breadcrumb URLs
        // must be root-relative.
        cy.visit(`/volunteer/occurrences/${occurrenceId}`);
        cy.get(".breadcrumb a").each(($a) => {
            expect($a.attr("href"), $a.text()).to.match(/^\//);
        });
        cy.get(".breadcrumb a").contains("Volunteer").click();
        cy.url().should("include", "/volunteer/dashboard");
        cy.get("#volunteer-dashboard, .page-title").should("exist");
    });

    it("shows the header, the effective event time and the requirement cards", () => {
            cy.visit(occurrenceUrl());

            cy.get("#volunteer-occurrence").should("exist");
            cy.get("#occurrence-ministry").should("contain", MINISTRY_NAME);
            cy.get("#occurrence-ministry").should("contain", TEAM_NAME);
            // D4 made visible: the time comes from the linked event.
            cy.get("#occurrence-when").should("contain", "10:30");
            cy.get("#occurrence-event-link").should("have.attr", "href").and("include", "/event/view/");

            cy.get("#requirements-loading").should("not.be.visible");
            cy.get(`.volunteer-requirement[data-position-id="${posEspresso}"]`)
                .should("be.visible")
                .and("contain", POSITION_ESPRESSO);
            cy.get(`.volunteer-requirement[data-position-id="${posMilk}"]`).should("exist");
        });

        it("shows the gap count while a requirement is unfilled", () => {
            cy.visit(occurrenceUrl());

            cy.get(`.volunteer-requirement[data-position-id="${posEspresso}"] .requirement-counts`)
                .should("contain", "0")
                .and("contain", "1");
            cy.get(`.volunteer-requirement[data-position-id="${posEspresso}"] .requirement-gap`)
                .should("be.visible");
        });

        it("renders a first-class empty state for a position with nobody assigned", () => {
            cy.visit(occurrenceUrl());

            // Expeditor is never assigned by any test here, so its card is the
            // one empty state that cannot be disturbed by a leftover row.
            cy.get(`.volunteer-requirement[data-position-id="${posExpeditor}"] .volunteer-empty`)
                .should("be.visible");
            cy.get(`.volunteer-requirement[data-position-id="${posExpeditor}"] .requirement-gap`)
                .should("not.be.visible");
        });

        it("renders the localized page strings, never a raw key", () => {
            cy.visit(occurrenceUrl());

            cy.get("#volunteer-occurrence").should("not.contain", "undefined");
            cy.contains("Assign").should("exist");
            cy.get("#swaps-card").should("exist");
        });

        it("shows the error block with a working retry when the API fails", () => {
            // ONE stateful intercept: fail the first call, let every later one through.
            // Registering a second cy.intercept for the same pattern is unreliable here
            // — both stay registered and which one answers is not worth depending on.
            let failed = false;
            cy.intercept("**/api/volunteer/occurrences/*/staffing", (req) => {
                if (failed) {
                    req.continue();

                    return;
                }
                failed = true;
                req.reply({ statusCode: 500, body: { success: false, message: "boom" } });
            }).as("staffing");

            cy.visit(occurrenceUrl());
            cy.wait("@staffing");

            cy.get("#requirements-error").should("be.visible");
            cy.get("#requirements-error .volunteer-retry").should("be.visible");

            // Retry genuinely re-runs the load — §5.8 requires the button to work,
            // not merely to exist.
            cy.get("#requirements-error .volunteer-retry").click();
            cy.wait("@staffing");
            cy.get("#requirements-error").should("not.be.visible");
            cy.get(`.volunteer-requirement[data-position-id="${posEspresso}"]`).should("be.visible");
        });
    });

    describe("assigning through the eligible picker (§5.5)", () => {
        beforeEach(() => {
            clearAssignments();
            freshAdminLogin();
        });

        it("offers only qualified people and assigns the chosen one", () => {
            cy.visit(occurrenceUrl());

            cy.get(`.volunteer-assign-btn[data-position-id="${posEspresso}"]`).click();
            cy.get("#volunteer-assign-modal").should("have.class", "show");

            // The picker is a TomSelect, so it is driven the way a coordinator drives
            // it: open the control and click an option. Its dropdown is mounted on
            // <body> (so a modal cannot clip it), which is why the option selector is
            // not scoped to the modal.
            cy.get("#volunteer-assign-modal .ts-control").click();
            cy.get(".ts-dropdown .option").should("have.length.greaterThan", 0);
            cy.get(".ts-dropdown .option").first().click();

            cy.get("#assign-save").click();

            cy.get("#volunteer-assign-modal").should("not.have.class", "show");
            cy.get(`.volunteer-requirement[data-position-id="${posEspresso}"] .volunteer-assignment-row[data-status="pending"]`)
                .should("have.length", 1);
            cy.get(`.volunteer-requirement[data-position-id="${posEspresso}"] .requirement-gap`)
                .should("not.be.visible");
            cy.get(`.volunteer-requirement[data-position-id="${posEspresso}"] .volunteer-status-badge`)
                .should("contain", "Pending");
        });

        it("warns — and does not block — when the person already serves another position (I7/D16)", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/occurrences/${occurrenceId}/assignments`,
                { positionId: posEspresso, personId: POOL_MEMBER_A },
                201,
            );

            freshAdminLogin();
            cy.visit(occurrenceUrl());

            cy.get(`.volunteer-assign-btn[data-position-id="${posMilk}"]`).click();
            cy.get("#volunteer-assign-modal").should("have.class", "show");

            cy.get("#volunteer-assign-modal .ts-control").click();
            cy.get(`.ts-dropdown .option[data-value="${POOL_MEMBER_A}"]`).click();

            // A Tabler alert-warning, not a bootbox gate and not a server error.
            cy.get("#assign-conflict-warning")
                .should("be.visible")
                .and("have.class", "alert-warning")
                .and("contain", POSITION_ESPRESSO);

            cy.get("#assign-save").should("not.be.disabled").click();
            cy.get(`.volunteer-requirement[data-position-id="${posMilk}"] .volunteer-assignment-row[data-status="pending"]`)
                .should("have.length", 1);
        });

        it("links each assigned person to their person record", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/occurrences/${occurrenceId}/assignments`,
                { positionId: posEspresso, personId: POOL_MEMBER_A },
                201,
            );

            freshAdminLogin();
            cy.visit(occurrenceUrl());

            cy.get(`.volunteer-requirement[data-position-id="${posEspresso}"] .volunteer-assignment-row a`)
                .first()
                .should("have.attr", "href")
                .and("include", `/people/view/${POOL_MEMBER_A}`);
        });
    });

    describe("row actions (§5.5, CR2)", () => {
        beforeEach(() => {
            clearAssignments();
            cy.makePrivateAdminAPICall(
                "POST",
                `${VOLUNTEER_URL}/occurrences/${occurrenceId}/assignments`,
                { positionId: posEspresso, personId: POOL_MEMBER_A },
                201,
            );
            freshAdminLogin();
        });

        it("uses the shared action-menu scaffold, not a volunteer-only one", () => {
            cy.visit(occurrenceUrl());

            cy.get(".volunteer-assignment-row .dropdown > button")
                .first()
                .should("have.attr", "data-bs-display", "static")
                .find("i.fa-ellipsis-vertical")
                .should("exist");
        });

        it("cancels an assignment behind a bootbox confirm and reopens the gap", () => {
            cy.visit(occurrenceUrl());

            cy.get(".volunteer-assignment-row .dropdown > button").first().click();
            cy.get('.dropdown-item[data-action="cancel"]').first().click();

            cy.get(".bootbox.modal").should("be.visible");
            cy.get(".bootbox .btn-primary, .bootbox .btn-danger").last().click();

            cy.get(`.volunteer-requirement[data-position-id="${posEspresso}"] .requirement-gap`)
                .should("be.visible");
            cy.get(`.volunteer-requirement[data-position-id="${posEspresso}"] .volunteer-status-badge`)
                .should("contain", "Cancelled");
        });

        it("records a decline on the volunteer's behalf from the menu", () => {
            cy.visit(occurrenceUrl());

            cy.get(".volunteer-assignment-row .dropdown > button").first().click();
            cy.get('.dropdown-item[data-action="declined"]').first().click();
            cy.get(".bootbox.modal").should("be.visible");
            cy.get(".bootbox .btn-primary, .bootbox .btn-danger").last().click();

            cy.get(`.volunteer-requirement[data-position-id="${posEspresso}"] .volunteer-status-badge`)
                .should("contain", "Declined");
        });
    });

    describe("the cart sink (P6)", () => {
        beforeEach(() => {
            clearAssignments();
            freshAdminLogin();
        });

        it("assigns everyone in the cart to one position", () => {
            // The cart is $_SESSION state, so it has to be seeded in the SAME PHP
            // session the browser is using. `cy.makePrivateAdminAPICall` authenticates
            // with an x-api-key and sends no session cookie, which lands the cart in a
            // different session entirely — and `freshAdminLogin()` clears cookies, so
            // seeding before the login would throw it away too. A plain cy.request
            // after the login rides the session cookie and is the only thing that works.
            cy.request({
                method: "DELETE",
                url: "/api/cart/",
                failOnStatusCode: false,
            });
            cy.request({
                method: "POST",
                url: "/api/cart/",
                headers: { "content-type": "application/json" },
                body: { Persons: [POOL_MEMBER_A, POOL_MEMBER_B] },
            });

            cy.visit(occurrenceUrl());

            cy.get(`.volunteer-cart-assign[data-position-id="${posEspresso}"]`).click();
            cy.get(".bootbox.modal").should("be.visible");
            cy.get(".bootbox .btn-primary, .bootbox .btn-danger").last().click();

            cy.get(`.volunteer-requirement[data-position-id="${posEspresso}"] .volunteer-assignment-row[data-status="pending"]`)
                .should("have.length", 2);
        });
    });

    describe("the swap queue (§5.5)", () => {
        beforeEach(() => {
            clearAssignments();
            freshAdminLogin();
        });

        it("shows its own empty state when nothing is proposed", () => {
            cy.visit(occurrenceUrl());
            cy.get("#swaps-empty").should("be.visible");
            cy.get("#swaps-loading").should("not.be.visible");
        });
    });
});
