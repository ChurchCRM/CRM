/// <reference types="cypress" />

/**
 * Finance Reports Index Page Tests
 *
 * Tests for the new /finance/reports page with organized report categories.
 * This page provides links to the legacy FinancialReports.php report generator
 * with better organization and descriptions.
 */

describe("Finance Reports Index", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should load the reports index page", () => {
        cy.visit("/finance/reports");
        cy.contains("Financial Reports");
        cy.contains("Generate reports for tax statements");
    });

    it("should display Tax & Giving Reports section", () => {
        cy.visit("/finance/reports");

        cy.contains("Tax & Giving Reports");
        cy.contains("Giving Report (Tax Statements)");
        cy.contains("Zero Givers");
    });

    it("should display Pledge Reports section", () => {
        cy.visit("/finance/reports");

        cy.contains("Pledge Reports");
        cy.contains("Pledge Summary");
        cy.contains("Pledge Family Summary");
        cy.contains("Pledge Reminders");
    });

    it("should display Deposit Reports section", () => {
        cy.visit("/finance/reports");

        cy.contains("Deposit Reports");
        cy.contains("Individual Deposit Report");
        cy.contains("Advanced Deposit Report");
    });

    it("should display Membership Reports section", () => {
        cy.visit("/finance/reports");

        cy.contains("Membership Reports");
        cy.contains("Voting Members");
    });

    it("should display Report Tips section", () => {
        cy.visit("/finance/reports");

        cy.contains("Report Tips");
        cy.contains("Fiscal Year");
        cy.contains("Export Options");
        cy.contains("Filtering");
    });

    it("should navigate to Giving Report from link", () => {
        cy.visit("/finance/reports");

        cy.contains("Giving Report (Tax Statements)").click();
        cy.url().should("contain", "FinancialReports.php");
        cy.url().should("contain", "Giving");
        cy.contains("Financial Reports");
    });

    it("should navigate to Zero Givers from link", () => {
        cy.visit("/finance/reports");

        cy.contains("Zero Givers").click();
        cy.url().should("contain", "FinancialReports.php");
        cy.url().should("contain", "Zero");
    });

    it("should navigate to Pledge Summary from link", () => {
        cy.visit("/finance/reports");

        cy.contains("h6", "Pledge Summary").click();
        cy.url().should("contain", "FinancialReports.php");
        cy.url().should("contain", "Pledge%20Summary");
    });

    it("should navigate to Advanced Deposit Report from link", () => {
        cy.visit("/finance/reports");

        cy.contains("Advanced Deposit Report").click();
        cy.url().should("contain", "FinancialReports.php");
        cy.url().should("contain", "Advanced");
    });

    it("should navigate to Voting Members from link", () => {
        cy.visit("/finance/reports");

        cy.contains("Voting Members").click();
        cy.url().should("contain", "FinancialReports.php");
        cy.url().should("contain", "Voting");
    });
});

describe("Finance Reports Index - Standard User Access", () => {
    beforeEach(() => {
        cy.setupStandardSession();
    });

    it("should allow standard users with finance permission to access reports", () => {
        // The standard test user (tony.wade) has finance permissions enabled in demo database
        cy.visit("/finance/reports");
        
        // Should be able to see the reports page
        cy.contains("Tax & Giving Reports").should("be.visible");
        cy.contains("Pledge Reports").should("be.visible");
    });
});

describe("Finance Reports Index - No Finance Permission", () => {
    beforeEach(() => {
        cy.setupNoFinanceSession();
    });

    it("should deny access to users without finance permission", () => {
        // User judith.matthews has no finance permission
        cy.visit("/finance/reports", { failOnStatusCode: false });

        // Should be redirected to access-denied page
        cy.url().should("include", "/v2/access-denied");
        cy.url().should("include", "role=Finance");
    });
});
