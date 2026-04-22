    /// <reference types="cypress" />

describe("Admin Event", () => {

    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Event Types page loads", () => {
        cy.visit("event/types");
        cy.contains("Event Types");
        cy.contains("Add Event Type");
    });

    it("Add new event type form loads", () => {
        cy.visit("event/types/new");
        cy.contains("Add New");
        cy.get("#newEvtName").should("exist");
        cy.get('input[name="newEvtTypeRecur"]').should("have.length.at.least", 4);
    });

    it("Events Dashboard", () => {
        cy.visit("event/dashboard");
        cy.contains("Events Dashboard");
        cy.contains("Event Type");
        cy.contains("Add Event");
    });

    it("Create New Event", () => {
        cy.visit("event/editor");
        // After the unified-editor rewrite the page title comes from
        // PageHeader via the "Create Event" breadcrumb and the shared
        // form renders into #event-editor-mount.
        cy.contains("Create Event");
        cy.get("#event-title-input").should("be.visible");
    });
});
