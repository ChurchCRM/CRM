/// <reference types="cypress" />

describe("Standard Calendar", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Create New Event", () => {
        const title = "My New Event - " + Cypress._.random(0, 1e6);
        cy.visit("event/calendars");
        cy.url().should("include", "event/calendars");

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
        cy.visit("event/calendars");
        cy.url().should("include", "event/calendars");

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

    it("Save button is disabled until title is filled (calendar is optional)", () => {
        cy.visit("event/calendars");

        cy.get(".fc-daygrid-day").first().click();
        cy.get("#event-title-input").should("be.visible");

        // Save starts disabled with no title. Start/End are pre-filled from
        // the calendar-day click, and PinnedCalendars is optional — only the
        // Title field blocks save.
        cy.get("#eventSaveBtn").should("be.disabled");

        cy.get("#event-title-input").type("Validation Test Event");
        cy.get("#eventSaveBtn").should("not.be.disabled");

        // Empty-calendar hint is visible because nothing is pinned yet.
        cy.get("#calendarsEmptyHint").should("be.visible");

        // Clear the title — save disables again.
        cy.get("#event-title-input").clear();
        cy.get("#eventSaveBtn").should("be.disabled");
    });

    it("All-day toggle switches date input types", () => {
        cy.visit("event/calendars");

        cy.get(".fc-daygrid-day").first().click();
        cy.get("#event-title-input").should("be.visible");

        // Verify start/end date inputs exist
        cy.get("#eventStartDate").should("be.visible");
        cy.get("#eventEndDate").should("be.visible");

        // Switch to all-day (click parent label — Tabler hides the actual radio input)
        cy.get('input[name="eventDayType"][value="allday"]').parent("label").click();
        cy.get("#eventStartDate").should("have.attr", "type", "date");
        cy.get("#eventEndDate").should("have.attr", "type", "date");

        // Switch to timed
        cy.get('input[name="eventDayType"][value="timed"]').parent("label").click();
        cy.get("#eventStartDate").should("have.attr", "type", "datetime-local");
        cy.get("#eventEndDate").should("have.attr", "type", "datetime-local");
    });

    it("Delete button is hidden for new events", () => {
        cy.visit("event/calendars");

        cy.get(".fc-daygrid-day").first().click();
        cy.get("#event-title-input").should("be.visible");

        // Delete button should be hidden for new (unsaved) events
        cy.get("#eventDeleteBtn").should("have.class", "d-none");
    });

    it("Modal closes on Cancel button click", () => {
        cy.visit("event/calendars");

        cy.get(".fc-daygrid-day").first().click();
        cy.get("#event-title-input").should("be.visible");

        // Click Cancel — modal should close and be removed from DOM
        cy.get("#eventCancelBtn").click();
        cy.get("#eventEditorModal").should("not.exist");
    });

    it("Modal cleanup removes element and restores body state", () => {
        cy.visit("event/calendars");

        cy.get(".fc-daygrid-day").first().click();
        cy.get("#event-title-input").should("be.visible");

        // Close the modal
        cy.get("#eventCancelBtn").click();

        // Modal element should be fully removed from DOM after hidden transition
        cy.get("#eventEditorModal").should("not.exist");

        // Body should not retain modal-open class or backdrop
        cy.get("body").should("not.have.class", "modal-open");
        cy.get(".modal-backdrop").should("not.exist");
    });

    it("TomSelect dropdowns initialize correctly", () => {
        cy.visit("event/calendars");

        cy.get(".fc-daygrid-day").first().click();
        cy.get("#event-title-input").should("be.visible");

        // Event type TomSelect should be initialized
        cy.get("#eventTypeSelect").siblings(".ts-wrapper").should("exist");
        cy.get("#eventTypeSelect").siblings(".ts-wrapper").find(".ts-control").should("be.visible");

        // Pinned calendars TomSelect should be initialized
        cy.get("#pinnedCalendarsSelect").siblings(".ts-wrapper").should("exist");
        cy.get("#pinnedCalendarsSelect").siblings(".ts-wrapper").find(".ts-control").should("be.visible");
    });

    /**
     * Advanced section — Active/Inactive, Linked Group, Attendance Counts.
     * These fields live in a collapse that's closed by default so the
     * quick-add experience stays minimal. View-only assertion; the save
     * round-trip lives in the admin-session describe block below.
     */
    it("Advanced section starts collapsed and expands on toggle", () => {
        cy.visit("event/calendars");
        cy.get(".fc-daygrid-day").first().click();
        cy.get("#event-title-input").should("be.visible");

        cy.get("#eventAdvancedFields").should("not.have.class", "show");
        cy.get("#eventAdvancedLabel").should("contain", "Show more options");

        cy.get('[data-bs-target="#eventAdvancedFields"]').click();
        cy.get("#eventAdvancedFields").should("have.class", "show");
        cy.get("#eventAdvancedLabel").should("contain", "Hide advanced options");
        cy.get('input[name="eventInActive"]').should("have.length", 2);
        cy.get("#linkedGroupSelect").should("be.visible");
    });
});

/**
 * Save-path tests — require AddEvents role (admin). POST /api/events
 * returns 403 for standard sessions, so these tests must run under an
 * admin session instead of the standard one used above.
 */
describe("Standard Calendar — save (admin-session)", () => {
    beforeEach(() => cy.setupAdminSession());

    /**
     * Regression: new-event payload sent Type:0 (invalid) when the user
     * accepted the default Event Type. No EventType has Id=0, so the
     * initial `event.Type = 0` in showNewEventForm matched nothing; the
     * rendered <select> had no `selected` option, the browser showed the
     * first option visually but TomSelect's `change` never fired, so the
     * payload stayed at 0 and the API returned "invalid event type id".
     * Fix seeds event.Type with the first EventType.Id after the types
     * fetch resolves — verify the save payload now contains a non-zero
     * Type without the user touching the Event Type dropdown.
     */
    it("New event saves successfully with a pinned calendar + default Event Type", () => {
        const title = "Default Type Test - " + Cypress._.random(0, 1e6);
        cy.intercept("POST", "**/api/events").as("createEvent");

        cy.visit("event/calendars");
        cy.get(".fc-daygrid-day").first().click();
        cy.get("#event-title-input").should("be.visible").type(title);

        // Pin a calendar. Do NOT touch Event Type — we want the default
        // value to flow through to the payload.
        cy.tomSelectByValue("#pinnedCalendarsSelect", "1");

        cy.get("#eventSaveBtn").should("not.be.disabled").click();

        cy.wait("@createEvent").then((intercepted) => {
            expect(intercepted.request.body.Type).to.be.a("number").and.to.be.greaterThan(0);
            expect(intercepted.request.body.PinnedCalendars).to.include(1);
            expect(intercepted.response.statusCode).to.eq(200);
        });
    });

    /**
     * Pinned Calendars is intentionally optional — events can be saved
     * without appearing on any calendar view (matches the legacy editor's
     * long-standing behavior). Asserts the save round-trips and the
     * payload's PinnedCalendars is an empty array.
     */
    it("New event saves without a pinned calendar (empty PinnedCalendars array)", () => {
        const title = "No Calendar Test - " + Cypress._.random(0, 1e6);
        cy.intercept("POST", "**/api/events").as("createEvent");

        cy.visit("event/calendars");
        cy.get(".fc-daygrid-day").first().click();
        cy.get("#event-title-input").should("be.visible").type(title);

        // Empty-state hint should be visible since no calendar is pinned.
        cy.get("#calendarsEmptyHint").should("be.visible");

        cy.get("#eventSaveBtn").should("not.be.disabled").click();

        cy.wait("@createEvent").then((intercepted) => {
            expect(intercepted.request.body.PinnedCalendars).to.deep.equal([]);
            expect(intercepted.response.statusCode).to.eq(200);
        });
    });

    /**
     * Clicking a saved event on the calendar opens the read-only viewer
     * modal. The viewer must render BOTH Quill bodies — Description and
     * Additional Information — so volunteers can see sermon notes /
     * other details without leaving the calendar or clicking Edit.
     * Drives the viewer via the public showEventForm API so the test
     * isn't coupled to which month or day the FullCalendar happens to
     * be rendering at run time.
     */
    it("Additional Information is visible in the event viewer overlay", () => {
        const title = `TextViewTest ${Date.now()}`;
        const descBody = "Service description body";
        const textBody = "Sermon notes body for Easter Sunday";

        // Create an event with both Quill bodies populated via the API.
        const now = new Date();
        const later = new Date(now.getTime() + 60 * 60 * 1000);
        cy.request({
            method: "POST",
            url: "/api/events",
            headers: { "Content-Type": "application/json" },
            body: {
                Title: title,
                Type: 1,
                PinnedCalendars: [1],
                Start: now.toISOString(),
                End: later.toISOString(),
                Desc: `<p>${descBody}</p>`,
                Text: `<p>${textBody}</p>`,
            },
        });

        // Look up the created event id, then pop the viewer via the same
        // global the FullCalendar event-click handler uses.
        cy.request("/api/events").then((response) => {
            const events = response.body.Events || response.body;
            const arr = Array.isArray(events) ? events : Object.values(events);
            const match = arr.find((e) => e.Title === title);
            expect(match, "event should exist").to.exist;

            cy.visit("event/calendars");
            cy.window().should("have.property", "showEventForm");
            cy.window().then((win) => win.showEventForm({ id: match.Id }));

            // Viewer shows both bodies without requiring an Edit click.
            cy.get("#eventEditorModal").should("be.visible");
            cy.contains("#eventEditorModal", "Description").should("be.visible");
            cy.contains("#eventEditorModal", descBody).should("be.visible");
            cy.contains("#eventEditorModal", "Additional Information").should("be.visible");
            cy.contains("#eventEditorModal", textBody).should("be.visible");
        });
    });

    it("InActive and LinkedGroupId flow into the POST /api/events payload", () => {
        cy.intercept("POST", "**/api/events").as("createEvent");

        cy.visit("event/calendars");
        cy.get(".fc-daygrid-day").first().click();
        cy.get("#event-title-input").should("be.visible").type(`Modal Advanced ${Date.now()}`);
        cy.tomSelectByValue("#pinnedCalendarsSelect", "1");

        cy.get('[data-bs-target="#eventAdvancedFields"]').click();
        cy.get("#eventAdvancedFields").should("have.class", "show");
        cy.get('input[name="eventInActive"][value="1"]').parent("label").click();

        // Pick the first non-empty group option, if any exist in this install.
        cy.get("#linkedGroupSelect option").then(($opts) => {
            const nonEmpty = [...$opts].map((o) => o.value).find((v) => v && v !== "0");
            if (nonEmpty) cy.get("#linkedGroupSelect").select(nonEmpty);
        });

        cy.get("#eventSaveBtn").click();
        cy.wait("@createEvent").then((intercepted) => {
            expect(intercepted.request.body.InActive).to.eq(1);
            expect(intercepted.request.body).to.have.property("LinkedGroupId");
            expect(intercepted.response.statusCode).to.eq(200);
        });
    });
});
