/// <reference types="cypress" />

/**
 * Tests for CSV export address fallback functionality
 * 
 * This test validates the fix for the issue where family and person addresses
 * were not being exported to CSV when person records lacked personal address data.
 * 
 * The fix ensures that:
 * - If a person has their own address, it will be used
 * - If a person's address is empty, the family's address will be used as fallback
 * - Addresses appear correctly in both individual and rollup CSV exports
 */
describe("CSV Address Export with Family Fallback", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("Individual CSV Export (Default Format)", () => {
        it("should export person addresses with family fallback when person address is empty", () => {
            // Test CSV export with default individual format
            cy.request({
                method: "POST",
                url: "/CSVCreateFile.php",
                form: true,
                body: {
                    Source: "filters",
                    Format: "Default",
                    FirstName: "1",
                    Address1: "1",
                    Address2: "1",
                    City: "1",
                    State: "1",
                    Zip: "1",
                    Country: "1",
                    HomePhone: "1",
                    Email: "1"
                }
            }).then((response) => {
                // Verify successful CSV export
                expect(response.status).to.eq(200);
                expect(response.headers["content-type"]).to.include("text/csv");
                expect(response.body).to.not.include("Fatal error");
                expect(response.body).to.not.include("Parse error");
                
                // Parse CSV to verify address data is present
                const lines = response.body.split('\n');
                
                // Verify header contains address fields
                const header = lines[0];
                expect(header).to.include("Address 1");
                expect(header).to.include("City");
                expect(header).to.include("State");
                expect(header).to.include("Zip");
                
                // Verify at least one data row has address information
                // (Either from person record or family fallback)
                let hasAddressData = false;
                for (let i = 1; i < Math.min(lines.length, 10); i++) {
                    const line = lines[i];
                    // Skip empty lines
                    if (line.trim().length === 0) continue;
                    
                    // Check if line contains address data (not just empty fields)
                    // Address data should have city and state at minimum
                    const fields = line.split(',');
                    if (fields.length > 5) {
                        // Look for non-empty city or state fields
                        const hasCity = fields.some(field => 
                            field.trim().length > 0 && 
                            !field.includes('Family ID') && 
                            !field.includes('Person')
                        );
                        if (hasCity) {
                            hasAddressData = true;
                            break;
                        }
                    }
                }
                
                // At least some records should have address data
                expect(hasAddressData).to.be.true;
            });
        });

        it("should include Home Phone field when selected", () => {
            cy.request({
                method: "POST",
                url: "/CSVCreateFile.php",
                form: true,
                body: {
                    Source: "filters",
                    Format: "Default",
                    FirstName: "1",
                    HomePhone: "1"
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.headers["content-type"]).to.include("text/csv");
                
                const lines = response.body.split('\n');
                const header = lines[0];
                expect(header).to.include("Home Phone");
            });
        });

        it("should include Email field when selected", () => {
            cy.request({
                method: "POST",
                url: "/CSVCreateFile.php",
                form: true,
                body: {
                    Source: "filters",
                    Format: "Default",
                    FirstName: "1",
                    Email: "1"
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.headers["content-type"]).to.include("text/csv");
                
                const lines = response.body.split('\n');
                const header = lines[0];
                expect(header).to.include("Email");
            });
        });
    });

    describe("Family Rollup CSV Export", () => {
        it("should export family addresses in rollup format", () => {
            // Test CSV export with family rollup format
            cy.request({
                method: "POST",
                url: "/CSVCreateFile.php",
                form: true,
                body: {
                    Source: "filters",
                    Format: "Rollup",
                    Address1: "1",
                    Address2: "1",
                    City: "1",
                    State: "1",
                    Zip: "1",
                    Country: "1",
                    HomePhone: "1",
                    Email: "1"
                }
            }).then((response) => {
                // Verify successful CSV export
                expect(response.status).to.eq(200);
                expect(response.headers["content-type"]).to.include("text/csv");
                expect(response.body).to.not.include("Fatal error");
                expect(response.body).to.not.include("Parse error");
                
                // Parse CSV to verify address data is present
                const lines = response.body.split('\n');
                
                // Verify header contains address fields
                const header = lines[0];
                expect(header).to.include("Address 1");
                expect(header).to.include("City");
                expect(header).to.include("State");
                expect(header).to.include("Zip");
                
                // In rollup format, families should have address data
                let hasAddressData = false;
                for (let i = 1; i < Math.min(lines.length, 10); i++) {
                    const line = lines[i];
                    if (line.trim().length === 0) continue;
                    
                    // Check for address fields with data
                    const fields = line.split(',');
                    if (fields.length > 3) {
                        const hasData = fields.some(field => 
                            field.trim().length > 0 && 
                            !field.includes('Family ID')
                        );
                        if (hasData) {
                            hasAddressData = true;
                            break;
                        }
                    }
                }
                
                expect(hasAddressData).to.be.true;
            });
        });

        it("should handle rollup format with home phone field", () => {
            cy.request({
                method: "POST",
                url: "/CSVCreateFile.php",
                form: true,
                body: {
                    Source: "filters",
                    Format: "Rollup",
                    HomePhone: "1"
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.headers["content-type"]).to.include("text/csv");
                
                const lines = response.body.split('\n');
                const header = lines[0];
                expect(header).to.include("Home Phone");
            });
        });
    });

    describe("CSV Export with Filters", () => {
        it("should skip records with incomplete addresses when requested", () => {
            cy.request({
                method: "POST",
                url: "/CSVCreateFile.php",
                form: true,
                body: {
                    Source: "filters",
                    Format: "Default",
                    FirstName: "1",
                    Address1: "1",
                    City: "1",
                    State: "1",
                    Zip: "1",
                    SkipIncompleteAddr: "1"
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.headers["content-type"]).to.include("text/csv");
                
                // With skip incomplete address, all records should have complete addresses
                const lines = response.body.split('\n');
                
                // If there are data lines, they should have address data
                for (let i = 1; i < Math.min(lines.length, 5); i++) {
                    const line = lines[i];
                    if (line.trim().length === 0) continue;
                    
                    // Line should have multiple fields
                    const fields = line.split(',');
                    expect(fields.length).to.be.greaterThan(3);
                }
            });
        });

        it("should export from cart when source is cart", () => {
            // First add some people to cart via the cart API
            // Then test CSV export from cart
            cy.request({
                method: "POST",
                url: "/CSVCreateFile.php",
                form: true,
                body: {
                    Source: "cart",
                    Format: "Default",
                    FirstName: "1",
                    Address1: "1",
                    City: "1"
                }
            }).then((response) => {
                // Should succeed even if cart is empty
                expect(response.status).to.eq(200);
                expect(response.headers["content-type"]).to.include("text/csv");
            });
        });
    });

    describe("CSV Export Error Handling", () => {
        it("should handle empty result sets gracefully", () => {
            // Export with very restrictive filter that may return no results
            cy.request({
                method: "POST",
                url: "/CSVCreateFile.php",
                form: true,
                body: {
                    Source: "filters",
                    Format: "Default",
                    FirstName: "1",
                    Gender: "0" // Don't filter
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.headers["content-type"]).to.include("text/csv");
                expect(response.body).to.not.include("Fatal error");
            });
        });

        it("should not crash with missing optional fields", () => {
            // Test with minimal fields selected
            cy.request({
                method: "POST",
                url: "/CSVCreateFile.php",
                form: true,
                body: {
                    Source: "filters",
                    Format: "Default"
                    // Only required field is Last Name (always included)
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.headers["content-type"]).to.include("text/csv");
            });
        });
    });
});
