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

    it("Email Setup section contains SMTP Port field", () => {
        cy.visit("SystemSettings.php");
        
        // Click on Email Setup tab
        cy.get('#EmailSetup-tab').click();
        
        // Verify SMTP Port field is visible
        cy.contains("sSMTPHost").should("exist");
        cy.contains("iSMTPPort").should("exist");
        cy.contains("bSMTPAuth").should("exist");
        cy.contains("sSMTPUser").should("exist");
    });
});
