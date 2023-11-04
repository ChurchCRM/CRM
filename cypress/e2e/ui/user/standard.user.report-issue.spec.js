/// <reference types="cypress" />

context('Report Issue', () => {

    it('Click Menus to Report issue', () => {
        cy.loginStandard('v2/dashboard');
        cy.get('.fa-headset').click();
        cy.get('#reportIssue').click();
        cy.get('#issueTitle').type('testing bug submit');
        cy.get('#issueDescription').type('My Background is blue');
        cy.get('#submitIssue').click();
        
    });

});