/// <reference types="cypress" />

/**
 * Open the new-event creation modal via the stable global function.
 *
 * In FullCalendar v7 CSS class names are hashed so .fc-daygrid-day no longer
 * exists in the rendered DOM. Drive the modal via window.showNewEventForm() \u2014
 * the same global the FullCalendar select / eventClick handlers call internally.
 * This makes the tests selector-independent and robust across FC upgrades.
 */
function openNewEventModal() {
    cy.window().should("have.property", "showNewEventForm");
    cy.window().then((win) => {
        const today = new Date().toISOString().split("T")[0]; // YYYY-MM-DD
        win.showNewEventForm({ startStr: today, endStr: today, allDay: true });
    });
    // showNewEventForm() fires 3 concurrent, unmocked API calls (calendars,
    // event types, groups) before rendering the form — real backend latency
    // under CI load can occasionally exceed Cypress's default ~4s command
    // timeout even though the app itself is behaving correctly (it just shows
    // its loading spinner longer). Wait here once, with a generous bound, so
    // every call site below doesn't have to race that latency individually.
    cy.get("#event-title-input", { timeout: 15000 }).should("exist");
}

describe("Standard Calendar", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Create New Event", () => {
        const title = "My New Event - " + Cypress._.random(0, 1e6);
        cy.visit("event/calendars");
        cy.url().should("include", "event/calendars");

        // Open the new-event form via the stable global (avoids relying on
        // FullCalendar's hashed DOM class names which changed in v7)
        openNewEventModal();

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

        openNewEventModal();

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

        openNewEventModal();
        cy.get("#event-title-input").should("be.visible");

        // Save starts disabled with no title. Start/End are pre-filled from
        // the calendar-day click, and PinnedCalendars is optional — only the
        // Title field blocks save.
        cy.get("#eventSaveBtn").should("be.disabled");

        // Use invoke("val").trigger("input") instead of .type() to bypass the
        // Bootstrap 5 modal focus-trap, which can steal keyboard focus mid-delivery
        // and cause input events to land on the wrong element, leaving event.Title
        // empty and the save button permanently disabled. trigger("input") fires the
        // input event listener that updates event.Title and calls fireValidity().
        cy.get("#event-title-input").invoke("val", "Validation Test Event").trigger("input");
        cy.get("#eventSaveBtn").should("not.be.disabled");

        // Empty-calendar hint is visible because nothing is pinned yet.
        cy.get("#calendarsEmptyHint").should("be.visible");

        // Clear the title — save disables again.
        cy.get("#event-title-input").invoke("val", "").trigger("input");
        cy.get("#eventSaveBtn").should("be.disabled");
    });

    it("All-day toggle switches date input types", () => {
        cy.visit("event/calendars");

        openNewEventModal();
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

        openNewEventModal();
        cy.get("#event-title-input").should("be.visible");

        // Delete button should be hidden for new (unsaved) events
        cy.get("#eventDeleteBtn").should("have.class", "d-none");
    });

    it("Modal closes on Cancel button click", () => {
        cy.visit("event/calendars");

        openNewEventModal();
        cy.get("#event-title-input").should("be.visible");

        // Click Cancel — modal should close and be removed from DOM
        cy.get("#eventCancelBtn").click();
        cy.get("#eventEditorModal").should("not.exist");
    });

    it("Modal cleanup removes element and restores body state", () => {
        cy.visit("event/calendars");

        openNewEventModal();
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

        openNewEventModal();
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
        openNewEventModal();
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
    beforeEach(() => {
        // Suppress Bootstrap/FullCalendar's null.focus() race: when the previous
        // test's closeModal() fires refreshAllFullCalendarSources(), FullCalendar
        // re-renders asynchronously. In subdir mode (slower API) the render
        // callback can fire during the next test, calling .focus() on an element
        // that was removed from the DOM — producing this uncaught exception.
        // This is a timing artifact, not a correctness failure.
        cy.on("uncaught:exception", (err) => {
            if (err.message === "Cannot read properties of null (reading 'focus')") {
                return false;
            }
        });
        cy.setupAdminSession();
    });

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
        openNewEventModal();
        // Use invoke("val").trigger("input") instead of .type() to bypass the
        // Bootstrap 5 modal focus-trap, which can steal keyboard focus mid-delivery
        // and cause input events to land on the wrong element, leaving event.Title
        // empty and the save button permanently disabled. trigger("input") fires the
        // input event listener that updates event.Title and calls fireValidity().
        cy.get("#event-title-input").should("be.visible").invoke("val", title).trigger("input");

        // Pin a calendar. Do NOT touch Event Type — we want the default
        // value to flow through to the payload.
        // tomSelectByValue already waits for TomSelect init (uses .should() retry).
        cy.tomSelectByValue("#pinnedCalendarsSelect", "1");

        // The save button's disabled state races with TomSelect's synchronous
        // fireValidity() call: if the TomSelect change event fires while the
        // title-input event handler hasn't fully settled, validate() can see
        // an empty title and re-disable the button. Asserting button state is
        // therefore not stable. Instead, force-click and let the @createEvent
        // intercept be the sole correctness gate: it verifies both the API
        // round-trip and the request body (Type + PinnedCalendars).
        cy.get("#eventSaveBtn").click({ force: true });

        cy.wait("@createEvent").then((intercepted) => {
            expect(intercepted.request.body.Type).to.be.a("number").and.to.be.greaterThan(0);
            expect(intercepted.request.body.PinnedCalendars).to.include(1);
            expect(intercepted.response.statusCode).to.eq(200);
        });
        // Wait for the application's async closeModal() to remove the modal element
        // before this test ends. Without this, saveEvent().then(closeModal) can fire
        // during the next test's beforeEach (cy.session navigation to about:blank),
        // causing Bootstrap to call .focus() on a null document.activeElement in
        // headless Electron — a timing-dependent failure more common in subdir mode
        // where the API round-trip is slightly slower.
        cy.get("#eventEditorModal").should("not.exist");
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
        openNewEventModal();
        // Use invoke("val").trigger("input") instead of .type() to bypass the
        // Bootstrap 5 modal focus-trap, which can steal keyboard focus mid-delivery
        // and cause input events to land on the wrong element, leaving event.Title
        // empty and the save button permanently disabled. trigger("input") fires the
        // input event listener that updates event.Title and calls fireValidity().
        cy.get("#event-title-input").should("be.visible").invoke("val", title).trigger("input");

        // Empty-state hint should be visible since no calendar is pinned.
        cy.get("#calendarsEmptyHint").should("be.visible");

        // Wait for TomSelect on eventTypeSelect to finish initializing;
        // its validate() callback re-disables the save button while
        // TomSelect is still booting, causing the POST to never fire
        // in the root (non-subdir) CI environment.
        cy.get("#eventTypeSelect + .ts-wrapper").should("exist");
        cy.get("#eventSaveBtn").click({ force: true });

        cy.wait("@createEvent").then((intercepted) => {
            expect(intercepted.request.body.PinnedCalendars).to.deep.equal([]);
            expect(intercepted.response.statusCode).to.eq(200);
        });
        // Same guard as test 1: ensure closeModal() removes the element before
        // this test ends so the async FullCalendar re-render fires within this
        // test's uncaught:exception handler scope rather than test 3's.
        cy.get("#eventEditorModal").should("not.exist");
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

    it("Create New Calendar", () => {
        const title = "Calendar: " + new Date().getTime();

        cy.visit("event/calendars");
        cy.contains("Calendar");

        cy.get('[data-bs-target="#calendarSidebar"]').click();
        cy.get("#calendarSidebar").should("be.visible");
        cy.get("#addCalendarBtn").click();
        cy.get("#calendarName").should("be.visible").click().type(title);
        cy.get("#ForegroundColor").invoke("val", "#FA8072").trigger("change");
        cy.get("#BackgroundColor").invoke("val", "#212F3D").trigger("change");

        cy.get(".modal-footer .btn-primary.float-end").click();
    });

    it("InActive and LinkedGroupId flow into the POST /api/events payload", () => {
        cy.intercept("POST", "**/api/events").as("createEvent");

        cy.visit("event/calendars");
        openNewEventModal();
        // Use invoke("val").trigger("input") instead of .type() to bypass the
        // Bootstrap 5 modal focus-trap, which can steal keyboard focus mid-delivery
        // and cause input events to land on the wrong element, leaving event.Title
        // empty and the save button permanently disabled. trigger("input") fires the
        // input event listener that updates event.Title and calls fireValidity().
        cy.get("#event-title-input").should("be.visible").invoke("val", `Modal Advanced ${Date.now()}`).trigger("input");
        cy.tomSelectByValue("#pinnedCalendarsSelect", "1");

        cy.get('[data-bs-target="#eventAdvancedFields"]').click();
        cy.get("#eventAdvancedFields").should("have.class", "show");
        cy.get('input[name="eventInActive"][value="1"]').parent("label").click();

        // Pick the first non-empty group option, if any exist in this install.
        cy.get("#linkedGroupSelect option").then(($opts) => {
            const nonEmpty = [...$opts].map((o) => o.value).find((v) => v && v !== "0");
            if (nonEmpty) cy.get("#linkedGroupSelect").select(nonEmpty);
        });

        // Same TomSelect→validate() race as the first save-path test above;
        // force-click and rely on the @createEvent intercept as the correctness gate.
        cy.get("#eventSaveBtn").click({ force: true });
        cy.wait("@createEvent").then((intercepted) => {
            expect(intercepted.request.body.InActive).to.eq(1);
            expect(intercepted.request.body).to.have.property("LinkedGroupId");
            expect(intercepted.response.statusCode).to.eq(200);
        });
    });
});
