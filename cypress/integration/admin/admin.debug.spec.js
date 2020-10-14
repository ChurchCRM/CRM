/// <reference types="cypress" />

context('Actions', () => {
    it('View system debug', () => {
        cy.login("admin", "changeme");
        cy.visit("/v2/admin/debug");
        cy.contains('ChurchCRM Installation Information');
        cy.contains('Database');
    });
});

