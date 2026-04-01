/// <reference types="cypress" />

/**
 * Test for ChurchInfoReport null-to-string conversion fix
 *
 * The ConfirmReport generates PDFs with family information including addresses
 * and country fields. These fields can be null from the database, which caused
 * a TypeError in convertToLatin1() when attempting to pass null to a function
 * expecting a string.
 *
 * The fix adds type casts and uses !empty() instead of !== '' to handle null values.
 *
 * This test verifies that ConfirmReport PDF generation works with families
 * that have null/missing address fields.
 */
describe("Confirm Report PDF Generation - Null Value Fix", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should generate Confirm Report PDF for all families without null conversion error", () => {
        // Access ConfirmReport directly with no familyId parameter (all families)
        cy.intercept("GET", "**/Reports/ConfirmReport.php*").as("confirmReportAll");

        cy.visit("Reports/ConfirmReport.php");

        // Wait for the PDF response
        cy.wait("@confirmReportAll", { timeout: 10000 }).then((interception) => {
            const statusCode = interception.response.statusCode;
            const contentType = interception.response.headers["content-type"] || "";

            // Should not return server error (the bug returned 500 with TypeError)
            expect(statusCode).to.not.equal(500);
            expect(statusCode).to.equal(200);

            // Should return a PDF
            expect(contentType).to.include("application/pdf");

            // Ensure no PHP fatal error in response
            const body = interception.response.body;
            if (typeof body === "string") {
                expect(body).to.not.include("Fatal error");
                expect(body).to.not.include("TypeError");
                expect(body).to.not.include("Argument #1");
            }
        });
    });

    it("should generate Confirm Report PDF for a single family", () => {
        // First get a family ID from the database via API
        cy.makePrivateAdminAPICall("/api/families", "GET").then((response) => {
            expect(response.status).to.equal(200);

            const families = response.body;
            // Ensure there is at least one family; fail meaningfully if not
            expect(
                families,
                "at least one family must exist for Confirm Report single-family test"
            ).to.have.length.greaterThan(0);

            const familyId = families[0].id;

            // Access ConfirmReport for specific family
            cy.intercept("GET", `**/Reports/ConfirmReport.php?familyId=${familyId}*`).as(
                "confirmReportFamily"
            );

            cy.visit(`Reports/ConfirmReport.php?familyId=${familyId}`);

            // Wait for the PDF response
            cy.wait("@confirmReportFamily", { timeout: 10000 }).then((interception) => {
                const statusCode = interception.response.statusCode;
                const contentType = interception.response.headers["content-type"] || "";

                // Should succeed
                expect(statusCode).to.not.equal(500);
                expect(statusCode).to.equal(200);

                // Should return a PDF
                expect(contentType).to.include("application/pdf");
            });
        });
    });

    it("should handle families with missing address fields (null values)", () => {
        // Get families and find one with potentially missing fields
        cy.makePrivateAdminAPICall("/api/families", "GET").then((response) => {
            const families = response.body;

            expect(
                families,
                "at least one family must exist for null-value test"
            ).to.have.length.greaterThan(0);

            // Try the first family (may have incomplete data)
            const familyId = families[0].id;

            cy.intercept("GET", `**/Reports/ConfirmReport.php?familyId=${familyId}*`).as(
                "incompleteFamily"
            );

            cy.visit(`Reports/ConfirmReport.php?familyId=${familyId}`);

            cy.wait("@incompleteFamily", { timeout: 10000 }).then((interception) => {
                const statusCode = interception.response.statusCode;

                // Should not error even if family has null address2 or country
                expect(statusCode).to.not.equal(500);
                expect(statusCode).to.equal(200);

                // Verify no PHP errors in response
                const body = interception.response.body;
                if (typeof body === "string") {
                    expect(body).to.not.include("Uncaught TypeError");
                    expect(body).to.not.include("convertToLatin1");
                }
            });
        });
    });

    it("should not have PHP fatal errors in logs after Confirm Report generation", () => {
        // Generate a report for all families
        cy.intercept("GET", "**/Reports/ConfirmReport.php*").as("confirmReport");

        cy.visit("Reports/ConfirmReport.php");

        cy.wait("@confirmReport", { timeout: 10000 });

        // Check the PHP error log to ensure no fatal errors were logged
        cy.makePrivateAdminAPICall("/api/system/logs", "GET").then((response) => {
            if (response.status === 200 && response.body) {
                const logs = response.body;

                // Look for fatal errors related to convertToLatin1
                const fatalErrors = logs.filter((log) =>
                    log.includes("Fatal error") && log.includes("convertToLatin1")
                );

                expect(fatalErrors.length).to.equal(0);
            }
        });
    });
});
