/// <reference types="cypress" />

describe("Main Dashboard", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("Loads all", () => {
        cy.visit("v2/dashboard");
        cy.contains("Families");
        cy.contains("People");
        cy.contains("Sunday School");
    });
});
