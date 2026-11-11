/// <reference types="cypress" />

describe("Email Pages", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Duplicate Emails", () => {
        cy.visit("people/email/duplicate");
        cy.contains("Duplicate Emails");
        // Wait for the DataTable AJAX call to complete before asserting row content
        cy.get("#dupEmails tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);
        cy.contains("boby@example.com");
    });

    it("People Without Emails lists people and filters them by age", () => {
        cy.visit("people/email/missing");
        cy.contains("h2", "People Without Emails").should("exist");
        cy.contains("People with no personal or work email on record").should("exist");
        cy.get(".breadcrumb").within(() => {
            cy.contains("Email").should("exist");
            cy.contains("People Without Emails").should("exist");
        });
        cy.get("#noEmails tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);
        cy.get("#personCount").should("not.have.class", "d-none").and("not.be.empty");

        // Adults filter
        cy.get("#ageFilter").contains("Adults").click();
        cy.get("#ageFilter").contains("Adults").should("have.class", "active");

        // Children filter
        cy.get("#ageFilter").contains("Children").click();
        cy.get("#ageFilter").contains("Children").should("have.class", "active");

        // Everyone resets to full list
        cy.get("#ageFilter").contains("Everyone").click();
        cy.get("#ageFilter").contains("Everyone").should("have.class", "active");
        cy.get("#noEmails tbody tr").should("have.length.greaterThan", 0);
    });
});
