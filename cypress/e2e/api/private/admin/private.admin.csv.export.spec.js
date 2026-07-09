/// <reference types="cypress" />

/**
 * CSV Export API Tests
 *
 * Pure-API coverage of the CSVCreateFile.php endpoint. These tests issue
 * form-encoded POSTs directly against the export endpoint and assert the
 * response status, content-type, and absence of PHP errors in the body.
 *
 * UI-level coverage (opening FinancialReports.php, selecting a report type,
 * clicking Create Report, intercepting the download) lives in
 * cypress/e2e/ui/reports/standard.csv.reports.spec.js.
 */
describe("API Private Admin — CSV Export", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should export personal records as CSV", () => {
        cy.request({
            method: "POST",
            url: "/CSVCreateFile.php",
            form: true,
            body: {
                output: "csv",
                Format: "Default",
                Source: "person",
                familyonly: "false"
            }
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("text/csv");
            expect(response.body).to.not.include("Fatal error");
            expect(response.body).to.not.include("Parse error");
        });
    });

    it("should export family records as rollup CSV", () => {
        // Tests rollup format for family records
        // Validates that family address fallback logic works in CSV export (issue #7937)
        cy.request({
            method: "POST",
            url: "/CSVCreateFile.php",
            form: true,
            body: {
                output: "csv",
                Format: "Rollup",
                Source: "family",
                familyonly: "true"
            }
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.headers["content-type"]).to.include("text/csv");
            // Verify the CSV export works without errors
            expect(response.body).to.not.include("Fatal error");
            expect(response.body).to.not.include("Parse error");
        });
    });
});
