/// <reference types="cypress" />

describe("Email Pages", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Email Dashboard", () => {
        cy.visit("v2/email/dashboard");
        cy.contains("Email Dashboard");
        cy.contains("People Without Emails");
    });

    it("Duplicate Emails", () => {
        cy.visit("v2/email/duplicate");
        cy.contains("Duplicate Emails");
        cy.contains("lady@nower.com");
    });

    it("People Without Emails page loads with breadcrumb and table", () => {
        cy.visit("v2/email/missing");

        // Page header
        cy.contains("h2", "People Without Emails").should("exist");
        cy.contains("People with no personal or work email on record").should("exist");

        // Breadcrumb
        cy.get(".breadcrumb").within(() => {
            cy.contains("Email").should("exist");
            cy.contains("People Without Emails").should("exist");
        });

        // DataTable loads rows
        cy.get("#noEmails tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);

        // Count badge is visible
        cy.get("#personCount").should("not.have.class", "d-none").and("not.be.empty");
    });

    it("People Without Emails age filter pills work", () => {
        cy.visit("v2/email/missing");
        cy.get("#noEmails tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);

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
