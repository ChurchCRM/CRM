/// <reference types="cypress" />

context('Standard Family', () => {

    it('Delete Person', () => {
        cy.loginStandard('PersonView.php?PersonID=69');
        cy.get('.bg-maroon').click();
        cy.get('.bootbox-accept').click();
        cy.url().should('contains', 'v2/dashboard');
        cy.visit('PersonView.php?PersonID=69');
        cy.contains('Not Found: Person');

    });

    it('Delete Family', () => {
        cy.loginStandard("v2/family/7");
        cy.get('.bg-maroon > .fa').click();
        cy.url().should('contains', 'SelectDelete.php');
        cy.get('.btn:nth-child(2)').click();
        cy.url().should('contains', 'v2/family');
        cy.visit('v2/family/7');
        cy.contains('Not Found: Family');

    });
});
