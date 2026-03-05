/// <reference types="cypress" />

/**
 * Tests for GitHub Issue: Report to PDF error - iconv extension missing
 *
 * When the PHP `iconv` extension is not installed, PDF report generation
 * (Church Directory, Tax Statements, Name Tags) crashed with a fatal
 * "Call to undefined function ChurchCRM\Reports\iconv()" error.
 *
 * The fix adds a convertToLatin1() helper in ChurchInfoReport that falls
 * back to mb_convert_encoding() when iconv is unavailable.
 *
 * These tests verify that the affected report pages load and generate
 * output without fatal PHP errors.
 */
describe("PDF Reports - iconv fallback fix", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Church Directory report page loads without fatal error", () => {
        cy.visit("DirectoryReports.php");
        cy.contains("Directory reports");
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("body").should("not.contain", "Call to undefined function");
    });

    it("Tax Statement (Giving Report) PDF generation does not return a server error", () => {
        cy.visit("FinancialReports.php");
        cy.contains("Financial Reports");

        cy.get("#FinancialReportTypes").select("Giving Report");
        cy.get("#FinancialReports").submit();
        cy.contains("Financial Reports: Giving Report");

        // Set a broad date range to include demo data
        cy.get("#DateStart").clear().type("2005-01-01");
        cy.get("#DateEnd").clear().type("2025-12-31");

        cy.get('input[name="output"][value="pdf"]').check();

        // Use the same intercept pattern as tax-report-pdf.spec.js which is known to work
        cy.intercept("POST", "**/Reports/TaxReport.php").as("taxReport");
        cy.get("#createReport").click();

        cy.wait("@taxReport").then((interception) => {
            expect(interception.response.statusCode).to.not.equal(500);

            const body = interception.response.body || "";
            expect(body).to.not.include("Call to undefined function");
            expect(body).to.not.include("Fatal error");
        });
    });

    it("Name Tags / Labels page loads without fatal error", () => {
        cy.visit("LettersAndLabels.php");
        cy.contains("Letters and Mailing Labels");
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("body").should("not.contain", "Call to undefined function");
    });
});
