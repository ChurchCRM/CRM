/// <reference types="cypress" />

/**
 * Regression tests for TomSelect `dropdownParent: "body"` fix — issue #9488.
 *
 * Without `dropdownParent: "body"`, TomSelect dropdowns inside Bootstrap 5
 * modal containers (or cards with `overflow: hidden`) are clipped/invisible.
 * This fix was applied to the remaining call sites after #9483.
 *
 * The key assertion: after a TomSelect dropdown opens, the `.ts-dropdown`
 * element must be a direct child of `<body>` — proving `dropdownParent: "body"`
 * is in effect.
 *
 * The modal cases also assert that the body-mounted `.ts-dropdown` is torn
 * down when the modal closes (via the `hidden.bs.modal` → `ts.destroy()`
 * handlers), so orphaned dropdown nodes do not accumulate per open/close.
 */
describe("TomSelect dropdownParent:body — remaining call sites (#9488)", () => {
    beforeEach(() => {
        cy.setupStandardSession();
        cy.on("uncaught:exception", () => false);
    });

    /**
     * GroupView.js `.personSearch` "Add Member" picker.
     * This is the NEW call site identified in the audit comment on issue #9488.
     * Group 9 (Church Board) is always present in seed data.
     */
    it("GroupView .personSearch (Add Member) TomSelect dropdown renders as a direct child of body", () => {
        cy.visit("/groups/view/9");

        // Wait for CRM globals and locales
        cy.window().should("have.property", "CRM");
        cy.window().its("CRM.localesLoaded").should("eq", true);

        // Wait for TomSelect to initialize on the Add Member picker.
        // TomSelect adds the 'tomselected' class to the original <select> when it wraps it.
        // We use this class (not visibility) as the ready signal because TomSelect's
        // ts-hidden-accessible style uses clip-path/1px dimensions, not display:none —
        // so Cypress's :visible check would still return true even after TS init.
        cy.get("select#addGroupMember", { timeout: 10000 }).should("have.class", "tomselected");

        // TomSelect inserts .ts-wrapper as a NEXT SIBLING of the original <select>
        // (via insertAdjacentElement('afterend',...)) — NOT as a parent wrapper.
        // .closest() traverses ancestors only, so it never finds the sibling .ts-wrapper.
        // Use .next(".ts-wrapper") to reach the sibling correctly.
        cy.get("select#addGroupMember").next(".ts-wrapper").find(".ts-control").click();

        // Key assertion: the dropdown must be appended to <body>,
        // NOT trapped inside the card DOM tree.
        // Note: The personSearch TomSelect uses a load callback that requires 2+
        // characters before fetching from the API, so clicking alone shows no
        // options. We assert existence (body > .ts-dropdown) to prove the
        // dropdownParent:"body" fix is in effect.
        cy.get("body > .ts-dropdown", { timeout: 5000 }).should("exist");

        // Clean up
        cy.get("body").type("{esc}");
    });

    /**
     * person-group-manager.js `handleAddToGroup` modal.
     * Person 1 (Church Admin) always exists in seed data. The "Assign New Group"
     * action (#addGroup) is rendered for all persons with edit rights.
     * person-group-manager.js is unconditionally loaded on person-view.php.
     */
    it("person-group-manager handleAddToGroup modal TomSelect renders as a direct child of body", () => {
        cy.visit("/people/view/1");

        // Wait for CRM globals and person-group-manager to be ready
        cy.window().should("have.property", "CRM");
        cy.window().its("CRM.localesLoaded").should("eq", true);
        cy.window().its("CRM.currentPersonID").should("exist");

        // Click the "Assign New Group" dropdown item.
        // It lives inside a dropdown-menu but the document-level click listener in
        // person-group-manager.js fires via closest() delegation even on hidden items.
        cy.get("#addGroup, #addGroupFromEmpty", { timeout: 10000 })
            .first()
            .click({ force: true });

        // The modal should appear
        cy.get(".modal.show", { timeout: 10000 }).should("exist");

        // Wait for TomSelect to initialise (gated on shown.bs.modal)
        cy.get(".modal.show .ts-control", { timeout: 10000 }).should("exist").click();

        // Key assertion: dropdown must be a direct child of <body>
        cy.get("body > .ts-dropdown", { timeout: 5000 }).should("exist").and("be.visible");

        // Close modal
        cy.get(".modal.show .btn-close, .modal.show [data-bs-dismiss='modal']")
            .first()
            .click({ force: true });
        cy.get(".modal.show", { timeout: 5000 }).should("not.exist");

        // Teardown: hidden.bs.modal must destroy the TomSelect so no orphaned
        // body > .ts-dropdown node is left behind.
        cy.get("body > .ts-dropdown", { timeout: 5000 }).should("not.exist");
    });
});

/**
 * event-checkin.js `openCheckoutByDialog` — the bootbox→native BS5 modal migration.
 *
 * Creates a transient test event, checks in person 1 via API, then verifies that
 * clicking "Check Out" opens a native Bootstrap 5 modal whose TomSelect search
 * renders as a direct child of <body>.
 */
describe("event-checkin.js openCheckoutByDialog TomSelect dropdownParent:body (#9488)", () => {
    let testEventId;

    before(() => {
        // Create a fresh event and pre-check-in person 1 so the checkout button appears
        cy.makePrivateAdminAPICall("POST", "/api/events/quick-create", { eventTypeId: 1 }, 200).then(
            (resp) => {
                testEventId = resp.body.eventId;
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/events/${testEventId}/checkin`,
                    { personId: 1 },
                    200,
                );
            },
        );
    });

    after(() => {
        if (testEventId) {
            // Person 1 was checked in but not checked out (test cancelled the dialog).
            // checkout-all first so the event has no checked-in attendees,
            // then delete (delete is blocked while active attendees remain).
            cy.makePrivateAdminAPICall("POST", `/api/events/${testEventId}/checkout-all`, {}, 200);
            cy.makePrivateAdminAPICall("DELETE", `/api/events/${testEventId}`, {}, 200);
        }
    });

    beforeEach(() => {
        cy.setupStandardSession();
        cy.on("uncaught:exception", () => false);
    });

    it("checkout dialog (native BS5 modal) TomSelect renders as a direct child of body", () => {
        cy.visit(`/event/checkin/${testEventId}`);

        cy.window().should("have.property", "CRM");
        cy.window().its("CRM.localesLoaded").should("eq", true);

        // Person 1 is checked in; their row is in the server-rendered
        // "People Checked In" table. Open the action dropdown first so
        // the .checkout-btn becomes visible, then click it normally.
        // (Clicking a hidden element with {force:true} is unreliable for
        // jQuery delegated handlers.)
        cy.get("tr[data-person-id='1'] [data-bs-toggle='dropdown']", { timeout: 10000 })
            .should("exist")
            .click();
        cy.get("tr[data-person-id='1'] .checkout-btn")
            .should("be.visible")
            .click();

        // Native BS5 modal should open (no bootbox)
        cy.get(".modal.show", { timeout: 10000 }).should("exist");
        cy.get(".modal.show .modal-title").should("contain.text", "Check out");

        // Wait for TomSelect to initialise inside shown.bs.modal
        cy.get(".modal.show .ts-wrapper", { timeout: 10000 }).should("exist");
        cy.get(".modal.show .ts-control").click();

        // Key assertion: dropdown is a direct child of <body>.
        // The existence of body > .ts-dropdown proves dropdownParent:"body" is in
        // effect (if it were missing, the dropdown would live inside .modal-content
        // and be clipped by overflow:hidden instead). Visibility is not checked here
        // because the TomSelect load callback requires a 2-char query before fetching
        // options, and clicking the control alone shows no list items.
        cy.get("body > .ts-dropdown", { timeout: 5000 }).should("exist");

        // Cancel without actually checking out (avoid side effects)
        cy.get(".modal.show #checkoutCancelBtn").click({ force: true });
        cy.window().then((win) => {
            const el = win.document.querySelector("#crm-checkout-by-modal");
            if (el) {
                const instance = win.bootstrap?.Modal?.getInstance(el);
                if (instance) instance.hide();
            }
        });
        cy.get("#crm-checkout-by-modal", { timeout: 5000 }).should("not.exist");

        // Teardown: hidden.bs.modal destroys the TomSelect and removes the
        // body-mounted dropdown.
        cy.get("body > .ts-dropdown", { timeout: 5000 }).should("not.exist");
    });
});
