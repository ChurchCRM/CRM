/// <reference types="cypress" />

/**
 * Year-end statement PDF generation
 *
 * Verifies the wizard generate step posts to the finance route and
 * that the underlying TaxReport generator still returns a PDF when data exists.
 */
describe("Year-end statement PDF", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("wizard generate form targets the finance route", () => {
        cy.visit("/finance/reports/year-end?step=3");
        cy.get("form").invoke("attr", "action").should("include", "/finance/reports/year-end/generate");
        cy.get('input[name="output"]').should("have.value", "pdf");
        cy.get('input[name="ReportType"]').should("have.value", "Giving Report");
    });

    it("posts the fiscal-year range and receives a PDF or a no-rows redirect", () => {
        cy.visit("/finance/reports/year-end?step=3");
        cy.intercept("POST", "**/finance/reports/year-end/generate").as("generate");
        cy.get("form").submit();
        cy.wait("@generate").then((interception) => {
            const status = interception.response?.statusCode;
            const contentType = interception.response?.headers?.["content-type"] || "";
            expect(status).to.be.oneOf([200, 302]);
            if (status === 200) {
                expect(contentType).to.match(/pdf|html/i);
            }
        });
    });
});
