/// <reference types="cypress" />

describe("Admin Debug", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("View system debug", () => {
        cy.visit("admin/system/debug");
        cy.contains("ChurchCRM Installation");
        cy.contains("Database");
    });

    it("View email debug", () => {
        cy.visit("admin/system/debug/email");
        cy.contains("Debug Email Connection");
    });

    it("View system settings", () => {
        cy.visit("SystemSettings.php");
    });
});
