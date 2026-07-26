/// <reference types="cypress" />

/**
 * Group view — email composer button integration tests.
 *
 * The old email dropdown (which lazy-loaded via ajax) has been replaced by
 * a single "Email" button that opens the in-app email composer modal via
 * /api/groups/{id}/emails. This spec verifies the new behaviour:
 *  - The composer button is present in the group toolbar.
 *  - Clicking it opens the modal and fetches recipients.
 *  - The modal shows a recipient count badge and action buttons.
 *
 * Group 11 (Clergy) is used — persons 2 and 26 are seeded members
 * (confirmed in person2group2role_p2g2r seed data).
 */

describe("Group view — email composer button", () => {
    const CLERGY_GROUP_ID = 11;

    beforeEach(() => cy.setupAdminSession());

    it("shows an 'Email' composer button in the group toolbar", () => {
        cy.visit(`/groups/view/${CLERGY_GROUP_ID}`);
        cy.get(
            `[data-email-composer][data-email-endpoint='groups/${CLERGY_GROUP_ID}/emails']`,
        ).should("be.visible");
    });

    it("clicking Email opens the composer modal with recipients", () => {
        cy.visit(`/groups/view/${CLERGY_GROUP_ID}`);

        cy.intercept("GET", `**/api/groups/${CLERGY_GROUP_ID}/emails`).as("groupEmailsApi");

        cy.get(
            `[data-email-composer][data-email-endpoint='groups/${CLERGY_GROUP_ID}/emails']`,
        ).click();

        cy.wait("@groupEmailsApi").its("response.statusCode").should("eq", 200);

        // Modal appears
        cy.get("#crm-email-composer-modal").should("be.visible");

        // Title and action buttons are visible
        cy.get("#crm-email-composer-modal .modal-title").should("contain.text", "Email");
        cy.get("#crm-email-copy-btn").should("be.visible");
        cy.get("#crm-email-client-btn").should("be.visible");
    });

    it("modal shows a positive recipient count badge (group has seeded members)", () => {
        cy.visit(`/groups/view/${CLERGY_GROUP_ID}`);
        cy.intercept("GET", `**/api/groups/${CLERGY_GROUP_ID}/emails`).as("groupEmailsApi");

        cy.get(
            `[data-email-composer][data-email-endpoint='groups/${CLERGY_GROUP_ID}/emails']`,
        ).click();
        cy.wait("@groupEmailsApi");

        cy.get("#crm-email-composer-modal .modal-title .badge")
            .invoke("text")
            .then((text) => {
                expect(parseInt(text.trim(), 10)).to.be.at.least(1);
            });
    });
});
