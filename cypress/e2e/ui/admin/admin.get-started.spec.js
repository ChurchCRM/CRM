/// <reference types="cypress" />

describe("Admin Get Started", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should display the Get Started landing page", () => {
        cy.visit("admin/get-started");
        cy.contains("Get Your Data Into ChurchCRM");
        cy.contains("Explore with Demo Data");
        cy.contains("Import from a Spreadsheet");
        cy.contains("Enter Data Manually");
        cy.contains("Restore a Backup");
    });

    it("should show skip link back to Admin Dashboard", () => {
        cy.visit("admin/get-started");
        cy.contains("a", "Skip — go to Admin Dashboard").should("have.attr", "href").and("include", "admin/");
    });

    it("Enter Data Manually card links to the manual data entry guide", () => {
        cy.visit("admin/get-started");
        cy.contains("a.gs-card", "Enter Data Manually").should("have.attr", "href").and("include", "admin/get-started/manual");
    });

    it("Import from a Spreadsheet card links to /admin/import/csv", () => {
        cy.visit("admin/get-started");
        cy.contains("a.gs-card", "Import from a Spreadsheet").should("have.attr", "href").and("include", "/admin/import/csv");
    });

    it("should display the manual data entry guide page", () => {
        cy.visit("admin/get-started/manual");
        cy.contains("Start Fresh");
        cy.contains("Recommended Order");
        cy.contains("Add Your First Family");
        cy.contains("Add People to the Family");
        cy.contains("Quick Tips");
    });

    it("should show the Add First Family button linking to FamilyEditor", () => {
        cy.visit("admin/get-started/manual");
        cy.contains("a", "Add First Family").first().should("have.attr", "href").and("include", "FamilyEditor.php");
    });

    it("should show the Add a Person button linking to PersonEditor", () => {
        cy.visit("admin/get-started/manual");
        cy.contains("a", "Add a Person").first().should("have.attr", "href").and("include", "PersonEditor.php");
    });

    it("should show Back to Get Started link on manual page", () => {
        cy.visit("admin/get-started/manual");
        cy.contains("a", "Back to Get Started").should("have.attr", "href").and("include", "admin/get-started");
    });

    it("should display quick tips on the manual page", () => {
        cy.visit("admin/get-started/manual");
        cy.contains("Families share an address and phone number.");
        cy.contains("Each person can have their own email and mobile number.");
        cy.contains("You can always import more data later via CSV.");
    });
});
