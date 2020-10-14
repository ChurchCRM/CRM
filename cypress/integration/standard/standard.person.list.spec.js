/// <reference types="cypress" />

context('Actions', () => {
    beforeEach(() => {
        cy.loginStandard();
    })

    it('Listing all persons', () => {
        cy.visit("/v2/people");
        cy.contains('Admin');
        cy.contains('Church');
        cy.contains('Joel');
        cy.contains('Emma');
    });

    it('Listing all persons with gender filter', () => {
        cy.visit("/v2/people?Gender=0");
        cy.contains('Admin');
        cy.contains('Church');
        cy.contains('Kennedy');
        cy.contains('Judith');
        cy.contains('Emma').should('not.exist');
    });

});

