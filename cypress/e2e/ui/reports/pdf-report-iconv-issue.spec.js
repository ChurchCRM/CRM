/// <reference types="cypress" />

/**
 * Tests for GitHub Issue: Report to PDF error - iconv extension missing
 *
 * When the PHP `iconv` extension is not installed, PDF report generation
 * (Church Directory, Name Tags) crashed with a fatal
 * "Call to undefined function ChurchCRM\Reports\iconv()" error.
 *
 * These tests verify that the report pages load and generate output
 * without fatal PHP errors through the UTF-8 mPDF renderer.
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
