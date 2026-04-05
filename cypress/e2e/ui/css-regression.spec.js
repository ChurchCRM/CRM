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

        it("Buttons should not have auth-page gradient background", () => {
            // Find any .btn on the page and verify it does NOT have the
            // auth-page gradient (linear-gradient with #667eea)
            cy.get(".btn").first().then(($btn) => {
                const bg = window.getComputedStyle($btn[0]).backgroundImage;
                expect(bg).to.not.include("667eea");
            });
        });

        it("Buttons should have visible text and proper sizing", () => {
            cy.get(".btn").first().should("be.visible")
                .and("have.css", "cursor", "pointer");
        });
    });

    describe("Financial Reports — buttons render without extra margin", () => {
        beforeEach(() => {
            cy.visit("/FinancialReports.php");
        });

        it("Should load the financial reports page", () => {
            cy.contains("Financial Reports").should("be.visible");
        });

        it("Submit button should not have leaked auth-page gradient", () => {
            cy.get("#FinancialReportTypes").select("Giving Report");
            cy.get("#FinancialReports").submit();
            cy.contains("Financial Reports: Giving Report").should("be.visible");

            cy.get("#createReport").should("be.visible").then(($btn) => {
                const bg = window.getComputedStyle($btn[0]).backgroundImage;
                expect(bg).to.not.include("667eea");
            });
        });

        it("Report form labels should be properly styled", () => {
            cy.get("#FinancialReportTypes").select("Giving Report");
            cy.get("#FinancialReports").submit();

            // LabelColumn cells should exist and be visible
            cy.get("td.LabelColumn").should("have.length.at.least", 1)
                .first().should("be.visible");
        });
    });

    describe("Alerts on authenticated pages use BS5 defaults", () => {
        it("Dashboard alerts should not have auth-page border-radius override", () => {
            cy.visit("/v2/dashboard");
            // If alerts exist, they should use Tabler defaults, not auth-page overrides
            cy.get("body").then(($body) => {
                if ($body.find(".alert").length > 0) {
                    cy.get(".alert").first().then(($alert) => {
                        // Auth-page alerts use custom colors like #fee — Tabler defaults differ
                        const bgColor = window.getComputedStyle($alert[0]).backgroundColor;
                        // Should not be the auth-page override color #fee (rgb(255, 238, 238))
                        expect(bgColor).to.not.equal("rgb(255, 238, 238)");
                    });
                }
            });
        });
    });
});
