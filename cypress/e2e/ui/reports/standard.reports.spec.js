/// <reference types="cypress" />

describe("Standard Reports", () => {
    beforeEach(() => cy.setupStandardSessionFromEnv());
    
    it("Gen Newsletter Labels ", () => {
        cy.visit("LettersAndLabels.php");
        cy.contains("Letters and Mailing Labels");
        cy.contains("Newsletter labels");
    });

    it("Gen data labels", () => {
        cy.visit("LettersAndLabels.php");
        cy.contains("Letters and Mailing Labels");
        cy.contains("Confirm data labels");
    });
});
