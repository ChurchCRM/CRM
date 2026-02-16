/// <reference types="cypress" />

describe("Standard User Session", () => {
    beforeEach(() => cy.setupStandardSession());

    it("View Event Checkin via URL", () => {
        cy.visit("Checkin.php?eventId=3");
        cy.contains("Event Checkin");
        cy.contains("Summer Camp");
    });

    it("View Checkin page without event", () => {
        cy.visit("Checkin.php");
        cy.contains("Event Checkin");
        cy.contains("Select event");
    });

    it("CheckIn People", () => {
        cy.visit("Checkin.php");
        cy.contains("Event Checkin");
        cy.get("#EventID").select(3);
        cy.contains("Check In Person");
    });

    it("Filter events by type", () => {
        cy.visit("Checkin.php");
        cy.contains("Event Checkin");
        cy.get("#EventTypeFilter").should("exist");
    });
});
