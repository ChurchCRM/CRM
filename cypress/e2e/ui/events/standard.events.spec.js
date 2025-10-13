/// <reference types="cypress" />

describe("Standard User Session", () => {
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

    it("View Event 3 in EventEditor via ListEvents", () => {
        // Step 1: Visit ListEvents.php
        cy.loginStandard("ListEvents.php");
        
        // Step 2: Change the year dropdown to 2017
        // The form auto-submits when the dropdown changes
        cy.get('select[name="WhichYear"]').should('exist');
        cy.get('select[name="WhichYear"]').select('2017');
        
        // Wait for the page to reload after auto-submit
        cy.wait(1500);
        
        // Step 3: Should show "Summer Camp Summer Camp"
        cy.contains("Summer Camp Summer Camp");
        
    
    });
});
