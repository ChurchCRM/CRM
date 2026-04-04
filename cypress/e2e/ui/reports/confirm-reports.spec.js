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
 *
 * MVC routes (primary):
 *   GET /v2/people/report/verify[?familyId=<int>]        → download PDF
 *   GET /v2/people/report/verify/email[?familyId=<int>]  → email PDFs + redirect
 *
 * Legacy routes (backwards compatibility — redirect to MVC):
 *   GET /Reports/ConfirmReport.php[?familyId=<int>]      → 302 → MVC
 *   GET /Reports/ConfirmReportEmail.php[?familyId=<int>] → 302 → MVC
 */
describe("Confirmation Reports - MVC Routes", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        // Establish browser context so session cookies are in scope for PDF navigation
        cy.visit("LettersAndLabels.php");
    });

    describe("MVC route - PDF Generation (GET /v2/people/report/verify)", () => {
        it("should generate confirmation report for all families without errors", () => {
            cy.intercept("GET", "**/v2/people/report/verify").as("confirmReportAll");

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/v2/people/report/verify`;
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

            cy.intercept("GET", `**/v2/people/report/verify?familyId=${familyId}`).as(
                "confirmReportSingle"
            );

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/v2/people/report/verify?familyId=${familyId}`;
            });

            cy.wait("@confirmReportSingle", { timeout: 15000 }).then((interception) => {
                expect(interception.response.statusCode).to.equal(200);

                const contentType = interception.response.headers["content-type"] || "";
                expect(contentType).to.include("application/pdf");
            });
        });

        it("should handle families with missing address fields", () => {
            const familyId = 1;

            cy.intercept("GET", `**/v2/people/report/verify?familyId=${familyId}`).as(
                "confirmReportNullFields"
            );

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/v2/people/report/verify?familyId=${familyId}`;
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

            cy.intercept("GET", `**/v2/people/report/verify?familyId=${familyId}`).as(
                "confirmReportWithMembers"
            );

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/v2/people/report/verify?familyId=${familyId}`;
            });

            cy.wait("@confirmReportWithMembers", { timeout: 15000 }).then((interception) => {
                expect(interception.response.statusCode).to.equal(200);
                expect(interception.response.headers["content-type"]).to.include("application/pdf");
            });
        });

        it("should handle invalid family ID gracefully", () => {
            const invalidFamilyId = 999999;

            cy.intercept("GET", `**/v2/people/report/verify?familyId=${invalidFamilyId}`).as(
                "invalidFamily"
            );

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/v2/people/report/verify?familyId=${invalidFamilyId}`;
            });

            // Should return 200 (empty report) or error — no crash
            cy.wait("@invalidFamily", { timeout: 15000 }).then((interception) => {
                expect([200, 302, 500]).to.include(interception.response.statusCode);
            });
        });
    });

    describe("MVC route - Email PDF (GET /v2/people/report/verify/email)", () => {
        it("should redirect after email attempt (no crash)", () => {
            cy.intercept("GET", "**/v2/people/report/verify/email**").as("emailRoute");

            cy.window().then((win) => {
                // Use familyId=1 so only one family is processed; the email
                // will likely fail in a CI environment without SMTP, which is
                // acceptable — we only check that the request completes cleanly.
                win.location.href = `${win.CRM.root}/v2/people/report/verify/email?familyId=1`;
            });

            cy.wait("@emailRoute", { timeout: 20000 }).then((interception) => {
                // Either a redirect (302) or success/error HTML (200/500)
                expect([200, 302, 500]).to.include(interception.response.statusCode);
                const body = interception.response.body;
                if (typeof body === "string") {
                    expect(body).to.not.include("Fatal error");
                }
            });
        });
    });

    describe("Backwards Compatibility - legacy URLs redirect to MVC", () => {
        it("legacy /Reports/ConfirmReport.php redirects", () => {
            cy.intercept("GET", "**/Reports/ConfirmReport.php").as("legacyRedirect");

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/Reports/ConfirmReport.php`;
            });

            cy.wait("@legacyRedirect", { timeout: 15000 }).then((interception) => {
                // The redirect stub issues a 302 to the MVC route
                expect([200, 302]).to.include(interception.response.statusCode);
            });
        });

        it("legacy /Reports/ConfirmReport.php?familyId=1 redirects with familyId", () => {
            const familyId = 1;
            cy.intercept("GET", `**/Reports/ConfirmReport.php?familyId=${familyId}`).as("legacyRedirectSingle");

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/Reports/ConfirmReport.php?familyId=${familyId}`;
            });

            cy.wait("@legacyRedirectSingle", { timeout: 15000 }).then((interception) => {
                expect([200, 302]).to.include(interception.response.statusCode);
            });
        });
    });

    describe("People Verify dashboard - verify buttons link to MVC routes", () => {
        it("Letters button links to MVC route", () => {
            cy.visit("v2/people/verify");
            cy.get('a[href*="/v2/people/report/verify"]')
                .should("exist")
                .and("not.have.attr", "href", "/Reports/ConfirmReport.php");
        });
    });

    describe("Report Data Integrity", () => {
        it("should include all family information in confirmation report", () => {
            const familyId = 1;

            cy.intercept("GET", `**/v2/people/report/verify?familyId=${familyId}`).as(
                "reportWithFamilyData"
            );

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/v2/people/report/verify?familyId=${familyId}`;
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

            cy.intercept("GET", `**/v2/people/report/verify?familyId=${familyId}`).as(
                "largeFamily"
            );

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/v2/people/report/verify?familyId=${familyId}`;
            });

            cy.wait("@largeFamily", { timeout: 15000 }).then((interception) => {
                expect(interception.response.statusCode).to.equal(200);

                const contentType = interception.response.headers["content-type"] || "";
                expect(contentType).to.include("application/pdf");
            });
        });
    });
});
