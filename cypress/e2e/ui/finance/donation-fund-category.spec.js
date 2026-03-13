/// <reference types="cypress" />

/**
 * Donation Fund Category Tests
 *
 * Tests for the fun_Category field on donationfund_fun table.
 * Covers:
 * - Creating and editing funds with a category in DonationFundEditor.php
 * - Grouping of funds by category in the Finance Dashboard
 * - Category-based optgroup grouping in the Financial Reports fund filter
 */

describe("Donation Fund Category - Editor", () => {
    const categoryName = "Test Category " + Date.now();
    const fundName = "Test Fund Cat " + Date.now();

    before(() => {
        cy.setupAdminSession();

        // Create the categorised fund once for the whole suite
        cy.visit("/DonationFundEditor.php");
        cy.get("#newFieldName").type(fundName);
        cy.get("#newFieldCategory").type(categoryName);
        cy.get("#newFieldDesc").type("Category test fund");
        cy.get("[name='AddField']").click();
        cy.contains("td", categoryName).should("exist");
    });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should display the Category column in the existing funds table", () => {
        cy.visit("/DonationFundEditor.php");
        cy.contains("th", "Category").should("be.visible");
    });

    it("should display Category field in the Add New Fund form", () => {
        cy.visit("/DonationFundEditor.php");
        cy.get("#newFieldCategory").should("be.visible");
    });

    it("should persist the fund category after creation", () => {
        cy.visit("/DonationFundEditor.php");
        cy.contains("td", categoryName).should("exist");
    });

    it("should allow editing the category of an existing fund", () => {
        const updatedCategory = "Updated Category " + Date.now();

        cy.visit("/DonationFundEditor.php");

        // Find the row for our fund and update its category
        cy.contains("td", categoryName)
            .siblings("td")
            .find("input[name$='category']")
            .clear()
            .type(updatedCategory);

        cy.get("[name='SaveChanges']").click();

        // After save the updated category should appear in the table
        cy.contains("td", updatedCategory).should("exist");
    });
});

describe("Donation Fund Category - Finance Dashboard", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should display the Donation Funds section on the dashboard", () => {
        cy.visit("/finance/");
        cy.contains("Donation Funds").should("be.visible");
    });

    it("should render category headers for funds that have a category assigned", () => {
        cy.visit("/finance/");

        // Category headers are rendered as <small> elements with class
        // text-muted font-weight-bold text-uppercase inside the fund card
        cy.get(".finance-card")
            .contains("Donation Funds")
            .parents(".card")
            .find("small.text-muted")
            .should("have.length.at.least", 1);
    });
});

describe("Donation Fund Category - Financial Reports Fund Filter", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should show fund filter optgroups on Giving Report", () => {
        cy.visit("/FinancialReports.php");
        cy.get("#FinancialReportTypes").select("Giving Report");
        cy.get("#FinancialReports").submit();

        cy.get("#fundsList").should("be.visible");
        cy.get("#fundsList optgroup").should("have.length.at.least", 1);
    });

    it("should show fund filter optgroups on Pledge Summary", () => {
        cy.visit("/FinancialReports.php");
        cy.get("#FinancialReportTypes").select("Pledge Summary");
        cy.get("#FinancialReports").submit();

        cy.get("#fundsList").should("be.visible");
        cy.get("#fundsList optgroup").should("have.length.at.least", 1);
    });

    it("should show fund filter optgroups on Advanced Deposit Report", () => {
        cy.visit("/FinancialReports.php");
        cy.get("#FinancialReportTypes").select("Advanced Deposit Report");
        cy.get("#FinancialReports").submit();

        cy.get("#fundsList").should("be.visible");
        cy.get("#fundsList optgroup").should("have.length.at.least", 1);
    });

    it("should list funds in the Uncategorized group when no category is set", () => {
        cy.visit("/FinancialReports.php");
        cy.get("#FinancialReportTypes").select("Giving Report");
        cy.get("#FinancialReports").submit();

        // The default demo database has at least the 'Pledges' fund with no category
        cy.get("#fundsList optgroup[label='Uncategorized']").should("exist");
        cy.get("#fundsList optgroup[label='Uncategorized'] option").should("have.length.at.least", 1);
    });
});
