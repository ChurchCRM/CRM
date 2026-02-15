/// <reference types="cypress" />

/**
 * Test for issue #7854: Advanced Deposit Report showing UNDESIGNATED/UNASSIGNED
 * 
 * This test verifies that:
 * 1. Advanced Deposit Report displays actual fund names (not "UNDESIGNATED")
 * 2. Advanced Deposit Report displays actual family names (not "UNASSIGNED")
 * 3. Report works correctly with large datasets
 */

describe("Issue #7854 - Financial Reports Fix", () => {
    beforeEach(() => {
        cy.setupAdminSessionFromEnv();
    });

    describe("Advanced Deposit Report - Fund and Family Names", () => {
        it("should display actual fund names and family names (not UNDESIGNATED/UNASSIGNED)", () => {
            // Navigate to Financial Reports
            cy.visit("/FinancialReports.php");
            cy.contains("Financial Reports").should("be.visible");

            // Select Advanced Deposit Report
            cy.get("#FinancialReportTypes").select("Advanced Deposit Report");
            cy.get("#FinancialReports").submit();
            cy.contains("Advanced Deposit Report").should("be.visible");

            // Configure report for CSV output (easier to parse than PDF)
            cy.get("input[name='output'][value='csv']").check({ force: true });

            // Set broad date range to capture all test data
            cy.get("input[name='DateStart']").clear({ force: true }).type("01/01/2018", { force: true });
            cy.get("input[name='DateEnd']").clear({ force: true }).type(getTodayDate(), { force: true });

            // Intercept the CSV download to inspect content
            cy.intercept("POST", "**/AdvancedDeposit.php").as("csvDownload");

            // Generate report
            cy.get("#createReport").click();

            // Wait for response and verify content
            cy.wait("@csvDownload", { timeout: 30000 }).then((interception) => {
                // Check if we got actual data (not a redirect to "No Data" page)
                cy.url().then((url) => {
                    if (!url.includes('ReturnMessage=NoRows')) {
                        // Verify we got a CSV response
                        expect(interception.response.statusCode).to.equal(200);
                        expect(interception.response.headers["content-type"]).to.include("text/csv");

                        const csvContent = interception.response.body;

                        // CRITICAL CHECKS for issue #7854:
                        // 1. CSV should NOT contain "UNDESIGNATED" for fund names
                        // 2. CSV should NOT contain "UNASSIGNED" for family names
                        
                        // Split CSV into lines
                        const lines = csvContent.split('\n');
                        
                        // First line should be headers
                        expect(lines[0]).to.include('fam_Name'); // Should have family name column
                        expect(lines[0]).to.include('fun_Name'); // Should have fund name column

                        // Check data rows (skip header)
                        const dataRows = lines.slice(1).filter(line => line.trim() !== '');
                        
                        if (dataRows.length > 0) {
                            // Parse CSV to verify fund and family names
                            const headers = lines[0].split(',').map(h => h.replace(/"/g, '').trim());
                            const famNameIndex = headers.indexOf('fam_Name');
                            const funNameIndex = headers.indexOf('fun_Name');

                            expect(famNameIndex).to.be.greaterThan(-1, "fam_Name column should exist");
                            expect(funNameIndex).to.be.greaterThan(-1, "fun_Name column should exist");

                            let hasActualFundName = false;
                            let hasActualFamilyName = false;

                            dataRows.forEach((row, index) => {
                                const columns = row.split(',').map(c => c.replace(/"/g, '').trim());
                                
                                const familyName = columns[famNameIndex];
                                const fundName = columns[funNameIndex];

                                // Family name should NOT be "UNASSIGNED" (unless truly unassigned)
                                if (familyName && familyName !== 'UNASSIGNED' && familyName !== '') {
                                    hasActualFamilyName = true;
                                }

                                // Fund name should NOT be "UNDESIGNATED" (unless truly undesignated)
                                if (fundName && fundName !== 'UNDESIGNATED' && fundName !== '') {
                                    hasActualFundName = true;
                                }
                            });

                            // At least one row should have actual fund and family names
                            expect(hasActualFundName, "At least one row should have an actual fund name").to.be.true;
                            expect(hasActualFamilyName, "At least one row should have an actual family name").to.be.true;

                            cy.log("✅ Advanced Deposit Report correctly displays fund and family names");
                        }
                    } else {
                        // No data found - this is acceptable, just verify the message
                        cy.contains('No Data Found').should('be.visible');
                        cy.log("⚠️ No data found in date range - test passed but no data to verify");
                    }
                });
            });
        });

        it("should handle date range filtering correctly", () => {
            cy.visit("/FinancialReports.php");
            cy.get("#FinancialReportTypes").select("Advanced Deposit Report");
            cy.get("#FinancialReports").submit();

            // Test with current month only
            const firstDayOfMonth = new Date();
            firstDayOfMonth.setDate(1);
            const today = new Date();

            const startDate = formatDate(firstDayOfMonth);
            const endDate = formatDate(today);

            cy.get("input[name='DateStart']").clear({ force: true }).type(startDate, { force: true });
            cy.get("input[name='DateEnd']").clear({ force: true }).type(endDate, { force: true });

            cy.get("input[name='output'][value='csv']").check({ force: true });
            cy.get("#createReport").click();

            // Should either show data or "No Data Found" - both are valid
            cy.url().then((url) => {
                if (url.includes('ReturnMessage=NoRows')) {
                    cy.contains('No Data Found').should('be.visible');
                } else {
                    // CSV should be generated
                    cy.url().should('not.include', 'error');
                }
            });
        });

        it("should sort by different criteria without errors", () => {
            cy.visit("/FinancialReports.php");
            cy.get("#FinancialReportTypes").select("Advanced Deposit Report");
            cy.get("#FinancialReports").submit();

            // Set date range
            cy.get("input[name='DateStart']").clear({ force: true }).type("01/01/2024", { force: true });
            cy.get("input[name='DateEnd']").clear({ force: true }).type(getTodayDate(), { force: true });

            // Test different sort orders (radio buttons, not select)
            const sortOptions = ['deposit', 'fund', 'family'];
            
            sortOptions.forEach((sortBy) => {
                cy.get(`input[name='sort'][value='${sortBy}']`).check({ force: true });
                cy.get("input[name='output'][value='csv']").check({ force: true });
                
                cy.intercept("POST", "**/AdvancedDeposit.php").as("sortedReport");
                cy.get("#createReport").click();

                cy.url().then((url) => {
                    if (!url.includes('ReturnMessage=NoRows')) {
                        cy.wait("@sortedReport").then((interception) => {
                            expect(interception.response.statusCode).to.equal(200);
                        });
                    }
                });

                // Go back to report config
                if (sortBy !== sortOptions[sortOptions.length - 1]) {
                    cy.visit("/FinancialReports.php");
                    cy.get("#FinancialReportTypes").select("Advanced Deposit Report");
                    cy.get("#FinancialReports").submit();
                    cy.get("input[name='DateStart']").clear({ force: true }).type("01/01/2024", { force: true });
                    cy.get("input[name='DateEnd']").clear({ force: true }).type(getTodayDate(), { force: true });
                }
            });
        });
    });

    describe("Giving Report (Tax Statements) - Large Dataset Handling", () => {
        it("should handle large datasets without white screen", () => {
            cy.visit("/FinancialReports.php");
            cy.get("#FinancialReportTypes").select("Giving Report");
            cy.get("#FinancialReports").submit();

            // Set broad date range (simulating ~945 records scenario)
            cy.get("input[name='DateStart']").clear({ force: true }).type("01/01/2024", { force: true });
            cy.get("input[name='DateEnd']").clear({ force: true }).type(getTodayDate(), { force: true });

            // Use CSV output for easier verification
            cy.get("input[name='output'][value='csv']").check({ force: true });

            cy.intercept("POST", "**/TaxReport.php").as("givingReport");
            cy.get("#createReport").click();

            // Wait for response with longer timeout for large datasets
            cy.wait("@givingReport", { timeout: 60000 }).then((interception) => {
                cy.url().then((url) => {
                    if (!url.includes('ReturnMessage=NoRows')) {
                        // Should return 200, not 500 or timeout
                        expect(interception.response.statusCode).to.equal(200);
                        
                        // Should be CSV content, not error page
                        expect(interception.response.headers["content-type"]).to.include("text/csv");
                        
                        // Should not contain PHP errors
                        const content = interception.response.body;
                        expect(content).to.not.include("Fatal error");
                        expect(content).to.not.include("Parse error");
                        expect(content).to.not.include("Maximum execution time");
                        expect(content).to.not.include("Allowed memory size");

                        cy.log("✅ Giving Report handles large dataset without errors");
                    } else {
                        cy.contains('No Data Found').should('be.visible');
                    }
                });
            });
        });
    });
});

// Helper functions
function getTodayDate() {
    const today = new Date();
    return formatDate(today);
}

function formatDate(date) {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const year = date.getFullYear();
    return `${month}/${day}/${year}`;
}
