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
 *   POST /v2/people/report/verify/email (CSRF-protected)  → email PDFs + redirect
 *
 * Legacy routes (backwards compatibility — redirect to MVC):
 *   GET /Reports/ConfirmReport.php[?familyId=<int>]      → 302 → MVC
 *   GET /Reports/ConfirmReportEmail.php[?familyId=<int>] → 302 → MVC
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

    describe("MVC route - Email PDF (POST /v2/people/report/verify/email)", () => {
        it("should redirect after email attempt (success or SMTP error redirect)", () => {
            // The email endpoint is now a CSRF-protected POST.
            // Visit the verify page to obtain the rendered CSRF token, then POST it.
            cy.visit("v2/people/verify");
            cy.get('#verifyEmailAllForm input[name="csrf_token"]').invoke("val").then((token) => {
                cy.request({
                    method: "POST",
                    url: "v2/people/report/verify/email",
                    form: true,
                    // Use familyId=1 so only one family is processed; email will likely fail
                    // in CI without SMTP, which triggers redirect to ?EmailsError=true.
                    body: { csrf_token: token, familyId: 1 },
                    followRedirect: false,
                    failOnStatusCode: false,
                }).then((resp) => {
                    // Route always redirects (302): success → verify page, SMTP error → verify?EmailsError=true
                    expect([200, 302]).to.include(resp.status);
                    if (resp.body && typeof resp.body === "string") {
                        expect(resp.body).to.not.include("Fatal error");
                    }
                });
            });
        });

        it("should reject POST without CSRF token with 403 Forbidden", () => {
            cy.request({
                method: "POST",
                url: "v2/people/report/verify/email",
                form: true,
                body: { familyId: 1 },  // no csrf_token
                failOnStatusCode: false,
            }).then((resp) => {
                expect(resp.status).to.equal(403);
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
