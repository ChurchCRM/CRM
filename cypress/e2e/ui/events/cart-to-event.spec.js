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
        // Step 1 — make sure an event exists. DemoData doesn't seed any events,
        // so we need to create one via the API before the form has anything to
        // select. cy.makePrivateAdminAPICall resets the PHP session, so we
        // re-establish the browser session immediately after.
        cy.makePrivateAdminAPICall("GET", "/api/events/types", null, 200).then((typesResp) => {
            const types = Array.isArray(typesResp.body)
                ? typesResp.body
                : Object.values(typesResp.body);
            expect(types.length, "at least one event type must be seeded").to.be.greaterThan(0);
            expect(types[0]).to.have.property("Id");

            cy.makePrivateAdminAPICall(
                "POST",
                "/api/events/quick-create",
                { eventTypeId: types[0].Id },
                200,
            ).then((createResp) => {
                const expectedEventId = createResp.body.eventId;
                expect(expectedEventId, "quick-create returned an eventId").to.be.a("number");

                // Step 2 — re-establish the browser session, then add a person
                // to the cart via a synthetic XHR from inside the same window
                // so the session cookie sticks for the next cy.visit().
                cy.setupAdminSession({ forceLogin: true });
                cy.visit("/");
                cy.window().then((win) => {
                    return new Cypress.Promise((resolve) => {
                        const xhr = new win.XMLHttpRequest();
                        xhr.open("POST", "/api/cart/", true);
                        xhr.setRequestHeader("Content-Type", "application/json");
                        xhr.onload = () => resolve();
                        xhr.onerror = () => resolve();
                        xhr.send(JSON.stringify({ Persons: [3] }));
                    });
                });

                // Step 3 — load the cart-to-event page. "Check In to Event"
                // text only renders when the cart is non-empty, so this also
                // verifies the cart was actually populated above.
                cy.visit("event/cart-to-event");
                cy.contains("Check In to Event");

                // Step 4 — verify the event we created shows up in the dropdown
                // and select it explicitly by id (not by index, which would
                // pick a different event if the seed grows).
                cy.get(`#EventID option[value="${expectedEventId}"]`).should("exist");
                cy.get("#EventID").select(String(expectedEventId));

                // Step 5 — submit and verify the redirect lands on the
                // check-in page for the event we created.
                cy.get('button[name="Submit"]').click();
                cy.url().should("include", `/event/checkin/${expectedEventId}`);
            });
        });
    });
});
