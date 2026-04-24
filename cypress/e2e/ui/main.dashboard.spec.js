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
 * Regression tests for the deposits widget authorization fix on
 * /v2/dashboard.
 *
 * The bug: non-finance users visiting /v2/dashboard received an "Admin or
 * Finance permission required" bootbox popup because the JS unconditionally
 * called /api/deposits/dashboard whenever #depositChartRow was visible —
 * which it always was, even when the card only showed a "no permissions"
 * empty state.
 *
 * The fix consists of two coordinated changes:
 *
 *   1. src/skin/js/MainDashboard.js — guard the deposits API call on
 *      #deposit-lineGraph presence instead of #depositChartRow visibility.
 *      This is defense-in-depth at the JS layer so a stale finance UI
 *      can never leak a finance API call.
 *
 *   2. src/v2/templates/root/dashboard.php — only render the deposit
 *      tracking row at all when $depositEnabled = isFinanceEnabled() is
 *      true. This removes the noisy "No Deposit Tracking / You do not
 *      have finance permissions" empty state from every non-finance
 *      user's home screen, and is the root-cause fix at the PHP layer.
 *
 * These tests enforce the full contract: a non-finance user must NOT
 * see any finance UI, must NOT trigger any finance API call, and must
 * NOT receive any permission-error popup. A finance user must still
 * see the deposit chart and trigger the backing API call.
 */
describe("Main Dashboard - Deposits widget authorization", () => {
    describe("Finance-enabled user (admin)", () => {
        beforeEach(() => cy.setupAdminSession());

        it("renders the deposit chart and calls the deposits API", () => {
            cy.intercept("GET", "**/api/deposits/dashboard").as("depositsApi");

            cy.visit("v2/dashboard");

            // The finance-only row must exist for finance users.
            cy.get("#depositChartRow").should("exist");
            cy.get("#deposit-lineGraph").should("exist");
            cy.contains("Deposit Tracking").should("be.visible");

            // The JS guard should trigger the deposits API call.
            cy.wait("@depositsApi").its("response.statusCode").should("eq", 200);
        });
    });

    describe("User without finance permission", () => {
        beforeEach(() => cy.setupNoFinanceSession());

        it("hides the entire deposit tracking card from non-finance users", () => {
            cy.visit("v2/dashboard");

            // Wait for the dashboard to fully render before asserting
            // absence — page content loads incrementally.
            cy.contains("Families");
            cy.contains("People");

            // Root-cause fix (PHP): the entire deposit tracking row
            // must be absent from the DOM, not just hidden.
            cy.get("#depositChartRow").should("not.exist");
            cy.get("#deposit-lineGraph").should("not.exist");

            // No noisy empty-state text advertising a feature the user
            // cannot access.
            cy.contains("Deposit Tracking").should("not.exist");
            cy.contains("No Deposit Tracking").should("not.exist");
            cy.contains(
                "You do not have finance permissions to view deposits.",
            ).should("not.exist");
        });

        it("does not call /api/deposits/dashboard and shows no permission popup", () => {
            // Register the intercept BEFORE cy.visit so every matching
            // request gets captured against the alias.
            cy.intercept("GET", "**/api/deposits/dashboard").as("depositsApi");

            cy.visit("v2/dashboard");

            // Wait for the dashboard to fully render before asserting the
            // API was NOT hit — widgets fire requests during page load.
            cy.contains("Families");
            cy.contains("People");
            cy.contains("Sunday School");

            // The bootbox error popup ("User must be an Admin or have
            // Finance permission.") used to appear here — it must not.
            cy.get(".bootbox").should("not.exist");

            // And the deposits endpoint must never have been called.
            // This is the exact regression being fixed.
            cy.get("@depositsApi.all").should("have.length", 0);
        });
    });
});

describe("Report Issue", () => {
    beforeEach(() => cy.setupStandardSession());

    it("opens modal, enters description, and submits to GitHub", () => {
        const description = "Cypress test: menu disappears after clicking the nav toggle on mobile";

        cy.intercept("POST", "**/api/issues", {
            statusCode: 200,
            body: { issueBody: "## System Info\nVersion: test\n" },
        }).as("postIssue");

        cy.visit("v2/dashboard");

        cy.window().then((win) => {
            cy.stub(win, "open").as("windowOpen");
        });

        cy.get("#supportMenu").click();
        cy.get("#reportIssue").click();

        cy.get("#IssueReportModal").should("be.visible");
        cy.get("#IssueReportModal .modal-title").should("contain.text", "Report an Issue");

        cy.get("#issueDescription").type(description);

        cy.get("#submitIssue").click();
        cy.wait("@postIssue");

        cy.get("@windowOpen").should("have.been.calledOnce");
        cy.get("@windowOpen").should(
            "have.been.calledWithMatch",
            /^https:\/\/github\.com\/ChurchCRM\/CRM\/issues\/new/
        );
        cy.get("@windowOpen").its("firstCall.args.0").should("include", encodeURIComponent(description));
    });
});
