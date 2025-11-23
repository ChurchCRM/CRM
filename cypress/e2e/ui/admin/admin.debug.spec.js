/// <reference types="cypress" />

describe("Admin Debug", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("View system debug", () => {
        cy.visit("admin/system/debug");
        cy.contains("ChurchCRM Installation Information");
        cy.contains("Database");
    });

    it("View email debug", () => {
        cy.visit("v2/email/debug");
        cy.contains("Debug Email Connection");
    });

    it("View system settings", () => {
        cy.visit("SystemSettings.php");
    });
});
