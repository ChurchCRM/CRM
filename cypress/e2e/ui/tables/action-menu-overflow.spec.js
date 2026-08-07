/// <reference types="cypress" />

/**
 * Regression spec for #9373 — action-menu dropdowns clipped by
 * `.table-responsive` (and `.card-body` on mobile) overflow.
 *
 * Root cause: `.table-responsive` applies `overflow-x: auto`; per the CSS
 * Overflow spec that forces `overflow-y` to compute as `auto` too, trapping
 * the absolutely-positioned `.dropdown-menu` inside a scroll container.
 *
 * Fix: `_tabler-bridge.scss` adds:
 *   .table-responsive:has(.dropdown-menu.show) { overflow: visible }  (global)
 *   @media (max-width:575.98px) {
 *     .card-body:has(.dropdown-menu.show) { overflow: visible }        (mobile only)
 *   }
 * so each wrapper relaxes only while a menu is open.
 *
 * Three scenarios, each verified at 375x667, 768x1024, and 1920x1080:
 *   1. Family View — Key People member table dropdown (PHP-rendered rows)
 *   2. Family View — Pledges DataTable dropdown (AJAX-loaded rows)
 *   3. Person View — Group Assignments list-group dropdown (.card-body clip)
 *
 * Assertion strategy
 * ------------------
 * `be.visible` alone is INSUFFICIENT to catch this bug: Cypress considers
 * a partially-in-bounds element visible, so a clipped dropdown still passes.
 * Each assertion therefore also checks that the nearest clipping wrapper's
 * computed `overflow-y` is `"visible"` while the menu is open — the direct
 * regression guard that the CSS fix provides.
 *
 * Seed data assumptions
 * ---------------------
 *   Family 1 (Campbell) — seeded with adult members and payments.
 *   Person 2, Group 9 ("Church Board") — membership ensured via API.
 *
 * Requires: Docker test environment (npm run docker:test:start).
 */

const VIEWPORTS = [
  { width: 375, height: 667, label: "375px (phone)" },
  { width: 768, height: 1024, label: "768px (tablet)" },
  { width: 1920, height: 1080, label: "1920px (desktop)" },
];

const FAMILY_ID = 1; // Campbell — seeded with members and payments
const PERSON_ID = 2; // standard test person, seeded
const GROUP_ID = 9; // "Church Board" — exists in seed data

/**
 * Assert that a dropdown menu escapes its overflow wrapper when open.
 *
 * @param {string} triggerSelector   Selector for the [data-bs-toggle="dropdown"] button.
 * @param {string} wrapperSelector   Selector for the nearest clipping wrapper ancestor.
 */
function assertDropdownEscapes(triggerSelector, wrapperSelector) {
  // Open the menu.
  cy.get(triggerSelector).first().click();

  // 1. Per-issue requirement: the menu must be visible.
  cy.get(".dropdown-menu.show").should("be.visible");

  // 2. Last item is also in view (partial-bounds sanity guard).
  cy.get(".dropdown-menu.show .dropdown-item").last().should("be.visible");

  // 3. Regression guard: the clipping wrapper's computed overflow-y must be
  //    "visible" while the menu is open. This is exactly what the :has() rule
  //    enforces and is the ONLY assertion that catches the original clip bug.
  cy.get(".dropdown-menu.show")
    .closest(wrapperSelector)
    .should(($wrapper) => {
      expect($wrapper, `found ${wrapperSelector} ancestor`).to.have.length.greaterThan(0);
      const overflowY = getComputedStyle($wrapper[0]).overflowY;
      expect(overflowY, `computed overflow-y on ${wrapperSelector}`).to.equal(
        "visible",
      );
    });
}

// ---------------------------------------------------------------------------
// Scenario 1 — Family View: Key People member table dropdown
// ---------------------------------------------------------------------------
describe("Dropdown overflow fix — Family Key People table (#9373)", () => {
  VIEWPORTS.forEach(({ width, height, label }) => {
    it(`menu opens unclipped at ${label}`, () => {
      cy.viewport(width, height);
      cy.setupAdminSession();
      cy.visit(`/people/family/${FAMILY_ID}`);

      // Family Members card: Key People is the first .table-responsive.
      // Wait for at least one member row before attempting to open a menu.
      cy.get(".table-responsive")
        .first()
        .find("tbody tr")
        .should("have.length.at.least", 1);

      // The first [data-bs-toggle="dropdown"] inside any .table-responsive
      // belongs to the Key People section (first to render).
      assertDropdownEscapes(
        ".table-responsive [data-bs-toggle='dropdown']",
        ".table-responsive",
      );
    });
  });
});

// ---------------------------------------------------------------------------
// Scenario 2 — Family View: Pledges & Payments DataTable dropdown
// ---------------------------------------------------------------------------
describe("Dropdown overflow fix — Family Pledges DataTable (#9373)", () => {
  VIEWPORTS.forEach(({ width, height, label }) => {
    it(`menu opens unclipped at ${label}`, () => {
      cy.viewport(width, height);
      cy.setupAdminSession();
      cy.visit(`/people/family/${FAMILY_ID}`);

      // Pledge table is AJAX-loaded; family 1 has seeded payments (rows 11-13
      // in cypress/data/seed.sql reference family 1).
      cy.get("#pledge-payment-v2-table tbody tr", { timeout: 15000 }).should(
        "have.length.at.least",
        1,
      );

      assertDropdownEscapes(
        "#pledge-payment-v2-table [data-bs-toggle='dropdown']",
        ".table-responsive",
      );
    });
  });
});

// ---------------------------------------------------------------------------
// Scenario 3 — Person View: Group Assignments list-group dropdown
//
// The Groups section renders a list-group (not a .table-responsive wrapper).
// At viewport <=575px our mobile rule adds `overflow-x: auto` to .card-body,
// which forces overflow-y to compute as auto and clips the dropdown. The fix
// adds .card-body:has(.dropdown-menu.show) { overflow: visible } for this case.
// ---------------------------------------------------------------------------
describe("Dropdown overflow fix — Person View Groups list (#9373)", () => {
  before(() => {
    // Ensure person 2 is in group 9 so the dropdown button is rendered.
    // makePrivateAdminAPICall uses API-key auth; runs before any browser
    // session is established (per the pattern in standard.person.groups.spec.js).
    cy.makePrivateAdminAPICall(
      "POST",
      `/api/groups/${GROUP_ID}/addperson/${PERSON_ID}`,
      { RoleID: 1 },
      [200, 409],
    );
  });

  VIEWPORTS.forEach(({ width, height, label }) => {
    it(`menu opens unclipped at ${label}`, () => {
      cy.viewport(width, height);
      cy.setupAdminSession();

      // The person view route is /people/view/{id}.
      // /people/person/{id} only handles the not-found sub-route.
      cy.visit(`/people/view/${PERSON_ID}`);

      // Activate the Groups tab.
      cy.get("#nav-item-groups").click();
      cy.get("#groups").should("be.visible");

      // At least one group must be listed (membership ensured in before()).
      cy.get("#groups .list-group-item").should("have.length.at.least", 1);

      // Admin session has isManageGroupsEnabled() === true, so each
      // list-group-item has a [data-bs-toggle="dropdown"] action button.
      //
      // Note: the computed overflow-y regression guard is only meaningful at
      // 375px — that is the sole viewport where our mobile rule adds
      // `overflow-x: auto` to `.card-body` (inside @media max-width:575.98px),
      // which is what clips the dropdown. At 768px and 1920px `.card-body`
      // already has default `overflow: visible`, so the assertion passes
      // trivially. The be.visible and items-enabled checks still have value at
      // those wider viewports (verifying the dropdown renders correctly at all
      // sizes), but the clip regression itself is only covered at 375px.
      assertDropdownEscapes(
        "#groups .list-group-item [data-bs-toggle='dropdown']",
        ".card-body",
      );
    });
  });
});
