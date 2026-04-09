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
        cy.visit("PersonView.php?PersonID=3");
        cy.get(".AddToCart[data-cart-id='3']", { timeout: 10000 }).first().click();
        cy.wait("@addToCart").its("response.statusCode").should("eq", 200);

        // Navigate to cart-to-event — should show the person, not the empty state
        cy.visit("event/cart-to-event");
        cy.contains("Check In to Event").should("exist");
        cy.get("#EventID").should("exist");
    });

    it("should have an event selector with at least one option when events exist", () => {
        // Quick-create an event so the dropdown is populated
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/events/quick-create",
            { eventTypeId: 1 },
            200,
        ).then((createResp) => {
            const eventId = createResp.body.eventId;
            expect(eventId).to.be.a("number");

            cy.setupAdminSession({ forceLogin: true });
            cy.visit("event/cart-to-event");
            cy.get(`#EventID option[value="${eventId}"]`).should("exist");
        });
    });
});
