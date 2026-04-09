/// <reference types="cypress" />

describe("Main Dashboard", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Loads all", () => {
        cy.visit("v2/dashboard");
        cy.contains("Families");
        cy.contains("People");
        cy.contains("Sunday School");
    });
});

/**
 * Regression tests for PR #8562
 *
 * The /v2/dashboard used to unconditionally call /api/deposits/dashboard
 * whenever the #depositChartRow card was visible. That card is rendered
 * for every user, so non-finance users triggered the endpoint and received
 * an "Admin or Finance permission required" error popup.
 *
 * The fix switches the JS guard in src/skin/js/MainDashboard.js to check
 * for #deposit-lineGraph, which the PHP template (src/v2/templates/root/dashboard.php)
 * only injects for users who pass AuthenticationManager::getCurrentUser()->isFinanceEnabled().
 */
describe("Main Dashboard - Deposits widget authorization (PR #8562)", () => {
    describe("Finance-enabled user (admin)", () => {
        beforeEach(() => cy.setupAdminSession());

        it("renders the deposit line graph container and calls the deposits API", () => {
            cy.intercept("GET", "**/api/deposits/dashboard").as("depositsApi");

            cy.visit("v2/dashboard");

            // The finance-only container must exist for finance users.
            cy.get("#depositChartRow").should("exist");
            cy.get("#deposit-lineGraph").should("exist");

            // The JS guard should trigger the deposits API call.
            cy.wait("@depositsApi").its("response.statusCode").should("eq", 200);
        });
    });

    describe("User without finance permission", () => {
        beforeEach(() => cy.setupNoFinanceSession());

        it("does not render the deposit line graph container", () => {
            cy.visit("v2/dashboard");

            // The deposit card header is still rendered (it shows an empty
            // state), but the inner #deposit-lineGraph element is gated on
            // $depositEnabled = isFinanceEnabled() in dashboard.php.
            cy.get("#depositChartRow").should("exist");
            cy.get("#deposit-lineGraph").should("not.exist");
        });

        it("does not call /api/deposits/dashboard for a non-finance user", () => {
            // Register the intercept BEFORE cy.visit so every matching
            // request gets captured against the alias.
            cy.intercept("GET", "**/api/deposits/dashboard").as("depositsApi");

            cy.visit("v2/dashboard");

            // Wait for the dashboard to fully render before asserting the
            // API was NOT hit — widgets fire requests during page load.
            cy.contains("Families");
            cy.contains("People");
            cy.contains("Sunday School");

            // The bootbox error popup ("User must be an Admin or have Finance
            // permission.") used to appear here before PR #8562 — it must not.
            cy.get(".bootbox").should("not.exist");

            // And the deposits endpoint must never have been called.
            // This is the exact regression PR #8562 fixes.
            cy.get("@depositsApi.all").should("have.length", 0);
        });
    });
});
