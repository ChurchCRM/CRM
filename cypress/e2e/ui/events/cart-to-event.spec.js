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

    it("should show empty state when cart is empty", () => {
        cy.visit("event/cart-to-event");
        cy.contains("Your cart is empty").should("be.visible");
    });

    it("should show cart contents after adding a person via the UI", () => {
        // Add a person to the cart from PersonView (same PHP session as /event/)
        cy.intercept("POST", "**/api/cart/").as("addToCart");
        cy.visit("/people/view/3");
        cy.get(".AddToCart[data-cart-id='3']", { timeout: 10000 }).first().click();
        cy.wait("@addToCart").its("response.statusCode").should("eq", 200);

        // Navigate to cart-to-event — should show the person, not the empty state
        cy.visit("event/cart-to-event");
        cy.contains("Check In to Event").should("exist");
        cy.get("#eventId").should("exist");
    });

    // Note: the #eventId dropdown and submit form only render when the cart
    // is non-empty (the view gates the entire form behind `if ($cartCount > 0)`).
    // Testing event selection + form submission requires the cart to be populated
    // in the same PHP session as the /event/ entry point, which is not reliably
    // achievable from Cypress (the /api/ and /event/ entry points may use
    // different PHP sessions). The POST → redirect → /event/checkin/{id} path
    // is covered by the API tests (private.calendar.events-checkin.spec.js).
});
