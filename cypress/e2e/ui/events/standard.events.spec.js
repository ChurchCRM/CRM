/// <reference types="cypress" />

describe("Standard User - Event Check-in", () => {
    beforeEach(() => cy.setupStandardSession());

    it("View Event Check-in via URL with event ID", () => {
        cy.visit("event/checkin/3");
        cy.contains("Event Check-in");
        // Direct event access shows event title in the info bar
        cy.get("body").should("contain.text", "Event:");
    });

    it("View Check-in page without event ID shows event selector", () => {
        cy.visit("event/checkin");
        cy.contains("Event Check-in");
        cy.contains("Select Event for Check-In");
    });

    it("Selecting an event shows the check-in form", () => {
        cy.visit("event/checkin");
        cy.get("#EventSelector").select(3);
        // Selecting an event navigates to /event/checkin/3
        cy.url().should("include", "/event/checkin/3");
        cy.contains("Check In Person");
    });

    it("Filter events by type dropdown exists", () => {
        cy.visit("event/checkin");
        cy.get("#EventTypeFilter").should("exist");
    });

    it("Walk-in check-in form has child and adult selectors", () => {
        cy.visit("event/checkin/3");
        cy.get("#child").should("exist");
        cy.get("#adult").should("exist");
        cy.get("#checkinBtn").should("exist");
    });
});
