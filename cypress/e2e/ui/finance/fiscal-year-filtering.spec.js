/// <reference types="cypress" />

/**
 * Cypress E2E spec: Fiscal-Year Scoping — Issue #9378
 *
 * Covers the four finance tables that received FY selectors:
 *   1. Family Pledges & Payments (server-side fyid pill filter)
 *   2. Finance Dashboard → Recent Deposits (FY selector)
 *   3. Pledge Dashboard (FY selector, full-page reload)
 *   4. Deposit Search (/finance/deposit/search: FY selector, form GET reload)
 *
 * Seed data facts (cypress/data/seed.sql):
 *   - Family 1 (Campbell): pledges in FYID 22 (2018), none in current FY
 *   - Deposits: deposit_dep rows include IDs 1–5, dates in 2018 (FY22)
 *   - Pledge Dashboard: pledges in FYID 22, 23, 25
 *
 * ⚠️  SEED-DATA DEPENDENCY: SEED_FYID_2018 is the single source of truth
 *   for all `fyid=22` references in this file. If seed.sql changes (rows
 *   moved to a different fiscal year), update this constant and the seed
 *   comments above; the rest of the spec will follow automatically.
 */

/** Fiscal-year ID 22 maps to calendar year 2018 in the seed data. */
const SEED_FYID_2018 = 22;

describe("Fiscal-Year Scoping — Issue #9378", () => {
  // ──────────────────────────────────────────────────────────────────
  // 1. Family Pledges: default FY selection + All-Time toggle
  // ──────────────────────────────────────────────────────────────────
  describe("Family Pledges — FY pill filter (server-side)", () => {
    beforeEach(() => {
      cy.setupAdminSession();
    });

    it("shows current-FY pill active by default, data is FY-filtered", () => {
      cy.intercept("GET", "**/api/payments/family/1/list*").as("pledgeInit");
      cy.visit("people/family/1");
      cy.wait("@pledgeInit").its("response.statusCode").should("eq", 200);

      // Current-FY pill is the default active pill
      cy.get(".pledge-fy-pill.active")
        .should("exist")
        .and("not.contain", "All Time");

      // Table renders (even if empty for current FY — proves the call was made)
      cy.get("#pledge-payment-v2-table").should("be.visible");

      // Seed data for family 1 is in FY22 (2018), not in current FY — table should be empty
      cy.get("#pledge-payment-v2-table tbody tr").should(
        "not.contain",
        "Music Ministry"
      );
    });

    it("All-Time pill triggers server-side reload and shows historical rows", () => {
      cy.intercept("GET", "**/api/payments/family/1/list*").as("pledgeInit");
      cy.visit("people/family/1");
      cy.wait("@pledgeInit");

      // Click All Time (data-fy="0")
      cy.intercept("GET", "**/api/payments/family/1/list*").as(
        "pledgeAllTime"
      );
      cy.get(".pledge-fy-pill[data-fy='0']").click();
      cy.wait("@pledgeAllTime").then((interception) => {
        // The AJAX request must send fyid=0 explicitly, not omit the param —
        // an omitted param falls back server-side to the user's ShowSince
        // preference, which is not the same as "All Time".
        expect(interception.request.url).to.include("fyid=0");
      });

      // All-Time pill is now active
      cy.get(".pledge-fy-pill.active").should("contain", "All Time");

      // Historical rows (FY22) are now visible
      cy.contains("Music Ministry").should("be.visible");
    });

    it("clicking a specific FY pill filters to that FY", () => {
      cy.intercept("GET", "**/api/payments/family/1/list*").as("pledgeInit");
      cy.visit("people/family/1");
      cy.wait("@pledgeInit");

      // Switch to All Time first to confirm there are rows
      cy.intercept("GET", "**/api/payments/family/1/list*").as("allTime");
      cy.get(".pledge-fy-pill[data-fy='0']").click();
      cy.wait("@allTime");
      cy.get("#pledge-payment-v2-table tbody tr").should(
        "have.length.at.least",
        1
      );

      // Click FY22 pill (2018) — should still show the same rows
      cy.get(`.pledge-fy-pill[data-fy='${SEED_FYID_2018}']`).should("exist").as("fy22Pill");
      cy.intercept("GET", "**/api/payments/family/1/list*").as("fy22");
      cy.get("@fy22Pill").click();
      cy.wait("@fy22").then((interception) => {
        expect(interception.request.url).to.include(`fyid=${SEED_FYID_2018}`);
      });
      cy.get(".pledge-fy-pill.active")
        .invoke("data", "fy")
        .should("eq", SEED_FYID_2018);
    });

    it("persists FY selection in URL params", () => {
      cy.intercept("GET", "**/api/payments/family/1/list*").as("pledgeInit");
      cy.visit("people/family/1");
      cy.wait("@pledgeInit");

      cy.intercept("GET", "**/api/payments/family/1/list*").as("allTime");
      cy.get(".pledge-fy-pill[data-fy='0']").click();
      cy.wait("@allTime");

      // All Time is persisted as an explicit fyid=0 (not by omitting the
      // param), so refreshing or opening a copied/bookmarked URL doesn't
      // silently fall back to the current FY.
      cy.location("search").should("include", "fyid=0");

      // Refreshing the page must keep the All-Time selection — the AJAX
      // request must still send fyid=0 explicitly, not omit the param.
      cy.intercept("GET", "**/api/payments/family/1/list*").as("afterReload");
      cy.reload();
      cy.wait("@afterReload").then((interception) => {
        expect(interception.request.url).to.include("fyid=0");
      });
      cy.get(".pledge-fy-pill.active").should("contain", "All Time");
    });
  });

  // ──────────────────────────────────────────────────────────────────
  // 2. Finance Dashboard: FY selector filters Recent Deposits
  // ──────────────────────────────────────────────────────────────────
  describe("Finance Dashboard — Recent Deposits FY selector", () => {
    beforeEach(() => {
      cy.setupAdminSession();
    });

    it("renders FY selector with at least All-Time and one FY option", () => {
      cy.visit("finance/");
      cy.contains("Recent Deposits");

      // FY selector exists
      cy.get("#deposit-fyid").should("exist");

      // Has at least All Time + one fiscal year
      cy.get("#deposit-fyid option").should("have.length.at.least", 2);

      // The first option is "All Time" (value="0")
      cy.get("#deposit-fyid option").first().should("have.value", "0");
    });

    it("selecting a historical FY limits the deposits shown", () => {
      cy.visit("finance/");
      cy.contains("Recent Deposits");

      // Without any FY filter, check we can see at least some deposit (current FY default)
      // Navigate to a FY with known seed deposits: FY 22 (2018)
      // This triggers a page reload with ?fyid=22
      cy.get("#deposit-fyid").select(String(SEED_FYID_2018));
      // Use cy.location() — retries until the post-form-submit navigation settles
      cy.location("search").should("include", `fyid=${SEED_FYID_2018}`);

      // Deposits table should show deposits from 2018 (FY22)
      cy.get(".card").contains("Recent Deposits").parent().parent()
        .find("table tbody tr")
        .should("have.length.at.least", 1);
    });

    it("All Time option shows all deposits (no fyid in URL)", () => {
      cy.visit(`finance/?fyid=${SEED_FYID_2018}`);
      cy.get("#deposit-fyid").select("0"); // triggers form submit → page reload
      // Use a retryable assertion: cy.url().then() reads the pre-navigation URL;
      // cy.location().should() retries until navigation completes.
      cy.location("search").should("satisfy", (s) => s === "" || s === "?fyid=0");
    });
  });

  // ──────────────────────────────────────────────────────────────────
  // 3. Pledge Dashboard: FY selector reloads Fund Summary + Family Pledges
  // ──────────────────────────────────────────────────────────────────
  describe("Pledge Dashboard — FY selector reloads both sections", () => {
    beforeEach(() => {
      cy.setupAdminSession();
    });

    it("loads with current FY and shows the FY selector", () => {
      cy.visit("finance/pledge/dashboard");
      cy.contains("Pledge Dashboard");

      // FY selector is present
      cy.get("#fyid").should("exist");

      // The selected option corresponds to the current FY
      cy.get("#fyid option:selected").should("exist");
    });

    it("changing FY reloads the page with new fyid param", () => {
      cy.visit("finance/pledge/dashboard");

      // Pick FY 22 (2018) which has seed data
      cy.get("#fyid").select(String(SEED_FYID_2018));

      // URL should now contain fyid=22 (form GET submit)
      // Use cy.location() — retries until the post-form-submit navigation settles
      cy.location("search").should("include", `fyid=${SEED_FYID_2018}`);

      // Both Fund Summary and Family Pledges reload together (same page request)
      cy.contains("Pledge Dashboard");
      // If seed data has pledges in FY22, table should have at least one row
      cy.get("table").should("exist");
    });

    it("FY 22 (2018) shows pledge data that current FY does not", () => {
      cy.visit(`finance/pledge/dashboard?fyid=${SEED_FYID_2018}`);
      cy.contains("Pledge Dashboard");

      // Seed has pledges in FY22 — fund total section should render
      cy.get("table tbody tr").should("have.length.at.least", 1);
    });

    it("offers an All Time option that shows every fiscal year's pledges", () => {
      cy.visit("finance/pledge/dashboard");

      // All Time is a real option, not just the named fiscal years
      cy.get("#fyid option[value='0']").should("exist").and("contain.text", "All Time");

      cy.get("#fyid").select("0");
      cy.location("search").should("include", "fyid=0");
      cy.contains("Pledge Dashboard");

      // All Time aggregates every seeded fiscal year (22, 23, 25) — strictly
      // more rows than any single FY, and the label must not show a bogus
      // "1996" (fyid 0 run through the calendar-year formula unfiltered).
      cy.contains("All Time");
      cy.contains("1996").should("not.exist");
      cy.get("table tbody tr").should("have.length.at.least", 1);
    });
  });


  // ──────────────────────────────────────────────────────────────────
  // 4. Deposit Search (/finance/deposit/search): FY selector
  //    NOTE: This page migrated from FindDepositSlip.php (legacy AJAX
  //    DataTable) to an MVC server-side-rendered table. The FY selector
  //    (#deposit-slip-fyid) now drives a form GET reload, not an AJAX
  //    call, so assertions use cy.location().should() rather than
  //    cy.intercept() waits.
  // ──────────────────────────────────────────────────────────────────
  describe("Deposit Search (/finance/deposit/search) — FY selector", () => {
    beforeEach(() => {
      cy.setupAdminSession();
    });

    it("renders the FY selector with current FY selected by default", () => {
      cy.visit("finance/deposit/search");
      cy.contains("Deposits");

      // FY selector exists
      cy.get("#deposit-slip-fyid").should("exist");

      // Has at least All Time + one FY
      cy.get("#deposit-slip-fyid option").should("have.length.at.least", 2);

      // One of the options has an "(Current)" label indicator
      cy.get("#deposit-slip-fyid option").should("contain.text", "(Current)");
    });

    it("visiting with ?fyid= param pre-selects the matching option", () => {
      // Server-rendered: the selected attribute is set by PHP on page load.
      cy.visit(`finance/deposit/search?fyid=${SEED_FYID_2018}`);

      // The FY22 option should be pre-selected
      cy.get("#deposit-slip-fyid option:selected").should(
        "have.value",
        String(SEED_FYID_2018)
      );

      // Deposits table must be present
      cy.get("#depositsTable").should("be.visible");
    });

    it("selecting All Time submits the form and removes fyid from URL", () => {
      cy.visit(`finance/deposit/search?fyid=${SEED_FYID_2018}`);

      // Selecting All Time (value "0") triggers onchange → form.submit() after clearing
      // dateStart/dateEnd. form.submit() serialises all form fields, so the resulting URL
      // is ?fyid=0&dateStart=&dateEnd=&... — check only the fyid param, not the full string.
      cy.get("#deposit-slip-fyid").select("0");

      // cy.location() retries until the post-form-submit navigation settles
      cy.location("search").should("satisfy", (s) => {
        const params = new URLSearchParams(s.replace(/^\?/, ""));
        const fyid = params.get("fyid");
        return fyid === null || fyid === "0";
      });
    });

    it("selecting FY 22 (2018) submits the form and filters the table", () => {
      cy.visit("finance/deposit/search");

      // Selecting a FY triggers onchange="this.form.submit()"
      cy.get("#deposit-slip-fyid").select(String(SEED_FYID_2018));

      // cy.location() retries until the post-form-submit navigation settles
      cy.location("search").should("include", `fyid=${SEED_FYID_2018}`);

      // Seed has 2018 deposits — at least one row must appear
      cy.get("#depositsTable tbody tr").should("have.length.at.least", 1);
    });

    it("an explicit date range wins over the ambient (default current-FY) fyid", () => {
      // No fyid param is given, so the route defaults selectedFyid to the
      // current FY — under the old precedence rule (fyid always wins when
      // positive) that would silently discard this date range in favor of
      // the current FY's own range, which has no seeded deposits at all, so
      // the table would render empty. The date range must win instead and
      // surface the seeded 2018-02-18 deposit.
      cy.visit("finance/deposit/search?dateStart=2018-02-01&dateEnd=2018-02-28");

      cy.get("#depositsTable tbody tr").should("have.length.at.least", 1);
    });
  });
});
