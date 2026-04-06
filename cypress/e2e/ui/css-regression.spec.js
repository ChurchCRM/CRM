/// <reference types="cypress" />

/**
 * CSS Regression: Global Styles
 *
 * Verifies that auth-page CSS does NOT leak into the main app.
 * After the global→scoped CSS migration, buttons and alerts on
 * authenticated pages must use standard Tabler/BS5 styling,
 * not the auth-page gradient overrides.
 */
describe("CSS Regression — No Auth Style Leakage", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("Dashboard — buttons use Tabler defaults", () => {
        beforeEach(() => {
            cy.visit("/v2/dashboard");
        });

        it("Should NOT have page-auth body class", () => {
            cy.get("body").should("not.have.class", "page-auth");
        });

        it("Visible buttons should not have auth-page gradient background", () => {
            // FAB buttons are always visible on the dashboard
            cy.get(".fab-container .fab-button").first().then(($btn) => {
                cy.window().then((win) => {
                    const bg = win.getComputedStyle($btn[0]).backgroundImage;
                    // Auth pages use #667eea gradient — dashboard buttons should not
                    expect(bg).to.not.include("667eea");
                });
            });
        });
    });

    describe("Financial Reports — buttons render without auth styles", () => {
        beforeEach(() => {
            cy.visit("/FinancialReports.php");
        });

        it("Should load the financial reports page", () => {
            cy.contains("Financial Reports").should("be.visible");
        });

        it("Submit button should not have auth-page gradient", () => {
            cy.get("#FinancialReportTypes").select("Giving Report");
            cy.get("#FinancialReports").submit();
            cy.contains("Financial Reports: Giving Report").should("be.visible");

            cy.get("#createReport").should("be.visible").then(($btn) => {
                cy.window().then((win) => {
                    const bg = win.getComputedStyle($btn[0]).backgroundImage;
                    expect(bg).to.not.include("667eea");
                });
            });
        });

        it("Report form labels should be properly styled", () => {
            cy.get("#FinancialReportTypes").select("Giving Report");
            cy.get("#FinancialReports").submit();

            // Form labels should exist and be visible on the Giving Report filter page
            cy.get("label.form-label").should("have.length.at.least", 1)
                .first().should("be.visible");
        });
    });
});
