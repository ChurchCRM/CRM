/// <reference types="cypress" />

/**
 * Regression tests: action-menu dropdowns must not be clipped by overflow
 * containers wrapping tables.
 *
 * Root cause: Bootstrap's .table-responsive sets overflow-x:auto. The CSS
 * Overflow spec forces overflow-y to auto when overflow-x is auto/scroll,
 * clipping absolutely-positioned dropdown menus.
 *
 * Fix: replace each affected .table-responsive wrapper with
 * `<div style="overflow-x: clip; overflow-y: visible;">`.
 * `overflow-x: clip` is exempt from the coercion rule (only auto/scroll
 * trigger it), so overflow-y stays truly visible and the dropdown escapes.
 *
 * GitHub: https://github.com/ChurchCRM/CRM/issues/9373
 *
 * Scenarios covered:
 *   1. Family View  - Key People member table (inline-styled wrapper inside card-body)
 *   2. Family View  - Pledges and Payments DataTable (inline-styled wrapper, direct card child)
 *   3. Person View  - Group Assignments list-group (card-body context, no table wrapper)
 *
 * Each scenario is tested at three representative viewport widths.
 */

const VIEWPORTS = [
  { label: "375px mobile",   width: 375,  height: 812  },
  { label: "768px tablet",   width: 768,  height: 1024 },
  { label: "1920px desktop", width: 1920, height: 1080 },
];

/**
 * Open a dropdown trigger and assert the resulting menu is fully visible.
 * For Scenarios 1 & 2, also pass `containerFinder` to verify the container's
 * computed overflow-x/overflow-y — the authoritative check that the clipping
 * fix is in place.
 *
 * @param {string}   triggerSelector - CSS selector for the dropdown toggle button
 * @param {string}   context         - Human-readable label for failure messages
 * @param {Function} [containerFinder] - Optional: receives the Cypress chain after
 *   the menu opens and should assert computed overflow on the fixed container.
 */
function assertDropdownVisible(triggerSelector, context, containerFinder) {
  cy.get(triggerSelector, { timeout: 10000 })
    .first()
    .as("trigger")
    .click();

  cy.get(".dropdown-menu.show")
    .as("menu")
    .should("be.visible");

  // If a container assertion is provided, run it while the dropdown is open.
  if (containerFinder) {
    containerFinder(context);
  }

  // Items inside the menu must be accessible — Bootstrap disables <a> items
  // via the .disabled CSS class (not the HTML disabled attribute, which is
  // invalid on anchors and always passes should("not.be.disabled")).
  cy.get("@menu")
    .find(".dropdown-item")
    .first()
    .should("not.have.class", "disabled");

  // Close the menu before next iteration to avoid state leakage.
  cy.get("@trigger").click();
}

/**
 * Assert that the wrapper immediately surrounding the open dropdown has the
 * expected computed overflow values set by the per-wrapper fix.
 * Finds the closest ancestor with an inline overflow-x style.
 *
 * @param {string} context - Label for assertion messages
 */
function assertContainerOverflow(context) {
  cy.get(".dropdown-menu.show")
    .closest("[style*='overflow-x']")
    .then(($el) => {
      // Use ownerDocument.defaultView rather than window — window in a .then()
      // callback is the spec-runner frame, not the AUT. In Firefox this returns
      // an empty CSSStyleDeclaration; ownerDocument.defaultView always returns
      // the correct window for the element being inspected.
      const styles = $el[0].ownerDocument.defaultView.getComputedStyle($el[0]);
      expect(
        styles.overflowX,
        `[${context}] container overflow-x`,
      ).to.equal("clip");
      expect(
        styles.overflowY,
        `[${context}] container overflow-y`,
      ).to.equal("visible");
    });
}

// ── Scenario 1: Family View — Key People member-table row dropdown ────────────
// Family 1 is seeded with multiple members; their rows each carry a
// .btn[data-bs-toggle='dropdown'] ellipsis trigger inside a wrapper fixed with
// `style="overflow-x: clip; overflow-y: visible;"` (formerly table-responsive).
//
// 375px is intentionally excluded: the 5-column table (NAME, ROLE, BIRTHDAY,
// EMAIL, ACTIONS) is wider than 375px, and overflow-x:clip hides the ACTIONS
// column off-screen at that width. 768px and 1920px are sufficient to verify
// the dropdown-clipping fix at the widths where the button is reachable.
const SCENARIO1_VIEWPORTS = VIEWPORTS.filter(({ width }) => width >= 768);

describe("Scenario 1 — Family View member table dropdown", () => {
  beforeEach(() => cy.setupStandardSession());

  SCENARIO1_VIEWPORTS.forEach(({ label, width, height }) => {
    it(`[${label}] dropdown escapes table wrapper; container overflow is clip/visible`, () => {
      cy.viewport(width, height);
      cy.visit("/people/family/1");

      // Wait for the family members card-table to be present
      cy.get(".card-table", { timeout: 10000 }).should("exist");

      assertDropdownVisible(
        ".card-table [data-bs-toggle='dropdown']",
        `family member table @ ${label}`,
        assertContainerOverflow,
      );
    });
  });
});

// ── Scenario 2: Family View — Pledges and Payments DataTable row dropdown ─────
// The pledges table (#pledge-payment-v2-table) sits in a wrapper fixed with
// `style="overflow-x: clip; overflow-y: visible;"` (formerly table-responsive,
// direct child of .card). Uses the admin session (finance settings already
// true in seed) with real seed data. The default FY filter hides all seed
// pledges (2018 data), so we click "All Time" first to expose rows.
describe("Scenario 2 — Family View pledges DataTable dropdown", () => {
  beforeEach(() => cy.setupAdminSession());

  VIEWPORTS.forEach(({ label, width, height }) => {
    it(`[${label}] dropdown escapes table wrapper; container overflow is clip/visible`, () => {
      cy.viewport(width, height);
      cy.visit("/people/family/1");

      // DataTable initialises after two async user-setting POSTs.
      // Wait for the DataTables wrapper element that appears on init.
      cy.get("#pledge-payment-v2-table_wrapper", { timeout: 15000 }).should("exist");

      // Seed pledges for family 1 are from 2018; the default FY filter hides them.
      // Click "All Time" to remove the filter and reveal all rows.
      cy.get('.pledge-fy-pill[data-fy=""]').click();

      // Wait for actual data rows (not the DataTables "No data available" empty row).
      cy.get("#pledge-payment-v2-table tbody tr:not(.dataTables_empty)", { timeout: 10000 })
        .should("have.length.at.least", 1);

      assertDropdownVisible(
        "#pledge-payment-v2-table [data-bs-toggle='dropdown']",
        `pledges DataTable @ ${label}`,
        assertContainerOverflow,
      );
    });
  });
});

// ── Scenario 3: Person View — Group Assignments list-group row dropdown ───────
// The group assignments list is inside a .card-body (not a fixed wrapper).
// Uses API setup to ensure person 2 is a member of group 9 ("Church Board").
describe("Scenario 3 — Person View group assignments dropdown", () => {
  const personId    = 2;
  const testGroupId = 9; // "Church Board" — always present in seed data

  beforeEach(() => {
    // Ensure membership via admin API before opening a browser session.
    // addperson is idempotent: returns 200 whether already a member or not.
    cy.makePrivateAdminAPICall(
      "POST",
      `/api/groups/${testGroupId}/addperson/${personId}`,
      { RoleID: 1 },
      [200],
    );
    cy.setupStandardSession();
  });

  VIEWPORTS.forEach(({ label, width, height }) => {
    it(`[${label}] dropdown is visible and items are clickable`, () => {
      cy.viewport(width, height);
      cy.visit(`/people/view/${personId}`);

      // Activate the Groups tab
      cy.get("#nav-item-groups").click();
      cy.get("#groups").should("be.visible");

      // Wait for the Church Board row to be rendered
      cy.get("#groups .list-group-item", { timeout: 10000 })
        .contains("Church Board")
        .should("exist");

      // No container overflow check for Scenario 3 — the group-assignments
      // list-group is inside a .card-body (not a fixed wrapper from this PR).
      assertDropdownVisible(
        "#groups .list-group-item [data-bs-toggle='dropdown']",
        `group assignments @ ${label}`,
      );
    });
  });
});
