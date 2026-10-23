/// <reference types="cypress" />

/**
 * Year-end statements wizard
 *
 * Covers the treasurer flow: close the books → review → generate → deliver.
 */
describe("Year-end statements wizard", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("loads the wizard from the reports index", () => {
        cy.visit("/finance/reports");
        cy.contains("Send year-end giving statements").click();
        cy.url().should("include", "/finance/reports/year-end");
        cy.contains("Close the books");
        cy.contains("Fiscal year");
    });

    it("walks through every step", () => {
        cy.visit("/finance/reports/year-end");
        cy.contains("Close the books");
        cy.contains("Books are closed").click();

        cy.url().should("include", "step=2");
        cy.contains("Review");
        cy.contains("Ready to generate").click();

        cy.url().should("include", "step=3");
        cy.contains("Generate statements");
        cy.contains("Continue to delivery").click();

        cy.url().should("include", "step=4");
        cy.contains("Deliver");
        cy.contains("Done");
    });

    it("posts generate to the finance route with fiscal-year dates", () => {
        cy.visit("/finance/reports/year-end?step=3");
        cy.get('form').should("have.attr", "action").and("include", "/finance/reports/year-end/generate");
        cy.get('input[name="DateStart"]').should("have.attr", "value").and("match", /^\d{4}-\d{2}-\d{2}$/);
        cy.get('input[name="DateEnd"]').should("have.attr", "value").and("match", /^\d{4}-\d{2}-\d{2}$/);
        cy.get('input[name="ReportType"]').should("have.value", "Giving Report");
    });
});
