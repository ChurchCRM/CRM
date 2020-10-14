/// <reference types="cypress" />

context('Admin Debug', () => {
    it('View system debug', () => {
        cy.loginAdmin();
        cy.visit("v2/admin/debug");
        cy.contains('ChurchCRM Installation Information');
        cy.contains('Database');
    });

    /* TODO 403 testing

    it('can not View system debug', () => {
        cy.loginStandard();
        cy.visit("/v2/admin/debug")
            .should((response) => {
                expect(response.status).to.eq(403);
            })
    });*/
});

