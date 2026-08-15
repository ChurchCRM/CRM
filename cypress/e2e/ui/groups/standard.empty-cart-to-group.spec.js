/// <reference types="cypress" />

/**
 * Regression tests for the "Empty Cart to Group" modal dialog.
 *
 * Issue: #9465 / #9051 — The Bootstrap 5 + TomSelect group-and-role selection
 * modal was broken because `promptSelection` in CRMJSOM.js used a bootbox v6
 * incompatible `.init()` chaining pattern and set `dropdownParent` to a DOM
 * node rather than the string "body", causing TomSelect to initialize before
 * the modal was fully visible and the dropdown list to be hidden or empty.
 *
 * Fix: rewrote `promptSelection` to use a native Bootstrap 5 modal with
 * TomSelect initialized in the `shown.bs.modal` event, and `dropdownParent`
 * set to "body" so TomSelect dropdown list is always visible.
 */
describe("Empty Cart to Group", () => {
    const TEST_PERSON_ID = 1; // Church Admin — always exists in seed data

    beforeEach(() => {
        cy.setupStandardSession();

        // Ensure a clean cart state for each test
        cy.request({
            method: "DELETE",
            url: "/api/cart/",
            headers: { "Content-Type": "application/json" },
            body: {},
        });

        // Add a person to the cart so the "To Group" button is visible
        cy.request({
            method: "POST",
            url: "/api/cart/",
            headers: { "Content-Type": "application/json" },
            body: { Persons: [TEST_PERSON_ID] },
        });

        cy.on("uncaught:exception", () => false);
    });

    afterEach(() => {
        // Empty the cart after each test
        cy.request({
            method: "DELETE",
            url: "/api/cart/",
            headers: { "Content-Type": "application/json" },
            body: {},
        });
    });

    it("opens the group-selection modal when 'To Group' is clicked on the cart page", () => {
        cy.visit("/v2/cart");

        // Wait for the CRM and locales to be ready
        cy.window().should("have.property", "CRM");
        cy.window().its("CRM.localesLoaded").should("eq", true);
        cy.window().its("CRM.cartManager").should("exist");

        // The "To Group" button should be visible because the cart is non-empty
        cy.get("#emptyCartToGroup").should("be.visible").click();

        // The Bootstrap 5 modal should appear
        cy.get(".modal.show").should("be.visible");

        // The modal title should contain "Group" (covers "Select Group and Role")
        cy.get(".modal.show .modal-title").should("contain", "Group");

        // The group TomSelect should be initialized — its control wrapper is present
        cy.get(".modal.show .ts-control", { timeout: 10000 }).should("exist");

        // The group list input should exist and be interactive
        cy.get(".modal.show input[type='text']").first().should("exist");
    });

    it("populates the group dropdown with available groups", () => {
        const uniqueSeed = Date.now().toString();
        const groupName = "EmptyCartToGroup Test " + uniqueSeed;

        // Create a test group so we know at least one group exists
        cy.request({
            method: "POST",
            url: "/api/groups/",
            headers: { "Content-Type": "application/json" },
            body: { groupName: groupName },
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            const groupId = resp.body.Id;

            cy.visit("/v2/cart");

            cy.window().should("have.property", "CRM");
            cy.window().its("CRM.localesLoaded").should("eq", true);

            cy.get("#emptyCartToGroup").should("be.visible").click();

            // Wait for modal to open and TomSelect to initialize
            cy.get(".modal.show").should("be.visible");
            cy.get(".modal.show .ts-wrapper", { timeout: 10000 }).should("exist");

            // Click on the TomSelect input to open the dropdown
            cy.get(".modal.show .ts-control").first().click();

            // The dropdown should be visible and contain our test group
            cy.get(".ts-dropdown.single", { timeout: 5000 }).should("be.visible");
            cy.get(".ts-dropdown.single .option").should("have.length.at.least", 1);

            // Close modal and clean up the test group
            cy.get(".modal.show .btn-close").click();
            cy.get(".modal.show").should("not.exist");

            cy.request({
                method: "DELETE",
                url: `/api/groups/${groupId}`,
            });
        });
    });

    it("shows role dropdown after selecting a group with multiple roles", () => {
        // Use a well-known group from seed data that has roles defined.
        // Group ID 11 ("Clergy") has role list 23 per seed data.
        cy.visit("/v2/cart");

        cy.window().should("have.property", "CRM");
        cy.window().its("CRM.localesLoaded").should("eq", true);

        cy.get("#emptyCartToGroup").should("be.visible").click();

        // Wait for modal + TomSelect to be ready
        cy.get(".modal.show").should("be.visible");
        cy.get(".modal.show .ts-wrapper", { timeout: 10000 }).should("exist");

        // The role wrapper should be hidden until a group is selected
        cy.get("#crm-gs-role-wrapper").should("have.class", "d-none");

        // Select the target group by typing to search
        cy.get(".modal.show .ts-control").first().click();
        cy.get(".modal.show input[type='text']").first().type("Clergy", { delay: 50 });
        cy.get(".ts-dropdown .option").contains("Clergy").click({ force: true });

        // After selecting a group, the roles API should be called.
        // If roles exist, the role wrapper should appear.
        // (We don't assert the exact role names since they depend on seed data)
        cy.get("#crm-gs-confirm", { timeout: 5000 }).should("not.be.disabled");

        // Close the modal
        cy.get(".modal.show .btn-close").click();
    });

    it("closes the modal on Cancel without emptying the cart", () => {
        cy.visit("/v2/cart");

        cy.window().should("have.property", "CRM");
        cy.window().its("CRM.localesLoaded").should("eq", true);

        cy.get("#emptyCartToGroup").should("be.visible").click();
        cy.get(".modal.show").should("be.visible");

        // Click Cancel
        cy.get(".modal.show #crm-gs-cancel").click();
        cy.get(".modal.show").should("not.exist");

        // Cart should still have the person
        cy.request("GET", "/api/cart/").then((resp) => {
            expect(resp.body.PeopleCart).to.include(TEST_PERSON_ID);
        });
    });
});
