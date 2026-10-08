/// <reference types="cypress" />

/**
 * Volunteer v2 rollout states — issue #9704, epic #9701.
 *
 * `sVolunteerVersion` is a three-state `choice` SystemConfig item (`v1` | `v2` |
 * `both`, default `v1`). This spec proves the rollout decision is enforced
 * SERVER-SIDE at every boundary the flag touches:
 *
 *   - `GET /api/ministries/status`        — V2 API, gated by VolunteerV2EnabledMiddleware
 *   - `GET /admin/api/volunteer-opportunities` — V1 API, deliberately enabled in EVERY state
 *                                          (design §3.8 surface 5; #9702 owns its retirement)
 *   - `GET /ministries/dashboard`         — V2 MVC module, gated by the same middleware
 *                                          plus VolunteerCoordinatorRoleAuthMiddleware on the
 *                                          route group (#9706 replaced #9704's admin gate)
 *
 * Two things are NOT asserted here and live in
 * cypress/e2e/ui-admin/admin.volunteer-v2.rollout.spec.js instead:
 *
 *   1. The person-view tab labels. `src/people/routes/view.php:3` requires
 *      Include/PageInit.php, which calls ensureAuthentication() at file-load
 *      time — before any Slim middleware runs — so `/people/*` 302s to
 *      /session/begin for an x-api-key request and cannot be asserted here.
 *   2. `VolunteerOpportunityEditor.php`, a legacy page with the same
 *      PageInit.php problem.
 *
 * Cleanup-before pattern (cypress-testing.md): the setting is restored to the
 * default in `before()` as well as `after()`, so a run that crashed mid-way
 * cannot leave the whole installation in v2 mode for every later spec.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const STATUS_URL = "/api/ministries/status";
const V1_API_URL = "/admin/api/volunteer-opportunities";
const DASHBOARD_URL = "/ministries/dashboard";

/** Set the rollout state. The POST response body is not asserted: ConfigItem::setValue()
 *  deletes the config_cfg row when the value equals the default but leaves the in-memory
 *  value cached, so the response echoes the previous value when restoring to `v1`. */
function setVersion(value) {
    cy.makePrivateAdminAPICall("POST", SETTING_URL, { value }, 200);
}

function expectStoredVersion(value) {
    cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
        expect(resp.body.value).to.eq(value);
    });
}

/** A browser-style page request: no Accept header games, no redirect following,
 *  authenticated with an API key (AuthMiddleware honours x-api-key on MVC pages
 *  that do not require Include/PageInit.php). */
function pageRequest(url, apiKey) {
    return cy.request({
        method: "GET",
        url,
        headers: { "x-api-key": apiKey },
        failOnStatusCode: false,
        followRedirect: false,
        withCredentials: false,
    });
}

function adminPageRequest(url) {
    return pageRequest(url, Cypress.testEnv("admin.api.key"));
}

before(() => {
    cy.rememberTestEnv(["admin.api.key", "user.api.key"]);
});

describe("Volunteer v2 rollout flag (#9704)", () => {
    before(() => {
        // Cleanup-before: a crashed earlier run may have left v2 or both set.
        setVersion("v1");
    });

    after(() => {
        setVersion("v1");
    });

    it("defaults to v1 and reports v1 through the config API", () => {
        expectStoredVersion("v1");
    });

    describe("v1 (default, legacy only)", () => {
        beforeEach(() => {
            setVersion("v1");
        });

        it("blocks the V2 status API with 403 JSON", () => {
            cy.makePrivateAdminAPICall("GET", STATUS_URL, null, 403).then((resp) => {
                expect(resp.body).to.have.property("success", false);
                expect(resp.body).to.have.property("message");
                expect(resp.body.message).to.be.a("string").and.not.be.empty;
            });
        });

        it("leaves the V1 volunteer-opportunities API enabled", () => {
            cy.makePrivateAdminAPICall("GET", V1_API_URL, null, 200).then((resp) => {
                expect(resp.body).to.have.property("volunteerOpportunities");
            });
        });

        it("redirects the V2 dashboard to the root path", () => {
            adminPageRequest(DASHBOARD_URL).then((resp) => {
                expect(resp.status).to.eq(302);
                expect(resp.headers.location).to.match(/\/$/);
                expect(resp.headers.location).to.not.include("volunteer");
            });
        });
    });

    describe("v2 (new experience only)", () => {
        beforeEach(() => {
            setVersion("v2");
        });

        it("serves the V2 status API", () => {
            cy.makePrivateAdminAPICall("GET", STATUS_URL, null, 200).then((resp) => {
                expect(resp.body).to.deep.eq({
                    version: "v2",
                    v1Enabled: false,
                    v2Enabled: true,
                });
            });
        });

        it("leaves the V1 volunteer-opportunities API enabled", () => {
            cy.makePrivateAdminAPICall("GET", V1_API_URL, null, 200).then((resp) => {
                expect(resp.body).to.have.property("volunteerOpportunities");
            });
        });

        it("serves the V2 dashboard to an administrator", () => {
            adminPageRequest(DASHBOARD_URL).then((resp) => {
                expect(resp.status).to.eq(200);
                // Renamed from "Volunteer Dashboard" on 2026-09-17.
                expect(resp.body).to.include("Ministry Dashboard");
            });
        });

        it("redirects the bare /ministries/ module URL to /ministries/dashboard", () => {
            adminPageRequest("/ministries/").then((resp) => {
                expect(resp.status).to.eq(302);
                expect(resp.headers.location).to.include("/ministries/dashboard");
            });
        });

        it("denies the V2 dashboard to a non-admin with no volunteer scope", () => {
            // person 3 holds every permission flag except Admin, no usr_ManageMinistries
            // and no volunteer_scope_vscp row, so VolunteerCoordinatorRoleAuthMiddleware
            // turns them away. The scoped positive path lives in
            // cypress/e2e/api/private/standard/private.volunteer.authorization.spec.js (#9706).
            pageRequest(DASHBOARD_URL, Cypress.testEnv("user.api.key")).then((resp) => {
                expect(resp.status).to.be.oneOf([302, 403]);
                if (resp.status === 302) {
                    expect(resp.headers.location).to.include("access-denied");
                }
            });
        });
    });

    describe("both (side-by-side transition)", () => {
        beforeEach(() => {
            setVersion("both");
        });

        it("serves the V2 status API and reports both experiences enabled", () => {
            cy.makePrivateAdminAPICall("GET", STATUS_URL, null, 200).then((resp) => {
                expect(resp.body).to.deep.eq({
                    version: "both",
                    v1Enabled: true,
                    v2Enabled: true,
                });
            });
        });

        it("leaves the V1 volunteer-opportunities API enabled", () => {
            cy.makePrivateAdminAPICall("GET", V1_API_URL, null, 200).then((resp) => {
                expect(resp.body).to.have.property("volunteerOpportunities");
            });
        });

        it("serves the V2 dashboard", () => {
            adminPageRequest(DASHBOARD_URL).then((resp) => {
                expect(resp.status).to.eq(200);
            });
        });
    });

    describe("unknown stored value", () => {
        beforeEach(() => {
            // Nothing validates the choice list server-side, so an operator (or a
            // botched migration) can store anything. Unknown must degrade to v1.
            setVersion("wat");
        });

        it("is treated as v1 everywhere", () => {
            cy.makePrivateAdminAPICall("GET", STATUS_URL, null, 403);
            cy.makePrivateAdminAPICall("GET", V1_API_URL, null, 200);
            adminPageRequest(DASHBOARD_URL).then((resp) => {
                expect(resp.status).to.eq(302);
            });
        });
    });
});

describe("Volunteer v2 \"Other\" event type, added when V2 is turned on (#10357)", () => {
    const SEED_OTHER_TYPE = 3;
    const RENAMED = "VROLL10357 Misc";
    const DEFAULT_TYPE_URL = "/admin/api/system/config/iVolunteerDefaultEventTypeId";

    function otherTypes() {
        return cy
            .dbQuery(
                "SELECT type_id AS id, type_defrecurtype AS recur, type_active AS active FROM event_types WHERE type_name = 'Other' ORDER BY type_id",
            )
            .then((result) => result.rows);
    }

    function defaultEventTypeId() {
        return cy.makePrivateAdminAPICall("GET", "/api/ministries/event-types", null, 200).its("body.defaultEventTypeId");
    }

    beforeEach(() => {
        setVersion("v1");
        cy.makePrivateAdminAPICall("POST", DEFAULT_TYPE_URL, { value: "" }, 200);
        // The seed keeps "Other" as type 3 for the V2 specs; a church that never turned V2 on has none.
        cy.dbQuery("DELETE FROM event_types WHERE type_name IN ('Other', ?)", [RENAMED]);
    });

    after(() => {
        setVersion("v1");
        cy.dbQuery("DELETE FROM event_types WHERE type_name IN ('Other', ?)", [RENAMED]);
        cy.dbQuery(
            `INSERT INTO event_types (type_id, type_name, type_defstarttime, type_defrecurtype, type_defrecurDOW, type_defrecurDOM, type_defrecurDOY, type_active)
             VALUES (?, 'Other', '00:00:00', 'none', 'Sunday', '', '2016-01-01', 1)`,
            [SEED_OTHER_TYPE],
        );
    });

    it("adds none while the church stays on v1", () => {
        setVersion("v1");
        setVersion("wat");
        setVersion("v1");
        cy.makePrivateAdminAPICall("GET", "/api/events/types", null, 200);
        otherTypes().should("have.length", 0);
    });

    it("adds one active type with no recurrence defaults when V2 is turned on, and new ministry events start on it", () => {
        setVersion("v2");
        otherTypes().then((rows) => {
            expect(rows).to.have.length(1);
            expect(rows[0]).to.include({ recur: "none", active: 1 });
            defaultEventTypeId().should("eq", rows[0].id);
        });
    });

    it("adds it for both, too", () => {
        setVersion("both");
        otherTypes().should("have.length", 1);
    });

    it("adds no second one however often V2 is switched off and on", () => {
        for (const value of ["v2", "v1", "both", "v1", "v2", "v2", "both"]) {
            setVersion(value);
        }
        otherTypes().should("have.length", 1);
    });

    it("leaves a renamed one renamed while V2 stays on", () => {
        setVersion("v2");
        otherTypes().then(([row]) => {
            cy.dbQuery("UPDATE event_types SET type_name = ? WHERE type_id = ?", [RENAMED, row.id]);
        });
        setVersion("v2");
        setVersion("both");
        otherTypes().should("have.length", 0);
        defaultEventTypeId().should("eq", null);
    });

    it("adds none beside a retired type of that name", () => {
        cy.dbQuery(
            `INSERT INTO event_types (type_name, type_defstarttime, type_defrecurtype, type_defrecurDOW, type_defrecurDOM, type_defrecurDOY, type_active)
             VALUES ('Other', '00:00:00', 'none', 'Sunday', '', '2016-01-01', 0)`,
        );
        setVersion("v2");
        otherTypes().then((rows) => {
            expect(rows).to.have.length(1);
            expect(rows[0].active).to.eq(0);
        });
        defaultEventTypeId().should("eq", null);
    });
});
