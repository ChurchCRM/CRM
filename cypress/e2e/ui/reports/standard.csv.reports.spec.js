/// <reference types="cypress" />

describe("csv export", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("FinancialReports.php");
    });

    describe("personal/family export", () => {
        it("should export personal records as CSV", () => {
            cy.request({
                method: "POST",
                url: "/CSVCreateFile.php",
                form: true,
                body: {
                    output: "csv",
                    format: "0",
                    familyonly: "false"
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.headers["content-type"]).to.include("text/csv");
                expect(response.body).to.not.include("Fatal error");
                expect(response.body).to.not.include("Parse error");
            });
        });
    });

    describe("advanced deposit report", () => {
        it("should export advanced deposit report as CSV", () => {
            cy.visit("/FinancialReports.php");
            cy.contains("Financial Reports");
            cy.get("#FinancialReportTypes").select("Advanced Deposit Report");
            cy.get("#FinancialReports").submit();
            cy.contains("Advanced Deposit Report");
            cy.get("input[name='output'][value='csv']").check({ force: true });
            
            // Set date range to match test database data (goes back to 2018)
            cy.get("input[name='DateStart']").clear({ force: true }).type("01/01/2018", { force: true });
            cy.get("input[name='DateEnd']").clear({ force: true }).type(getTodayDate(), { force: true });
            
            // Intercept the download to verify CSV content
            cy.intercept("POST", "**/AdvancedDeposit.php").as("csvDownload");
            
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
            cy.get("#FinancialReportTypes").select("Advanced Deposit Report");
            cy.get("#FinancialReports").submit();
            cy.contains("Advanced Deposit Report");
            cy.get("input[name='output'][value='csv']").check({ force: true });
            
            cy.get("tbody tr:nth-of-type(2) input").then(($input) => {
                if ($input.length > 0) {
                    cy.get("tbody tr:nth-of-type(2) input").first().click({ force: true });
                }
            });
            
            cy.get("tbody tr:nth-of-type(4) input").then(($input) => {
                if ($input.length > 0) {
                    cy.get("tbody tr:nth-of-type(4) input").first().click({ force: true });
                }
            });
            
            cy.get("input[name='DateStart']").clear({ force: true }).type("10/28/2015", { force: true });
            cy.get("input[name='DateEnd']").clear({ force: true }).type(getTodayDate(), { force: true });
            
            cy.get("tbody tr:nth-of-type(9) select").then(($select) => {
                if ($select.length > 0) {
                    cy.get("tbody tr:nth-of-type(9) select").then(($select2) => {
                        const options = $select2.find("option");
                        if (options.length > 1) {
                            cy.get("tbody tr:nth-of-type(9) select").select(options.eq(1).val(), { force: true });
                        }
                    });
                }
            });
            
            cy.get("#createReport").click({ force: true });
            cy.url().should("include", "/FinancialReports.php");
        });
    });

    describe("tax report / giving report", () => {
        it("should export giving report as CSV", () => {
            cy.visit("/FinancialReports.php");
            cy.contains("Financial Reports");
            cy.get("#FinancialReportTypes").select("Giving Report");
            cy.get("#FinancialReports").submit();
            cy.contains("Giving Report");
            cy.get("input[name='output'][value='csv']").check({ force: true });
            
            // Set date range to match test database data (goes back to 2018)
            cy.get("input[name='DateStart']").clear({ force: true }).type("01/01/2018", { force: true });
            cy.get("input[name='DateEnd']").clear({ force: true }).type(getTodayDate(), { force: true });
            
            // Intercept the download to verify CSV content
            cy.intercept("POST", "**/TaxReport.php").as("csvDownload");
            
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
            cy.intercept("POST", "**/ZeroGivers.php").as("csvDownload");
            
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
            
            cy.get("input[name='DateStart']").clear({ force: true }).type("10/28/2024", { force: true });
            cy.get("input[name='DateEnd']").clear({ force: true }).type(getTodayDate(), { force: true });
            
            // Intercept the download to verify CSV content
            cy.intercept("POST", "**/ZeroGivers.php").as("csvDownload");
            
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
    return `${month}/${day}/${year}`;
}
