/// <reference types="cypress" />

describe("Login", () => {
    it("Bad password", () => {
        cy.loginWithCredentials("admin", "badpassword", "bad-password-session", false);
        cy.location("pathname").should("include", "session/begin");
    });

    it("Bad username", () => {
        cy.loginWithCredentials("idonknowyou", "badpassword", "bad-username-session", false);
        cy.location("pathname").should("include", "session/begin");
    });
});
