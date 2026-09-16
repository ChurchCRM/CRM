/// <reference types="cypress" />

describe("Property Type Operations", () => {
    beforeEach(() => cy.setupAdminSession());

    it("PropertyTypeList page loads without errors", () => {
        cy.visit("PropertyTypeList.php");
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("body").should("not.contain", "Warning:");
    });

    it("PropertyEditor loads for new person property without errors", () => {
        cy.visit("PropertyEditor.php?Type=p");
        cy.contains("Property Editor");
        cy.get("body").should("not.contain", "Fatal error");
        cy.get('select[name="Class"]').should("exist");
        cy.get('input[name="Name"]').should("exist");
    });

    it("PropertyEditor loads for new family property without errors", () => {
        cy.visit("PropertyEditor.php?Type=f");
        cy.contains("Property Editor");
        cy.get("body").should("not.contain", "Fatal error");
        cy.get('select[name="Class"]').should("exist");
    });
});
