/// <reference types="cypress" />

describe("Photo Gallery Page", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Photo Directory page loads and filters work", () => {
        // Test page load
        cy.visit("v2/people/photos");
        cy.contains("Photo Directory");
        cy.get("#photo-grid");

        // Test photosOnly filter with All Classifications (the bug fix)
        cy.visit("v2/people/photos?photosOnly=1");
        cy.get("#photosOnly").should("be.checked");
        cy.get("#photo-grid");

        // Test reset filters
        cy.contains("Reset Filters").click();
        cy.url().should("not.include", "photosOnly=");
    });
});
