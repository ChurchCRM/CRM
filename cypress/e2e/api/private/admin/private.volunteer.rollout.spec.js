/// <reference types="cypress" />

/**
 * Volunteer v2 rollout states — issue #9704, epic #9701.
 *
 * `sVolunteerVersion` is a three-state `choice` SystemConfig item (`v1` | `v2` |
 * `both`, default `v1`). This spec proves the rollout decision is enforced
 * SERVER-SIDE at every boundary the flag touches:
 *
 *   - `GET /api/volunteer/status`        — V2 API, gated by VolunteerV2EnabledMiddleware
 *   - `GET /api/volunteer-opportunities` — V1 API, deliberately enabled in EVERY state
 *                                          (design §3.8 surface 5; #9702 owns its retirement)
 *   - `GET /volunteer/dashboard`         — V2 MVC module, gated by the same middleware
 *                                          plus AdminRoleAuthMiddleware on the route group
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
const STATUS_URL = "/api/volunteer/status";
const V1_API_URL = "/api/volunteer-opportunities";
const DASHBOARD_URL = "/volunteer/dashboard";

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
    return pageRequest(url, Cypress.env("admin.api.key"));
}

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
                expect(resp.body).to.include("Volunteer");
            });
        });

        it("redirects /volunteer to /volunteer/dashboard", () => {
            adminPageRequest("/volunteer/").then((resp) => {
                expect(resp.status).to.eq(302);
                expect(resp.headers.location).to.include("/volunteer/dashboard");
            });
        });

        it("denies the V2 dashboard to a non-admin (the route group is admin-gated for now)", () => {
            pageRequest(DASHBOARD_URL, Cypress.env("user.api.key")).then((resp) => {
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
