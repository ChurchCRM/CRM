/// <reference types="cypress" />

describe("Standard Calendar", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("Create New Event", () => {
        const title = "My New Event - " + Cypress._.random(0, 1e6);
        cy.visit("v2/calendar");
        cy.url().should("include", "v2/calendar");
        
        // Find and click on an empty calendar day (no events or birthdays)
        cy.get(".fc-daygrid-day").first().click();
        
        cy.get(".modal-header input").type(title);
        cy.typeInQuill("Desc", "New adult Service");
        cy.typeInQuill("Text", "Come join us");
       
    });
});
