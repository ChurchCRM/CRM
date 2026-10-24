/// <reference types="cypress" />

// Family 3 (Lewis) has an address and no coordinates; person 14 belongs to it.
// "Refresh Coordinates" calls POST /api/family/{id}/geocode, which needs Edit Records.
describe("Refresh Coordinates button permissions", () => {
    const pages = [
        ["family page", "/people/family/3"],
        ["person page", "/people/view/14"],
    ];

    pages.forEach(([label, path]) => {
        it(`shows on the ${label} for an admin`, () => {
            cy.setupPersonaSession("church-admin");
            cy.visit(path);
            cy.get("#refresh-coordinates-btn").should("be.visible");
        });

        it(`shows on the ${label} for a non-admin with Edit Records`, () => {
            cy.setupPersonaSession("staff-all-but-admin");
            cy.visit(path);
            cy.get("#refresh-coordinates-btn").should("be.visible");
        });

        it(`is hidden on the ${label} without Edit Records`, () => {
            cy.setupPersonaSession("notes-only-staff");
            cy.visit(path);
            cy.get("body").should("be.visible");
            cy.get("#refresh-coordinates-btn").should("not.exist");
        });
    });

    it("the geocode API refuses a user without Edit Records", () => {
        cy.personaAPICall("notes-only-staff", "POST", "/api/family/3/geocode", null, 403);
    });
});

describe("Update All Coordinates button on the map", () => {
    it("is hidden without Edit Records", () => {
        cy.setupPersonaSession("notes-only-staff");
        cy.visit("/people/map");
        cy.get("#map").should("exist");
        cy.get("#geocodeAllBtn").should("not.exist");
    });
});
