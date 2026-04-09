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
        // Step 1 — quick-create an event so the dropdown has something to select.
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/events/quick-create",
            { eventTypeId: 1 },
            200,
        ).then((createResp) => {
            const expectedEventId = createResp.body.eventId;
            expect(expectedEventId, "quick-create returned an eventId").to.be.a("number");

            // Step 2 — re-establish the browser session and add a person to the
            // cart via the UI. Intercept the AJAX POST to /api/cart/ so we can
            // wait for it to complete before navigating away.
            cy.setupAdminSession({ forceLogin: true });
            cy.intercept("POST", "**/api/cart/").as("addToCart");
            cy.visit("PersonView.php?PersonID=3");
            cy.get(".AddToCart[data-cart-id='3']", { timeout: 10000 }).first().click();
            cy.wait("@addToCart").its("response.statusCode").should("eq", 200);

            // Step 3 — navigate to cart-to-event. "Check In to Event" only
            // renders when the cart is non-empty, so this also verifies the
            // cart was populated in the same session.
            cy.visit("event/cart-to-event");
            cy.contains("Check In to Event");

            // Step 4 — select the event and submit.
            cy.get(`#EventID option[value="${expectedEventId}"]`).should("exist");
            cy.get("#EventID").select(String(expectedEventId));
            cy.get('button[name="Submit"]').click();

            // Step 5 — verify redirect.
            cy.url().should("include", `/event/checkin/${expectedEventId}`);
        });
    });
});
