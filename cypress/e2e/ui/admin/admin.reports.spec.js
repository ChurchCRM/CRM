/// <reference types="cypress" />

describe("Admin Reports", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Gen DirectoryReports", () => {
        cy.visit("DirectoryReports.php");
        cy.contains("Directory reports");
        cy.contains("Select classifications to include");
    });
});
