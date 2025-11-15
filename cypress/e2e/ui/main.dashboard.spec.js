/// <reference types="cypress" />

describe("Main Dashboard", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("Loads all", () => {
        cy.visit("v2/dashboard");
        cy.contains("Welcome to");
        cy.contains("See all Families");
        cy.contains("See All People");
        cy.contains("Sunday School Classes");
    });
});
