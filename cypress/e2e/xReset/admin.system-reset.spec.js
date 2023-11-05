/// <reference types="cypress" />

context("Admin System Reset", () => {
    it("Reset Members", () => {
        cy.loginAdmin("v2/admin/database/reset");
        cy.contains(
            "Please type I AGREE to access the database reset functions page.",
        );
        // cy.get('.bootbox-input').type('I AGREE');
        // cy.get('.btn-success').click();
        // cy.contains('Please type I AGREE to access the database reset functions page.').should('not.be.visible');
        // cy.get('#confirm-people').click();
        // cy.contains('This will remove all the member data, people, and families and can\'t be undone.').should("be.visible");
        // cy.get('.bootbox-accept').should('be.visible').click();
        // cy.url().should('contains', 'v2/dashboard');
    });

    // it('Reset Full System', () => {
    //     cy.loginAdmin("v2/admin/database/reset");
    //     cy.contains('Please type I AGREE to access the database reset functions page.');
    //     cy.get('.bootbox-input').type('I AGREE');
    //     cy.get('.btn-success').click();
    //     cy.contains('Please type I AGREE to access the database reset functions page.').should('not.be.visible');
    //     cy.get('#confirm-db').click();
    //     cy.contains('This will reset the system data and will restart the system as a new install.').should("be.visible");
    //     cy.get('.bootbox-accept').should('be.visible').click();
    //     cy.url().should('contains', 'session/begin');

    // });
});
