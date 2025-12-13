/// <reference types="cypress" />

describe("Standard Family List", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Listing all families shows correct columns", () => {
        cy.visit("v2/family");
        cy.wait(500);
        
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
        cy.wait(500);
        
        // Verify there are rows in the table
        cy.get("#families tbody tr").should("have.length.at.least", 1);
        
        // Verify action buttons exist
        cy.get("#families tbody tr:first .btn-info").should("exist"); // View button
        cy.get("#families tbody tr:first .btn-warning").should("exist"); // Edit button
    });

    it("Family list search works", () => {
        cy.visit("v2/family");
        cy.wait(500);
        
        // Get the first family name from the table and search for it
        cy.get("#families tbody tr:first td:nth-child(2)").invoke("text").then((familyName) => {
            const searchTerm = familyName.trim().split(" ")[0]; // Get first word of family name
            cy.get("#families_filter input[type='search']").type(searchTerm);
            cy.get("#families tbody").contains(searchTerm).should("exist");
        });
    });
});
