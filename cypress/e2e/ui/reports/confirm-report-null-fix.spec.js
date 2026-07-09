/// <reference types="cypress" />

/**
 * Test for ChurchInfoReport null-to-string conversion fix
 *
 * The ConfirmReport generates PDFs with family information including addresses
 * and country fields. These fields can be null from the database, which caused
 * a TypeError in convertToLatin1() when attempting to pass null to a function
 * expecting a string.
 *
 * Pattern: cy.setupAdminSession() + cy.visit() to load session cookies, then
 * cy.intercept() + win.location.href to trigger the PDF — same reason
 * tax-report-pdf.spec.js always does cy.visit() before intercepting the PDF.
 * cy.request() alone does NOT carry session cookies for auth-protected legacy
 * PHP pages; the browser context must be established first via cy.visit().
 */
describe("Confirm Report PDF Generation - Null Value Fix", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        // Visit any authenticated HTML page to load session cookies into browser
        // context before we intercept and navigate to the PDF endpoint.
        cy.visit("LettersAndLabels.php");
    });

    it("should generate Confirm Report PDF for all families without null conversion error", () => {
        cy.intercept("GET", "**/Reports/ConfirmReport.php").as("confirmReportAll");

        cy.window().then((win) => {
            win.location.href = `${win.CRM.root}/Reports/ConfirmReport.php`;
        });

        cy.wait("@confirmReportAll", { timeout: 15000 }).then((interception) => {
            const statusCode = interception.response.statusCode;
            const contentType = interception.response.headers["content-type"] || "";

            // Should not return server error (the bug returned 500 with TypeError)
            expect(statusCode).to.not.equal(500);
            expect(statusCode).to.equal(200);

            // Should return a PDF
            expect(contentType).to.include("application/pdf");

            // Ensure no PHP fatal error in response body
            const body = interception.response.body;
            if (typeof body === "string") {
                expect(body).to.not.include("Fatal error");
                expect(body).to.not.include("TypeError");
                expect(body).to.not.include("Argument #1");
            }
        });
    });

    it("should generate Confirm Report PDF for a single family", () => {
        // Use family ID 1 — always present in demo data.
        // Hardcoded to avoid makePrivateAdminAPICall() which resets PHP sessions.
        const familyId = 1;

        cy.intercept("GET", `**/Reports/ConfirmReport.php?familyId=${familyId}`).as(
            "confirmReportSingle"
        );

        cy.window().then((win) => {
            win.location.href = `${win.CRM.root}/Reports/ConfirmReport.php?familyId=${familyId}`;
        });

        cy.wait("@confirmReportSingle", { timeout: 15000 }).then((interception) => {
            const statusCode = interception.response.statusCode;
            const contentType = interception.response.headers["content-type"] || "";

            expect(statusCode).to.not.equal(500);
            expect(statusCode).to.equal(200);
            expect(contentType).to.include("application/pdf");
        });
    });

    it("should handle families with missing address fields (null values)", () => {
        const familyId = 1;

        cy.intercept("GET", `**/Reports/ConfirmReport.php?familyId=${familyId}`).as(
            "confirmReportNullFields"
        );

        cy.window().then((win) => {
            win.location.href = `${win.CRM.root}/Reports/ConfirmReport.php?familyId=${familyId}`;
        });

        cy.wait("@confirmReportNullFields", { timeout: 15000 }).then((interception) => {
            const statusCode = interception.response.statusCode;

            // Should not error even with null address2 or country
            expect(statusCode).to.not.equal(500);
            expect(statusCode).to.equal(200);

            const body = interception.response.body;
            if (typeof body === "string") {
                expect(body).to.not.include("Uncaught TypeError");
                expect(body).to.not.include("convertToLatin1");
            }
        });
    });

    it("should not return a server error when generating Confirm Report", () => {
        // A 200 application/pdf response proves no PHP fatal error occurred —
        // fatal errors produce a 500 HTML error page, not a valid PDF.
        cy.intercept("GET", "**/Reports/ConfirmReport.php").as("confirmReport");

        cy.window().then((win) => {
            win.location.href = `${win.CRM.root}/Reports/ConfirmReport.php`;
        });

        cy.wait("@confirmReport", { timeout: 15000 }).then((interception) => {
            expect(interception.response.statusCode).to.not.equal(500);
            expect(interception.response.statusCode).to.equal(200);

            const contentType = interception.response.headers["content-type"] || "";
            expect(contentType).to.include("application/pdf");
        });
    });
});
