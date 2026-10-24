/// <reference types="cypress" />

/**
 * UI test for PropertyList.php, the admin-only property catalogue.
 *
 * Requires: Docker / local environment with seeded data.
 */
describe("UI: Property List (PropertyList.php)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("loads PropertyList for groups", () => {
        cy.visit("/PropertyList.php?Type=g");
        cy.contains("Group Property List");
    });
});
