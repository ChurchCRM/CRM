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
        cy.typeInQuill("quill-Desc", "New adult Service");
        cy.typeInQuill("quill-Text", "Come join us");
       
    });

    /**
     * Regression: QuillEditor toolbar duplication.
     * Verifies that interacting with other form fields (title, event type dropdown)
     * does not cause Quill to re-initialize and duplicate toolbars.
     * Each editor must have exactly one .ql-toolbar at all times.
     */
    it("Quill toolbars do not duplicate after form interactions", () => {
        cy.visit("v2/calendar");
        cy.url().should("include", "v2/calendar");

        cy.get(".fc-daygrid-day").first().click();

        // Wait for the modal and both Quill editors to be visible
        cy.get(".modal-header input").should("be.visible");
        cy.get(".ql-toolbar").should("have.length", 2);

        // Type a title — this re-renders the parent component
        cy.get(".modal-header input").type("Test event title");

        // Toolbar count must still be exactly 2 (one per editor)
        cy.get(".ql-toolbar").should("have.length", 2);

        // Opening the Event Type dropdown (TomSelect wraps the original select)
        cy.get("#eventTypeSelect").siblings(".ts-wrapper").find(".ts-control").click();

        // Toolbar count must still be exactly 2
        cy.get(".ql-toolbar").should("have.length", 2);
    });
});

