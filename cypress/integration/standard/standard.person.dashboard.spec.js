/// <reference types="cypress" />

context('Actions', () => {
    beforeEach(() => {
        cy.loginStandard();
    })

    it('Open the People Dashboard', () => {
        cy.visit("/PeopleDashboard.php");
        cy.contains('People Dashboard');
        cy.contains('People Functions');
        cy.contains('Reports');
        cy.contains('Family Roles');
        cy.contains('People Classification');
        cy.contains('Gender Demographics');
    });

});

