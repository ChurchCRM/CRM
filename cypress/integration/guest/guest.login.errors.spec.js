/// <reference types="cypress" />

context('Login', () => {

    it('Bad password', () => {
        cy.login("admin", "badpassword", false);
        cy.location('pathname').should('include', "session/begin");
    });

    it('Bad username', () => {
        cy.login("idonknowyou", "badpassword", false);
        cy.location('pathname').should('include', "session/begin");
    });

});

