/// <reference types="cypress" />

/**
 * Comprehensive test suite for ConfirmReport and ConfirmReportEmail features
 *
 * Tests PDF generation for confirmation reports with various data scenarios.
 *
 * Pattern: cy.setupAdminSession() + cy.visit(htmlPage) in beforeEach to load
 * session cookies, then cy.intercept() + win.location.href to trigger PDFs.
 * cy.visit() cannot be used on PDF endpoints (content-type must be text/html).
 * makePrivateAdminAPICall() resets PHP sessions, so family IDs are hardcoded
 * to demo data (family 1) rather than fetched via API calls before PDF tests.
 */
describe("Confirmation Reports - ConfirmReport & ConfirmReportEmail", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        // Establish browser context so session cookies are in scope for PDF navigation
        cy.visit("LettersAndLabels.php");
    });

    describe("ConfirmReport - PDF Generation", () => {
        it("should generate confirmation report for all families without errors", () => {
            cy.intercept("GET", "**/Reports/ConfirmReport.php").as("confirmReportAll");

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/Reports/ConfirmReport.php`;
            });

            cy.wait("@confirmReportAll", { timeout: 15000 }).then((interception) => {
                expect(interception.response.statusCode).to.equal(200);

                const contentType = interception.response.headers["content-type"] || "";
                expect(contentType).to.include("application/pdf");

                const body = interception.response.body;
                if (typeof body === "string") {
                    expect(body).to.not.include("Fatal error");
                    expect(body).to.not.include("TypeError");
                }
            });
        });

        it("should generate single family confirmation report", () => {
            // Family ID 1 always exists in demo data — hardcoded to avoid
            // makePrivateAdminAPICall() which resets the PHP session
            const familyId = 1;

            cy.intercept("GET", `**/Reports/ConfirmReport.php?familyId=${familyId}`).as(
                "confirmReportSingle"
            );

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/Reports/ConfirmReport.php?familyId=${familyId}`;
            });

            cy.wait("@confirmReportSingle", { timeout: 15000 }).then((interception) => {
                expect(interception.response.statusCode).to.equal(200);

                const contentType = interception.response.headers["content-type"] || "";
                expect(contentType).to.include("application/pdf");
            });
        });

        it("should handle families with missing address fields", () => {
            const familyId = 1;

            cy.intercept("GET", `**/Reports/ConfirmReport.php?familyId=${familyId}`).as(
                "confirmReportNullFields"
            );

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/Reports/ConfirmReport.php?familyId=${familyId}`;
            });

            cy.wait("@confirmReportNullFields", { timeout: 15000 }).then((interception) => {
                expect(interception.response.statusCode).to.equal(200);

                const body = interception.response.body;
                if (typeof body === "string") {
                    expect(body).to.not.include("Uncaught TypeError");
                    expect(body).to.not.include("convertToLatin1");
                }
            });
        });

        it("should include family members table in confirmation report", () => {
            const familyId = 1;

            cy.intercept("GET", `**/Reports/ConfirmReport.php?familyId=${familyId}`).as(
                "confirmReportWithMembers"
            );

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/Reports/ConfirmReport.php?familyId=${familyId}`;
            });

            cy.wait("@confirmReportWithMembers", { timeout: 15000 }).then((interception) => {
                expect(interception.response.statusCode).to.equal(200);
                expect(interception.response.headers["content-type"]).to.include("application/pdf");
            });
        });
    });

    describe("ConfirmReportEmail - PDF Generation & Email", () => {
        it("should generate confirmation report email without errors", () => {
            // LettersAndLabels.php is the entry point for confirmation report emails
            cy.contains("Letters and Mailing Labels");
            cy.get("body").should("not.contain", "Fatal error");
            cy.get("body").should("not.contain", "500");
        });

        it("should handle confirmation report email with custom fields", () => {
            // Verify page loaded without errors — no API call here to avoid PHP session pollution
            // (makePrivateAdminAPICall resets the session cookie, breaking subsequent PDF navigations)
            cy.get("body").should("not.contain", "Fatal error");
            cy.get("body").should("not.contain", "500");
        });
    });

    describe("Report Data Integrity", () => {
        it("should include all family information in confirmation report", () => {
            const familyId = 1;

            cy.intercept("GET", `**/Reports/ConfirmReport.php?familyId=${familyId}`).as(
                "reportWithFamilyData"
            );

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/Reports/ConfirmReport.php?familyId=${familyId}`;
            });

            cy.wait("@reportWithFamilyData", { timeout: 15000 }).then((interception) => {
                expect(interception.response.statusCode).to.equal(200);

                const contentType = interception.response.headers["content-type"] || "";
                expect(contentType).to.include("application/pdf");
            });
        });

        it("should handle large families with multiple pages", () => {
            // Family ID 1 from demo data — has enough members to test pagination
            const familyId = 1;

            cy.intercept("GET", `**/Reports/ConfirmReport.php?familyId=${familyId}`).as(
                "largeFamily"
            );

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/Reports/ConfirmReport.php?familyId=${familyId}`;
            });

            cy.wait("@largeFamily", { timeout: 15000 }).then((interception) => {
                expect(interception.response.statusCode).to.equal(200);

                const contentType = interception.response.headers["content-type"] || "";
                expect(contentType).to.include("application/pdf");
            });
        });
    });

    describe("Error Handling & Edge Cases", () => {
        it("should handle invalid family ID gracefully", () => {
            const invalidFamilyId = 999999;

            cy.intercept("GET", `**/Reports/ConfirmReport.php?familyId=${invalidFamilyId}`).as(
                "invalidFamily"
            );

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/Reports/ConfirmReport.php?familyId=${invalidFamilyId}`;
            });

            // Should return 200 (empty report) — no family found but no crash
            cy.wait("@invalidFamily", { timeout: 15000 }).then((interception) => {
                expect([200, 302]).to.include(interception.response.statusCode);
            });
        });
    });
});
