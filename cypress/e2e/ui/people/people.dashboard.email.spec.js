/// <reference types="cypress" />

/**
 * People Dashboard — email composer button integration tests.
 *
 * The old mailto: dropdown links have been replaced by a single
 * "Email All" button that opens the in-app email composer modal.
 * This spec verifies the new behaviour:
 *  - The composer button is rendered when email is enabled.
 *  - Clicking it opens the modal (waits for the /api/people/emails fetch).
 *  - The modal shows a recipient count and action buttons.
 *  - sToEmailAddress dedup logic is tested via the /api/people/emails API
 *    (see private.email-endpoints.spec.js for the API spec).
 */

describe("People Dashboard — email composer button", () => {
    beforeEach(() => cy.setupAdminSession());

    it("shows a single 'Email All' button (not a dropdown) when email is enabled", () => {
        cy.visit("/people/dashboard");
        // The button has the data-email-composer attribute
        cy.get("[data-email-composer][data-email-endpoint='people/emails']").should("be.visible");
        // No old-style dropdown toggles for email
        cy.contains(".dropdown-toggle", "Email All").should("not.exist");
        cy.contains(".dropdown-toggle", "Email BCC").should("not.exist");
    });

    it("clicking Email All opens the composer modal with recipients", () => {
        cy.visit("/people/dashboard");

        // Intercept the API call
        cy.intercept("GET", "**/api/people/emails").as("emailsApi");

        cy.get("[data-email-composer][data-email-endpoint='people/emails']").click();

        cy.wait("@emailsApi").its("response.statusCode").should("eq", 200);

        // Modal should appear
        cy.get("#crm-email-composer-modal").should("be.visible");

        // Should show the modal title and action buttons
        cy.get("#crm-email-composer-modal .modal-title").should("contain.text", "Email");
        cy.get("#crm-email-copy-btn").should("be.visible");
        cy.get("#crm-email-client-btn").should("be.visible");
    });

    it("modal shows a recipient count badge when people have emails", () => {
        cy.visit("/people/dashboard");
        cy.intercept("GET", "**/api/people/emails").as("emailsApi");

        cy.get("[data-email-composer][data-email-endpoint='people/emails']").click();
        cy.wait("@emailsApi");

        // The badge should show a positive number
        cy.get("#crm-email-composer-modal .modal-title .badge")
            .invoke("text")
            .then((text) => {
                expect(parseInt(text.trim(), 10)).to.be.at.least(1);
            });
    });
});
