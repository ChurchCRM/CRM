/// <reference types="cypress" />

context('Family', () => {
    beforeEach(() => {
        cy.loginAdmin();
    })

    it('View a Family', () => {
        cy.visit("v2/family/1");
        cy.contains('Campbell - Family');
        cy.contains('Darren Campbell');
        cy.contains('Music Ministry');

        cy.visit("v2/family/20");
        cy.contains('Black - Family');
        cy.contains('New Building Fund');
    });


});

