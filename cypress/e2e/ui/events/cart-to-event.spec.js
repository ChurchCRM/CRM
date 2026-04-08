/// <reference types="cypress" />

describe("Cart to Event (MVC)", () => {
    beforeEach(() => cy.setupAdminSession());

    it("should display the cart-to-event page", () => {
        cy.visit("event/cart-to-event");
        // Either shows cart contents or empty state
        cy.get("body").then(($body) => {
            const hasCart = $body.text().includes("Check In to Event");
            const hasEmpty = $body.text().includes("Your cart is empty");
            expect(hasCart || hasEmpty).to.equal(true);
        });
    });

    it("should redirect to checkin page after submitting cart-to-event", () => {
        // Add a person to cart first via API
        cy.request("POST", "/api/cart/", { Persons: [3] }).then(() => {
            cy.visit("event/cart-to-event");
            cy.contains("Check In to Event");
            cy.get("#EventID").select(1);
            cy.get('button[name="Submit"]').click();
            cy.url().should("include", "/event/checkin/");
        });
    });
});
