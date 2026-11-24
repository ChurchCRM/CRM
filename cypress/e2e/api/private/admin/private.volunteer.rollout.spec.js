/// <reference types="cypress" />

/**
 * Ministries is the only volunteer experience (#10606): the Volunteer Opportunities
 * surfaces and the rollout settings are gone, and the Ministries module answers with no
 * rollout gate. The browser-visible half lives in
 * cypress/e2e/ui-admin/admin.volunteer-v2.rollout.spec.js.
 */

const DASHBOARD_URL = "/ministries/dashboard";

/** A browser-style page request authenticated with an API key, redirects not followed. */
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

describe("Volunteer Management with Ministries only (#10606)", () => {
    it("has no rollout, conversion or notice setting", () => {
        ["sVolunteerVersion", "aVolunteerV1Conversion", "sVolunteerV1NoticeDismissed"].forEach((name) => {
            cy.makePrivateAdminAPICall("GET", `/admin/api/system/config/${name}`, null, 404);
        });
    });

    it("has no Volunteer Opportunities, conversion or rollout status API", () => {
        cy.makePrivateAdminAPICall("GET", "/admin/api/volunteer-opportunities", null, 404);
        cy.makePrivateAdminAPICall("GET", "/admin/api/volunteer-conversion/preview", null, 404);
        cy.makePrivateAdminAPICall("GET", "/api/ministries/status", null, 404);
    });

    it("has no conversion page", () => {
        adminPageRequest("/admin/volunteer-conversion").its("status").should("eq", 404);
    });

    it("serves the Ministry Dashboard to an administrator", () => {
        adminPageRequest(DASHBOARD_URL).then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body).to.include("Ministry Dashboard");
        });
    });

    it("redirects the bare /ministries/ module URL to /ministries/dashboard", () => {
        adminPageRequest("/ministries/").then((resp) => {
            expect(resp.status).to.eq(302);
            expect(resp.headers.location).to.include("/ministries/dashboard");
        });
    });

    it("denies the Ministry Dashboard to a non-admin with no volunteer scope", () => {
        // person 3 holds every permission flag except Admin, no usr_ManageMinistries
        // and no volunteer_scope_vscp row; the scoped positive path lives in
        // private.volunteer.authorization.spec.js.
        pageRequest(DASHBOARD_URL, Cypress.testEnv("user.api.key")).then((resp) => {
            expect(resp.status).to.be.oneOf([302, 403]);
            if (resp.status === 302) {
                expect(resp.headers.location).to.include("access-denied");
            }
        });
    });
});
