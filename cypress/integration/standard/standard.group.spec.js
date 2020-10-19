/// <reference types="cypress" />

context('Groups', () => {
    beforeEach(() => {
        cy.loginStandard();
    });

    it('Group Report', () => {

        cy.visit('GroupReports.php');
        cy.contains("Group reports");
        cy.contains("Select the group you would like to report")
        cy.get('.box-body > form').submit();
        cy.url().should('contains', 'GroupReports.php');
        cy.contains("Select which information you want to include");
        // TODO cy.get('.box-body > form').submit();


    });

});
