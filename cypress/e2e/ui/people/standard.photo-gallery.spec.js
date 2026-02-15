/// <reference types="cypress" />

describe("Photo Gallery Page", () => {
    beforeEach(() => cy.setupStandardSessionFromEnv());

    it("Photo Directory page loads and filters work", () => {
        // Test page load
        cy.visit("v2/people/photos");
        cy.contains("Photo Directory");
        // Page should show either photo grid or "no results" message
        cy.get(".card-body").should("exist");

        // Test photosOnly filter with All Classifications (the bug fix)
        // This verifies the filter doesn't cause a server error (the original bug)
        cy.visit("v2/people/photos?photosOnly=1");
        cy.get("#photosOnly").should("be.checked");
        // Should show either photos or empty message - both are valid
        cy.get(".card-body").should("exist");

        // Test reset filters
        cy.contains("Reset Filters").click();
        cy.url().should("not.include", "photosOnly=");
    });
});
