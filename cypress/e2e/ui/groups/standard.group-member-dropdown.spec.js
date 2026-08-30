/// <reference types="cypress" />

/**
 * Regression tests for TomSelect dropdownParent:"body" in GroupView modals.
 *
 * Issue #9488 — Several TomSelect dropdowns inside Bootstrap 5 modals (and one
 * page-level select inside a card with potential overflow constraints) were
 * rendered without `dropdownParent: "body"`, causing the dropdown list to be
 * clipped or hidden by `overflow: hidden` on ancestor containers.
 *
 * This spec covers:
 *  1. The "Add Member" personSearch TomSelect (#addGroupMember) on the group
 *     view page — a page-level select inside a card that needs dropdownParent
 *     so its dropdown is not clipped by the card layout.
 *  2. The "Copy Members to Group" _showGroupAndRoleModal TomSelect — a group
 *     select rendered inside a programmatic Bootstrap 5 modal, triggered via
 *     the "Actions → Copy to Group → All Members" toolbar button.
 *
 * The key assertion is that the TomSelect dropdown is appended as a DIRECT
 * child of <body> when opened (`body > .ts-dropdown` is visible), not nested
 * inside a `.card` or `.modal-content` ancestor. This is only true when
 * `dropdownParent: "body"` is set on the TomSelect instance.
 *
 * Requires: Admin session (bCanManageGroups must be true for the Actions button
 * to be rendered).
 */
describe("GroupView TomSelect dropdownParent regression (#9488)", () => {
    let testGroupId;
    const uniqueSeed = Date.now().toString();
    const testGroupName = `DropdownParent Test ${uniqueSeed}`;

    before(() => {
        // Create a test group so we have a predictable group view to load.
        // Use API-key auth so we don't depend on cy.session() being properly
        // initialised in a before() hook (cy.session() is designed for beforeEach()).
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/groups/",
            { groupName: testGroupName, description: "" },
            200,
        ).then((resp) => {
            testGroupId = resp.body.Id;
        });
    });

    after(() => {
        if (testGroupId) {
            cy.makePrivateAdminAPICall("DELETE", `/api/groups/${testGroupId}`, null, 200);
        }
    });

    beforeEach(() => {
        // Admin session required: the "Actions" toolbar button (which triggers
        // _showGroupAndRoleModal) is only rendered when bCanManageGroups is true.
        cy.setupAdminSession();
        cy.on("uncaught:exception", () => false);
    });

    // ------------------------------------------------------------------ //
    // 1. "Add Member" personSearch (page-level select, bug #2 fix)
    // ------------------------------------------------------------------ //
    it('Add Member personSearch: dropdown appends to body (dropdownParent:"body")', () => {
        cy.visit(`/groups/view/${testGroupId}`);

        // Wait for TomSelect to initialize on select#addGroupMember.
        // TomSelect wraps the <select> inside a .ts-wrapper div, so we wait
        // for `.ts-wrapper select#addGroupMember` to appear in the DOM.
        // This retries with Cypress's implicit timeout (up to 10 s).
        cy.get(".ts-wrapper select#addGroupMember", { timeout: 10000 }).should("exist");

        // Click the TomSelect control (the visible div, not the hidden <select>)
        cy.get("select#addGroupMember")
            .closest(".ts-wrapper")
            .find(".ts-control")
            .click();

        // The dropdown MUST be a direct child of <body> — not nested inside the card.
        // Without dropdownParent:"body", it would be inside .ts-wrapper inside .card-body.
        cy.get("body > .ts-dropdown").should("exist").and("be.visible");

        // Typing ≥2 chars triggers the remote-load callback
        cy.get(".ts-wrapper .ts-control input")
            .first()
            .type("Ad", { delay: 50 });

        // The dropdown remains body-mounted during and after the load
        cy.get("body > .ts-dropdown").should("exist");

        // Close by pressing Escape
        cy.get("body").type("{esc}");
        cy.get("body > .ts-dropdown").should("not.be.visible");
    });

    // ------------------------------------------------------------------ //
    // 2. _showGroupAndRoleModal (modal TomSelect, bug #2 fix)
    // ------------------------------------------------------------------ //
    it("Copy Members to Group modal: group TomSelect dropdown appends to body", () => {
        cy.visit(`/groups/view/${testGroupId}`);

        // Wait for the page's Actions toolbar to be rendered (requires admin session)
        cy.get("#group-view-toolbar").should("be.visible");

        // Open the "Actions" toolbar dropdown (always visible to admin users;
        // contains "Copy to Group → All Members" (.copy-role-to-group) regardless
        // of how many members the group has — the "All Members" link is static HTML).
        cy.contains("#group-view-toolbar button", "Actions").click();

        // "All Members" is always present in the Copy section
        cy.get(".dropdown-menu.show .copy-role-to-group").first().click();

        // The Bootstrap 5 modal created by _showGroupAndRoleModal should appear
        cy.get(".modal.show", { timeout: 8000 }).should("be.visible");

        // TomSelect is initialised inside the shown.bs.modal handler — wait for it
        cy.get(".modal.show .ts-wrapper", { timeout: 10000 }).should("exist");

        // Open the group TomSelect dropdown
        cy.get(".modal.show .ts-control").first().click();

        // Dropdown MUST be a direct child of <body> — not clipped by modal-content
        cy.get("body > .ts-dropdown").should("exist").and("be.visible");

        // Close the modal
        cy.get(".modal.show .btn-close").first().click();
        cy.get(".modal.show").should("not.exist");
    });
});
