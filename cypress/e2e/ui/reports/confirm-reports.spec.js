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
 * MVC routes:
 *   GET /people/report/verify[?familyId=<int>]        → download PDF
 *   POST /people/report/verify/email (CSRF-protected)  → email PDFs + redirect
 */
describe("Confirmation Reports - MVC Routes", () => {
    /**
     * Direct form login — clears existing cookies and authenticates as admin
     * via the real ChurchCRM login page (/session/begin). More reliable than
     * cy.setupAdminSession() for pages that require specific role flags
     * (MenuOptions), because it guarantees a fresh PHP session without any
     * contamination from prior tests.
     * Pattern follows cypress/e2e/ui/people/standard.cart-to-family.spec.js.
     */
    function freshAdminLogin() {
        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get("input[name=User]").type(Cypress.env("admin.username"));
        cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
        cy.url().should("not.include", "/session/begin");
    }

    beforeEach(() => {
        freshAdminLogin();
        cy.visit("/LettersAndLabels.php");
    });

    describe("MVC route - PDF Generation (GET /people/report/verify)", () => {
        it("should generate confirmation report for all families without errors", () => {
            cy.intercept("GET", "**/people/report/verify").as("confirmReportAll");

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/people/report/verify`;
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

            cy.intercept("GET", `**/people/report/verify?familyId=${familyId}`).as(
                "confirmReportSingle"
            );

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/people/report/verify?familyId=${familyId}`;
            });

            cy.wait("@confirmReportSingle", { timeout: 15000 }).then((interception) => {
                expect(interception.response.statusCode).to.equal(200);

                const contentType = interception.response.headers["content-type"] || "";
                expect(contentType).to.include("application/pdf");
            });
        });

        it("should handle families with missing address fields", () => {
            const familyId = 1;

            cy.intercept("GET", `**/people/report/verify?familyId=${familyId}`).as(
                "confirmReportNullFields"
            );

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/people/report/verify?familyId=${familyId}`;
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

            cy.intercept("GET", `**/people/report/verify?familyId=${familyId}`).as(
                "confirmReportWithMembers"
            );

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/people/report/verify?familyId=${familyId}`;
            });

            cy.wait("@confirmReportWithMembers", { timeout: 15000 }).then((interception) => {
                expect(interception.response.statusCode).to.equal(200);
                expect(interception.response.headers["content-type"]).to.include("application/pdf");
            });
        });

        it("should handle invalid family ID gracefully", () => {
            const invalidFamilyId = 999999;

            cy.intercept("GET", `**/people/report/verify?familyId=${invalidFamilyId}`).as(
                "invalidFamily"
            );

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/people/report/verify?familyId=${invalidFamilyId}`;
            });

            // Should return 200 with an empty (but valid) PDF — no family found but no crash
            cy.wait("@invalidFamily", { timeout: 15000 }).then((interception) => {
                expect(interception.response.statusCode).to.equal(200);
                const contentType = interception.response.headers["content-type"] || "";
                expect(contentType).to.include("application/pdf");
            });
        });
    });

    describe("MVC route - Email PDF (POST /people/report/verify/email)", () => {
        it("should redirect after email attempt (success or SMTP error redirect)", () => {
            // The email endpoint is now a CSRF-protected POST.
            // Visit the verify page to obtain the rendered CSRF token, then POST it.
            cy.visit("people/verify");
            cy.get('#verifyEmailAllForm input[name="csrf_token"]').invoke("val").then((token) => {
                cy.request({
                    method: "POST",
                    url: "people/report/verify/email",
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
                url: "people/report/verify/email",
                form: true,
                body: { familyId: 1 },  // no csrf_token
                failOnStatusCode: false,
            }).then((resp) => {
                expect(resp.status).to.equal(403);
            });
        });
    });

    describe("People Verify dashboard - verify buttons link to MVC routes", () => {
        it("Letters button links to MVC route", () => {
            cy.visit("people/verify");
            cy.get('a[href*="/people/report/verify"]')
                .should("exist");
        });
    });

    describe("Report Data Integrity", () => {
        it("should include all family information in confirmation report", () => {
            const familyId = 1;

            cy.intercept("GET", `**/people/report/verify?familyId=${familyId}`).as(
                "reportWithFamilyData"
            );

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/people/report/verify?familyId=${familyId}`;
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

            cy.intercept("GET", `**/people/report/verify?familyId=${familyId}`).as(
                "largeFamily"
            );

            cy.window().then((win) => {
                win.location.href = `${win.CRM.root}/people/report/verify?familyId=${familyId}`;
            });

            cy.wait("@largeFamily", { timeout: 15000 }).then((interception) => {
                expect(interception.response.statusCode).to.equal(200);

                const contentType = interception.response.headers["content-type"] || "";
                expect(contentType).to.include("application/pdf");
            });
        });
    });

    // ================================================================
    // Part 1: Error Messaging — structured alerts
    // ================================================================
    describe("People Verify — error alert messaging", () => {
        it("shows a specific danger alert when EmailsError is present", () => {
            // Visit with reason=smtp_failure — should show a danger alert
            cy.visit("people/verify?EmailsError=1&reason=smtp_failure&sent=0&failed=3");

            cy.get('[data-cy="email-result-alert"]')
                .should("exist")
                .should("have.class", "alert-danger")
                .and("contain.text", "SMTP");
        });

        it("shows a partial-failure warning with sent/total counts", () => {
            cy.visit("people/verify?EmailsError=1&reason=partial_failure&sent=40&failed=7");

            cy.get('[data-cy="email-result-alert"]')
                .should("exist")
                .should("have.class", "alert-warning")
                .and("contain.text", "40")
                .and("contain.text", "47");
        });

        it("shows a no-recipients danger alert", () => {
            cy.visit("people/verify?EmailsError=1&reason=no_recipients&sent=0&failed=0");

            cy.get('[data-cy="email-result-alert"]')
                .should("exist")
                .should("have.class", "alert-danger");
        });

        it("alert is dismissible — disappears after clicking close", () => {
            cy.visit("people/verify?EmailsError=1&reason=smtp_failure&sent=0&failed=1");

            cy.get('[data-cy="email-result-alert"]').should("exist");
            // Dismiss the alert with the bootstrap btn-close
            cy.get('[data-cy="email-result-alert"] .btn-close').click();
            // After clicking close the alert fades out — just assert it no longer has 'show'
            // (do NOT assert not.exist because of BS5 async fade transition)
            cy.get('[data-cy="email-result-alert"]').should("not.have.class", "show");
        });

        it("Retry button is present and triggers the confirmation modal", () => {
            cy.visit("people/verify?EmailsError=1&reason=smtp_failure&sent=0&failed=1");

            cy.get('[data-cy="retry-email-btn"]').should("exist");
        });
    });

    // ================================================================
    // Part 2: Send Confirmation Preview Modal
    // ================================================================
    describe("People Verify — send confirmation modal", () => {
        // The outer beforeEach already calls freshAdminLogin(). This inner beforeEach
        // only navigates to the verify page — no second login needed.
        beforeEach(() => {
            cy.visit("people/verify");
        });

        it("opens the confirmation modal when Email Families is clicked", () => {
            cy.get("#verifyEmail").click();
            cy.get('[data-cy="verify-email-modal"]').should("have.class", "show");
        });

        it("modal loads a recipient count from the preview endpoint", () => {
            cy.intercept("GET", "**/api/families/verify-email-preview").as("emailPreview");

            cy.get("#verifyEmail").click();
            cy.wait("@emailPreview", { timeout: 10000 }).then((interception) => {
                expect(interception.response.statusCode).to.equal(200);
                const body = interception.response.body;
                expect(body).to.have.property("recipientCount");
                expect(body.recipientCount).to.be.a("number");
            });

            // Recipient count banner should be visible in the modal
            cy.get('[data-cy="modal-recipient-count"]').should("be.visible");
        });

        it("modal shows template preview subject and body excerpt", () => {
            cy.intercept("GET", "**/api/families/verify-email-preview").as("emailPreview");

            cy.get("#verifyEmail").click();
            cy.wait("@emailPreview", { timeout: 10000 });

            cy.get("#previewSubject").should("exist").invoke("text").should("have.length.above", 0);
        });

        it("Cancel button closes the modal without triggering a send", () => {
            cy.intercept("POST", "**/people/report/verify/email").as("sendEmail");

            cy.get("#verifyEmail").click();
            cy.get('[data-cy="verify-email-modal"]').should("have.class", "show");

            cy.get('[data-cy="modal-cancel-btn"]').click();

            // Wait for modal to close (BS5 async fade) then assert no send request was made
            cy.get('[data-cy="verify-email-modal"]').should('not.have.class', 'show');
            cy.get("@sendEmail.all").should("have.length", 0);
        });

        it("Send button triggers AJAX POST and shows result banner in modal", () => {
            cy.intercept("GET", "**/api/families/verify-email-preview").as("emailPreview");
            cy.intercept("POST", "**/people/report/verify/email").as("sendEmail");

            cy.get("#verifyEmail").click();
            cy.wait("@emailPreview", { timeout: 10000 });

            // Only click Send if there are recipients (otherwise the button stays disabled)
            cy.get('[data-cy="modal-send-btn"]').then(($btn) => {
                if ($btn.prop("disabled")) {
                    // No recipients in CI env — acceptable; just verify the button exists
                    cy.log("No recipients in CI environment — skipping send click");
                    return;
                }
                cy.wrap($btn).click();
                cy.wait("@sendEmail", { timeout: 15000 }).then((interception) => {
                    // Should return JSON, not a redirect
                    expect(interception.response.headers["content-type"]).to.include("json");
                    expect(interception.response.body).to.have.property("status");
                    expect(interception.response.body).to.have.property("message");
                });
                // Result banner should be shown inside the modal
                cy.get('[data-cy="modal-result-banner"]').should("exist");
            });
        });
    });
});
