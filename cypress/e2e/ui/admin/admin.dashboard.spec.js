/// <reference types="cypress" />

describe("Admin Dashboard", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should display the admin dashboard", () => {
        cy.visit("admin/");
        cy.contains("Welcome to ChurchCRM");
        cy.contains("Quick Start");
    });

    it("should display all Quick Start cards", () => {
        cy.visit("admin/");
        
        // Verify all 6 quick start cards are present
        cy.contains("System Settings");
        cy.contains("Manage Users");
        cy.contains("Groups");
        cy.contains("Sunday School");
        cy.contains("Import Data");
        cy.contains("Donation Funds");
    });

    it("should display System Info card", () => {
        cy.visit("admin/");
        
        cy.contains("System Info");
        cy.contains("Version:");
        cy.contains("Database:");
        cy.contains("Backup");
        cy.contains("Upgrade");
        cy.contains("Documentation");
    });

    it("should display System Health card", () => {
        cy.visit("admin/");
        
        cy.contains("System Health");
        cy.contains("File Integrity:");
        cy.contains("Orphaned Files:");
        cy.contains("Debug Info");
    });

    it("should display Demo Data card", () => {
        cy.visit("admin/");
        
        cy.contains("Demo Data");
        cy.get("#importDemoDataV2").should("exist").and("contain", "Import Demo Data");
    });

    it("should display Advanced Operations section", () => {
        cy.visit("admin/");
        
        cy.contains("Advanced Operations");
        cy.contains("Restore Database");
        cy.contains("Reset Database");
    });

    it("should open import demo data overlay and cancel", () => {
        cy.visit("admin/");
        
        // Click the Import Demo Data button
        cy.get("#importDemoDataV2").click();
        
        // Verify the overlay is displayed
        cy.get("#demoImportConfirmOverlay").should("be.visible");
        cy.contains("Import Demo Data");
        cy.contains("This will add sample families, people, and groups to your database.");
        
        // Verify options are present
        cy.contains("Optional Data to Include");
        cy.get("#includeDemoSundaySchool").should("be.checked");
        cy.get("#includeDemoFinancial").should("be.disabled");
        cy.get("#includeDemoEvents").should("be.disabled");
        
        // Verify instructions section
        cy.contains("Need to remove this data later?");
        cy.contains("Admin Dashboard");
        
        // Click Cancel to close without importing
        cy.get("#demoImportCancelBtn").click();
        
        // Verify overlay is hidden
        cy.get("#demoImportConfirmOverlay").should("not.be.visible");
    });

    it("should navigate to System Settings from Quick Start", () => {
        cy.visit("admin/");
        
        cy.contains("a.quick-start-card", "System Settings").click();
        cy.url().should("include", "SystemSettings.php");
    });

    it("should navigate to User List from Quick Start", () => {
        cy.visit("admin/");
        
        cy.contains("a.quick-start-card", "Manage Users").click();
        cy.url().should("include", "admin/system/users");
    });

    it("should navigate to Groups from Quick Start", () => {
        cy.visit("admin/");
        
        cy.contains("a.quick-start-card", "Groups").click();
        cy.url().should("include", "GroupList.php");
    });
});
