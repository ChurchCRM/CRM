/// <reference types="cypress" />

context("Admin People", () => {
    it("Person Classifications Editor", () => {
        cy.loginAdmin("OptionManager.php?mode=classes");
        cy.contains("Person Classifications Editor");
    });

    it("Family Roles Editor", () => {
        cy.loginAdmin("OptionManager.php?mode=famroles");
        cy.contains("Family Roles Editor");
    });

    it("Custom Family Fields Editor", () => {
        cy.loginAdmin("FamilyCustomFieldsEditor.php");
        cy.contains("Custom Family Fields Editor");
    });

    it("Custom Person Fields Editor", () => {
        cy.loginAdmin("PersonCustomFieldsEditor.php");
        cy.contains("Custom Person Fields Editor");
    });

    it("Volunteer Opportunity Editor", () => {
        cy.loginAdmin("VolunteerOpportunityEditor.php");
        cy.contains("Volunteer Opportunity Editor");
    });

    it("Family Property List", () => {
        cy.loginAdmin("PropertyList.php?Type=f");
        cy.contains("Family Property List");
        cy.get("p > .btn").click();
        cy.url().should("contains", "PropertyEditor.php");
        cy.get(".row:nth-child(1) .form-control").select("2");
        cy.get(".row:nth-child(2) .form-control").type("Test");
        cy.get(".row:nth-child(3) .form-control").type("Who");
        cy.get(".row:nth-child(4) .form-control").type("What do you want");
        cy.get("#save").click();
        cy.url().should("contains", "PropertyList.php");
    });

    it("Person Property List", () => {
        cy.loginAdmin("PropertyList.php?Type=p");
        cy.contains("Person Property List");
        cy.get("p > .btn").click();
        cy.url().should("contains", "PropertyEditor.php");
        cy.get(".row:nth-child(1) .form-control").select("1");
        cy.get(".row:nth-child(2) .form-control").type("Test");
        cy.get(".row:nth-child(3) .form-control").type("Who");
        cy.get(".row:nth-child(4) .form-control").type("What do you want");
        cy.get("#save").click();
        cy.url().should("contains", "PropertyList.php");
    });
});
