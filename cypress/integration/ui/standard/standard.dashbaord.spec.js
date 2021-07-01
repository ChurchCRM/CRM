/// <reference types="cypress" />

context('Standard Dashbaord', () => {

    it('Loads all', () => {
        cy.loginStandard();
        cy.contains("Welcome to");
        cy.contains("See all Families")
        cy.contains("See All People")
        cy.contains("Sunday School Classes")

    });

});
