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

    it("shows validation error for empty name", () => {
        cy.visit("VolunteerOpportunityEditor.php");
        cy.get("#newFieldName").clear();
        cy.get('button[name="AddField"]').click();

        cy.get("body").should("not.contain", "Fatal error");
        cy.contains("You must enter a name");
    });
});
