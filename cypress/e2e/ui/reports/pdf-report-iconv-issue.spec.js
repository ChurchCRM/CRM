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

    it("Church Directory PDF generation does not return a server error", () => {
        cy.intercept("POST", "**/DirectoryReport.php").as("dirReport");
        cy.visit("DirectoryReports.php");
        cy.contains("Directory reports");

        // Submit the form with default options to trigger PDF generation
        cy.get("form[action='Reports/DirectoryReport.php']").submit();

        cy.wait("@dirReport", { timeout: 30000 }).then((interception) => {
            // Must not be a 500 server error
            expect(interception.response.statusCode).to.not.equal(500);

            const body = interception.response.body || "";
            // Must not contain the iconv-related fatal error from the issue
            expect(body).to.not.include("Call to undefined function");
            expect(body).to.not.include("iconv()");
            expect(body).to.not.include("Fatal error");
        });
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

        cy.intercept("POST", "**/TaxReport.php").as("taxReport");
        cy.get("#createReport").click();

        cy.wait("@taxReport", { timeout: 30000 }).then((interception) => {
            expect(interception.response.statusCode).to.not.equal(500);

            const body = interception.response.body || "";
            expect(body).to.not.include("Call to undefined function");
            expect(body).to.not.include("iconv()");
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
