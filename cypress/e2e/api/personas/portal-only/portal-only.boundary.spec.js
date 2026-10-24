/// <reference types="cypress" />

// A member session reaches only the Member Portal API and the public API; every
// other route answers it as signed out.
const REFUSED = [
    ["GET", "/api/person/2"],
    ["GET", "/api/persons/latest"],
    ["GET", "/api/family/1"],
    ["GET", "/api/deposits"],
    ["GET", "/api/cart"],
    ["GET", "/api/calendars"],
    ["GET", "/api/ministries/ministries"],
    ["GET", "/api/user/current/2fa-status"],
    ["GET", "/admin/api/system/config/sChurchName"],
    ["GET", "/finance/api/funds"],
    ["POST", "/api/user/4/apikey/regen"],
    ["GET", "/api/portal-evil"],
    ["GET", "/api/evil/api/portal/me"],
    ["GET", "/api/person/2/api/portal/me"],
];

describe("portal-only: the API is closed outside the portal", () => {
    beforeEach(() => cy.setupPersonaSession("portal-only"));

    REFUSED.forEach(([method, url]) => {
        it(`${method} ${url} is refused`, () =>
            cy
                .request({ method, url, body: method === "POST" ? {} : undefined, failOnStatusCode: false })
                .then((response) => {
                    expect(response.status).to.eq(401);
                    expect(response.body.success).to.eq(false);
                }));
    });

    it("reaches its own portal API", () => {
        cy.request("/api/portal/me").its("status").should("eq", 200);
    });

    it("reaches the public API", () => {
        cy.request("/api/public/echo").its("status").should("eq", 200);
    });
});
