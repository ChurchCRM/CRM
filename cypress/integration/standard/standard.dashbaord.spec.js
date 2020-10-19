/// <reference types="cypress" />

context('Dashbaord', () => {
    beforeEach(() => {
        cy.loginStandard();
    });

    it('Loads all', () => {

        cy.contains("Welcome to");
        cy.contains("See all Families")
        cy.contains("See all People")
        cy.contains("Sunday School Classes")

    });

});
