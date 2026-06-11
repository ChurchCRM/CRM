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

// Shared test data — defined at file scope so all describe blocks can reuse them
const categoryName = "Test Category " + Date.now();
const fundName = "Test Fund Cat " + Date.now();

/** Helper: create the test fund with a category via the DonationFundEditor UI */
function createCategorizedFund() {
    cy.setupAdminSession();
    cy.visit("/DonationFundEditor.php");
    cy.get("#newFieldName").type(fundName);
    cy.get("#newFieldCategory").type(categoryName);
    cy.get("#newFieldDesc").type("Category test fund");
    cy.get("[name='AddField']").click();

    // The Category column renders as an <input> inside <td>, not as text content
    cy.get("input[name$='name'][value='" + fundName + "']")
        .closest("tr")
        .find("input[name$='category']")
        .should("have.value", categoryName);
}

describe("Donation Fund Category - Editor", () => {
    before(() => {
        createCategorizedFund();
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

        // The Category column renders as an <input> — find the row by fund name input
        // and assert the category input value
        cy.get("input[name$='name'][value='" + fundName + "']")
            .closest("tr")
            .find("input[name$='category']")
            .should("have.value", categoryName);
    });

    it("should allow editing the category of an existing fund", () => {
        const updatedCategory = "Updated Category " + Date.now();

        cy.visit("/DonationFundEditor.php");

        // Find the row for our fund via the name input and update its category input
        cy.get("input[name$='name'][value='" + fundName + "']")
            .closest("tr")
            .find("input[name$='category']")
            .clear()
            .type(updatedCategory);

        cy.get("[name='SaveChanges']").click();

        // After save the updated category value should appear in the category input
        cy.get("input[name$='name'][value='" + fundName + "']")
            .closest("tr")
            .find("input[name$='category']")
            .should("have.value", updatedCategory);
    });
});

describe("Donation Fund Category - Finance Dashboard", () => {
    // Use separate fund/category names so this describe block is fully
    // independent of the Editor describe block above (which modifies the
    // shared fund's category in its edit test, breaking the createCategorizedFund
    // assertion if we try to reuse the same constants).
    const dashCategoryName = "Dashboard Category " + Date.now();
    const dashFundName = "Dashboard Fund " + Date.now();

    before(() => {
        // Ensure a categorized fund exists regardless of spec execution order
        cy.setupAdminSession();
        cy.visit("DonationFundEditor.php");
        cy.get("#newFieldName").type(dashFundName);
        cy.get("#newFieldCategory").type(dashCategoryName);
        cy.get("#newFieldDesc").type("Dashboard category test fund");
        cy.get("[name='AddField']").click();
        cy.get("input[name$='name'][value='" + dashFundName + "']")
            .closest("tr")
            .find("input[name$='category']")
            .should("have.value", dashCategoryName);
    });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should display the Donation Funds section on the dashboard", () => {
        cy.visit("/finance/");
        cy.contains("Donation Funds").should("be.visible");
    });

    it("should render category headers for funds that have a category assigned", () => {
        cy.visit("/finance/");

        // Category headers are rendered as <small class="text-muted fw-bold text-uppercase">
        // inside the Donation Funds card
        cy.contains(".card-title", "Donation Funds")
            .closest(".card")
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
