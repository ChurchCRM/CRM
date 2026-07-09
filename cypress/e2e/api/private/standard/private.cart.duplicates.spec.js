/// <reference types="cypress" />

/**
 * Cart Duplicate Handling API Tests
 *
 * Verifies the /api/cart/ POST endpoint correctly reports which persons were
 * newly added and which were already present (duplicates). The endpoint must
 * return two arrays per call: `added` and `duplicate`.
 *
 * UI-level cart coverage (dropdown, v2/cart page, cart manager JS) lives in
 * cypress/e2e/ui/people/standard.cart.spec.js.
 */
describe("API Private Cart - Duplicate Detection", () => {
    beforeEach(() => {
        cy.setupStandardSession();
        // Ensure cart starts empty so the first add is never a duplicate.
        cy.request({
            method: "DELETE",
            url: "/api/cart/",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({}),
        });
    });

    after(() => {
        // Leave the cart empty for subsequent specs.
        cy.setupStandardSession();
        cy.request({
            method: "DELETE",
            url: "/api/cart/",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({}),
        });
    });

    it("Cart API returns correct duplicate information", () => {
        // Add person via API - should succeed
        cy.request({
            method: "POST",
            url: "/api/cart/",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                Persons: [1]
            })
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body).to.have.property("added");
            expect(response.body).to.have.property("duplicate");
            expect(response.body.added).to.include(1);
            expect(response.body.duplicate).to.be.empty;
        });

        // Try to add same person again - should be duplicate
        cy.request({
            method: "POST",
            url: "/api/cart/",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                Persons: [1]
            })
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body).to.have.property("added");
            expect(response.body).to.have.property("duplicate");
            expect(response.body.added).to.be.empty;
            expect(response.body.duplicate).to.include(1);
        });

        // Add multiple people including one duplicate
        cy.request({
            method: "POST",
            url: "/api/cart/",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                Persons: [1, 2, 3]
            })
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body.added).to.include.members([2, 3]);
            expect(response.body.duplicate).to.include(1);
        });
    });
});
