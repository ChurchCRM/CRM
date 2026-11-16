/// <reference types="cypress" />

describe("Standard Family List", () => {
    beforeEach(() => cy.setupStandardSession());

    it("lists families with their columns and row actions", () => {
        cy.visit("people/family");
        ["Actions", "Name", "Address", "Home Phone", "Email", "Created", "Edited"].forEach((column) => {
            cy.get("#families thead").contains(column);
        });
        cy.get("#families tbody tr").should("have.length.at.least", 1);
        cy.get("#families tbody tr:first").within(() => {
            cy.get('[data-bs-toggle="dropdown"], .dropdown-toggle, button[aria-expanded]').first().click();
        });
        cy.get(".dropdown-menu.show:not(.navbar-vertical .dropdown-menu)").within(() => {
            cy.contains("View").should("exist");
            cy.contains("Edit").should("exist");
            cy.get(".AddToCart, .RemoveFromCart").should("exist");
            cy.contains("Delete").should("exist");
        });
    });

    it("filters the list by a search term", () => {
        cy.visit("people/family");
        cy.get("#families tbody tr:first td:nth-child(1)").invoke("text").then((familyName) => {
            const searchTerm = familyName.trim().split(" ")[0];
            cy.get(".dt-search input").first().type(searchTerm);
            cy.get("#families tbody").contains(searchTerm).should("exist");
        });
    });
});
