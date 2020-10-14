/// <reference types="cypress" />

context('People', () => {
    beforeEach(() => {
        cy.loginStandard();
    })

    it('Open the People Dashboard', () => {
        cy.visit("PeopleDashboard.php");
        cy.contains('People Dashboard');
        cy.contains('People Functions');
        cy.contains('Reports');
        cy.contains('Family Roles');
        cy.contains('People Classification');
        cy.contains('Gender Demographics');
    });

    it('Listing all persons', () => {
        cy.visit("v2/people");
        cy.contains('Admin');
        cy.contains('Church');
        cy.contains('Joel');
        cy.contains('Emma');
    });

    it('Listing all persons with gender filter', () => {
        cy.visit("v2/people?Gender=0");
        cy.contains('Admin');
        cy.contains('Church');
        cy.contains('Kennedy');
        cy.contains('Judith');
        cy.contains('Emma').should('not.exist');
    });

    it('Person Not Found', () => {
        cy.visit("PersonView.php?PersonID=9999");
        cy.contains('Oops! PERSON 9999 Not Found');
    });

});

