/// <reference types="cypress" />

describe("Admin Settings", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("View system settings", () => {
        cy.visit("SystemSettings.php");
        cy.contains("System Settings");
        
        cy.contains("People");
        cy.contains("Report Settings");
        cy.contains("Financial Settings");
        cy.contains("Quick Search");
        cy.contains("Localization");
        cy.contains("Confession");
    });
});
