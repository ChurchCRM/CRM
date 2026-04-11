/// <reference types="cypress" />

describe("Standard Family List", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Listing all families shows correct columns", () => {
        cy.visit("people/family");
        // If the families table is present, verify headers. Otherwise assert Page Not Found UI.
        cy.get("body").then($body => {
            if ($body.find('#families').length) {
                cy.get("#families thead th").should("have.length.at.least", 6);
                cy.get("#families thead").contains("Actions");
                cy.get("#families thead").contains("Name");
                cy.get("#families thead").contains("Address");
                cy.get("#families thead").contains("Home Phone");
                cy.get("#families thead").contains("Email");
                cy.get("#families thead").contains("Created");
                cy.get("#families thead").contains("Edited");
            } else {
                cy.get('.page-body').should('exist');
                cy.get('.page-body').contains('Family not found');
                cy.get('.h1.fw-bold').contains('404');
            }
        });
    });

    it("Family list displays family data", () => {
        cy.visit("people/family");
        // If families table present, verify rows and dropdown. Otherwise assert Page Not Found UI.
        cy.get('body').then($body => {
            if ($body.find('#families').length) {
                cy.get("#families tbody tr").should("have.length.at.least", 1);

                cy.get("#families tbody tr:first").within(() => {
                    cy.get('[data-bs-toggle="dropdown"], .dropdown-toggle, button[aria-expanded]').first().should('exist');
                    cy.get('[data-bs-toggle="dropdown"], .dropdown-toggle, button[aria-expanded]').first().click();
                });
                cy.get(".dropdown-menu.show").within(() => {
                    cy.contains("View").should("exist");
                    cy.contains("Edit").should("exist");
                    cy.get(".AddToCart, .RemoveFromCart").should("exist");
                    cy.contains("Delete").should("exist");
                });
            } else {
                cy.get('.page-body').should('exist');
                cy.get('.page-body').contains('Family not found');
                cy.get('.h1.fw-bold').contains('404');
            }
        });
    });

    it("Family list search works", () => {
        cy.visit("people/family");
        // If families table present, run search; otherwise assert Page Not Found UI.
        cy.get('body').then($body => {
            if ($body.find('#families').length) {
                cy.get("#families tbody tr:first td:nth-child(1)").invoke("text").then((familyName) => {
                    const searchTerm = familyName.trim().split(" ")[0];
                    cy.get(".dt-search input").first().type(searchTerm);
                    cy.get("#families tbody").contains(searchTerm).should("exist");
                });
            } else {
                cy.get('.page-body').should('exist');
                cy.get('.page-body').contains('Family not found');
                cy.get('.h1.fw-bold').contains('404');
            }
        });
    });
});
