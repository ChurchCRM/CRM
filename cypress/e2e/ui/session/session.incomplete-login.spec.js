/// <reference types="cypress" />

/**
 * A login that did not finish must not leave a usable session, whatever
 * iSessionTimeout is set to. Session timeouts are turned off for this spec
 * (iSessionTimeout = 0), since that is the setting under which an unfinished
 * login used to pass as a finished one.
 */
describe("Unfinished logins are not sessions", () => {
    before(() => {
        cy.rememberTestEnv(["standard.username", "standard.password"]);
    });
    const STANDARD_USER_ID = 3;
    let originalTimeout;

    const postLogin = (user, password) =>
        cy.request({
            method: "POST",
            url: "/session/begin",
            form: true,
            body: { User: user, Password: password },
            followRedirect: false,
            failOnStatusCode: false,
        });

    const expectSignedOut = () => {
        cy.request({ url: "/v2/dashboard", followRedirect: false, failOnStatusCode: false }).then((resp) => {
            expect(resp.status, "dashboard must redirect, not render").to.eq(302);
            expect(resp.redirectedToUrl).to.include("/session/begin");
        });
        cy.request({ url: "/api/persons/latest", failOnStatusCode: false })
            .its("status")
            .should("eq", 401);
    };

    before(() => {
        cy.getSystemConfig("iSessionTimeout").then((value) => {
            originalTimeout = value;
        });
        cy.makePrivateAdminAPICall("POST", "/admin/api/system/config/iSessionTimeout", { value: "0" }, 200);
    });

    after(() => {
        cy.restoreSystemConfig("iSessionTimeout", originalTimeout);
    });

    beforeEach(() => {
        cy.clearCookies();
    });

    afterEach(() => {
        cy.makePrivateAdminAPICall("POST", `/admin/api/user/${STANDARD_USER_ID}/login/reset`, null, 200);
    });

    it("a wrong password does not sign the user in", () => {
        postLogin(Cypress.testEnv("standard.username"), "not-the-password").its("status").should("eq", 200);
        expectSignedOut();
    });

    it("a correct password that still needs a 2FA code does not sign the user in", () => {
        postLogin("twofa_user", "changeme").then((resp) => {
            expect(resp.status).to.eq(302);
            expect(resp.redirectedToUrl).to.include("/session/two-factor");
        });
        expectSignedOut();
    });

    it("a completed login still works", () => {
        postLogin(Cypress.testEnv("standard.username"), Cypress.testEnv("standard.password")).then((resp) => {
            expect(resp.status).to.eq(302);
            expect(resp.redirectedToUrl).to.include("/v2/dashboard");
        });
        cy.request({ url: "/v2/dashboard", followRedirect: false }).its("status").should("eq", 200);
        cy.request("/api/persons/latest").its("status").should("eq", 200);
    });
});
