/// <reference types="cypress" />

/**
 * These tests exercise the /groups/cart-to-group MVC page.
 * They are self-sufficient — every precondition (target group, cart
 * members) is created via the API at the start of each test so the
 * tests do not depend on whatever happens to be in the seed database.
 */
describe("Standard Groups - Cart to Group", () => {
    beforeEach(() => cy.setupStandardSession());

    it("displays cart members and adds them to a group", () => {
        const uniqueSeed = Date.now().toString();
        const groupName  = "CartToGroup Test " + uniqueSeed;

        // Seed: ensure cart is empty, then add person 1 (Church Admin)
        cy.request({
            method:  "DELETE",
            url:     "/api/cart/",
            headers: { "Content-Type": "application/json" },
            body:    {},
        });

        cy.request({
            method:  "POST",
            url:     "/api/cart/",
            headers: { "Content-Type": "application/json" },
            body:    { Persons: [1] },
        });

        // Create a fresh target group so the test is self-contained
        cy.request({
            method:  "POST",
            url:     "/api/groups/",
            headers: { "Content-Type": "application/json" },
            body:    { groupName: groupName },
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            const groupId = resp.body.Id;
            expect(groupId).to.be.a("number").and.be.greaterThan(0);

            cy.visit("/groups/cart-to-group");

            // Cart contents card should be visible with our person
            cy.contains("People in Cart").should("be.visible");
            cy.contains("Church Admin").should("be.visible");

            // Select the freshly-created group
            cy.get("#GroupID").select(String(groupId));

            // Wait for the role dropdown to be populated (UpdateRoles fires onchange)
            cy.get("#GroupRole").should("not.contain", "No Group Selected");

            // Click the submit button (using .click() ensures button values are POSTed)
            cy.get('button[type="submit"][name="Submit"]').click();

            // Should redirect to the group view page
            cy.url().should("include", "/groups/view/" + groupId);

            // Success flash message from $_SESSION['sGlobalMessage']
            cy.contains("successfully added to selected Group", { timeout: 10000 }).should(
                "be.visible"
            );
        });
    });

    it("shows empty-cart state when cart has no members", () => {
        // Ensure cart is empty
        cy.request({
            method:  "DELETE",
            url:     "/api/cart/",
            headers: { "Content-Type": "application/json" },
            body:    {},
        });

        cy.visit("/groups/cart-to-group");

        cy.contains("Your cart is empty!").should("be.visible");
        cy.contains("Add people to your cart first").should("be.visible");
    });
});
