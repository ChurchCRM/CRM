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
 *     select rendered inside a programmatic Bootstrap 5 modal.
 *
 * The key assertion is that the TomSelect dropdown is appended as a DIRECT
 * child of <body> when opened (`body > .ts-dropdown` is visible), not nested
 * inside a `.card` or `.modal-content` ancestor. This is only true when
 * `dropdownParent: "body"` is set on the TomSelect instance.
 */
describe("GroupView TomSelect dropdownParent regression (#9488)", () => {
    let testGroupId;
    const uniqueSeed = Date.now().toString();
    const testGroupName = `DropdownParent Test ${uniqueSeed}`;

    before(() => {
        // Create a test group so we have a predictable group view to load.
        // Use API-key auth so we don't depend on cy.session() being properly
        // initialised in a before() hook (cy.session() is designed for beforeEach().
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
        cy.setupStandardSession();
        cy.on("uncaught:exception", () => false);
    });

    // ------------------------------------------------------------------ //
    // 1. "Add Member" personSearch (page-level select, bug #2 fix)
    // ------------------------------------------------------------------ //
    it("Add Member personSearch: dropdown appends to body (dropdownParent:\"body\")", () => {
        cy.visit(`/groups/view/${testGroupId}`);

        // Wait for the page to be ready
        cy.window().should("have.property", "CRM");
        cy.window().its("CRM.localesLoaded").should("eq", true);

        // TomSelect should have been initialized on #addGroupMember
        cy.get("#addGroupMember").closest(".ts-wrapper").should("exist");

        // Click the TomSelect control to open the dropdown
        cy.get("#addGroupMember").closest(".ts-wrapper").find(".ts-control").click();

        // The dropdown MUST be a direct child of <body> — not nested inside the card.
        // Without dropdownParent:"body", it would be inside .ts-wrapper inside .card-body.
        cy.get("body > .ts-dropdown").should("exist").and("be.visible");

        // Typing ≥2 chars triggers the remote load callback
        cy.get("body > .ts-dropdown input[type='text'], .ts-wrapper input[type='text']")
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

        cy.window().should("have.property", "CRM");
        cy.window().its("CRM.localesLoaded").should("eq", true);

        // The "Copy Members to Group" button appears on role pill dropdowns.
        // If no role pills are rendered (group has only the default Member role
        // which may be hidden), trigger the modal via the JS API directly.
        cy.window().then((win) => {
            // Manually trigger _showGroupAndRoleModal via the global function
            // which is defined in the page scope of GroupView.js (used by the
            // copy/move-role-to-group click handlers).
            // _showGroupAndRoleModal is not exported; invoke it via a click on
            // a .copy-role-to-group element, or use the window eval trick.
            // Fall back: call the underlying groups.get() to verify API is live.
            //
            // The safest approach: click the first .copy-role-to-group button
            // if it exists; otherwise skip with a note.
            const copyBtns = win.document.querySelectorAll(".copy-role-to-group");
            if (copyBtns.length > 0) {
                copyBtns[0].click();
            } else {
                // No role pills visible — use cy.window() to call the function
                win.eval(
                    `(function() {
                        if (typeof _showGroupAndRoleModal === 'function') {
                            _showGroupAndRoleModal('Test modal', function() {});
                        }
                    })()`,
                );
            }
        });

        // The Bootstrap 5 modal should appear
        cy.get(".modal.show", { timeout: 8000 }).should("be.visible");

        // Wait for TomSelect to init (it fires after shown.bs.modal)
        cy.get(".modal.show .ts-wrapper", { timeout: 10000 }).should("exist");

        // Open the group TomSelect dropdown
        cy.get(".modal.show .ts-control").first().click();

        // Dropdown MUST be a direct child of <body> — not clipped by modal-content
        cy.get("body > .ts-dropdown").should("exist").and("be.visible");

        // Close the modal
        cy.get(".modal.show .btn-close").click();
        cy.get(".modal.show").should("not.exist");
    });
});
