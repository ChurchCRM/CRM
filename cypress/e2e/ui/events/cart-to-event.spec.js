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
        // Establish browser session, then add a person to the cart from a real
        // browser context (not cy.request, which resets the PHP session). We
        // visit a page that always renders, then POST to /api/cart/ via a
        // synthetic XHR from inside the same window so the cookie/session
        // sticks for the subsequent /event/cart-to-event visit.
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

        cy.visit("event/cart-to-event");
        cy.contains("Check In to Event");

        // Pick the first real (non-placeholder) event option by id, not by index,
        // so the test isn't sensitive to seed data IDs.
        cy.get("#EventID option").then(($options) => {
            const realOption = [...$options].find((o) => o.value && o.value !== "");
            if (!realOption) {
                cy.log("No events seeded — skipping submit assertion");
                return;
            }
            cy.get("#EventID").select(realOption.value);
            cy.get('button[name="Submit"]').click();
            cy.url().should("include", "/event/checkin/");
        });
    });
});
