/// <reference types="cypress" />

describe("Standard People", () => {
    beforeEach(() => cy.setupStandardSession());
 
    it("Person Not Found", () => {
        cy.visit("PersonView.php?PersonID=9999");
        cy.location("pathname").should("include", "person/not-found");
        cy.contains("Oops! PERSON 9999 Not Found");
    });

    it("Listing all persons", () => {
        cy.visit("v2/people");
        cy.wait(500);
        cy.contains("Admin");
        cy.contains("Church");
        cy.contains("Joel");
        cy.contains("Emma");
    });


   it("Listing all persons with gender url filter", () => {
        cy.visit("v2/people?Gender=0");
        cy.wait(500);
        cy.contains("Admin");
        cy.contains("Church");
        cy.contains("Kennedy");
        cy.contains("Judith");
        cy.contains("Emma").should("not.exist");
    });


    it("Multiple filter combinations", () => {
        cy.visit("v2/people");
        
       cy.wait(500);

        // Apply gender filter using Select2
        cy.get(".filter-Gender").parent().find(".select2-selection").click();
        cy.get(".select2-results__option").contains("Female").click();
        
        // Apply classification filter using Select2
        cy.get(".filter-Classification").parent().find(".select2-selection").click();
        cy.get(".select2-results__option").contains("Member").click();
        
        // Table should show filtered results
        cy.get("#members tbody tr").should("have.length.greaterThan", 0);
        
        // Clear all filters
        cy.get("#ClearFilter").click();
    });
});
