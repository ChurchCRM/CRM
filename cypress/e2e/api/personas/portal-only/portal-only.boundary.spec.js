/// <reference types="cypress" />

// A self-service account may reach only the Member Portal, its own sign-in flow
// and the public API. Probes use the browser session, which a portal member keeps
// after the sign-in cutover; the API key does not.
const DENIED = [
    ["GET", "/api/person/2"],
    ["GET", "/api/persons/latest"],
    ["GET", "/api/family/1"],
    ["GET", "/api/deposits"],
    ["GET", "/api/cart"],
    ["GET", "/api/calendars"],
    ["GET", "/api/ministries/ministries"],
    ["GET", "/admin/api/system/config/sChurchName"],
    ["GET", "/finance/api/funds"],
    ["POST", "/api/user/4/apikey/regen"],
    ["GET", "/api/portal-evil"],
    ["GET", "/api/evil/api/user/current/2fa-status"],
    ["GET", "/api/person/2/api/portal/me"],
];

const DORMANT_FLAGS_DENIED = [
    ["GET", "/api/person/2"],
    ["GET", "/api/family/1"],
    ["GET", "/admin/api/system/config/sChurchName"],
    ["GET", "/finance/api/funds"],
];

const expectRefused = (method, url) =>
    cy.request({ method, url, body: method === "POST" ? {} : undefined, failOnStatusCode: false }).then((response) => {
        expect(response.status).to.eq(403);
        expect(response.body.success).to.eq(false);
    });

describe("portal-only: the API is closed outside the portal", () => {
    beforeEach(() => cy.setupPersonaSession("portal-only"));

    DENIED.forEach(([method, url]) => {
        it(`${method} ${url} is refused`, () => expectRefused(method, url));
    });

    it("reaches its own portal API", () => {
        cy.request("/api/portal/me").its("status").should("eq", 200);
    });

    it("reaches the 2FA status route of its sign-in flow", () => {
        cy.request("/api/user/current/2fa-status").its("status").should("eq", 200);
    });

    it("reaches the public API", () => {
        cy.request("/api/public/echo").its("status").should("eq", 200);
    });
});

describe("portal-only-dormant-flags: a leftover staff flag does not open the API", () => {
    beforeEach(() => cy.setupPersonaSession("portal-only-dormant-flags"));

    DORMANT_FLAGS_DENIED.forEach(([method, url]) => {
        it(`${method} ${url} is refused`, () => expectRefused(method, url));
    });
});
