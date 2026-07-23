/// <reference types="cypress" />

/**
 * Cart view — email composer button integration tests.
 *
 * The old To/BCC mailto: btn-group has been replaced by a single "Email"
 * button that opens the in-app email composer modal via /api/cart/emails.
 * This spec verifies the new behaviour:
 *  - The composer button is rendered (no old-style mailto links).
 *  - Clicking it opens the modal and waits for the API response.
 *  - The modal shows a recipient count badge and Copy/Open action buttons.
 *
 * Seed: person 2 (Mathew Campbell) is added to the cart in before()
 * so the API returns at least one recipient.
 */

describe("Cart view — email composer button", () => {
    before(() => {
        // Add person 2 to the cart so /api/cart/emails returns recipients
        cy.request({
            method: "POST",
            url: "/api/person/2/addToCart",
            headers: { "x-api-key": Cypress.env("CRM_ADMIN_API_KEY") ?? "" },
        });
    });

    after(() => {
        // Clean up: remove person 2 from cart
        cy.request({
            method: "DELETE",
            url: "/api/cart/",
            headers: { "x-api-key": Cypress.env("CRM_ADMIN_API_KEY") ?? "" },
            body: { Persons: [2] },
        });
    });

    beforeEach(() => cy.setupAdminSession());

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
