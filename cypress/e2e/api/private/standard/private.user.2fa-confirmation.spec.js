/// <reference types="cypress" />

import { totp } from "../../../../support/totp";

/**
 * #10200: once 2FA is set up, changing it needs the current password or a
 * current authenticator code, not just a session or an API key. Enrolling
 * for the first time still works without one.
 *
 * The standard user (person 3) enrolls through a browser session; the
 * refusals and confirmations then go through their API key, which has no
 * session to remember a confirmation in.
 */
describe("2FA changes need the password or a code (#10200)", () => {
    const USER_ID = 3;
    let secret;

    const asUser = (method, url, body = null, expectedStatus = 200) => {
        cy.clearCookies();
        return cy.makePrivateUserAPICall(method, url, body, expectedStatus);
    };
    const session = (method, url, body) =>
        cy.request({ method, url, body, failOnStatusCode: false, headers: { "content-type": "application/json" } });

    before(() => {
        cy.rememberTestEnv(["standard.password"]);
        cy.makePrivateAdminAPICall("POST", `/admin/api/user/${USER_ID}/disableTwoFactor`, null, 200);

        // First enrollment needs no confirmation.
        cy.setupStandardSession({ forceLogin: true });
        session("POST", "/api/user/current/refresh2fasecret").then((resp) => {
            expect(resp.status).to.eq(200);
            secret = resp.body.TwoFASecret;
            expect(secret, "setup key").to.match(/^[A-Z2-7]+$/);
        });
        cy.then(() => totp(secret))
            .then((code) => session("POST", "/api/user/current/test2FAEnrollmentCode", { enrollmentCode: code }))
            .its("body.IsEnrollmentCodeValid")
            .should("eq", true);
        // The wizard's next step, in the same session, is remembered.
        session("POST", "/api/user/current/refresh2farecoverycodes").its("status").should("eq", 200);
    });

    after(() => {
        cy.makePrivateAdminAPICall("POST", `/admin/api/user/${USER_ID}/disableTwoFactor`, null, 200);
        cy.makePrivateAdminAPICall("POST", `/admin/api/user/${USER_ID}/login/reset`, null, 200);
    });

    it("refuses every change without a confirmation", () => {
        for (const path of ["remove2fasecret", "refresh2fasecret", "refresh2farecoverycodes", "test2FAEnrollmentCode"]) {
            asUser("POST", `/api/user/current/${path}`, null, 403).its("body.confirmationRequired").should("eq", true);
        }
        asUser("GET", "/api/user/current/2fa-status").its("body.IsEnabled").should("eq", true);
    });

    it("never shows the live secret again", () => {
        asUser("GET", "/api/user/current/get2faqrcode", null, 409);
    });

    it("refuses a wrong password", () => {
        asUser("POST", "/api/user/current/refresh2farecoverycodes", { confirmation: "not-the-password" }, 403);
        cy.makePrivateAdminAPICall("POST", `/admin/api/user/${USER_ID}/login/reset`, null, 200);
    });

    it("accepts a current authenticator code", () => {
        totp(secret).then((code) => asUser("POST", "/api/user/current/refresh2farecoverycodes", { confirmation: code }, 200));
    });

    it("accepts the password, and disabling clears 2FA", () => {
        asUser("POST", "/api/user/current/remove2fasecret", { confirmation: Cypress.testEnv("standard.password") }, 200);
        asUser("GET", "/api/user/current/2fa-status").its("body.IsEnabled").should("eq", false);
        // With no 2FA left there is nothing to protect.
        asUser("POST", "/api/user/current/remove2fasecret", null, 200);
        asUser("GET", "/api/user/current/get2faqrcode", null, 404);
    });
});
