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
        cy.get(".dt-search input").first().type("Admin");
        cy.get("#members tbody").contains("Admin").should("exist");
        
        // Clear and search for Joel
        cy.get(".dt-search input").first().clear().type("Joel");
        cy.get("#members tbody").contains("Joel").should("exist");
        
        // Clear and search for Emma
        cy.get(".dt-search input").first().clear().type("Emma");
        cy.get("#members tbody").contains("Emma").should("exist");
    });


   it("Listing all persons with gender url filter", () => {
        cy.visit("v2/people?Gender=0");
        cy.wait(500);
        
        // Search for Admin (male)
        cy.get(".dt-search input").first().type("Admin");
        cy.get("#members tbody").contains("Admin").should("exist");
        
        // Clear and search for Kennedy (male)
        cy.get(".dt-search input").first().clear().type("Kennedy");
        cy.get("#members tbody").contains("Kennedy").should("exist");
        
        // Clear search and verify no female entries appear in the filtered results
        cy.get(".dt-search input").first().clear().type("Emma");
        cy.get("#members tbody").should("not.contain", "Female");
    });


    it("Multiple filter combinations", () => {
        cy.visit("v2/people");

       cy.wait(500);

        // Apply gender filter using TomSelect
        cy.get(".filter-Gender").siblings(".ts-wrapper").find(".ts-control").click();
        cy.get(".filter-Gender").siblings(".ts-wrapper").find(".ts-dropdown .ts-option").contains("Female").click();

        // Apply classification filter using TomSelect
        cy.get(".filter-Classification").siblings(".ts-wrapper").find(".ts-control").click();
        cy.get(".filter-Classification").siblings(".ts-wrapper").find(".ts-dropdown .ts-option").contains("Member").click();

        // Table should show filtered results
        cy.get("#members tbody tr", { timeout: 5000 }).should("have.length.greaterThan", 0);

        // Clear all filters
        cy.get("#ClearFilter").click();

        // Verify filters are cleared
        cy.get(".filter-Gender").siblings(".ts-wrapper").find(".ts-control .item").should("not.exist");
    });
});
