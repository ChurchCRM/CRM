/// <reference types="cypress" />

// Use user 99 (amanda.black) for these tests to avoid conflicts with
// user 95 (judith.matthews) which is used for nofinance session tests
describe("API Private Admin User", () => {
    it("Reset User failed logins", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/user/99/login/reset",
            null,
            200,
        );
    });

    it("Reset User Password", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/user/99/password/reset",
            null,
            200,
        );
    });

    it("DisableTwoFactor", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/user/99/disableTwoFactor",
            null,
            200,
        );
    });
});
