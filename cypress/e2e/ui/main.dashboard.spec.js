/// <reference types="cypress" />

describe("Main Dashboard", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Loads all", () => {
        cy.visit("people/dashboard");
        cy.contains("Families");
        cy.contains("People");
        cy.contains("Sunday School");
    });
});

/**
 * Regression tests for the deposits widget authorization fix on
 * /people/dashboard.
 *
 * The bug: non-finance users visiting /people/dashboard received an "Admin or
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

        it("renders the deposit chart on the finance dashboard, not on the home dashboard", () => {
            // Deposit #1 is the only fixture deposit with pledges attached, so
            // it's the only one with real chart data (the others sum to
            // null). The fixture ships a static date that inevitably ages
            // past the API's 90-day window, starving the chart of data —
            // refreshing it to today here keeps the test stable regardless
            // of when it runs.
            cy.request({
                method: "POST",
                url: "/api/deposits/1",
                body: {
                    depositType: "Bank",
                    depositComment: "Updated Test Deposit",
                    depositDate: new Date().toISOString().slice(0, 10),
                    depositClosed: false,
                },
            });

            cy.intercept("GET", "**/api/deposits/dashboard*").as("depositsApi");

            cy.visit("people/dashboard");
            cy.contains("Families");
            cy.get("#depositChartRow").should("not.exist");

            cy.visit("finance/?days=90");

            // The finance-only row must exist for finance users.
            cy.get("#depositChartRow").should("exist");
            cy.get("#deposit-lineGraph").should("exist");
            cy.contains("Deposit Tracking").should("be.visible");

            // The JS guard should trigger the deposits API call.
            cy.wait("@depositsApi").its("response.statusCode").should("eq", 200);
            cy.get("@depositsApi").its("request.url").should("match", /from=\d{4}-\d{2}-\d{2}/);

            // ApexCharts must actually render into the container, not just
            // leave an empty div — catches chart-library regressions
            // (e.g. major version bumps) that a container-existence check
            // alone would miss.
            cy.get("#deposit-lineGraph .apexcharts-canvas").should("exist");
            cy.get("#deposit-lineGraph svg.apexcharts-svg").should("exist");
        });

        it("keeps a range chosen in the address as the default from then on", () => {
            cy.visit("finance/?days=90");
            cy.visit("finance/");
            cy.get("#finance-range").should("have.value", "days:90");
        });

        it("applies one saved Time Range to the whole finance dashboard", () => {
            cy.intercept("POST", "**/setting/ui.dashboard.finance.range").as("saveRange");
            cy.visit("finance/");
            cy.get("#finance-range").select("days:30");
            cy.wait("@saveRange").its("response.statusCode").should("eq", 200);
            cy.location("search").should("eq", "?days=30");
            cy.get("#payments-by-fund").should("contain", "Last 30 days");
            cy.get("#depositChartRow").should("contain", "Last 30 days");

            cy.visit("finance/");
            cy.get("#finance-range").should("have.value", "days:30");

            cy.get('#finance-range option:contains("(Current)")').then(($option) => {
                cy.get("#finance-range").select($option.val());
            });
            cy.wait("@saveRange");
            cy.get("#finance-range").should("not.have.value", "days:30");
        });
    });

    describe("User without finance permission", () => {
        beforeEach(() => cy.setupNoFinanceSession());

        it("hides the entire deposit tracking card from non-finance users", () => {
            cy.visit("people/dashboard");

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
            cy.intercept("GET", "**/api/deposits/dashboard*").as("depositsApi");

            cy.visit("people/dashboard");

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

        cy.visit("people/dashboard");

        cy.window().then((win) => {
            cy.stub(win, "open").as("windowOpen");
        });

        cy.get("#supportMenu").click();
        cy.get("#reportIssue").click();

        cy.get("#IssueReportModal").should("be.visible");
        cy.get("#IssueReportModal .modal-title").should("contain.text", "Report an Issue");

        cy.get("#issueDescription").type(description);

        cy.get("#submitIssue").click();
        cy.wait("@postIssue").its("request.body").then((body) => {
            expect(body).to.have.property("browserLocale");
            expect(body).to.have.property("browserTimezone");
        });

        cy.get("@windowOpen").should("have.been.calledOnce");
        cy.get("@windowOpen").should(
            "have.been.calledWithMatch",
            /^https:\/\/github\.com\/ChurchCRM\/CRM\/issues\/new/
        );
        cy.get("@windowOpen").its("firstCall.args.0").should("include", "labels=in-app-report");
        cy.get("@windowOpen").its("firstCall.args.0").should("include", encodeURIComponent(description));
    });

    it("does not show the admin log-review hint to standard users", () => {
        cy.visit("people/dashboard");
        cy.get("#supportMenu").click();
        cy.get("#reportIssue").click();

        cy.get("#IssueReportModal").should("be.visible");
        cy.get("#IssueReportModal").contains("system logs").should("not.exist");
    });
});

describe("Report Issue (Admin)", () => {
    beforeEach(() => cy.setupAdminSession());

    it("shows a hint to review and attach system logs, linking to the log viewer", () => {
        cy.visit("people/dashboard");
        cy.get("#supportMenu").click();
        cy.get("#reportIssue").click();

        cy.get("#IssueReportModal").should("be.visible");
        cy.get("#IssueReportModal")
            .contains("a", "system logs")
            .should("have.attr", "href")
            .and("include", "/admin/system/logs");
    });
});

describe("Main Dashboard - People and Finance switch", () => {
    it("lets a finance user move between the two dashboards", () => {
        cy.setupAdminSession();
        cy.visit("people/dashboard");
        cy.get("#dashboardSwitch .nav-link.active").should("contain", "People");
        cy.get("#dashboardSwitch").contains("a", "Finance").click();
        cy.location("pathname").should("match", /\/finance\/?$/);
        cy.get("#dashboardSwitch .nav-link.active").should("contain", "Finance");
    });

    it("hides the Finance tab from a user without finance permission", () => {
        cy.setupNoFinanceSession();
        cy.visit("people/dashboard");
        cy.contains("Families");
        cy.get("#dashboardSwitch").should("contain", "Events").and("not.contain", "Finance").and("not.contain", "Deposits");
    });
});

describe("Main Dashboard - Today's Events card", () => {
    beforeEach(() => cy.setupAdminSession());

    it("offers no check-in and nothing to expand when there are no events today", () => {
        cy.intercept("GET", "**/api/events/today*", { body: { events: [] } }).as("today");
        cy.visit("people/dashboard");
        cy.wait("@today");
        cy.get("#todayEventsCount").should("contain", "No Events Today");
        cy.get("#todayEventsCheckin").should("not.be.visible");
        cy.get("#todayEventsToggle").should("not.be.visible");
    });

    it("expands to the event list and checks in to a single event directly", () => {
        const events = [{ id: 7, title: "Sunday Service", typeName: "Church Service", start: new Date().toISOString(), checkedIn: 0, totalAttendees: 0 }];
        cy.intercept("GET", "**/api/events/today*", { body: { events } }).as("today");
        cy.visit("people/dashboard");
        cy.wait("@today");
        cy.get("#todayEventsBody").should("not.be.visible");
        cy.get("#todayEventsCheckin").should("be.visible").and("have.attr", "href").and("match", /\/event\/checkin\/7$/);
        cy.get("#todayEventsToggle").click();
        cy.get("#todayEventsBody").should("be.visible").and("contain", "Sunday Service");
    });
});
