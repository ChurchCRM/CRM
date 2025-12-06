/// <reference types="cypress" />

describe("Person Custom Fields", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("PersonCustomFieldsEditor.php");
    });

    it("should load Custom Person Fields Editor without errors", () => {
        // Verify the page loads successfully (tests the ORM changes work)
        cy.contains("Custom Person Fields Editor").should("exist");
        
        // Verify no fatal PHP errors - page should be functional
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("body").should("not.contain", "TypeError");
        cy.get("body").should("not.contain", "UnknownColumnException");
    });
});
