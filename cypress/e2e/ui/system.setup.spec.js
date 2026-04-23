/// <reference types="cypress" />

describe("Test Post Setup block", () => {
    it("Redirects to session/begin", () => {
        // Clear any session from previous tests so /setup sees an unauthenticated request
        // and redirects to session/begin rather than serving a logged-in page.
        cy.clearCookies();
        cy.clearLocalStorage();
        cy.visit("/setup");
        cy.location("pathname", { timeout: 10000 }).should("include", "/session/begin");
    });
});
