/// <reference types="cypress" />

describe("Standard User Setting", () => {
    beforeEach(() => cy.setupStandardSessionFromEnv());
    
    it("View User Setting and Edit Page", () => {
        cy.visit("/v2/user/3");
        cy.contains("User -");
        cy.get("#editSettings").click();
        cy.url().should("contain", "SettingsIndividual.php");
        cy.contains("My User Settings");
    });
});
