/// <reference types="cypress" />

context('Admin Reports', () => {
    beforeEach(() => {
        cy.loginAdmin();
    });

    it('Gen DirectoryReports', () => {

        cy.visit('DirectoryReports.php');
        cy.contains("Directory reports");
        cy.contains("Select classifications to include")
//  TODO       cy.get('.btn-default:nth-child(2)').click();

    });

});
