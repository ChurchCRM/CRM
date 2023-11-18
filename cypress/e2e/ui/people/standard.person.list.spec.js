/// <reference types="cypress" />

context("Standard People", () => {
    it("Listing all persons", () => {
        cy.loginStandard("v2/people");
        cy.contains("Admin");
        cy.contains("Church");
        cy.contains("Joel");
        cy.contains("Emma");
    });

    it("Listing all persons with gender filter", () => {
        cy.loginStandard("v2/people?Gender=0");
        cy.contains("Admin");
        cy.contains("Church");
        cy.contains("Kennedy");
        cy.contains("Judith");
        cy.contains("Emma").should("not.exist");
    });

    it("Person Not Found", () => {
        cy.loginStandard("PersonView.php?PersonID=9999", false);
        cy.location("pathname").should("include", "person/not-found");
        cy.contains("Oops! PERSON 9999 Not Found");
    });
});
