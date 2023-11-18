/// <reference types="cypress" />

context("Admin Reports", () => {
    it("Gen DirectoryReports", () => {
        cy.loginAdmin("DirectoryReports.php");
        cy.contains("Directory reports");
        cy.contains("Select classifications to include");
        //  TODO       cy.get('.btn-default:nth-child(2)').click();
    });
});
