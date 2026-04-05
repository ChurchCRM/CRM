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
            // Target a visible button on the dashboard — FAB or navbar buttons
            // Use :visible filter to avoid hidden modal buttons
            cy.get(".btn:visible").first()
                .should("have.css", "background-image")
                .and("not.include", "667eea");
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

            cy.get("#createReport").should("be.visible")
                .should("have.css", "background-image")
                .and("not.include", "667eea");
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
        it("Dashboard alerts should not have auth-page color overrides", () => {
            cy.visit("/v2/dashboard");
            // If alerts exist, verify they don't use auth-page override colors
            cy.get("body").then(($body) => {
                if ($body.find(".alert:visible").length > 0) {
                    // Auth-page overrides .alert-danger to #fee (rgb(255, 238, 238))
                    // Tabler defaults differ — verify no auth leakage
                    cy.get(".alert:visible").first()
                        .should("have.css", "background-color")
                        .and("not.equal", "rgb(255, 238, 238)");
                }
            });
        });
    });
});
