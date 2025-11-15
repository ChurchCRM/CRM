/// <reference types="cypress" />

describe("Report Issue", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("Click Menus to Report issue", () => {
        cy.visit("v2/dashboard");
        cy.get(".fa-headset").click();
        cy.get("#reportIssue").click();
        cy.contains("Issue Report!");
    });
});
