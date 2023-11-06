/// <reference types="cypress" />

context("API Private Admin User", () => {
    it("Reset User failed logins", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/user/95/login/reset",
            null,
            200,
        );
    });

    it("Reset User Password", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/user/95/password/reset",
            null,
            200,
        );
    });

    it("DisableTwoFactor", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/user/95/disableTwoFactor",
            null,
            200,
        );
    });
});
