/// <reference types="cypress" />

describe("Test Post Setup block", () => {
    
    it("Redirects to session/begin", () => {
        cy.visit("/setup");
        // Use 'include' instead of 'eq' to support both root (/) and subdirectory (/churchcrm/) installations
        cy.location("pathname").should("include", "/session/begin");
    });
});
