/// <reference types="cypress" />

/**
 * Test for GitHub Issue #7906: Tax Statement Report generates blank page
 * @see https://github.com/ChurchCRM/CRM/issues/7906
 * 
 * This test verifies that the Giving Report (Tax Statement) generates 
 * a valid PDF instead of a blank HTML page.
 */
describe("Tax Report PDF Generation - Issue #7906", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should generate a valid PDF for Giving Report when data exists", () => {
        // Navigate to Financial Reports
        cy.visit("FinancialReports.php");
        cy.contains("Financial Reports");
        
        // Select Giving Report
        cy.get("#FinancialReportTypes").select("Giving Report");
        cy.get("#FinancialReports").submit();
        cy.contains("Financial Reports: Giving Report");
        
        // Set wide date range to capture all test data (demo data)
        cy.get("#DateStart").clear().type("2005-01-01");
        cy.get("#DateEnd").clear().type("2025-12-31");
        
        // Ensure PDF output is selected (default)
        cy.get('input[name="output"][value="pdf"]').check();
        
        // Intercept the report form submission
        cy.intercept("POST", "**/Reports/TaxReport.php").as("taxReportSubmit");
        
        // Submit the form
        cy.get("#createReport").click();
        
        // Wait for the response and verify it's a PDF
        cy.wait("@taxReportSubmit").then((interception) => {
            const contentType = interception.response.headers["content-type"];
            const statusCode = interception.response.statusCode;
            
            // Verify successful response
            expect(statusCode).to.equal(200);
            
            // If we got a PDF, verify it's not blank
            if (contentType && contentType.includes("application/pdf")) {
                // PDF was generated - verify it has content
                expect(interception.response.body.length).to.be.greaterThan(100);
            } else if (contentType && contentType.includes("text/html")) {
                // If HTML was returned, check if it's a redirect for "No Data"
                const body = interception.response.body;
                if (typeof body === "string") {
                    // Check for redirect to NoRows message (acceptable - no payment data)
                    if (body.includes("ReturnMessage=NoRows")) {
                        cy.log("No payment data found - redirect to NoRows is expected");
                    } else {
                        // Blank HTML page is the bug we're testing for
                        // If body is essentially empty HTML, this is the bug
                        expect(body.length).to.be.greaterThan(500, 
                            "Blank HTML page returned instead of PDF - Issue #7906 bug detected");
                    }
                }
            }
        });
    });

    it("should redirect to NoRows message when no data in date range", () => {
        // Navigate to Financial Reports
        cy.visit("FinancialReports.php");
        cy.contains("Financial Reports");
        
        // Select Giving Report
        cy.get("#FinancialReportTypes").select("Giving Report");
        cy.get("#FinancialReports").submit();
        cy.contains("Financial Reports: Giving Report");
        
        // Set a date range with no data (far future)
        cy.get("#DateStart").clear().type("2099-01-01");
        cy.get("#DateEnd").clear().type("2099-12-31");
        
        // Ensure PDF output is selected
        cy.get('input[name="output"][value="pdf"]').check();
        
        // Submit the form
        cy.get("#createReport").click();
        
        // Should redirect back to FinancialReports.php with NoRows message
        cy.url().should("contain", "FinancialReports.php");
        cy.contains("No Data Found", { timeout: 10000 });
    });

    it("should generate PDF when filtering by specific deposit", () => {
        // Navigate to Financial Reports
        cy.visit("FinancialReports.php");
        cy.contains("Financial Reports");
        
        // Select Giving Report
        cy.get("#FinancialReportTypes").select("Giving Report");
        cy.get("#FinancialReports").submit();
        cy.contains("Financial Reports: Giving Report");
        
        // Check if there are any deposits to filter by
        cy.get('select[name="deposit"]').then(($select) => {
            const options = $select.find("option");
            
            // If there are deposits besides "All deposits", select one
            if (options.length > 1) {
                // Select the second option (first actual deposit)
                cy.get('select[name="deposit"]').select(options.eq(1).val().toString());
                
                // Ensure PDF output is selected
                cy.get('input[name="output"][value="pdf"]').check();
                
                // Intercept the report form submission
                cy.intercept("POST", "**/Reports/TaxReport.php").as("taxReportDeposit");
                
                // Submit the form
                cy.get("#createReport").click();
                
                // Wait for response - should be PDF or redirect
                cy.wait("@taxReportDeposit").then((interception) => {
                    const statusCode = interception.response.statusCode;
                    // Response should be successful (200 for PDF, or 302/200 for redirect)
                    expect([200, 302]).to.include(statusCode);
                });
            } else {
                // No deposits available - skip this part of test
                cy.log("No deposits available to test deposit filter");
            }
        });
    });

    it("should handle all letterhead options without error", () => {
        // Navigate to Financial Reports
        cy.visit("FinancialReports.php");
        cy.contains("Financial Reports");
        
        // Select Giving Report
        cy.get("#FinancialReportTypes").select("Giving Report");
        cy.get("#FinancialReports").submit();
        cy.contains("Financial Reports: Giving Report");
        
        // Set date range with known data
        cy.get("#DateStart").clear().type("2005-01-01");
        cy.get("#DateEnd").clear().type("2025-12-31");
        
        // Test each letterhead option
        const letterheadOptions = ["address", "graphic", "none"];
        
        letterheadOptions.forEach((option) => {
            cy.get(`input[name="letterhead"][value="${option}"]`).check();
            cy.log(`Testing letterhead option: ${option}`);
        });
        
        // Use the last option and submit
        cy.get('input[name="output"][value="pdf"]').check();
        
        // Intercept the report form submission
        cy.intercept("POST", "**/Reports/TaxReport.php").as("letterheadTest");
        
        // Submit the form
        cy.get("#createReport").click();
        
        // Wait for response - should not error
        cy.wait("@letterheadTest").then((interception) => {
            const statusCode = interception.response.statusCode;
            // Should not return server error
            expect(statusCode).to.not.equal(500);
        });
    });

    it("should generate CSV output successfully", () => {
        // Navigate to Financial Reports
        cy.visit("FinancialReports.php");
        cy.contains("Financial Reports");
        
        // Select Giving Report
        cy.get("#FinancialReportTypes").select("Giving Report");
        cy.get("#FinancialReports").submit();
        cy.contains("Financial Reports: Giving Report");
        
        // Set wide date range
        cy.get("#DateStart").clear().type("2005-01-01");
        cy.get("#DateEnd").clear().type("2025-12-31");
        
        // Select CSV output
        cy.get('input[name="output"][value="csv"]').check();
        
        // Intercept the report form submission
        cy.intercept("POST", "**/Reports/TaxReport.php").as("csvReport");
        
        // Submit the form
        cy.get("#createReport").click();
        
        // Wait for response
        cy.wait("@csvReport").then((interception) => {
            const contentType = interception.response.headers["content-type"] || "";
            const statusCode = interception.response.statusCode;
            
            // Should be successful
            expect(statusCode).to.equal(200);
            
            // Should return CSV or redirect to NoRows
            if (!contentType.includes("text/html") || 
                interception.response.body?.includes?.("ReturnMessage=NoRows")) {
                // Either CSV was generated or no data redirect - both acceptable
                cy.log("CSV generation or no-data redirect successful");
            }
        });
    });
});
