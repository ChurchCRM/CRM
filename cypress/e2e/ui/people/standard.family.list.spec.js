/// <reference types="cypress" />

describe("Standard Family List", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Listing all families shows correct columns", () => {
        cy.visit("v2/family");
        
        // Verify the table headers are present
        cy.get("#families thead th").should("have.length.at.least", 6);
        cy.get("#families thead").contains("Actions");
        cy.get("#families thead").contains("Name");
        cy.get("#families thead").contains("Address");
        cy.get("#families thead").contains("Home Phone");
        cy.get("#families thead").contains("Email");
        cy.get("#families thead").contains("Created");
        cy.get("#families thead").contains("Edited");
    });

    it("Family list displays family data", () => {
        cy.visit("v2/family");

        // Verify there are rows in the table
        cy.get("#families tbody tr").should("have.length.at.least", 1);

        // Verify action dropdown exists (Tabler standard: dropdown toggle)
        cy.get("#families tbody tr:first").within(() => {
            cy.get('[data-bs-toggle="dropdown"], .dropdown-toggle, button[aria-expanded]').first().should('exist');
            // Open the dropdown and verify View, Edit, Cart, Delete items are present
            cy.get('[data-bs-toggle="dropdown"], .dropdown-toggle, button[aria-expanded]').first().click();
        });
        cy.get(".dropdown-menu.show").within(() => {
            cy.contains("View").should("exist");
            cy.contains("Edit").should("exist");
            cy.get(".AddToCart, .RemoveFromCart").should("exist");
            cy.contains("Delete").should("exist");
        });
    });

    it("Family list search works", () => {
        cy.visit("v2/family");
        
        // Get the first family name from the table and search for it
        cy.get("#families tbody tr:first td:nth-child(1)").invoke("text").then((familyName) => {
            const searchTerm = familyName.trim().split(" ")[0]; // Get first word of family name
            cy.get(".dt-search input").first().type(searchTerm);
            cy.get("#families tbody").contains(searchTerm).should("exist");
        });
    });
});
