/// <reference types="cypress" />

describe("Standard Reports", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("Gen Newsletter Labels ", () => {
        cy.visit("/people/reports/letters-and-labels");
        cy.contains("Letters and Mailing Labels");
        cy.contains("Newsletter labels");
    });

    it("Gen data labels", () => {
        cy.visit("/people/reports/letters-and-labels");
        cy.contains("Letters and Mailing Labels");
        cy.contains("Confirm data labels");
    });
});
