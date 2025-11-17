/// <reference types="cypress" />

describe("Standard User Session", () => {
    beforeEach(() => cy.setupStandardSession());
    
    it("Visit Event Attendance", () => {
        cy.visit("EventAttendance.php");
        cy.contains("Church Service");
    });

    it("Visit Church Servers", () => {
        cy.visit(
            "EventAttendance.php?Action=List&Event=1&Type=Church%20Service",
        );
        cy.contains("Christmas Service");
        cy.get("#Non-Attending-1").click();
        cy.contains("Berry, Miss Brianna");
    });

    it("View Event via URL", () => {
        cy.visit("EditEventAttendees.php?eventId=3");
        cy.contains("Attendees for Event : Summer Camp");
    });

    it("View Event via Bad URL", () => {
        cy.visit("EditEventAttendees.php");
        cy.contains("Listing All Church Events");
    });

    it("View Event via invalid URL id", () => {
        cy.visit("EditEventAttendees.php?eventId=99999");
        cy.contains("Listing All Church Events");
    });

    it("CheckIn People", () => {
        cy.visit("Checkin.php");
        cy.contains("Event Checkin");
        cy.get("#EventID").select("Summer Camp");
        cy.contains("Add Attendees for Event:");
    });
});
