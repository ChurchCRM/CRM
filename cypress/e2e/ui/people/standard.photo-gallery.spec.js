/// <reference types="cypress" />

describe("Photo Gallery Page", () => {
    beforeEach(() => cy.setupStandardSession());

    it("loads with photos-only selected by default", () => {
        cy.visit("v2/people/photos");
        cy.contains("Photo Directory");
        cy.get("#photosOnly-toggle").should("be.checked");
        // Docker has 1.jpg seeded — at least 1 person should appear
        cy.get("#photo-grid", { timeout: 10000 }).should("exist");
        cy.get("#photo-grid .card").should("have.length.at.least", 1);
    });

    it("shows more people when photos-only is unchecked", () => {
        cy.visit("v2/people/photos?photosOnly=0");
        cy.get("#photosOnly-toggle").should("not.be.checked");

        // All ~228 seeded people visible (no photo filter)
        cy.get("#photo-grid .card").should("have.length.greaterThan", 1);

        // Total badge count should exceed photos-only count (1)
        cy.get(".card-options .badge").invoke("text").then((text) => {
            expect(parseInt(text.trim())).to.be.greaterThan(1);
        });
    });

    it("filters by Unassigned classification", () => {
        cy.visit("v2/people/photos?photosOnly=0");

        cy.get("#classification-select").select("-1", { force: true });

        // URL should include classification=-1
        cy.url().should("include", "classification=-1");

        // photosOnly=0 should be preserved in URL
        cy.url().should("include", "photosOnly=0");

        // Page should render without error
        cy.get(".card-body").should("exist");
        cy.contains("Photo Directory");
    });

    it("per-page selector is respected and carries through pagination", () => {
        cy.visit("v2/people/photos?photosOnly=0&perPage=20");
        cy.get("#perpage-select").should("have.value", "20");

        // With 228+ people at 20/page there will be pagination
        cy.get(".pagination").should("exist");
        cy.get(".pagination .page-link").first().invoke("attr", "href").should("include", "perPage=20");
    });

    it("All per-page shows all people without pagination", () => {
        cy.visit("v2/people/photos?photosOnly=0&perPage=0");
        cy.get("#perpage-select").should("have.value", "0");
        // All people on one page — no pagination needed
        cy.get(".pagination").should("not.exist");
        cy.get("#photo-grid .card").should("have.length.greaterThan", 20);
    });

    it("reset button returns to default state", () => {
        cy.visit("v2/people/photos?photosOnly=0&perPage=20&classification=-1");
        cy.contains("Reset").click();
        cy.url().should("not.include", "photosOnly=");
        cy.url().should("not.include", "classification=");
        cy.url().should("not.include", "perPage=");
        // Default: photos-only is on
        cy.get("#photosOnly-toggle").should("be.checked");
    });
});
