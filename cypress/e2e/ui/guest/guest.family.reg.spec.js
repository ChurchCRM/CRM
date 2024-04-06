/// <reference types="cypress" />

context("Family Reg", () => {
    it("Family of 2", () => {
        cy.visit("external/register/");
        cy.contains("Main St. Cathedral");

        cy.get("#familyName").type("Master");
        cy.get("#familyAddress1").clear().type("123 Main St.");
        cy.get("#familyZip").type("98001");
        cy.get("#familyCount").select("2");
        cy.get(".actions li:nth-child(2) > a").click();

        cy.get("#memberFirstName-1").type("Sr.");
        cy.get("#memberEmail-1").type("sr@mater.cmo");
        cy.get("#memberBirthday-1").type("8/7/1990");
        cy.get("#memberFirstName-2").click();

        cy.get("#memberFirstName-2").type("lady");
        cy.get("#memberEmail-2").type("lady@nower.com");
        cy.get("#memberBirthday-2").type("8/7/2000");
        cy.get("#memberHideAge-2").click();

        cy.get(".actions li:nth-child(2) > a").click();
        cy.get(".actions li:nth-child(3) > a").click();
        cy.get(".btn-default").click();
        cy.url().should("contains", "external/register/");
    });
});
