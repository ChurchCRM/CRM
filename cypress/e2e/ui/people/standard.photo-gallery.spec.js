/// <reference types="cypress" />

describe("Photo Gallery Page", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Photo Directory page loads successfully", () => {
        cy.visit("v2/people/photos");
        cy.contains("Photo Directory").should("exist");
        cy.get("#photo-grid").should("exist");
    });

    it("Filter by classification works", () => {
        cy.visit("v2/people/photos");
        cy.get("#classification").select("Member");
        cy.url().should("include", "classification=");
        cy.contains("Photo Directory").should("exist");
    });

    it("Show only people with photos filter works", () => {
        cy.visit("v2/people/photos?photosOnly=1");
        cy.get("#photosOnly").should("be.checked");
        cy.contains("Photo Directory").should("exist");
    });

    it("Both filters combined work with All Classifications", () => {
        // This tests the bug fix - photosOnly should work with All Classifications
        cy.visit("v2/people/photos?photosOnly=1");
        cy.get("#classification").should("have.value", "");
        cy.get("#photosOnly").should("be.checked");
        // Should not show "No people found" if there are people with photos
        cy.get("#photo-grid").should("exist");
    });

    it("Reset Filters clears all filters", () => {
        cy.visit("v2/people/photos?classification=1&photosOnly=1");
        cy.contains("Reset Filters").click();
        cy.url().should("include", "/v2/people/photos");
        cy.url().should("not.include", "classification=");
        cy.url().should("not.include", "photosOnly=");
    });
});
