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
        
        // Search for Admin
        cy.get("#members_filter input[type='search']").type("Admin");
        cy.get("#members tbody").contains("Admin").should("exist");
        
        // Clear and search for Joel
        cy.get("#members_filter input[type='search']").clear().type("Joel");
        cy.get("#members tbody").contains("Joel").should("exist");
        
        // Clear and search for Emma
        cy.get("#members_filter input[type='search']").clear().type("Emma");
        cy.get("#members tbody").contains("Emma").should("exist");
    });


   it("Listing all persons with gender url filter", () => {
        cy.visit("v2/people?Gender=0");
        cy.wait(500);
        
        // Search for Admin (male)
        cy.get("#members_filter input[type='search']").type("Admin");
        cy.get("#members tbody").contains("Admin").should("exist");
        
        // Clear and search for Kennedy (male)
        cy.get("#members_filter input[type='search']").clear().type("Kennedy");
        cy.get("#members tbody").contains("Kennedy").should("exist");
        
        // Clear search and verify Emma (female) is not in filtered results
        cy.get("#members_filter input[type='search']").clear().type("Emma");
        cy.get("#members tbody").contains("Emma").should("not.exist");
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
