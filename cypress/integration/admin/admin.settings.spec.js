/// <reference types="cypress" />

context('Actions', () => {
    beforeEach(() => {
        cy.login("admin", "changeme");
    })

    it('Visit Settings', () => {
        cy.visit("/SystemSettings.php");
        cy.location('pathname').should('eq', "/SystemSettings.php");
    });

});

