/// <reference types="cypress" />

context("Finance Family", () => {
    it("View a Family", () => {
        cy.loginAdmin("v2/family/1");
        cy.contains("Campbell - Family");
        cy.contains("Darren Campbell");
        cy.contains("Music Ministry");

        cy.visit("v2/family/20");
        cy.contains("Black - Family");
        cy.contains("New Building Fund");
    });
});
