/// <reference types="cypress" />

describe("Admin Settings", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("View system settings", () => {
        cy.visit("SystemSettings.php");
        cy.contains("Church Information");
        cy.contains("Email Setup");
        cy.contains("People Setup");
        cy.contains("System Settings");
        cy.contains("Map Settings");
        cy.contains("Report Settings");
        cy.contains("Localization");
        cy.contains("Financial Settings");
        cy.contains("Integration");
        cy.contains("Backup");
    });
});
