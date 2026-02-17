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
        cy.contains("Enabled Features");
        cy.contains("Map Settings");
        cy.contains("Report Settings");
        cy.contains("Financial Settings");
        cy.contains("Quick Search");
        cy.contains("Localization");
        cy.contains("Church Services");
        cy.contains("Two-Factor Authentication");
        cy.contains("System Settings");
    });
});
