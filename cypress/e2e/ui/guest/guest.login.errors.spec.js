/// <reference types="cypress" />

context("Login", () => {
    it("Bad password", () => {
        cy.login("admin", "badpassword");
        cy.location("pathname").should("include", "session/begin");
    });

    it("Bad username", () => {
        cy.login("idonknowyou", "badpassword");
        cy.location("pathname").should("include", "session/begin");
    });
});
