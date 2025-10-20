/// <reference types="cypress" />

describe("Test Post Setup block", () => {
    
    it("Redirects to session/begin", () => {
        cy.visit("/setup");
        cy.location("pathname").should("eq", "/session/begin");
    });
});
