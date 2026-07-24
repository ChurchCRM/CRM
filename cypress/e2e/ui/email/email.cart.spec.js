/// <reference types="cypress" />

/**
 * Cart view — email composer button integration tests.
 *
 * Key design rule: cart setup MUST use the browser's session cookie, NOT X-API-Key.
 * The cart is stored in $_SESSION['aPeopleCart']; requests made with X-API-Key and
 * withCredentials:false populate a *different* PHP session than the one the browser
 * holds, so the UI cart would always appear empty.
 * Pattern follows cypress/e2e/ui/people/standard.cart-to-family.spec.js.
 *
 * The old To/BCC mailto: btn-group has been replaced by a single "Email"
 * button that opens the in-app email composer modal via /api/cart/emails.
 */

/** Add persons to the cart using the browser's session cookie (no API key). */
const addToCart = (personIds) =>
    cy.request({
        method: "POST",
        url: "/api/cart/",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({ Persons: personIds }),
        failOnStatusCode: false,
    }).then((resp) => expect(resp.status).to.equal(200));

/** Empty the cart using the browser's session cookie. */
const emptyCart = () =>
    cy.request({
        method: "DELETE",
        url: "/api/cart/",
        headers: { "content-type": "application/json" },
        body: {},
        failOnStatusCode: false,
    });

describe("Cart view — email composer button", () => {
    beforeEach(() => {
        // setupAdminSession() restores a cached browser session with the correct
        // PHP session cookie. All subsequent cy.request() calls without an API key
        // will share this session, so cart state is consistent with what the
        // browser sees when cy.visit() is called.
        cy.setupAdminSession();
        // Empty the cart so each test starts clean, then add person 2 (Mathew Campbell).
        emptyCart();
        addToCart([2]);
    });

    afterEach(() => {
        // Clean up the cart after each test for isolation.
        emptyCart();
    });

    it("shows a single 'Email' composer button (not a mailto: link)", () => {
        cy.visit("/v2/cart");
        cy.get("[data-email-composer][data-email-endpoint='cart/emails']").should("be.visible");
        // No old-style mailto To/BCC btn-group
        cy.get("a[href^='mailto:']").should("not.exist");
    });

    it("clicking Email opens the composer modal with recipients", () => {
        cy.visit("/v2/cart");

        cy.intercept("GET", "**/api/cart/emails").as("cartEmailsApi");

        cy.get("[data-email-composer][data-email-endpoint='cart/emails']").click();

        cy.wait("@cartEmailsApi").its("response.statusCode").should("eq", 200);

        // Modal appears
        cy.get("#crm-email-composer-modal").should("be.visible");

        // Title and action buttons are visible
        cy.get("#crm-email-composer-modal .modal-title").should("contain.text", "Email");
        cy.get("#crm-email-copy-btn").should("be.visible");
        cy.get("#crm-email-client-btn").should("be.visible");
    });

    it("modal shows a positive recipient count badge", () => {
        cy.visit("/v2/cart");
        cy.intercept("GET", "**/api/cart/emails").as("cartEmailsApi");

        cy.get("[data-email-composer][data-email-endpoint='cart/emails']").click();
        cy.wait("@cartEmailsApi");

        cy.get("#crm-email-composer-modal .modal-title .badge")
            .invoke("text")
            .then((text) => {
                expect(parseInt(text.trim(), 10)).to.be.at.least(1);
            });
    });
});
