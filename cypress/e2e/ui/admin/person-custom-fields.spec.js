/// <reference types="cypress" />

describe("Person Custom Fields", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should load Custom Person Fields Editor without errors", () => {
        cy.visit("PersonCustomFieldsEditor.php");
        cy.contains("Custom Person Fields Editor").should("exist");
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("body").should("not.contain", "TypeError");
        cy.get("body").should("not.contain", "BadMethodCallException");
    });

    it("should save existing person custom fields without crashing", () => {
        cy.visit("PersonCustomFieldsEditor.php");

        // Only test save if fields exist (Save Changes button is present)
        cy.get("body").then(($body) => {
            if ($body.find('button[name="SaveChanges"]').length > 0) {
                cy.get('button[name="SaveChanges"]').click();
                // After save, page reloads — verify no fatal errors
                cy.get("body").should("not.contain", "Fatal error");
                cy.get("body").should("not.contain", "BadMethodCallException");
                cy.contains("Custom Person Fields Editor").should("exist");
            }
        });
    });
});

describe("Family Custom Fields", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should load Custom Family Fields Editor without errors", () => {
        cy.visit("FamilyCustomFieldsEditor.php");
        cy.contains("Custom Family Fields Editor").should("exist");
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("body").should("not.contain", "TypeError");
        cy.get("body").should("not.contain", "BadMethodCallException");
    });

    it("should save existing family custom fields without crashing", () => {
        cy.visit("FamilyCustomFieldsEditor.php");

        // Only test save if fields exist (Save Changes button is present)
        cy.get("body").then(($body) => {
            if ($body.find('button[name="SaveChanges"]').length > 0) {
                cy.get('button[name="SaveChanges"]').click();
                // After save, page reloads — verify no fatal errors
                cy.get("body").should("not.contain", "Fatal error");
                cy.get("body").should("not.contain", "BadMethodCallException");
                cy.contains("Custom Family Fields Editor").should("exist");
            }
        });
    });
});
