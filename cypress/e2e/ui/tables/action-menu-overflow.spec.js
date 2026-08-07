/// <reference types="cypress" />

/**
 * Regression tests: action-menu dropdowns must not be clipped by overflow
 * containers wrapping tables.
 *
 * Root cause: Bootstrap's .table-responsive sets overflow-x:auto. The CSS
 * Overflow spec forces overflow-y to auto when overflow-x is auto/scroll,
 * clipping absolutely-positioned dropdown menus. On phones (<=575px) the
 * ChurchCRM mobile rule additionally sets overflow-x:auto on .card-body,
 * clipping dropdowns inside any card at that breakpoint.
 *
 * Fix (src/skin/scss/_tabler-bridge.scss):
 *   .table-responsive:has(.dropdown-menu.show) { overflow: visible; }
 *   and a mobile @media block for .card-body and .card>.table-responsive.
 *   Scoped to .show so the fix only fires while the dropdown is open —
 *   closed containers keep their normal overflow-x:auto horizontal scroll.
 *
 * GitHub: https://github.com/ChurchCRM/CRM/issues/9373
 *
 * Scenarios covered:
 *   1. Family View  - Key People member table (table-responsive inside card-body)
 *   2. Family View  - Pledges and Payments DataTable (table-responsive direct card child)
 *   3. Person View  - Group Assignments list-group (card-body clip on mobile)
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
 *
 * @param {string} triggerSelector - CSS selector for the dropdown toggle button
 * @param {string} context         - Human-readable label for failure messages
 */
function assertDropdownVisible(triggerSelector, context) {
  cy.get(triggerSelector, { timeout: 10000 })
    .first()
    .as("trigger")
    .click();

  cy.get(".dropdown-menu.show")
    .as("menu")
    .should("be.visible");

  // The menu's bounding rect bottom must stay within the viewport.
  // A clipped dropdown inside a small scroll container would extend
  // below the container edge and, when near the page bottom, below the viewport.
  cy.window().then((win) => {
    const menu = win.document.querySelector(".dropdown-menu.show");
    if (menu) {
      const rect = menu.getBoundingClientRect();
      expect(
        rect.bottom,
        `[${context}] dropdown bottom (${Math.round(rect.bottom)}px) must be within viewport (${win.innerHeight}px)`,
      ).to.be.lte(win.innerHeight + 5);
    }
  });

  // Items inside the menu must be accessible (not hidden or disabled).
  cy.get("@menu")
    .find(".dropdown-item")
    .first()
    .should("not.be.disabled");

  // Close the menu before next iteration to avoid state leakage.
  cy.get("@trigger").click();
}

// ── Scenario 1: Family View — Key People member-table row dropdown ────────────
// Family 1 is seeded with multiple members; their rows each carry a
// .btn[data-bs-toggle='dropdown'] ellipsis trigger inside a .table-responsive
// that is itself nested inside a .card-body (two-level clip on mobile).
describe("Scenario 1 — Family View member table dropdown", () => {
  beforeEach(() => cy.setupStandardSession());

  VIEWPORTS.forEach(({ label, width, height }) => {
    it(`[${label}] dropdown escapes table-responsive and card-body overflow`, () => {
      cy.viewport(width, height);
      cy.visit("/people/family/1");

      // Wait for the family members card-table to be present
      cy.get(".card-table", { timeout: 10000 }).should("exist");

      assertDropdownVisible(
        ".card-table [data-bs-toggle='dropdown']",
        `family member table @ ${label}`,
      );
    });
  });
});

// ── Scenario 2: Family View — Pledges and Payments DataTable row dropdown ─────
// The pledges table (#pledge-payment-v2-table) sits in a .table-responsive that
// is a direct child of .card (no intervening card-body). Uses the admin session
// (which has finance.show.pledges=true) and real seed data. The default FY filter
// hides all seed pledges (2018 data), so we click "All Time" first to expose rows.
describe("Scenario 2 — Family View pledges DataTable dropdown", () => {
  beforeEach(() => cy.setupAdminSession());

  VIEWPORTS.forEach(({ label, width, height }) => {
    it(`[${label}] dropdown escapes table-responsive overflow`, () => {
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
      );
    });
  });
});

// ── Scenario 3: Person View — Group Assignments list-group row dropdown ───────
// The group assignments list is inside a .card-body (not a .table-responsive).
// On phones (<=575px) the mobile .card-body{overflow-x:auto} rule is the clip
// source; the fix adds a :has(.dropdown-menu.show) exception.
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
    it(`[${label}] dropdown escapes card-body overflow`, () => {
      cy.viewport(width, height);
      cy.visit(`/people/view/${personId}`);

      // Activate the Groups tab
      cy.get("#nav-item-groups").click();
      cy.get("#groups").should("be.visible");

      // Wait for the Church Board row to be rendered
      cy.get("#groups .list-group-item", { timeout: 10000 })
        .contains("Church Board")
        .should("exist");

      assertDropdownVisible(
        "#groups .list-group-item [data-bs-toggle='dropdown']",
        `group assignments @ ${label}`,
      );
    });
  });
});
