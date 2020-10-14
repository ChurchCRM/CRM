/// <reference types="cypress" />

context('Admin Debug', () => {
    it('View system debug', () => {
        cy.loginAdmin();
        cy.visit("/v2/admin/debug");
        cy.contains('ChurchCRM Installation Information');
        cy.contains('Database');
    });

    /*it('can not View system debug', () => {
        cy.loginStandard();
        cy.request("/v2/admin/debug")
            .should((response) => {
                expect(response.status).to.eq(403);
            })
    });*/
});

