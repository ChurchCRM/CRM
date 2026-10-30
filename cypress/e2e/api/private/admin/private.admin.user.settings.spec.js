/// <reference types="cypress" />

// User assignment:
//   Login-reset + DisableTwoFactor  → user 901 (noperm.user); neither changes its API key.
//     Avoid user 95 (judith.matthews) — used by nofinance session tests.
//
//   Password-reset → user 8 (mustchange.user, usr_apiKey = NULL)
//     updatePassword() also rotates usr_ApiKey (GHSA-f2fq-4rmp-9x8c fix), so
//     resetting the password of a user whose API key other specs use would
//     break them. User 8 has usr_apiKey = NULL and no other API-suite spec depends on it.
describe("API Private Admin User", () => {
    it("Reset User failed logins", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/user/901/login/reset",
            null,
            200,
        );
    });

    it("Reset User Password", () => {
        // Use user 8 (mustchange.user) — see comment at top of file.
        const inbox = `${Cypress.mailpitUrl()}/api/v1/search?query=${encodeURIComponent("to:herminia.bennett@example.com")}`;
        cy.request({ method: "DELETE", url: inbox });
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/user/8/password/reset",
            null,
            200,
        );
        // The sender name is the church, so the subject does not repeat it.
        cy.request(`${inbox}&limit=1`).its("body.messages.0.Subject").should("eq", "Password Reset");
    });

    it("DisableTwoFactor", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/user/901/disableTwoFactor",
            null,
            200,
        );
    });
});
