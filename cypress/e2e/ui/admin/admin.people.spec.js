/// <reference types="cypress" />

describe("Admin People", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Person Classifications Editor", () => {
        cy.visit("OptionManager.php?mode=classes");
        cy.contains("Person Classifications Editor");
    });

    it("Family Roles Editor", () => {
        cy.visit("OptionManager.php?mode=famroles");
        cy.contains("Family Roles Editor");
    });

    it("Custom Family Fields Editor", () => {
        cy.visit("FamilyCustomFieldsEditor.php");
        cy.contains("Custom Family Fields Editor");
    });

    it("Custom Person Fields Editor", () => {
        cy.visit("PersonCustomFieldsEditor.php");
        cy.contains("Custom Person Fields Editor");
    });

    it("Volunteer Opportunity Editor", () => {
        cy.visit("VolunteerOpportunityEditor.php");
        cy.contains("Volunteer Opportunity Editor");
    });

    it("Family Property List", () => {
        cy.visit("PropertyList.php?Type=f");
        cy.contains("Family Property List");
        cy.get("p > .btn").click();
        cy.url().should("contain", "PropertyEditor.php");
        cy.get(".row:nth-child(1) .form-control").select("2");
        cy.get(".row:nth-child(2) .form-control").type("Test");
        cy.get(".row:nth-child(3) .form-control").type("Who");
        cy.get(".row:nth-child(4) .form-control").type("What do you want");
        cy.get("#save").click();
        cy.url().should("contain", "PropertyList.php");
    });

    it("Person Property List", () => {
        cy.visit("PropertyList.php?Type=p");
        cy.contains("Person Property List");
        cy.get("p > .btn").click();
        cy.url().should("contain", "PropertyEditor.php");
        cy.get(".row:nth-child(1) .form-control").select("1");
        cy.get(".row:nth-child(2) .form-control").type("Test");
        cy.get(".row:nth-child(3) .form-control").type("Who");
        cy.get(".row:nth-child(4) .form-control").type("What do you want");
        cy.get("#save").click();
        cy.url().should("contain", "PropertyList.php");
    });
});
