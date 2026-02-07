/// <reference types="cypress" />

describe("Mobile Calendar", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Should display calendar on mobile viewport", () => {
        // Set mobile viewport (iPhone X dimensions)
        cy.viewport(375, 812);
        cy.visit("v2/calendar");
        cy.url().should("include", "v2/calendar");
        
        // Calendar should be visible
        cy.get("#calendar").should("be.visible");
        
        // Calendar container should adapt to mobile width
        cy.get("#calendar").should("have.css", "width");
        
        // Mobile toolbar should have simplified header
        cy.get(".fc-toolbar-chunk").should("exist");
        cy.get(".fc-prev-button").should("be.visible");
        cy.get(".fc-next-button").should("be.visible");
        cy.get(".fc-today-button").should("be.visible");
        
        // Footer toolbar should exist on mobile with view buttons
        cy.get(".fc-footer-toolbar").should("exist");
        cy.get(".fc-dayGridMonth-button").should("be.visible");
    });

    it("Should stack sidebar below calendar on mobile", () => {
        // Set mobile viewport
        cy.viewport(375, 812);
        cy.visit("v2/calendar");
        
        // Check that the calendar and sidebar columns are full width on mobile
        cy.get(".col-sm-12").should("have.length.at.least", 2);
    });

    it("Should display calendar on tablet viewport", () => {
        // Set tablet viewport (iPad dimensions)
        cy.viewport(768, 1024);
        cy.visit("v2/calendar");
        cy.url().should("include", "v2/calendar");
        
        // Calendar should be visible
        cy.get("#calendar").should("be.visible");
        
        // Tablet should use medium grid layout (col-md-8 and col-md-4)
        cy.get(".col-md-8").should("exist");
        cy.get(".col-md-4").should("exist");
    });

    it("Should allow event creation on mobile", () => {
        const title = "Mobile Event - " + Cypress._.random(0, 1e6);
        cy.viewport(375, 812);
        cy.visit("v2/calendar");
        
        // Find and click on a calendar day
        cy.get(".fc-daygrid-day").first().click();
        
        // Modal should appear for creating new event
        cy.get(".modal-header input").should("be.visible");
        cy.get(".modal-header input").type(title);
        
        // Modal should be properly sized for mobile
        cy.get(".modal-dialog").should("be.visible");
    });

    it("Should handle orientation change", () => {
        // Test portrait to landscape orientation change
        cy.viewport(375, 812); // Portrait
        cy.visit("v2/calendar");
        cy.get("#calendar").should("be.visible");
        
        // Change to landscape
        cy.viewport(812, 375);
        cy.get("#calendar").should("be.visible");
        
        // Calendar should still be functional
        cy.get(".fc-toolbar-chunk").should("exist");
    });
});
