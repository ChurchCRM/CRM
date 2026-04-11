/// <reference types="cypress" />

describe("Volunteer Opportunity Editor", () => {
    beforeEach(() => cy.setupAdminSession());

    it("loads the editor page without errors", () => {
        cy.visit("VolunteerOpportunityEditor.php");
        cy.contains("Volunteer Opportunity Editor");
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("body").should("not.contain", "Warning:");
        cy.get("body").should("not.contain", "BadMethodCallException");
    });

    it("can create a new volunteer opportunity", () => {
        cy.visit("VolunteerOpportunityEditor.php");
        cy.get("#newFieldName").type("Test Opportunity");
        cy.get("#newFieldDesc").type("Test description for volunteer opp");
        cy.get('button[name="AddField"]').click();

        cy.url().should("contain", "VolunteerOpportunityEditor.php");
        cy.get("body").should("not.contain", "Fatal error");
        cy.contains("Test Opportunity");
    });

    it("shows validation error for empty name", () => {
        cy.visit("VolunteerOpportunityEditor.php");
        cy.get("#newFieldName").clear();
        cy.get('button[name="AddField"]').click();

        cy.get("body").should("not.contain", "Fatal error");
        cy.contains("You must enter a name");
    });

    it("delete confirmation page loads without errors", () => {
        // First ensure there's at least one opportunity
        cy.visit("VolunteerOpportunityEditor.php");
        cy.get("#newFieldName").type("Delete Test Opp");
        cy.get("#newFieldDesc").type("Will be deleted");
        cy.get('button[name="AddField"]').click();
        cy.get("body").should("not.contain", "Fatal error");

        // Click delete on the last opportunity
        cy.get('a[href*="act=delete"]').last().click();

        // Should show delete confirmation page
        cy.contains("Confirm Volunteer Opportunity Deletion");
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("body").should("not.contain", "Warning:");
        cy.contains("Delete Test Opp");

        // Confirm deletion
        cy.get('button[type="submit"]').contains("Yes").click();
        cy.url().should("contain", "VolunteerOpportunityEditor.php");
        cy.get("body").should("not.contain", "Fatal error");
    });
});
