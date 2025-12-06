/// <reference types="cypress" />

/**
 * Finance Dashboard Tests
 *
 * Tests for the new /finance/ module with Slim 4 MVC structure.
 * The finance dashboard provides:
 * - YTD payment and pledge metrics
 * - Tax year reporting checklist
 * - Quick actions for deposits and reports
 * - Recent deposits list
 * - Donation funds overview
 */

describe("Finance Dashboard", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should load the finance dashboard", () => {
        cy.visit("/finance/");
        cy.contains("Finance Dashboard");
        cy.contains("Fiscal Year");
    });

    it("should display YTD metrics cards", () => {
        cy.visit("/finance/");

        // Check for the 4 metric cards
        cy.contains("YTD Payments");
        cy.contains("YTD Pledges");
        cy.contains("Donor Families");
        cy.contains("Total Payments");
    });

    it("should display Quick Actions section", () => {
        cy.visit("/finance/");

        cy.contains("Quick Actions");
        cy.contains("Create Deposit");
        cy.contains("Add Payment");
        cy.contains("Generate Reports");
    });

    it("should navigate to deposits page from Create Deposit button", () => {
        cy.visit("/finance/");

        // Find and click the Create Deposit button
        cy.contains("a", "Create Deposit").click();
        cy.url().should("contain", "FindDepositSlip.php");
        cy.contains("Deposit Listing");
    });

    it("should navigate to reports page from Generate Reports button", () => {
        cy.visit("/finance/");

        // Find and click the Generate Reports button
        cy.contains("a", "Generate Reports").click();
        cy.url().should("contain", "/finance/reports");
        cy.contains("Financial Reports");
    });

    it("should display Tax Year Reporting Checklist", () => {
        cy.visit("/finance/");

        cy.contains("Tax Year Reporting Checklist");
        cy.contains("Close All Deposits");
        cy.contains("Review Donation Funds");
        cy.contains("Church Information");
        cy.contains("Tax Report Verbiage");
        cy.contains("Generate Tax Statements");
    });

    it("should display Recent Deposits section", () => {
        cy.visit("/finance/");

        cy.contains("Recent Deposits");
        cy.contains("View All");

        // Check table headers if deposits exist
        cy.get("body").then(($body) => {
            if ($body.find("table.table-hover").length > 0) {
                cy.contains("th", "ID");
                cy.contains("th", "Date");
                cy.contains("th", "Type");
                cy.contains("th", "Status");
            }
        });
    });

    it("should display Deposit Statistics sidebar", () => {
        cy.visit("/finance/");

        cy.contains("Deposit Statistics");
        cy.contains("Total Deposits:");
        cy.contains("Open Deposits:");
        cy.contains("Closed Deposits:");
    });

    it("should display Donation Funds sidebar", () => {
        cy.visit("/finance/");

        cy.contains("Donation Funds");
    });

    it("should link to Donation Fund Editor from Manage Funds button", () => {
        cy.visit("/finance/");

        // Admin should see Manage Funds link
        cy.contains("a", "Manage Funds").click();
        cy.url().should("contain", "DonationFundEditor.php");
    });

    it("should navigate to settings from Church Information checklist item", () => {
        cy.visit("/finance/");

        // Find the Settings button in the Church Information row
        cy.contains("Church Information")
            .parents(".list-group-item")
            .find("a")
            .contains("Settings")
            .click();

        cy.url().should("contain", "SystemSettings.php");
    });

    it("should link deposits checklist to FindDepositSlip", () => {
        cy.visit("/finance/");

        // Find the View button in the Close All Deposits row
        cy.contains("Close All Deposits")
            .parents(".list-group-item")
            .find("a")
            .contains("View")
            .click();

        cy.url().should("contain", "FindDepositSlip.php");
    });
});

describe("Finance Dashboard - Standard User Access", () => {
    beforeEach(() => {
        cy.setupStandardSession();
    });

    it("should allow standard users with finance permission to access the dashboard", () => {
        // The standard test user (tony.wade) has finance permissions enabled in demo database
        cy.visit("/finance/");
        
        // Should be able to see the dashboard
        cy.get("h1").should("contain", "Finance Dashboard");
        
        // Metrics should be visible
        cy.get(".finance-metric-card").should("have.length.at.least", 3);
    });
});

describe("Finance Dashboard - No Finance Permission", () => {
    beforeEach(() => {
        cy.setupNoFinanceSession();
    });

    it("should deny access to users without finance permission", () => {
        // User judith.matthews has no finance permission
        cy.visit("/finance/", { failOnStatusCode: false });
        
        // Should be redirected to access-denied page
        cy.url().should("include", "/v2/access-denied");
        cy.url().should("include", "role=Finance");
    });

    it("should deny access to finance reports for users without finance permission", () => {
        cy.visit("/finance/reports", { failOnStatusCode: false });
        
        // Should be redirected to access-denied page
        cy.url().should("include", "/v2/access-denied");
        cy.url().should("include", "role=Finance");
    });
});
