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

    /**
     * Regression: QuillEditor toolbar duplication on re-render.
     * When the user interacted with any other form field (e.g. Event Type dropdown),
     * the React parent re-rendered which changed the inline onChange reference,
     * causing the Quill useEffect to tear down and re-mount — appending an extra
     * toolbar to the same DOM node on every interaction.
     *
     * After the fix (useEffect with [] + onChangeRef), each editor must have
     * exactly one .ql-toolbar even after multiple parent re-renders.
     */
    it("Quill toolbars do not duplicate after parent re-renders", () => {
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

        // Opening the Event Type dropdown re-renders the form again
        cy.get("#EventType").click();

        // Toolbar count must still be exactly 2
        cy.get(".ql-toolbar").should("have.length", 2);
    });
});

