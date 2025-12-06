/// <reference types="cypress" />

describe("Standard Family", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("Delete Person", () => {
        cy.visit("PersonView.php?PersonID=69");
        cy.get("#deletePersonBtn").click();
        cy.get(".bootbox-accept").should("be.visible").click();
        cy.url().should("contain", "v2/dashboard");
        cy.visit("PersonView.php?PersonID=69");
        cy.contains("Not Found: Person");
    });

    it("Delete Family", () => {
        cy.visit("v2/family/7");
        cy.get("#deleteFamilyBtn").click();
        cy.url().should("contain", "SelectDelete.php");
        cy.get("#deleteFamilyAndMembersBtn").click();
        cy.url().should("contain", "v2/family");
        cy.visit("v2/family/7");
        cy.contains("Not Found: Family");
    });
});
