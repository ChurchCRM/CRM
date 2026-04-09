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

            // Step 2 — re-establish the browser session and visit a page so we
            // have a real PHP session cookie set in the browser.
            cy.setupAdminSession({ forceLogin: true });
            cy.visit("event/cart-to-event");

            // Step 3 — populate the cart via cy.request, which sends the
            // browser's session cookies (sticky session). We must wait for it
            // to complete before reloading the page, otherwise the cart-to-event
            // GET will see an empty cart.
            cy.request({
                method: "POST",
                url: "/api/cart/",
                body: { Persons: [3] },
                headers: { "Content-Type": "application/json" },
            }).then((cartResp) => {
                expect(cartResp.status).to.eq(200);

                // Step 4 — reload so the page re-fetches the (now non-empty) cart.
                cy.visit("event/cart-to-event");
                cy.contains("Check In to Event");

                // Step 5 — verify the event we created shows up in the dropdown
                // and select it explicitly by id.
                cy.get(`#EventID option[value="${expectedEventId}"]`).should("exist");
                cy.get("#EventID").select(String(expectedEventId));

                // Step 6 — submit and verify the redirect lands on the
                // check-in page for the event we created.
                cy.get('button[name="Submit"]').click();
                cy.url().should("include", `/event/checkin/${expectedEventId}`);
            });
        });
    });
});
