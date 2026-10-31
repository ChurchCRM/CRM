/// <reference types="cypress" />

describe("csv export", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("FinancialReports.php");
    });

    // NOTE: personal/family CSV export pure-API tests live in
    // cypress/e2e/api/private/admin/private.admin.csv.export.spec.js.

    describe("advanced deposit report", () => {
        it("should export advanced deposit report as CSV", () => {
            cy.visit("/FinancialReports.php");
            cy.contains("Financial Reports");
            cy.visit("/finance/reports/deposits");
            cy.get("#depositAdvancedSettings > summary").click();
            cy.contains("Deposit Reports");
            cy.get("input[name='output'][value='csv']").check({ force: true });
            
            // Set date range to match test database data (goes back to 2018)
            cy.get("input[name='DateStart']").clear({ force: true }).type("2018-01-01", { force: true });
            cy.get("input[name='DateEnd']").clear({ force: true }).type(getTodayDate(), { force: true });
            
            // Intercept the download to verify CSV content
            cy.intercept("POST", "**/finance/reports/deposits/download").as("csvDownload");
            
            cy.get("#createReport").click();
            
            // Check if we got data or a "no data" redirect
            cy.url().then((url) => {
                if (url.includes('ReturnMessage=NoRows')) {
                    // No data found - verify the message is displayed
                    cy.contains('No Data Found');
                    cy.contains('No records were returned');
                } else {
                    // Data found - verify CSV download
                    cy.get("@csvDownload").then((interception) => {
                        expect(interception.response.statusCode).to.equal(200);
                        expect(interception.response.headers["content-type"]).to.include("text/csv");
                    });
                }
            });
        });

        it("should handle advanced deposit report with multiple filters", () => {
            cy.visit("/FinancialReports.php");
            cy.contains("Financial Reports");
            cy.visit("/finance/reports/deposits");
            cy.get("#depositAdvancedSettings > summary").click();
            cy.contains("Deposit Reports");
            cy.get("input[name='output'][value='csv']").check({ force: true });

            // Select first classification option if available
            cy.get("#classList").then(($select) => {
                if ($select.find("option").length > 0) {
                    cy.get("#classList option").first().then(($opt) => {
                        cy.get("#classList").select($opt.val(), { force: true });
                    });
                }
            });

            // Set date range
            cy.get("input[name='DateStart']").clear({ force: true }).type("2015-10-28", { force: true });
            cy.get("input[name='DateEnd']").clear({ force: true }).type(getTodayDate(), { force: true });

            // Select a deposit if available
            cy.get("#deposit").then(($select) => {
                const options = $select.find("option");
                if (options.length > 1) {
                    cy.get("#deposit").select(options.eq(1).val(), { force: true });
                }
            });

            cy.get("#createReport").click({ force: true });
            cy.url().should("include", "/finance/reports/deposits");
        });
    });

    describe("zero givers report", () => {
        it("should export zero givers report as CSV", () => {
            cy.visit("/FinancialReports.php");
            cy.contains("Financial Reports");
            cy.get("#FinancialReportTypes").select("Zero Givers");
            cy.get("#FinancialReports").submit();
            cy.contains("Zero Givers");
            cy.get("input[name='output'][value='csv']").check({ force: true });
            
            const startOfYear = new Date().getFullYear() + "-01-01";
            cy.get("input[name='DateStart']").type(startOfYear, { force: true });
            cy.get("input[name='DateEnd']").type(getTodayDate(), { force: true });
            
            // Intercept the download to verify CSV content
            cy.intercept("POST", "**/finance/reports/zero-givers/download").as("csvDownload");
            
            cy.get("#createReport").click({ force: true });
            
            // Verify the download was successful with CSV content type
            cy.get("@csvDownload").then((interception) => {
                expect(interception.response.statusCode).to.equal(200);
                expect(interception.response.headers["content-type"]).to.include("text/csv");
            });
        });

        it("should handle zero givers report with custom date range", () => {
            cy.visit("/FinancialReports.php");
            cy.contains("Financial Reports");
            cy.get("#FinancialReportTypes").select("Zero Givers");
            cy.get("#FinancialReports").submit();
            cy.contains("Zero Givers");
            cy.get("input[name='output'][value='csv']").check({ force: true });
            
            cy.get("input[name='DateStart']").clear({ force: true }).type("2024-10-28", { force: true });
            cy.get("input[name='DateEnd']").clear({ force: true }).type(getTodayDate(), { force: true });
            
            // Intercept the download to verify CSV content
            cy.intercept("POST", "**/finance/reports/zero-givers/download").as("csvDownload");
            
            cy.get("#createReport").click({ force: true });
            
            // Verify the download was successful with CSV content type
            cy.get("@csvDownload").then((interception) => {
                expect(interception.response.statusCode).to.equal(200);
                expect(interception.response.headers["content-type"]).to.include("text/csv");
            });
        });
    });
});

/**
 * Helper Functions
 */
function getTodayDate() {
    return formatDate(new Date());
}



function formatDate(date) {
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    const year = date.getFullYear();
    return `${year}-${month}-${day}`;
}
