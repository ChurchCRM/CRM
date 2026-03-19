/// <reference types="cypress" />

describe("Admin Get Started", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should display the Get Started landing page", () => {
        cy.visit("admin/get-started");
        cy.contains("Get Started");
        cy.contains("Start Fresh");
        cy.contains("Import from CSV");
        cy.contains("Use Demo Data");
    });

    it("should show Back to Admin Dashboard link on Get Started page", () => {
        cy.visit("admin/get-started");
        cy.contains("Back to Admin Dashboard").should("have.attr", "href").and("include", "admin/");
    });

    it("Start Fresh card links to the manual data entry guide", () => {
        cy.visit("admin/get-started");
        cy.contains("a", "Start Fresh").first().click();
        cy.url().should("include", "admin/get-started/manual");
    });

    it("Import CSV card links to CSVImport.php", () => {
        cy.visit("admin/get-started");
        cy.contains("a", "Import CSV").click();
        cy.url().should("include", "CSVImport.php");
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

    it("admin dashboard should show Start Fresh quick-start card", () => {
        cy.visit("admin/");
        cy.contains("a.quick-start-card", "Start Fresh").should("have.attr", "href").and("include", "admin/get-started/manual");
    });

    it("admin dashboard sidebar should show Get Started card", () => {
        cy.visit("admin/");
        cy.contains("Get Started");
        cy.contains("a", "Get Started").should("have.attr", "href").and("include", "admin/get-started");
    });
});
