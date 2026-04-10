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

        it("should include address columns in CSV export with family fallback", () => {
            // Test default format includes address fields when requested
            // This validates the family fallback logic for addresses (issue #7937)
            cy.request({
                method: "POST",
                url: "/CSVCreateFile.php",
                form: true,
                body: {
                    output: "csv",
                    Format: "default",
                    familyonly: "false",
                    Source: "all",
                    Address1: "1",
                    Address2: "1",
                    City: "1",
                    State: "1",
                    Zip: "1"
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.headers["content-type"]).to.include("text/csv");

                // Verify no PHP errors in response
                expect(response.body).to.not.include("Fatal error");
                expect(response.body).to.not.include("Parse error");

                // Parse CSV to validate structure and data
                const lines = response.body.split('\n').filter(line => line.trim().length > 0);
                expect(lines.length).to.be.greaterThan(1, "Expected header + data rows");

                // Verify CSV header contains address columns
                const headerLine = lines[0];
                const headers = headerLine.split(',').map(h => h.toLowerCase().trim());
                expect(headers).to.include("address 1");
                expect(headers).to.include("city");
                expect(headers).to.include("state");

                // Find column indices for address fields
                const addr1Index = headers.findIndex(h => h.includes("address 1"));
                const cityIndex = headers.findIndex(h => h.includes("city"));
                const stateIndex = headers.findIndex(h => h.includes("state"));

                // Validate that at least one data row has populated address values (proves fallback works)
                let hasAddressData = false;
                for (let i = 1; i < lines.length; i++) {
                    const fields = lines[i].split(',');
                    if (fields[addr1Index] && fields[addr1Index].trim() && fields[cityIndex] && fields[cityIndex].trim()) {
                        hasAddressData = true;
                        break;
                    }
                }
                expect(hasAddressData).to.be.true("Expected at least one row with populated address (fallback should work)");
            });
        });

        it("should include address columns in rollup format CSV export", () => {
            // Test rollup format includes address fields when requested
            // Rollup format rolls up multiple family members into single family rows
            cy.request({
                method: "POST",
                url: "/CSVCreateFile.php",
                form: true,
                body: {
                    output: "csv",
                    Format: "rollup",
                    familyonly: "false",
                    Source: "all",
                    Address1: "1",
                    Address2: "1",
                    City: "1",
                    State: "1",
                    Zip: "1"
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.headers["content-type"]).to.include("text/csv");

                // Verify no PHP errors in response
                expect(response.body).to.not.include("Fatal error");
                expect(response.body).to.not.include("Parse error");

                // Parse CSV to validate structure and data
                const lines = response.body.split('\n').filter(line => line.trim().length > 0);
                expect(lines.length).to.be.greaterThan(1, "Expected header + data rows");

                // Verify CSV header contains address columns
                const headerLine = lines[0];
                const headers = headerLine.split(',').map(h => h.toLowerCase().trim());
                expect(headers).to.include("address 1");
                expect(headers).to.include("city");
                expect(headers).to.include("state");

                // Find column indices for address fields
                const addr1Index = headers.findIndex(h => h.includes("address 1"));
                const cityIndex = headers.findIndex(h => h.includes("city"));

                // Validate that at least one data row has populated address values (proves fallback works)
                let hasAddressData = false;
                for (let i = 1; i < lines.length; i++) {
                    const fields = lines[i].split(',');
                    if (fields[addr1Index] && fields[addr1Index].trim() && fields[cityIndex] && fields[cityIndex].trim()) {
                        hasAddressData = true;
                        break;
                    }
                }
                expect(hasAddressData).to.be.true("Expected at least one row with populated address (fallback should work)");
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

            // Select first classification option if available
            cy.get("#classList").then(($select) => {
                if ($select.find("option").length > 0) {
                    cy.get("#classList option").first().then(($opt) => {
                        cy.get("#classList").select($opt.val(), { force: true });
                    });
                }
            });

            // Set date range
            cy.get("input[name='DateStart']").clear({ force: true }).type("10/28/2015", { force: true });
            cy.get("input[name='DateEnd']").clear({ force: true }).type(getTodayDate(), { force: true });

            // Select a deposit if available
            cy.get("#deposit").then(($select) => {
                const options = $select.find("option");
                if (options.length > 1) {
                    cy.get("#deposit").select(options.eq(1).val(), { force: true });
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
