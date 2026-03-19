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

        // Verify the 5 setup-step quick start cards are present
        cy.contains("Church Information");
        cy.contains("Add Your Data");
        cy.contains("Configure Email");
        cy.contains("Invite Your Team");
        cy.contains("Enable Plugins");
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

    it("should display Advanced Operations section", () => {
        cy.visit("admin/");

        cy.contains("Advanced Operations");
        cy.contains("Restore Database");
        cy.contains("Reset Database");
    });

    it("should navigate to Church Information from Quick Start", () => {
        cy.visit("admin/");

        cy.contains("a.quick-start-card", "Church Information").click();
        cy.url().should("include", "admin/system/church-info");
    });

    it("should navigate to Get Started (Add Your Data) from Quick Start", () => {
        cy.visit("admin/");

        cy.contains("a.quick-start-card", "Add Your Data").click();
        cy.url().should("include", "admin/get-started");
    });

    it("should navigate to User List from Quick Start", () => {
        cy.visit("admin/");

        cy.contains("a.quick-start-card", "Invite Your Team").click();
        cy.url().should("include", "admin/system/users");
    });

    it("should display Get Started page with data import options", () => {
        cy.visit("admin/get-started");

        cy.contains("Get Your Data Into ChurchCRM");
        cy.contains("Explore with Demo Data");
        cy.contains("Import from a Spreadsheet");
        cy.contains("Enter Data Manually");
        cy.contains("Restore a Backup");
    });

    it("should open import demo data overlay from Get Started page and cancel", () => {
        cy.visit("admin/get-started");

        // Click the demo data card
        cy.get("#importDemoDataV2").click();

        // Verify the overlay is displayed
        cy.get("#demoImportConfirmOverlay").should("be.visible");
        cy.contains("Import Demo Data");
        cy.contains("This will add sample families, people, and groups to your database.");

        // Verify options are present
        cy.contains("Optional Data to Include");
        cy.get("#includeDemoSundaySchool").should("be.checked");
        cy.get("#includeDemoFinancial").should("be.checked");
        cy.get("#includeDemoEvents").should("be.disabled");

        // Verify instructions section
        cy.contains("Need to remove this data later?");
        cy.contains("Admin Dashboard");

        // Click Cancel to close without importing
        cy.get("#demoImportCancelBtn").click();

        // Verify overlay is hidden
        cy.get("#demoImportConfirmOverlay").should("not.be.visible");
    });
});
