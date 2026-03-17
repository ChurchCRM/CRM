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

            const contentType = interception.response.headers["content-type"] || "";

            // If a valid PDF was returned, there can be no PHP fatal error strings.
            // Only inspect body text when the response is NOT a binary PDF (e.g., an HTML error page).
            if (!contentType.includes("application/pdf")) {
                // cy.intercept in Cypress 15 does not buffer binary response bodies, so the body
                // may arrive as an ArrayBuffer from a different JS realm. Use
                // Object.prototype.toString for cross-context-safe type detection instead of
                // instanceof, which fails across iframe boundaries.
                const rawBody = interception.response.body;
                const isArrayBuffer =
                    Object.prototype.toString.call(rawBody) === "[object ArrayBuffer]";
                const body = isArrayBuffer
                    ? new TextDecoder().decode(rawBody)
                    : typeof rawBody === "string"
                      ? rawBody
                      : "";
                expect(body).to.not.include("Call to undefined function");
                expect(body).to.not.include("Fatal error");
            }
        });
    });

    it("Name Tags / Labels page loads without fatal error", () => {
        cy.visit("LettersAndLabels.php");
        cy.contains("Letters and Mailing Labels");
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("body").should("not.contain", "Call to undefined function");
    });
});
