/// <reference types="cypress" />

context('Finance Family', () => {

    it('View a Family', () => {
        cy.intercept({ method: "GET", url: "/api/payments/family/1/**"}).as("getFamilyPayments1");
        cy.intercept({ method: "GET", url: "/api/payments/family/20/**"}).as("getFamilyPayments20");

        cy.loginAdmin("v2/family/1");
        cy.contains('Campbell - Family');
        cy.contains('Darren Campbell');

        cy.wait("@getFamilyPayments1");
        cy.contains('Music Ministry');

        cy.visit("v2/family/20");
        cy.contains('Black - Family');
        cy.wait("@getFamilyPayments20");
        cy.contains('New Building Fund');
    });


});

