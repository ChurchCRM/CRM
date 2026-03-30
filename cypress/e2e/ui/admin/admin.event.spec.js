    /// <reference types="cypress" />

describe("Admin Event", () => {

    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Create New Event Type", () => {
        cy.visit("EventNames.php");
        cy.contains("Edit Event Types");
        cy.contains("Add Event Type");
    });

    it("Events Dashboard", () => {
        cy.visit("ListEvents.php");
        cy.contains("Events Dashboard");
        cy.contains("Event Type");
        cy.contains("Add Event");
    });

    it("Create New Event", () => {
        cy.visit("EventEditor.php");
        cy.contains("Church Event Editor");
        cy.contains("Create a new Event");
    });
});
