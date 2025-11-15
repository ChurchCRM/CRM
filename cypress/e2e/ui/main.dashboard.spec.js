/// <reference types="cypress" />

describe("Main Dashboard", () => {
    it("Loads all", () => {
        cy.loginStandard("v2/dashboard");
        cy.contains("Welcome to");
        cy.contains("See all Families");
        cy.contains("See All People");
        cy.contains("Sunday School Classes");
    });
});
