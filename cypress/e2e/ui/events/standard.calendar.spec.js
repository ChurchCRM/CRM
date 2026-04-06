/// <reference types="cypress" />

describe("Standard Calendar", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Create New Event", () => {
        const title = "My New Event - " + Cypress._.random(0, 1e6);
        cy.visit("v2/calendar");
        cy.url().should("include", "v2/calendar");

        // Click an empty calendar day to trigger showNewEventForm
        cy.get(".fc-daygrid-day").first().click();

        // Wait for the edit modal to load (loading spinner replaced by form)
        cy.get("#event-title-input").should("be.visible").type(title);
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

        // Wait for the modal edit form to load
        cy.get("#event-title-input").should("be.visible");
        cy.get(".ql-toolbar").should("have.length", 2);

        // Type a title
        cy.get("#event-title-input").type("Test event title");

        // Toolbar count must still be exactly 2 (one per editor)
        cy.get(".ql-toolbar").should("have.length", 2);

        // Opening the Event Type dropdown (TomSelect wraps the original select)
        cy.get("#eventTypeSelect").siblings(".ts-wrapper").find(".ts-control").click();

        // Toolbar count must still be exactly 2
        cy.get(".ql-toolbar").should("have.length", 2);
    });

    it("Save button is disabled until required fields are filled", () => {
        cy.visit("v2/calendar");

        cy.get(".fc-daygrid-day").first().click();
        cy.get("#event-title-input").should("be.visible");

        // Save button starts disabled (no title, no calendars selected)
        cy.get("#eventSaveBtn").should("be.disabled");

        // Type a title — still disabled (no calendar selected)
        cy.get("#event-title-input").type("Validation Test Event");
        cy.get("#eventSaveBtn").should("be.disabled");

        // Select a calendar — save should now be enabled
        // (start/end dates are pre-filled from the calendar click)
        cy.tomSelectByValue("#pinnedCalendarsSelect", "1");
        cy.get("#eventSaveBtn").should("not.be.disabled");

        // Clear the title — should be disabled again
        cy.get("#event-title-input").clear();
        cy.get("#eventSaveBtn").should("be.disabled");
    });

    it("All-day toggle switches date input types", () => {
        cy.visit("v2/calendar");

        cy.get(".fc-daygrid-day").first().click();
        cy.get("#event-title-input").should("be.visible");

        // Default should be all-day (calendar day click gives midnight-midnight)
        cy.get("#eventStartDate").should("have.attr", "type", "date");
        cy.get("#eventEndDate").should("have.attr", "type", "date");

        // Switch to timed
        cy.get('input[name="eventDayType"][value="timed"]').check({ force: true });
        cy.get("#eventStartDate").should("have.attr", "type", "datetime-local");
        cy.get("#eventEndDate").should("have.attr", "type", "datetime-local");

        // Switch back to all-day
        cy.get('input[name="eventDayType"][value="allday"]').check({ force: true });
        cy.get("#eventStartDate").should("have.attr", "type", "date");
        cy.get("#eventEndDate").should("have.attr", "type", "date");
    });

    it("Delete button is hidden for new events", () => {
        cy.visit("v2/calendar");

        cy.get(".fc-daygrid-day").first().click();
        cy.get("#event-title-input").should("be.visible");

        // Delete button should be hidden for new (unsaved) events
        cy.get("#eventDeleteBtn").should("have.class", "d-none");
    });

    it("Modal closes on Cancel button click", () => {
        cy.visit("v2/calendar");

        cy.get(".fc-daygrid-day").first().click();
        cy.get("#event-title-input").should("be.visible");

        // Click Cancel — modal should close
        cy.get(".modal-footer .btn-secondary").click();
        cy.get("#eventEditorModal").should("not.be.visible");
    });

    it("Opening a second modal cleanly replaces the first", () => {
        cy.visit("v2/calendar");

        // Open first modal
        cy.get(".fc-daygrid-day").first().click();
        cy.get("#event-title-input").should("be.visible").type("First Event");

        // Close it
        cy.get(".modal-footer .btn-secondary").click();
        cy.get("#eventEditorModal").should("not.be.visible");

        // Open second modal on a different day
        cy.get(".fc-daygrid-day").eq(2).click();
        cy.get("#event-title-input").should("be.visible");

        // Title should be empty (new event, not the first one)
        cy.get("#event-title-input").should("have.value", "");

        // Only one modal should exist
        cy.get("#eventEditorModal").should("have.length", 1);
    });

    it("TomSelect dropdowns initialize correctly", () => {
        cy.visit("v2/calendar");

        cy.get(".fc-daygrid-day").first().click();
        cy.get("#event-title-input").should("be.visible");

        // Event type TomSelect should be initialized
        cy.get("#eventTypeSelect").siblings(".ts-wrapper").should("exist");
        cy.get("#eventTypeSelect").siblings(".ts-wrapper").find(".ts-control").should("be.visible");

        // Pinned calendars TomSelect should be initialized
        cy.get("#pinnedCalendarsSelect").siblings(".ts-wrapper").should("exist");
        cy.get("#pinnedCalendarsSelect").siblings(".ts-wrapper").find(".ts-control").should("be.visible");
    });
});
