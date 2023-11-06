/// <reference types="cypress" />

context("Admin Debug", () => {
    it("View system debug", () => {
        cy.loginAdmin("v2/admin/debug");
        cy.contains("ChurchCRM Installation Information");
        cy.contains("Database");
    });

    it("View email debug", () => {
        cy.loginAdmin("v2/email/debug");
        cy.contains("Debug Email Connection");
    });

    it("View system settings", () => {
        cy.loginAdmin("SystemSettings.php");
    });
});
