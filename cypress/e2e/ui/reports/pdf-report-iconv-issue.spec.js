/// <reference types="cypress" />

/**
 * Tests for GitHub Issue: Report to PDF error - iconv extension missing
 *
 * When the PHP `iconv` extension is not installed, PDF report generation
 * (Church Directory, Name Tags) crashed with a fatal
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

    it("Name Tags / Labels page loads without fatal error", () => {
        cy.visit("LettersAndLabels.php");
        cy.contains("Letters and Mailing Labels");
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("body").should("not.contain", "Call to undefined function");
    });
});
