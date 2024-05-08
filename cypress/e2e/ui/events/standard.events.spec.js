/// <reference types="cypress" />

context("Standard User Session", () => {
    it("Visit Event Attendance", () => {
        cy.loginStandard("EventAttendance.php");
        cy.contains("Church Service");
    });

    it("Visit Church Servers", () => {
        cy.loginStandard(
            "EventAttendance.php?Action=List&Event=1&Type=Church%20Service",
        );
        cy.contains("Christmas Service");
        cy.get("#Non-Attending-1").click();
        cy.contains("Berry, Miss Brianna");
    });

    it("View Event via URL", () => {
        cy.loginStandard("EditEventAttendees.php?eventId=3");
        cy.contains("Attendees for Event : Summer Camp");
    });

    it("View Event via Bad URL", () => {
        cy.loginStandard("EditEventAttendees.php", false);
        cy.contains("Listing All Church Events");
    });

    it("View Event via invalid URL id", () => {
        cy.loginStandard("EditEventAttendees.php?eventId=99999", false);
        cy.contains("Listing All Church Events");
    });

    it("CheckIn People", () => {
        cy.loginStandard("Checkin.php");
        cy.contains("Event Checkin");
        cy.get("#EventID").select("Summer Camp");
        cy.contains("Add Attendees for Event:");
    });
});
